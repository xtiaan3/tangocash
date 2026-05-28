<?php
/**
 * Stubbed demo data for the TangoCash v0 visual scaffold.
 *
 * Nothing here is real. No Postgres yet. No real BrainLock sign-in yet.
 * This file exists so the visual layouts have something to render against
 * while we iterate on look and feel. When the PHP SDK + Postgres land,
 * everything in this file gets replaced with real queries.
 */

// The "currently signed-in" demo user. Changes here ripple to every page.
$DEMO_USER = [
    'first_name' => 'Christiaan',
    'last_name'  => 'Rendle',
    'email'      => 'xtiaan@gmail.com',
    'handle'     => 'xtiaan',
    // Avatar pulled from BrainLock — when we go real, this comes from the
    // `picture` claim on the JWT we receive from /v1/auth/verify.
    'picture'    => 'https://brain-lock.sfo3.digitaloceanspaces.com/profile/5e2753a4-fb5c-46f7-a36d-c84103d76e64_1779972054_c11a270f.jpg',
    'balance'    => 1247.50,
];

// Recent activity feed for the wallet home. Each row renders as a single
// line in the feed. `type` controls how the amount is colored / signed.
$DEMO_ACTIVITY = [
    [
        'type'   => 'received',
        'who'    => 'sarah',
        'note'   => 'dinner split',
        'amount' => 28.50,
        'when'   => '2 hours ago',
    ],
    [
        'type'   => 'sent',
        'who'    => 'mike',
        'note'   => 'concert tickets',
        'amount' => 120.00,
        'when'   => 'yesterday',
    ],
    [
        'type'   => 'received',
        'who'    => 'jamie',
        'note'   => 'gas',
        'amount' => 40.00,
        'when'   => '3 days ago',
    ],
    [
        'type'   => 'sent',
        'who'    => 'lena',
        'note'   => 'birthday gift split',
        'amount' => 35.00,
        'when'   => 'last week',
    ],
    [
        'type'   => 'received',
        'who'    => 'devin',
        'note'   => null,
        'amount' => 15.00,
        'when'   => 'last week',
    ],
    [
        'type'   => 'sent',
        'who'    => 'pat',
        'note'   => 'rent',
        'amount' => 800.00,
        'when'   => 'May 1',
    ],
];

// Recipient suggestions for the send/request forms.
$DEMO_RECENTS = ['sarah', 'mike', 'jamie', 'lena', 'devin', 'pat'];

// Small helpers used by every page.
function tc_money(float $amount): string {
    return '$' . number_format($amount, 2);
}
function tc_initial(string $handle): string {
    return strtoupper(substr($handle, 0, 1));
}
