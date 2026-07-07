<?php
/**
 * BrainLock disconnect webhook receiver.
 *
 * Fired by brainlock.id whenever a binding between TangoCash and a
 * BrainLock vault is revoked — either by the user clicking "Remove"
 * on https://brainlock.id/connections, or by a partner-side admin
 * calling POST /v1/auth/unbind. Without this, the user's TangoCash
 * PHP session keeps them signed in here even after they've severed
 * the connection on the BrainLock side. With it, every active TC
 * session for that user (this browser, their phone, anything else)
 * gets killed immediately.
 *
 * Contract (matches internal/http/handlers/disconnect_webhook.go on
 * the BrainLock side):
 *
 *   POST /auth/disconnect
 *   Content-Type: application/json
 *   X-BrainLock-Signature: sha256=<hex>
 *   X-BrainLock-Timestamp: <unix-seconds>
 *
 *   {
 *     "event":       "user.disconnected",
 *     "user_id":     "<the developer_user_id we minted on first sign-in>",
 *     "environment": "live" | "test",
 *     "app_id":      "<uuid of this app on the BL side>",
 *     "timestamp":   <unix-seconds>
 *   }
 *
 * Signature: HMAC-SHA256 over the exact request body, keyed by this
 * app's current LIVE BrainLock API key. We verify with the same key
 * we use for outbound calls — see _bootstrap.php / .env.
 *
 * Replay protection: the timestamp must be within 5 minutes of "now".
 * That window is generous enough for legitimate clock skew + retries
 * but tight enough that a captured POST can't be replayed days later.
 *
 * Idempotency: any user_id that doesn't match a tc_users row, or
 * matches a row that's already been logged out, returns 200 anyway.
 * BrainLock's webhook delivery is fire-and-forget; if the user was
 * already gone, that's still success from BrainLock's perspective.
 */
require __DIR__ . '/../_bootstrap.php';

// Method gate — only POST is allowed; anything else is almost
// certainly a misconfigured probe.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    \http_response_code(405);
    \header('Allow: POST');
    echo \json_encode(['error' => 'method_not_allowed']);
    exit;
}

$raw = \file_get_contents('php://input') ?: '';
$sigHeader = $_SERVER['HTTP_X_BRAINLOCK_SIGNATURE'] ?? '';

if ($raw === '' || $sigHeader === '') {
    \http_response_code(400);
    echo \json_encode(['error' => 'missing_signature_or_body']);
    exit;
}

// HMAC verification runs FIRST — the signed body carries its own
// timestamp inside the JSON, so we can't trust anything about the
// message until the signature checks out. This ordering closes the
// pre-2026-07-04 replay window where the skew check happened against
// the unsigned X-BrainLock-Timestamp header, letting an attacker
// replay a captured (body, sig) pair forever by refreshing the header.
//
// The signing key is this app's LIVE BrainLock API key. Read via
// getenv() first (PHP-FPM exposes pool env[] entries there), falling
// back to $_ENV for setups that populate it that way. Constant-time
// compare on the signature.
$apiKey = \getenv('BRAINLOCK_API_KEY');
if ($apiKey === false || $apiKey === '') {
    $apiKey = (string) ($_ENV['BRAINLOCK_API_KEY'] ?? '');
}
if ($apiKey === '') {
    \error_log('[tangocash disconnect] BRAINLOCK_API_KEY not set');
    \http_response_code(500);
    echo \json_encode(['error' => 'server_misconfigured']);
    exit;
}
$expected = 'sha256=' . \hash_hmac('sha256', $raw, $apiKey);
if (!\hash_equals($expected, $sigHeader)) {
    \error_log('[tangocash disconnect] bad signature');
    \http_response_code(401);
    echo \json_encode(['error' => 'bad_signature']);
    exit;
}

