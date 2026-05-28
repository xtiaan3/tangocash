<?php
/**
 * Sign-in entry point. The landing-page button hits this URL — we ask
 * BrainLock to start an auth session and then BrainLock::connect()
 * emits the popup-opener page. From the user's perspective:
 *
 *   click Sign In → loader spins briefly → popup with BrainLock UI
 *   → finish ceremony → popup closes → user lands on /auth/callback.php
 *   → which then bounces them to /wallet.php signed in.
 *
 * Note: nothing in this file mentions TangoCash-specific identity. It
 * could live in any partner integration unchanged. The canonical
 * 8-line shape — almost.
 */
require __DIR__ . '/../_bootstrap.php';

// `user_id` is YOUR app's stable identifier for the user. TangoCash
// doesn't have its own user IDs yet (no Postgres), so we use the PHP
// session ID as a one-off binder. Once we wire tc_users, swap this
// for the real tc_users.id.
\BrainLock::connect([
    'user_id' => \session_id(),
]);
