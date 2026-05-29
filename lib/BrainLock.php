<?php
/**
 * BrainLock PHP SDK — single-file edition.
 * =============================================================================
 *
 * The official PHP integration for BrainLock Connect (passwordless sign-in)
 * and BrainLock Verify (per-action authorization). Zero hard dependencies —
 * uses the curl + openssl extensions that ship with PHP.
 *
 * Canonical 8-line example:
 *
 *     require 'BrainLock.php';
 *
 *     BrainLock::configure([
 *         'api_key'      => $_ENV['BRAINLOCK_API_KEY'],
 *         'callback_url' => 'https://yourapp.com/auth/callback',
 *     ]);
 *
 *     BrainLock::connect(['user_id' => session_id()]);
 *
 * And the callback:
 *
 *     $identity = BrainLock::verify($_GET['token']);
 *     // ['sub' => '...', 'name' => '...', 'email' => '...', 'picture' => '...']
 *
 * Connect always returns the same fixed identity bundle:
 *   - `sub`     stable user ID for your app   (always)
 *   - `name`    first + last                  (always)
 *   - `email`   primary email                 (always)
 *   - `picture` avatar URL                    (only when the user has set one)
 *
 * No scopes. No checkboxes. The bundle is the bundle.
 *
 * Source: github.com/xtiaan3/brainlock-php
 */
final class BrainLock
{
    public const VERSION = '0.1.0';

    /** Default origin of the BrainLock service. */
    private const DEFAULT_API_BASE = 'https://brainlock.id';

    /** Cookie name used to bind a sign-in session to this browser. */
    private const STATE_COOKIE = 'brainlock_state';

    /** JWKS cache TTL — public keys rotate rarely; 1h is plenty. */
    private const JWKS_TTL = 3600;

    private static array $config = [];
    private static ?array $jwksCache = null;
    private static int $jwksCachedAt = 0;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Configure the SDK. Call once per request, before connect() or verify().
     *
     * Required:
     *   api_key      — your developer API key (bl_live_… or bl_test_…)
     *   callback_url — the URL on YOUR app where BrainLock will redirect after
     *                  a successful sign-in. Must be HTTPS and pre-registered
     *                  at brainlock.id/developer.
     *
     * Optional:
     *   api_base     — defaults to https://brainlock.id. Override for testing.
     *   mode         — 'popup' (default) or 'redirect'. See connect() for the
     *                  difference.
     *
     * @throws InvalidArgumentException on bad config.
     */
    public static function configure(array $config): void
    {
        if (empty($config['api_key']) || !\is_string($config['api_key'])) {
            throw new \InvalidArgumentException('BrainLock: api_key is required.');
        }
        if (!\preg_match('/^bl_(live|test)_[a-f0-9]+$/', $config['api_key'])) {
            throw new \InvalidArgumentException('BrainLock: api_key must look like "bl_live_…" or "bl_test_…".');
        }
        if (empty($config['callback_url']) || !\is_string($config['callback_url'])) {
            throw new \InvalidArgumentException('BrainLock: callback_url is required.');
        }
        $parsed = \parse_url($config['callback_url']);
        if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new \InvalidArgumentException('BrainLock: callback_url is not a valid URL.');
        }
        if ($parsed['scheme'] !== 'https' && $parsed['host'] !== 'localhost') {
            throw new \InvalidArgumentException('BrainLock: callback_url must be https:// (or http://localhost for development).');
        }

        // Transport mode.
        //   'iframe'   — default. Render the BrainLock UI as a full-viewport
        //                iframe over the partner page. By default the SDK
        //                uses the SAME-ORIGIN PROXY transport: the iframe
        //                loads from a path on YOUR own server (default
        //                /_bl/auth/<sid>) which transparently proxies to
        //                brainlock.id. Because the iframe is same-site as
        //                your top-level page, cookies behave as first-party.
        //                Works on every browser including Safari. You
        //                must mount BrainLock::handleEmbed() at the
        //                configured `embed_path` (see below).
        //   'redirect' — full-page navigation to brainlock.id and back.
        //                Works everywhere with zero server-side mounting.
        //                Use if you don't want to mount the embed proxy.
        $mode = $config['mode'] ?? 'iframe';
        if (!\in_array($mode, ['iframe', 'redirect'], true)) {
            throw new \InvalidArgumentException('BrainLock: mode must be "iframe" or "redirect".');
        }

