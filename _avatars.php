<?php
/**
 * Avatar handoff helper.
 *
 * Implements the "one-shot avatar handoff" contract from BrainLock's
 * Connect SDK. On the first signin for a given email, we:
 *
 *   1. Fetch the avatar bytes from the BrainLock-presigned URL in the
 *      JWT (URL dies in ~1h — we have to hurry).
 *   2. Decode + process with Imagick: strip EXIF, write the original
 *      as-is for the full-size copy, cover-crop a 512×512 thumb for
 *      list/avatar display.
 *   3. Upload both to TangoCash's own DO Spaces bucket
 *      (tangocash-avatars) with public-read ACL, so <img src> just
 *      works.
 *   4. Return the bucket URLs to the caller; callback.php writes them
 *      into tc_users.picture_full_url + picture_thumb_url.
 *
 * Subsequent signins do NOT re-fetch — tc_users already has the URLs.
 * If the user wants to change avatar, they do it via TangoCash's own
 * profile editor (changes never round-trip to BrainLock).
 *
 * SigV4 signing is hand-rolled here rather than pulled from AWS SDK
 * for PHP — we only need GET/PUT/DELETE against a single bucket, no
 * composer setup, ~50 lines of crypto.
 *
 * Spaces credentials come from PHP-FPM env vars (TC_SPACES_KEY,
 * TC_SPACES_SECRET, TC_SPACES_BUCKET, TC_SPACES_REGION,
 * TC_SPACES_ENDPOINT) — set in /etc/php/8.3/fpm/pool.d/tangocash.etonica.com.conf,
 * never in source.
 */

// ============================================================
// SigV4 — sign + execute a Spaces request via curl
// ============================================================

/**
 * tc_spaces_request — issue a signed SigV4 request to DO Spaces.
 *
 * @param string $method   GET | PUT | DELETE
 * @param string $key      Object key (no leading slash).
 * @param string $body     Body bytes (PUT only; '' for GET/DELETE).
 * @param array  $headers  Extra request headers as ["Name: value", ...].
 *                         Must include Content-Type on PUTs. The
 *                         payload hash + Authorization header are
 *                         added automatically.
 * @return array { 'status' => int, 'body' => string, 'error' => string }
 */
