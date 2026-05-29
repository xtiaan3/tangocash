<?php
/**
 * TangoCash bootstrap. Every page requires this once at the top — it
 * starts the PHP session and loads the BrainLock SDK with our app
 * config.
 *
 * Why session_start happens here (and not in _header.php): pages call
 * tc_current_user() before any HTML is sent, so the session must be
 * live before we know whether to render signed-in vs signed-out
 * chrome.
 */

if (\session_status() !== \PHP_SESSION_ACTIVE) {
    \session_name('tc_session');
    \session_set_cookie_params([
        'lifetime' => 0,         // session cookie — dies with the browser
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    \session_start();
}

require_once __DIR__ . '/lib/BrainLock.php';

\BrainLock::configure([
    'api_key'      => \getenv('BRAINLOCK_API_KEY')      ?: ($_ENV['BRAINLOCK_API_KEY']      ?? ''),
    'callback_url' => \getenv('BRAINLOCK_CALLBACK_URL') ?: ($_ENV['BRAINLOCK_CALLBACK_URL'] ?? ''),
    'api_base'     => \getenv('BRAINLOCK_API_BASE')     ?: ($_ENV['BRAINLOCK_API_BASE']     ?? 'https://brainlock.id'),
    'mode'         => 'redirect',
]);

/**
 * Returns the currently signed-in user (BrainLock identity) or null.
 * Shape matches BrainLock::verify() output:
 *   ['sub', 'name', 'email', 'picture'?, 'verified', 'biometric_used', ...]
 */
function tc_current_user(): ?array {
    if (!empty($_SESSION['bl_user']) && \is_array($_SESSION['bl_user'])) {
        return $_SESSION['bl_user'];
    }
    return null;
}

/** Sign-out by destroying the session. */
function tc_sign_out(): void {
    $_SESSION = [];
    if (\session_status() === \PHP_SESSION_ACTIVE) {
        \session_destroy();
    }
    \setcookie('tc_session', '', [
        'expires'  => 1,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
