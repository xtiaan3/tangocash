<?php
/**
 * TangoCash demo data — partly real, partly stubbed.
 *
 *   - $DEMO_USER is REAL when the visitor has signed in via BrainLock
 *     (name / email / picture come straight from the verified JWT).
 *     When signed out, it falls back to a hardcoded preview persona
 *     so the wallet/profile screens still render for casual visitors.
 *
 *   - $DEMO_ACTIVITY, $DEMO_RECENTS, and the balance are still hardcoded
 *     fiction — TangoCash has no Postgres yet. When persistence lands,
 *     these come from tc_transactions / tc_users.
 *
 * Bootstrap must already have run by this point (most pages do
 * require __DIR__ . '/_bootstrap.php' in their header. _demo_data.php
 * pulls it in defensively in case a page forgets.
 */

require_once __DIR__ . '/_bootstrap.php';

$tc_user = \tc_current_user();

// Default fallback persona — only shown when signed out.
$DEMO_USER = [
    'first_name' => 'Christiaan',
    'last_name'  => 'Rendle',
    'email'      => 'xtiaan@gmail.com',
    'handle'     => 'xtiaan',
    'picture'    => 'https://brain-lock.sfo3.digitaloceanspaces.com/profiles/vault_5e2753a4-fb5c-46f7-a36d-c84103d76e64_1779972054_c11a270f.jpg',
    'balance'    => 1247.50,
    '_real'      => false,
];

if ($tc_user !== null) {
    // Split name back into first/last for the UI bits that care.
    $full   = \trim((string)($tc_user['name'] ?? ''));
    $bits   = \preg_split('/\s+/', $full, 2) ?: [$full, ''];
    $first  = $bits[0] ?? '';
    $last   = $bits[1] ?? '';
    // Make a TangoCash @handle from the email local-part as a stub.
    $email  = (string)($tc_user['email'] ?? '');
    $handle = $email !== '' ? \strstr($email, '@', true) : '';
    if ($handle === false || $handle === '') $handle = 'user';

    $DEMO_USER = [
        'first_name' => $first,
        'last_name'  => $last,
        'email'      => $email,
        'handle'     => \strtolower($handle),
        'picture'    => (string)($tc_user['picture'] ?? ''),
        'balance'    => 1247.50,    // still stub until tc_transactions exists
        '_real'      => true,
    ];
}

// Recent activity feed. Stubbed.
$DEMO_ACTIVITY = [
    ['type' => 'received', 'who' => 'sarah', 'note' => 'dinner split',      'amount' => 28.50, 'when' => '2 hours ago'],
    ['type' => 'sent',     'who' => 'mike',  'note' => 'concert tickets',   'amount' => 120.00,'when' => 'yesterday'],
    ['type' => 'received', 'who' => 'jamie', 'note' => 'gas',               'amount' => 40.00, 'when' => '3 days ago'],
    ['type' => 'sent',     'who' => 'lena',  'note' => 'birthday gift split','amount'=> 35.00, 'when' => 'last week'],
    ['type' => 'received', 'who' => 'devin', 'note' => null,                'amount' => 15.00, 'when' => 'last week'],
    ['type' => 'sent',     'who' => 'pat',   'note' => 'rent',              'amount' => 800.00,'when' => 'May 1'],
];

$DEMO_RECENTS = ['sarah', 'mike', 'jamie', 'lena', 'devin', 'pat'];

// Helpers
function tc_money(float $amount): string { return '$' . \number_format($amount, 2); }
function tc_initial(string $handle): string { return \strtoupper(\substr($handle, 0, 1)); }