function tc_spaces_request(string $method, string $key, string $body = '', array $headers = []): array {
    $accessKey = (string)\getenv('TC_SPACES_KEY');
    $secretKey = (string)\getenv('TC_SPACES_SECRET');
    $bucket    = (string)\getenv('TC_SPACES_BUCKET');
    $region    = (string)\getenv('TC_SPACES_REGION');
    if ($accessKey === '' || $secretKey === '' || $bucket === '' || $region === '') {
        return ['status' => 0, 'body' => '', 'error' => 'TC_SPACES_* env vars not set'];
    }

    $service  = 's3';
    $host     = $bucket . '.' . $region . '.digitaloceanspaces.com';
    $uriPath  = '/' . \ltrim($key, '/');
    $now      = \gmdate('Ymd\THis\Z');
    $today    = \gmdate('Ymd');
    $scope    = $today . '/' . $region . '/' . $service . '/aws4_request';
    $payloadHash = \hash('sha256', $body);

    // ---- Build canonical headers ----------------------------------------
    // host + x-amz-content-sha256 + x-amz-date are always signed. Caller-
    // supplied headers join in alphabetical order. Header names are
    // lowercased per SigV4 rules; values are trimmed.
    $signedHeaders = [
        'host'                 => $host,
        'x-amz-content-sha256' => $payloadHash,
        'x-amz-date'           => $now,
    ];
    foreach ($headers as $h) {
        $colon = \strpos($h, ':');
        if ($colon === false) continue;
        $name  = \strtolower(\trim(\substr($h, 0, $colon)));
        $value = \trim(\substr($h, $colon + 1));
        $signedHeaders[$name] = $value;
    }
    \ksort($signedHeaders);
    $canonicalHeaders = '';
    $signedHeaderList = [];
    foreach ($signedHeaders as $name => $value) {
        $canonicalHeaders .= $name . ':' . $value . "\n";
        $signedHeaderList[] = $name;
    }
    $signedHeaderStr = \implode(';', $signedHeaderList);

    $canonicalRequest = $method . "\n"
        . $uriPath . "\n"
        . '' . "\n"          // no query string
        . $canonicalHeaders . "\n"
        . $signedHeaderStr . "\n"
        . $payloadHash;

    $stringToSign = "AWS4-HMAC-SHA256\n"
        . $now . "\n"
        . $scope . "\n"
        . \hash('sha256', $canonicalRequest);

    // ---- Derive the signing key -----------------------------------------
    $kDate    = \hash_hmac('sha256', $today,        'AWS4' . $secretKey, true);
    $kRegion  = \hash_hmac('sha256', $region,       $kDate,              true);
    $kService = \hash_hmac('sha256', $service,      $kRegion,            true);
    $kSigning = \hash_hmac('sha256', 'aws4_request', $kService,          true);
    $signature = \hash_hmac('sha256', $stringToSign, $kSigning);

    $authHeader = 'AWS4-HMAC-SHA256 '
        . 'Credential=' . $accessKey . '/' . $scope . ', '
        . 'SignedHeaders=' . $signedHeaderStr . ', '
        . 'Signature=' . $signature;

    // ---- Fire via curl --------------------------------------------------
    // Only Authorization + the three SigV4 headers are unconditional.
    // Host is set automatically by curl from the URL — emitting our own
    // would duplicate the header.
    $curlHeaders = [
        'x-amz-content-sha256: ' . $payloadHash,
        'x-amz-date: ' . $now,
        'Authorization: ' . $authHeader,
    ];
    foreach ($headers as $h) $curlHeaders[] = $h;
    // Suppress curl's default Expect: 100-continue — DO Spaces doesn't
    // negotiate it and the resulting hang adds ~1s per upload.
    $curlHeaders[] = 'Expect:';

    $url  = 'https://' . $host . $uriPath;
    $ch   = \curl_init($url);
    $opts = [
        \CURLOPT_CUSTOMREQUEST  => $method,
        \CURLOPT_HTTPHEADER     => $curlHeaders,
        \CURLOPT_RETURNTRANSFER => true,
        \CURLOPT_TIMEOUT        => 15,
    ];
    // POSTFIELDS only makes sense when there's actually a body. Setting
    // it to '' on DELETE made curl emit Content-Length: 0 + chunked
    // encoding signals that confused Spaces enough to return 403 on the
    // signature check. Skip it entirely for empty bodies.
    if ($body !== '') {
        $opts[\CURLOPT_POSTFIELDS] = $body;
    }
    \curl_setopt_array($ch, $opts);
    $respBody = (string)\curl_exec($ch);
    $status   = (int)\curl_getinfo($ch, \CURLINFO_HTTP_CODE);
    $err      = \curl_error($ch);
    \curl_close($ch);
    return ['status' => $status, 'body' => $respBody, 'error' => $err];
}

/**
 * Public read URL for a key. Construct from bucket+region rather than
 * env (TC_SPACES_ENDPOINT is the API endpoint, different URL shape).
 */
function tc_spaces_public_url(string $key): string {
    $bucket = (string)\getenv('TC_SPACES_BUCKET');
    $region = (string)\getenv('TC_SPACES_REGION');
    if ($bucket === '' || $region === '') return '';
    return 'https://' . $bucket . '.' . $region . '.digitaloceanspaces.com/' . \ltrim($key, '/');
}

// ============================================================
// Cache orchestration
// ============================================================

/**
 * tc_cache_avatar — download avatar from BrainLock presigned URL,
 * process locally, upload full + thumb to our DO Spaces bucket.
 *
 * Returns ['full' => $fullURL, 'thumb' => $thumbURL] on success, or
 * null on any failure. We log via error_log but never throw — a
 * failed avatar cache should NOT block a signin from completing.
 *
 * @param string $blSub        JWT 'sub' — used to derive the bucket key.
 * @param string $blPresignedURL  The BL JWT 'picture' claim (~1h TTL).
 */
