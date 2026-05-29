<?php
/**
 * Sign-in callback. BrainLock redirects here with ?token=<JWT>&status=…
 * after a completed (or failed) ceremony. We verify the token, stash
 * the identity in the session, and land the user on the wallet.
 *
 * Error paths:
 *   status=failed | denied → render a small inline page explaining
 *     what happened with a "try again" link. We never blame the user.
 *   token verification throws → same handling.
 */
require __DIR__ . '/../_bootstrap.php';

$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$token  = isset($_GET['token'])  ? (string)$_GET['token']  : '';
$reason = isset($_GET['reason']) ? (string)$_GET['reason'] : '';

function tc_callback_error(string $headline, string $body): void {
    \http_response_code(400);
    \header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
       . '<title>Sign-in incomplete — TangoCash</title>'
       . '<link rel="stylesheet" href="/css/tangocash.css?v=' . (@\filemtime(__DIR__ . '/../css/tangocash.css') ?: \time()) . '">'
       . '</head><body class="is_signed_out">'
       . '<main class="tc_main"><section class="tc_form_section">'
       . '<h1 class="tc_form_h">' . \htmlspecialchars($headline) . '</h1>'
       . '<p style="color:var(--tc_text_dim); line-height:1.65;">' . \htmlspecialchars($body) . '</p>'
       . '<p style="margin-top:24px;"><a class="tc_chip" href="/">Try again</a></p>'
       . '</section></main></body></html>';
    exit;
}

if ($status === 'denied') {
    tc_callback_error(
        "You didn't finish signing in",
        "Looks like you cancelled the BrainLock prompt. No worries — start over whenever you're ready."
    );
}
if ($status === 'failed' || $status !== 'success') {
    $detail = $reason !== '' ? (' (' . $reason . ')') : '';
    tc_callback_error(
        'Sign-in didn\'t complete',
        'BrainLock returned a non-success status' . $detail . '. Give it another go.'
    );
}
if ($token === '') {
    tc_callback_error(
        'No token on the callback',
        'We expected a sign-in token from BrainLock but didn\'t receive one. Please try again.'
    );
}

try {
    $identity = \BrainLock::verify($token);
} catch (\Throwable $e) {
    \error_log('[tangocash] BrainLock::verify failed: ' . $e->getMessage());
    tc_callback_error(
        'We couldn\'t verify your sign-in',
        'The token from BrainLock didn\'t pass our signature check. This is on us — please try again, and if it keeps happening, drop us a note.'
    );
}

if (empty($identity['sub'])) {
    tc_callback_error(
        'Token verified but is missing identity',
        'The token verified, but the identity bundle was empty. That shouldn\'t happen.'
    );
}

// Sign the user in for this session.
\error_log('[tc-callback] verify OK sub=' . ($identity['sub'] ?? '?') . ' session_id_before=' . \session_id());
\session_regenerate_id(true); // privilege change — fresh session ID
\error_log('[tc-callback] regenerated session_id_after=' . \session_id());
$_SESSION['bl_user']   = $identity;
$_SESSION['bl_signed_in_at'] = \time();
\error_log('[tc-callback] stored bl_user, $_SESSION keys=' . \implode(',', \array_keys($_SESSION)));
\session_write_close(); // commit BEFORE redirect — belt + suspenders

\header('Location: /wallet.php');
exit;