$payload = \json_decode($raw, true);
if (!\is_array($payload) || ($payload['event'] ?? '') !== 'user.disconnected') {
    \http_response_code(400);
    echo \json_encode(['error' => 'unrecognised_event']);
    exit;
}

// Timestamp window — read from the SIGNED body, not the header. The
// header is defense-in-depth only; ignore it once we've verified sig.
$ts = isset($payload['timestamp']) ? (int) $payload['timestamp'] : 0;
$skew = \abs(\time() - $ts);
if ($ts <= 0 || $skew > 300) {
    \http_response_code(400);
    echo \json_encode(['error' => 'stale_or_skewed_timestamp']);
    exit;
}
// Identity-first: BrainLock sends `subject` (the pairwise per-app account key,
// == tc_users.bl_sub). Prefer it; fall back to user_id only for a webhook
// fired mid-cutover before this deploy. Either way we resolve to a bl_sub.
$subject = \trim((string) ($payload['subject'] ?? ''));
$userID  = \trim((string) ($payload['user_id'] ?? ''));
if ($subject === '' && $userID === '') {
    \http_response_code(400);
    echo \json_encode(['error' => 'missing_subject']);
    exit;
}

// bl_sub IS the subject, so this is a direct primary-key hit. The user_id
// fallback keys on the legacy bl_user_id column (cutover only).
$blSub = null;
try {
    if ($subject !== '') {
        $stmt = \tc_db()->prepare('SELECT bl_sub FROM tc_users WHERE bl_sub = ? LIMIT 1');
        $stmt->execute([$subject]);
    } else {
        $stmt = \tc_db()->prepare('SELECT bl_sub FROM tc_users WHERE bl_user_id = ? LIMIT 1');
        $stmt->execute([$userID]);
    }
    $row = $stmt->fetch();
    if ($row && !empty($row['bl_sub'])) {
        $blSub = (string) $row['bl_sub'];
    }
} catch (\Throwable $e) {
    \error_log('[tangocash disconnect] DB lookup failed: ' . $e->getMessage());
    \http_response_code(500);
    echo \json_encode(['error' => 'db_error']);
    exit;
}

if ($blSub === null) {
    // No match — the user may have been deleted on our side already,
    // or signed in under a different user_id. Either way, nothing to
    // do here; respond 200 so BrainLock marks the webhook as
    // delivered and doesn't retry.
    \http_response_code(200);
    echo \json_encode(['success' => true, 'matched' => false]);
    exit;
}

// Mark every active session for this bl_sub as "must sign in again
// on next request." Implementation note: PHP sessions are file-based
// by default, so we can't iterate by user. Instead we set a flag on
// tc_users that the front-controller checks at the top of every page
// load — if `force_signout_at` > the session's last verified time,
// the session is destroyed before any page renders.
//
// (A heavier "scan every session file" approach would also work but
// adds disk-walking per disconnect; the flag pattern is what
// production session frameworks use for this.)
try {
    $upd = \tc_db()->prepare('UPDATE tc_users SET force_signout_at = NOW() WHERE bl_sub = ?');
    $upd->execute([$blSub]);
} catch (\Throwable $e) {
    \error_log('[tangocash disconnect] force_signout_at write failed: ' . $e->getMessage());
    \http_response_code(500);
    echo \json_encode(['error' => 'db_error']);
    exit;
}

// Best-effort: if the request came from the SAME browser as the
// currently-active PHP session (i.e. the user revoked from their own
// laptop), nuke that session inline too so the next page tick
// already reflects the change without waiting for the front
// controller's check.
//
// Skipped when the request doesn't carry our session cookie (the
// common case — webhook is server-to-server).
if (isset($_COOKIE[\session_name()])) {
    \session_start();
    $sessionSub = $_SESSION['bl_user']['sub'] ?? '';
    if ($sessionSub === $blSub) {
        $_SESSION = [];
        \session_destroy();
    }
    \session_write_close();
}

\http_response_code(200);
echo \json_encode(['success' => true, 'matched' => true]);