function tc_cache_avatar(string $blSub, string $blPresignedURL): ?array {
    if ($blSub === '' || $blPresignedURL === '') return null;

    // ---- Fetch from BrainLock ------------------------------------------
    $ch = \curl_init($blPresignedURL);
    \curl_setopt_array($ch, [
        \CURLOPT_RETURNTRANSFER => true,
        \CURLOPT_FOLLOWLOCATION => true,
        \CURLOPT_TIMEOUT        => 15,
    ]);
    $srcBytes = (string)\curl_exec($ch);
    $srcCode  = (int)\curl_getinfo($ch, \CURLINFO_HTTP_CODE);
    \curl_close($ch);
    if ($srcCode !== 200 || $srcBytes === '') {
        \error_log("[tangocash] avatar fetch failed: HTTP $srcCode for $blPresignedURL");
        return null;
    }

    // ---- Process with Imagick ------------------------------------------
    try {
        $full = new \Imagick();
        $full->readImageBlob($srcBytes);
        $full->stripImage();                 // drop EXIF / metadata
        $full->setImageFormat('jpeg');
        $full->setImageCompressionQuality(90);
        $fullBytes = (string)$full;

        $thumb = clone $full;
        $thumb->cropThumbnailImage(512, 512); // cover-crop, then resize
        $thumb->setImageCompressionQuality(85);
        $thumbBytes = (string)$thumb;

        $full->clear(); $full->destroy();
        $thumb->clear(); $thumb->destroy();
    } catch (\Throwable $e) {
        \error_log('[tangocash] avatar Imagick failure: ' . $e->getMessage());
        return null;
    }

    // ---- Upload to TC Spaces (public-read) ----------------------------
    $hash      = \sha1($blSub);
    $fullKey   = "avatars/{$hash}_full.jpg";
    $thumbKey  = "avatars/{$hash}_thumb.jpg";

    $putHeaders = function (int $len): array {
        return [
            'Content-Type: image/jpeg',
            'x-amz-acl: public-read',
            'Cache-Control: public, max-age=31536000, immutable',
            'Content-Length: ' . $len,
        ];
    };

    $r1 = tc_spaces_request('PUT', $fullKey,  $fullBytes,  $putHeaders(\strlen($fullBytes)));
    if ($r1['status'] !== 200) {
        \error_log("[tangocash] avatar full upload failed: HTTP {$r1['status']} {$r1['error']} body=" . \substr($r1['body'], 0, 200));
        return null;
    }
    $r2 = tc_spaces_request('PUT', $thumbKey, $thumbBytes, $putHeaders(\strlen($thumbBytes)));
    if ($r2['status'] !== 200) {
        \error_log("[tangocash] avatar thumb upload failed: HTTP {$r2['status']} {$r2['error']} body=" . \substr($r2['body'], 0, 200));
        // Roll back the full upload so we don't leave an orphan.
        tc_spaces_request('DELETE', $fullKey);
        return null;
    }

    return [
        'full'  => tc_spaces_public_url($fullKey),
        'thumb' => tc_spaces_public_url($thumbKey),
    ];
}

/**
 * tc_delete_avatars — best-effort delete of both bucket objects for a
 * user. Called from tc_delete_user(). Returns nothing; failures are
 * logged. The cleanup matters but isn't load-bearing for the signin
 * path so we never throw.
 */
function tc_delete_avatars(string $blSub): void {
    if ($blSub === '') return;
    $hash = \sha1($blSub);
    foreach (["avatars/{$hash}_full.jpg", "avatars/{$hash}_thumb.jpg"] as $key) {
        $r = tc_spaces_request('DELETE', $key);
        if ($r['status'] !== 204 && $r['status'] !== 200 && $r['status'] !== 404) {
            // Include the response body (Spaces XML error) so we can see
            // the actual SignatureDoesNotMatch / AccessDenied detail.
            \error_log(
                "[tangocash] avatar delete failed: $key HTTP {$r['status']} "
                . "curl='{$r['error']}' body=" . \substr($r['body'], 0, 400)
            );
        }
    }
}
