<?php
/**
 * Sign-in entry point. The landing-page button hits this URL — we ask
 * BrainLock to start an auth session and BrainLock::connect() emits
 * a 302 to brainlock.id/auth/<session>. From the user's perspective:
 *
 *   click Sign In → bounce to brainlock.id auth landing → finish
 *   ceremony there → 302 back to /auth/callback.php → bounce to
 *   /wallet.php signed in.
 *
 * Note: nothing in this file mentions TangoCash-specific identity. It
 * could live in any partner integration unchanged. The canonical
 * 8-line shape — almost.
 */
require __DIR__ . '/../_bootstrap.php';

// `user_id` is YOUR app's stable identifier for the user — BrainLock
// uses it to remember which BrainLock vault is bound to which TangoCash
// account, so the second-and-onward sign-in can magic-flash (no
// challenge, instant JWT). It MUST be stable across browser sessions.
//
// TangoCash has no Postgres user table yet, so we mint a UUID on first
// visit and stash it in a long-lived HttpOnly cookie. Cookie survives
// PHP session expiry; PHP session does not — that's the difference
// that broke magic flash before we did this.
//
// When tc_users.id is real, swap to that — same idea, different store.
$cookieName = 'tc_user_id';
if (empty($_COOKIE[$cookieName])) {
    $userId = \bin2hex(\random_bytes(16));
    \setcookie($cookieName, $userId, [
        'expires'  => \time() + (60 * 60 * 24 * 365 * 2), // 2 years
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[$cookieName] = $userId; // make it readable for the current request too
} else {
    $userId = $_COOKIE[$cookieName];
}

\BrainLock::connect([
    'user_id' => $userId,
]);