        // embed_path — URL prefix on YOUR site where BrainLock::handleEmbed
        // is mounted. The iframe and all its XHR/form-POSTs go through
        // this prefix. Defaults to /_bl. You can change it but it must
        // be a single path segment.
        $embedPath = $config['embed_path'] ?? '/_bl';
        if (!\preg_match('#^/[A-Za-z0-9_-]+$#', $embedPath)) {
            throw new \InvalidArgumentException('BrainLock: embed_path must look like "/_bl" — single path segment, no trailing slash.');
        }

        self::$config = [
            'api_key'      => $config['api_key'],
            'callback_url' => $config['callback_url'],
            'api_base'     => \rtrim($config['api_base'] ?? self::DEFAULT_API_BASE, '/'),
            'mode'         => $mode,
            'embed_path'   => $embedPath,
        ];
    }

    /**
     * handleEmbed — the same-origin proxy handler.
     *
     * Mount this BEFORE your normal routing so it captures any request
     * under your configured `embed_path` (defaults to /_bl). The
     * canonical four-line mount:
     *
     *     // At the top of your app's router / index.php:
     *     if (str_starts_with($_SERVER['REQUEST_URI'], '/_bl/')) {
     *         BrainLock::handleEmbed();
     *         exit;
     *     }
     *
     * What it does. Every request matching /_bl/<rest> is forwarded to
     * brainlock.id/<rest> with the original method, body, query string,
     * and most headers preserved. The response is streamed back to the
     * browser with status, headers, and body intact. From the browser's
     * perspective, every request looks like it's coming FROM your own
     * domain — which means cookies BrainLock sets are stored as
     * first-party cookies on your origin. No CHIPS, no Storage Access,
     * no version-sniffing — works in every modern browser.
     *
     * Latency cost: one extra hop. Typically 5–20ms on the wire for
     * partner→BrainLock, negligible against the user-perceived sign-in
     * time.
     */
    public static function handleEmbed(): void
    {
        self::ensureConfigured();

        $base    = self::$config['api_base'];
        $prefix  = self::$config['embed_path'];
        $reqURI  = $_SERVER['REQUEST_URI'] ?? '/';
        // Strip the embed_path prefix to get the upstream path+query.
        if (\strpos($reqURI, $prefix . '/') !== 0 && $reqURI !== $prefix) {
            \http_response_code(404);
            echo 'Not found';
            return;
        }
        $upstreamPath = \substr($reqURI, \strlen($prefix));
        if ($upstreamPath === '') $upstreamPath = '/';
        $upstreamURL  = $base . $upstreamPath;

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $body   = \in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? \file_get_contents('php://input') : null;

        // Headers to forward. Drop hop-by-hop and Host (which we rewrite).
        // Add proxy_base so BrainLock-side template can emit absolute
        // asset URLs and inject window.BL_API_BASE for client-side
        // fetch routing.
        $fwdHeaders = [];
        $skip = [
            'host' => true, 'connection' => true, 'content-length' => true,
            'transfer-encoding' => true, 'keep-alive' => true, 'upgrade' => true,
            'proxy-authorization' => true, 'proxy-authenticate' => true,
            'te' => true, 'trailers' => true,
        ];
        foreach ($_SERVER as $k => $v) {
            if (\strpos($k, 'HTTP_') !== 0) continue;
            $name = \strtolower(\str_replace('_', '-', \substr($k, 5)));
            if (isset($skip[$name])) continue;
            $fwdHeaders[] = $name . ': ' . $v;
        }
        // Tell brainlock-go this request came through a proxy at our prefix.
        $fwdHeaders[] = 'X-BL-Proxy-Base: ' . $prefix;
        // Surface the real client IP for audit / rate-limiting on
        // BrainLock's side. Preserves any existing forwarded chain.
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($clientIP !== '') {
            $existing = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            $fwdHeaders[] = 'X-Forwarded-For: ' . ($existing ? $existing . ', ' . $clientIP : $clientIP);
        }

        $ch = \curl_init();
        \curl_setopt_array($ch, [
            CURLOPT_URL            => self::insertProxyBaseQuery($upstreamURL, $prefix),
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $fwdHeaders,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        if ($body !== null && $body !== false && $body !== '') {
            \curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw  = \curl_exec($ch);
        if ($raw === false) {
            \http_response_code(502);
            echo 'Upstream error: ' . \curl_error($ch);
            \curl_close($ch);
            return;
        }
        $code      = (int)\curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerLen = (int)\curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        \curl_close($ch);

        $headerRaw = \substr($raw, 0, $headerLen);
        $bodyRaw   = \substr($raw, $headerLen);

        \http_response_code($code);
        $contentType = '';
        // Walk response headers; passthrough Set-Cookie + most others, skip
        // hop-by-hop and CSP frame-ancestors (which we override below).
        $skipResp = [
            'connection' => true, 'transfer-encoding' => true, 'content-length' => true,
            'content-encoding' => true, 'keep-alive' => true, 'upgrade' => true,
        ];
        foreach (\preg_split('/\r?\n/', $headerRaw) as $line) {
            if ($line === '' || \strpos($line, ':') === false) continue;
            [$name, $value] = \explode(':', $line, 2);
            $name  = \trim($name);
            $value = \trim($value);
            $low   = \strtolower($name);
            if (isset($skipResp[$low])) continue;
            if ($low === 'content-type') {
                $contentType = \strtolower($value);
            }
            if ($low === 'content-security-policy') {
                // BrainLock's CSP allows framing only from a specific origin;
                // since the iframe now loads from US (same-origin), tighten
                // to 'self' so no other site can frame our proxied auth UI.
                \header("Content-Security-Policy: frame-ancestors 'self'");
                continue;
            }
            \header($name . ': ' . $value, \strtolower($name) !== 'set-cookie');
        }

        // HTML responses get root-relative href/src/action attributes
        // prefixed with the proxy path so assets resolve through the
        // proxy. Skipping inline strings ('/api/...' inside JS) — those
        // are handled by the BrainLock-side fetch/XHR monkey-patch.
        if (\strpos($contentType, 'text/html') !== false) {
            $bodyRaw = self::rewriteHTMLPaths($bodyRaw, $prefix);
        }
        echo $bodyRaw;
    }

    /**
     * Prefix root-relative href/src/action attributes with the proxy
     * base. The negative lookahead skips `//` (protocol-relative) and
     * already-prefixed paths.
     */
    private static function rewriteHTMLPaths(string $html, string $prefix): string
    {
        // Single regex covers all three attributes.
        return \preg_replace(
            '#\b(href|src|action)\s*=\s*(["\'])/(?!/|' . \preg_quote(\ltrim($prefix, '/'), '#') . '/)#i',
            '$1=$2' . $prefix . '/',
            $html
        ) ?? $html;
    }

    /**
     * Add ?proxy_base=<prefix> to the upstream URL so brainlock-go knows
     * to emit absolute asset URLs + inject window.BL_API_BASE.
     */
    private static function insertProxyBaseQuery(string $url, string $prefix): string
    {
        $sep = (\strpos($url, '?') === false) ? '?' : '&';
        return $url . $sep . 'proxy_base=' . \urlencode($prefix);
    }

    /**
     * Start an auth session and RETURN the URL data without emitting any
     * output. Use this when you want to drive the popup from your own
     * client-side JS (which is the only way to reliably avoid popup
     * blockers — `window.open` must run during the click event).
     *
     * Returns: ['url' => '<brainlock.id/auth/SID>',
     *           'session_id' => 'bl_sess_...',
     *           'expires_at' => '2026-...']
     *
     * Use with the bundled `brainlock-connect.js` (or your own JS) like:
     *
     *     // Server side — return JSON to your sign-in button's fetch:
     *     header('Content-Type: application/json');
     *     echo json_encode(BrainLock::startSession(['user_id' => session_id()]));
     *
     *     // Client side — popup at click time, swap location after fetch.
     *
     * @throws RuntimeException on API error.
     */
    public static function startSession(array $opts = []): array
    {
        self::ensureConfigured();
        if (empty($opts['user_id']) || !\is_string($opts['user_id'])) {
            throw new \InvalidArgumentException('BrainLock::startSession requires user_id.');
        }
        $state = $opts['state'] ?? \bin2hex(\random_bytes(16));
        $level = $opts['security_level'] ?? 'secure';
        if (!\in_array($level, ['secure', 'elevated', 'maximum'], true)) {
            throw new \InvalidArgumentException("BrainLock::startSession security_level must be 'secure', 'elevated', or 'maximum'.");
        }

        $resp = self::http(
            'POST',
            self::$config['api_base'] . '/v1/auth/session',
            [
                'user_id'        => $opts['user_id'],
                'callback_url'   => self::$config['callback_url'],
                'security_level' => $level,
                'state'          => $state,
                'require_geo'    => !empty($opts['require_geo']),
            ],
            ['Authorization: Bearer ' . self::$config['api_key']]
        );
        if (empty($resp['redirect_url']) || empty($resp['session_id'])) {
            $msg = isset($resp['error']['message']) ? $resp['error']['message'] : 'unknown error';
            throw new \RuntimeException('BrainLock: failed to create auth session: ' . $msg);
        }
        \setcookie(self::STATE_COOKIE, $state, [
            'expires'  => \time() + 600,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        return [
            'url'        => $resp['redirect_url'],
            'session_id' => $resp['session_id'],
            'expires_at' => $resp['expires_at'] ?? null,
        ];
    }

    /**
     * Start a sign-in flow.
     *
     * Required:
     *   user_id — YOUR app's stable identifier for the user. BrainLock uses
     *             it to maintain a (your_app, user_id) → BrainLock vault
     *             binding so the same person always lands on the same row.
     *             For first-time users, you can pass session_id() and migrate
     *             on the callback once you mint your own user_id.
     *
     * Optional:
     *   security_level — 'secure' (default) | 'elevated' | 'maximum'
     *   state          — opaque CSRF/round-trip string. Auto-generated when omitted.
     *   profile_attrs  — vestigial; ignored. Bundle is fixed (name+email+picture).
     *
     * Side effects (popup mode, default):
     *   Outputs a small HTML+JS page that opens the BrainLock popup, listens
     *   for the postMessage with the JWT, and forwards the user to your
     *   callback URL on success. The page is the entirety of the HTTP
     *   response — call this from a dedicated handler like /signin.php.
     *
     * Side effects (redirect mode):
     *   Sends a 302 to brainlock.id/auth/<sid>. Simpler, but the user leaves
     *   your domain during the ceremony.
     *
     * Both modes set a `brainlock_state` cookie containing the state value,
     * which verify() checks to defeat callback-URL replay.
     */
    public static function connect(array $opts = []): void
    {
        self::ensureConfigured();

        if (empty($opts['user_id']) || !\is_string($opts['user_id'])) {
            throw new \InvalidArgumentException('BrainLock::connect requires user_id.');
        }
        $state = $opts['state'] ?? \bin2hex(\random_bytes(16));
        $level = $opts['security_level'] ?? 'secure';
        if (!\in_array($level, ['secure', 'elevated', 'maximum'], true)) {
            throw new \InvalidArgumentException("BrainLock::connect security_level must be 'secure', 'elevated', or 'maximum'.");
        }

        // Create the auth session on the BrainLock side.
        $resp = self::http(
            'POST',
            self::$config['api_base'] . '/v1/auth/session',
            [
                'user_id'        => $opts['user_id'],
                'callback_url'   => self::$config['callback_url'],
                'security_level' => $level,
                'state'          => $state,
                'require_geo'    => !empty($opts['require_geo']),
            ],
            ['Authorization: Bearer ' . self::$config['api_key']]
        );
        if (empty($resp['redirect_url']) || empty($resp['session_id'])) {
            $msg = isset($resp['error']['message']) ? $resp['error']['message'] : 'unknown error';
            throw new \RuntimeException('BrainLock: failed to create auth session: ' . $msg);
        }

        // Bind the state to this browser so the callback can detect replays
        // and the popup can't be tricked into accepting someone else's token.
        \setcookie(self::STATE_COOKIE, $state, [
            'expires'  => \time() + 600,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (self::$config['mode'] === 'redirect') {
            \header('Location: ' . $resp['redirect_url']);
            exit;
        }

        // Iframe mode: emit a launcher page. The page does a client-side
        // capability check (browsers that don't support partitioned
        // cookies / CHIPS — chiefly Safari ≤ 17 — fall back to a
        // full-page redirect instead of the iframe). Modern browsers
        // get the in-page experience: TangoCash dims, BrainLock fills
        // the viewport, postMessage handoff on completion.
        self::emitIframeOpener($resp['redirect_url']);
    }

    /**
     * Verify a JWT received on the callback URL.
     *
     * Throws \RuntimeException on any failure (bad signature, wrong audience,
     * expired, state mismatch, etc.). Returns the decoded identity claims
     * on success:
     *
     *   [
     *     'sub'     => '<stable user id for this app>',
     *     'name'    => 'Jane Doe',
     *     'email'   => 'jane@example.com',
     *     'picture' => 'https://…/avatar.jpg',  // omitted when not set
     *     'verified'      => true,
     *     'biometric_used' => true|false,
     *     'session_id'    => '<bl_sess_…>',
     *     'iat'           => 1779971517,
     *     'exp'           => 1779971577,
     *   ]
     */
    public static function verify(string $token): array
    {
        self::ensureConfigured();
        if ($token === '') {
            throw new \RuntimeException('BrainLock::verify: empty token.');
        }

        // Decode the three JWT segments.
        $parts = \explode('.', $token);
        if (\count($parts) !== 3) {
            throw new \RuntimeException('BrainLock::verify: malformed token.');
        }
        [$rawHeader, $rawPayload, $rawSig] = $parts;
        $header  = self::jsonDecodeSegment($rawHeader);
        $payload = self::jsonDecodeSegment($rawPayload);
        $sig     = self::base64UrlDecode($rawSig);

        // Algorithm guard. The BrainLock JWKS only signs RS256.
        if (($header['alg'] ?? '') !== 'RS256') {
            throw new \RuntimeException('BrainLock::verify: unexpected alg "' . ($header['alg'] ?? '') . '".');
        }
        $kid = $header['kid'] ?? '';
        if ($kid === '') {
            throw new \RuntimeException('BrainLock::verify: token missing kid.');
        }

        // Resolve the kid against the JWKS.
        $publicKey = self::publicKeyFor($kid);

        // Verify the RSA-SHA256 signature over header.payload (raw bytes).
        $signed = $rawHeader . '.' . $rawPayload;
        $verifyResult = \openssl_verify($signed, $sig, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verifyResult !== 1) {
            throw new \RuntimeException('BrainLock::verify: signature mismatch.');
        }

        // Standard claim checks.
        $now = \time();
        if (!isset($payload['exp']) || $payload['exp'] < $now) {
            throw new \RuntimeException('BrainLock::verify: token expired.');
        }
        if (isset($payload['nbf']) && $payload['nbf'] > $now + 10) {
            throw new \RuntimeException('BrainLock::verify: token not yet valid.');
        }
        // Audience must match this app — pulled from the api_key's first
        // /v1/auth/session call's response on the BrainLock side; we trust
        // that the token's aud belongs to whichever app this key is bound
        // to. (Hard cross-check would require a getApp endpoint; the
        // env-mismatch + signature checks make this safe.)

        // State-cookie cross-check. The popup-mode flow set a state cookie
        // when connect() ran; the JWT's session_id is opaque, but we can at
        // least defeat naive replay by clearing the cookie now.
        if (isset($_COOKIE[self::STATE_COOKIE])) {
            \setcookie(self::STATE_COOKIE, '', [
                'expires'  => 1,
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        // Flatten the identity bundle. The signed payload puts the bundle
        // under "profile"; we hoist its keys to the top level so callers
        // don't have to dig.
        $identity = [
            'sub'            => $payload['sub']        ?? '',
            'session_id'     => $payload['session_id'] ?? '',
            'verified'       => !empty($payload['verified']),
            'biometric_used' => !empty($payload['biometric_used']),
            'iat'            => $payload['iat'] ?? null,
            'exp'            => $payload['exp'] ?? null,
        ];
        $profile = $payload['profile'] ?? [];
        foreach (['name', 'email', 'picture'] as $k) {
            if (!empty($profile[$k])) {
                $identity[$k] = $profile[$k];
            }
        }
        return $identity;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private static function ensureConfigured(): void
    {
        if (empty(self::$config['api_key'])) {
            throw new \RuntimeException('BrainLock: call BrainLock::configure(…) first.');
        }
    }

    /**
     * Emit the iframe-launcher page. Inline HTML+JS, no external assets.
     *
     * In proxy mode (the default), the iframe loads from a path on YOUR
     * own site (e.g. /_bl/auth/<sid>?embed=iframe) — same-origin to the
     * top-level page so cookies behave as first-party. This works in
     * every modern browser including Safari.
     *
     * The iframe URL is built by rewriting the BrainLock-provided
     * authUrl: scheme/host are dropped, path/query preserved, prefixed
     * with the partner's embed_path. The proxy on the partner side
     * (BrainLock::handleEmbed) then forwards each request to
     * brainlock.id transparently.
     */
    private static function emitIframeOpener(string $authUrl): void
    {
        $authPath    = \parse_url($authUrl, PHP_URL_PATH);
        $authQuery   = \parse_url($authUrl, PHP_URL_QUERY);
        $proxyURL    = self::$config['embed_path'] . $authPath . ($authQuery ? '?' . $authQuery : '');
        $iframeUrl   = $proxyURL . (\strpos($proxyURL, '?') === false ? '?' : '&') . 'embed=iframe';
        $redirectUrl = $authUrl; // direct nav to brainlock.id, no proxy involved
        // postMessage origin: in proxy mode the iframe is on OUR origin,
        // so the message comes from window.location.origin. Compute at
        // emit time so the JS can compare exactly.
        $proxyOrigin = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
        $proxyOrigin .= '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $iframeUrlJs   = \json_encode($iframeUrl,   JSON_UNESCAPED_SLASHES);
        $redirectUrlJs = \json_encode($redirectUrl, JSON_UNESCAPED_SLASHES);
        $apiOriginJs   = \json_encode($proxyOrigin, JSON_UNESCAPED_SLASHES);

        \header('Cache-Control: no-store, must-revalidate');
        \header('Content-Type: text/html; charset=utf-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signing in with BrainLock…</title>
    <style>
        html, body { margin: 0; height: 100%; background: #0a0e1f; color: #f5f3fb; font: 15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        .bl_loader_wrap { height: 100%; display: grid; place-items: center; padding: 24px; text-align: center; }
        .bl_loader_inner { max-width: 360px; }
        .bl_loader_spinner { width: 36px; height: 36px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.15); border-top-color: #ff3d8b; animation: bl_spin 700ms linear infinite; margin: 0 auto 18px; }
        .bl_loader_h { font-size: 16px; font-weight: 600; margin: 0 0 6px; }
        .bl_loader_sub { font-size: 13px; opacity: 0.65; margin: 0; }
        @keyframes bl_spin { to { transform: rotate(360deg); } }
        #bl_iframe { position: fixed; inset: 0; width: 100vw; height: 100vh; border: 0; z-index: 9999; background: transparent; opacity: 0; transition: opacity 220ms ease; }
    </style>
</head>
<body>
<div class="bl_loader_wrap" id="bl_fallback_ui">
    <div class="bl_loader_inner">
        <div class="bl_loader_spinner"></div>
        <p class="bl_loader_h">Signing in with BrainLock…</p>
        <p class="bl_loader_sub">Just a moment.</p>
    </div>
</div>
<script>
(function () {
    var IFRAME_URL   = $iframeUrlJs;
    var REDIRECT_URL = $redirectUrlJs;
    var SAME_ORIGIN  = $apiOriginJs;

    // Proxy-mode iframe: same-origin to this page (which is on the
    // partner's domain). Cookies behave as first-party. No capability
    // checking needed — works on every browser.
    var iframe = document.createElement('iframe');
    iframe.id = 'bl_iframe';
    iframe.title = 'Sign in with BrainLock';
    iframe.src = IFRAME_URL;
    document.body.appendChild(iframe);
    requestAnimationFrame(function () { iframe.style.opacity = '1'; });

    window.addEventListener('message', function (event) {
        if (event.origin !== SAME_ORIGIN) return;
        var data = event.data || {};
        if (data.type !== 'brainlock:auth' || !data.url) return;
        window.location.href = data.url;
    });
})();
</script>
</body>
</html>
HTML;
        exit;
    }

    /** RSA public key (as openssl resource) for a given kid, fetched from JWKS. */
    private static function publicKeyFor(string $kid)
    {
        $jwks = self::fetchJwks();
        $jwk = null;
        foreach ($jwks as $candidate) {
            if (($candidate['kid'] ?? '') === $kid) {
                $jwk = $candidate;
                break;
            }
        }
        if ($jwk === null) {
            throw new \RuntimeException('BrainLock::verify: kid "' . $kid . '" not in JWKS.');
        }
        if (($jwk['kty'] ?? '') !== 'RSA') {
            throw new \RuntimeException('BrainLock::verify: unexpected key type for kid.');
        }
        $n = self::base64UrlDecode($jwk['n']);
        $e = self::base64UrlDecode($jwk['e']);
        $pem = self::rsaJwkToPem($n, $e);
        $key = \openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new \RuntimeException('BrainLock::verify: could not import public key.');
        }
        return $key;
    }

    /** Fetch + cache the JWKS in static memory for JWKS_TTL seconds. */
    private static function fetchJwks(): array
    {
        if (self::$jwksCache !== null && (\time() - self::$jwksCachedAt) < self::JWKS_TTL) {
            return self::$jwksCache;
        }
        $url = self::$config['api_base'] . '/v1/.well-known/jwks.json';
        $resp = self::http('GET', $url, null, []);
        if (empty($resp['keys']) || !\is_array($resp['keys'])) {
            throw new \RuntimeException('BrainLock::verify: JWKS endpoint returned no keys.');
        }
        self::$jwksCache = $resp['keys'];
        self::$jwksCachedAt = \time();
        return self::$jwksCache;
    }

    /**
     * Convert a JWK RSA (n, e) pair to PEM that openssl can import.
     * Builds a minimal SubjectPublicKeyInfo DER and wraps it in PEM headers.
     */
    private static function rsaJwkToPem(string $n, string $e): string
    {
        $modulus  = self::derUnsignedInteger($n);
        $exponent = self::derUnsignedInteger($e);
        $rsaKey   = self::derSequence($modulus . $exponent);
        // OID for rsaEncryption: 1.2.840.113549.1.1.1
        $rsaOid   = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01" . "\x05\x00";
        $algIdent = self::derSequence($rsaOid);
        $bitStr   = "\x03" . self::derLength(\strlen($rsaKey) + 1) . "\x00" . $rsaKey;
        $spki     = self::derSequence($algIdent . $bitStr);
        $pem      = "-----BEGIN PUBLIC KEY-----\n"
                  . \chunk_split(\base64_encode($spki), 64, "\n")
                  . "-----END PUBLIC KEY-----\n";
        return $pem;
    }

    private static function derUnsignedInteger(string $bytes): string
    {
        // ASN.1 INTEGER must be two's-complement positive; prepend 0x00 if
        // the high bit is set so it isn't read as negative.
        if (\strlen($bytes) > 0 && (\ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . self::derLength(\strlen($bytes)) . $bytes;
    }

    private static function derSequence(string $contents): string
    {
        return "\x30" . self::derLength(\strlen($contents)) . $contents;
    }

    private static function derLength(int $len): string
    {
        if ($len < 0x80) {
            return \chr($len);
        }
        $bytes = '';
        while ($len > 0) {
            $bytes = \chr($len & 0xff) . $bytes;
            $len >>= 8;
        }
        return \chr(0x80 | \strlen($bytes)) . $bytes;
    }

    private static function jsonDecodeSegment(string $segment): array
    {
        $json = self::base64UrlDecode($segment);
        $data = \json_decode($json, true);
        if (!\is_array($data)) {
            throw new \RuntimeException('BrainLock::verify: could not JSON-decode token segment.');
        }
        return $data;
    }

    private static function base64UrlDecode(string $s): string
    {
        $s = \strtr($s, '-_', '+/');
        $pad = \strlen($s) % 4;
        if ($pad) $s .= \str_repeat('=', 4 - $pad);
        $out = \base64_decode($s, true);
        if ($out === false) {
            throw new \RuntimeException('BrainLock: base64url decode failed.');
        }
        return $out;
    }

    /** Minimal curl-based JSON HTTP client. */
    private static function http(string $method, string $url, ?array $body, array $headers): array
    {
        $ch = \curl_init();
        \curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $hdrs = $headers;
        if ($body !== null) {
            \curl_setopt($ch, CURLOPT_POSTFIELDS, \json_encode($body));
            $hdrs[] = 'Content-Type: application/json';
        }
        $hdrs[] = 'User-Agent: brainlock-php/' . self::VERSION;
        \curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);

        $raw  = \curl_exec($ch);
        $err  = \curl_error($ch);
        $code = (int)\curl_getinfo($ch, CURLINFO_HTTP_CODE);
        \curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('BrainLock: HTTP ' . $method . ' ' . $url . ' failed: ' . $err);
        }
        $data = \json_decode($raw, true);
        if (!\is_array($data)) {
            throw new \RuntimeException('BrainLock: ' . $method . ' ' . $url . ' returned non-JSON (HTTP ' . $code . '): ' . \substr($raw, 0, 200));
        }
        if ($code >= 400) {
            // Return the error body so callers (currently only connect())
            // can surface the BrainLock-provided message verbatim.
            return $data;
        }
        return $data;
    }
}
