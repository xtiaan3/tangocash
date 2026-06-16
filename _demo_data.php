<?php
/**
 * TangoCash data loader.
 *
 *   - When signed in via BrainLock: pulls $DEMO_USER from tc_users +
 *     tc_wallets, $DEMO_ACTIVITY from tc_transactions joined with the
 *     counterparty's tc_users row, and $DEMO_RECENTS from tc_contacts.
 *     Everything below the wallet card is REAL persistent state.
 *
 *   - When signed out: falls back to a hardcoded preview persona +
 *     fake activity so the wallet/profile screens still render
 *     attractively for casual visitors landing on /wallet.php via
 *     a deep link with no session.
 *
 * Bootstrap must run first; we pull it in defensively in case a page
 * forgets.
 */

require_once __DIR__ . '/_bootstrap.php';

$tc_user = \tc_current_user();

// ---------------------------------------------------------------
// Signed-out fallback persona.
// ---------------------------------------------------------------
$DEMO_USER = [
    'bl_sub'     => null,
    'first_name' => 'Tim',
    'last_name'  => 'Apple',
    'email'      => 'tim.apple@example.com',
    'handle'     => 'tim_apple',
    'picture'    => null,
    'balance'    => 1247.50,
    '_real'      => false,
];

$DEMO_ACTIVITY = [
    ['type' => 'received', 'who' => 'sarah', 'note' => 'dinner split',      'amount' => 28.50, 'when' => '2 hours ago'],
    ['type' => 'sent',     'who' => 'mike',  'note' => 'concert tickets',   'amount' => 120.00,'when' => 'yesterday'],
    ['type' => 'received', 'who' => 'jamie', 'note' => 'gas',               'amount' => 40.00, 'when' => '3 days ago'],
    ['type' => 'sent',     'who' => 'lena',  'note' => 'birthday gift split','amount'=> 35.00, 'when' => 'last week'],
    ['type' => 'received', 'who' => 'devin', 'note' => null,                'amount' => 15.00, 'when' => 'last week'],
    ['type' => 'sent',     'who' => 'pat',   'note' => 'rent',              'amount' => 800.00,'when' => 'May 1'],
];

$DEMO_RECENTS = ['sarah', 'mike', 'jamie', 'lena', 'devin', 'pat'];

// ---------------------------------------------------------------
// Real data when signed in.
// ---------------------------------------------------------------
if ($tc_user !== null) {
    try {
        $blSub  = $tc_user['sub'];
        $userRow = \tc_get_user($blSub);
        $wallet  = \tc_get_wallet($blSub);

        if ($userRow !== null) {
            // Read first_name + last_name as separate columns (migration 0003).
            // Falls back to splitting the legacy `name` column for rows that
            // haven't been re-signed-in since the split shipped.
            $first = (string)($userRow['first_name'] ?? '');
            $last  = (string)($userRow['last_name']  ?? '');
            if ($first === '' && $last === '') {
                $bits  = \preg_split('/\s+/', \trim((string)$userRow['name']), 2) ?: ['', ''];
                $first = $bits[0] ?? '';
                $last  = $bits[1] ?? '';
            }
            $DEMO_USER = [
                'bl_sub'     => $blSub,
                'first_name' => $first,
                'last_name'  => $last,
                'email'      => (string)$userRow['email'],
                'handle'     => \tc_handle_for($userRow),
                // Prefer the TC-owned thumb (one-shot avatar handoff —
                // see _avatars.php). picture_url is the legacy hot-link
                // column kept around for one migration cycle; fall back
                // to it for rows that predate the handoff. After the
                // legacy column is dropped this collapses to just
                // picture_thumb_url.
                'picture'    => (string)(
                    $userRow['picture_thumb_url']
                    ?? $userRow['picture_full_url']
                    ?? $userRow['picture_url']
                    ?? ''
                ),
                'balance'    => ((int)$wallet['balance_cents']) / 100,
                '_real'      => true,
            ];
        }

        // Recent activity — last 20 transactions involving this user,
        // newest first. JOIN to fetch the counterparty's profile for
        // the row display.
        $activityStmt = \tc_db()->prepare(
            "SELECT
                t.kind, t.status, t.amount_cents, t.memo, t.created_at,
                CASE WHEN t.from_sub = :sub THEN 'sent' ELSE 'received' END AS direction,
                CASE WHEN t.from_sub = :sub THEN t.to_sub  ELSE t.from_sub END AS other_sub
             FROM tc_transactions t
             WHERE (t.from_sub = :sub OR t.to_sub = :sub)
               AND t.status IN ('completed', 'pending')
             ORDER BY t.created_at DESC
             LIMIT 20"
        );
        $activityStmt->execute([':sub' => $blSub]);
        $rows = $activityStmt->fetchAll();

        // Bulk-load counterparty rows so we don't N+1 the DB.
        $otherSubs = \array_unique(\array_column($rows, 'other_sub'));
        $otherMap = [];
        if (!empty($otherSubs)) {
            $placeholders = \implode(',', \array_fill(0, \count($otherSubs), '?'));
            $stmt = \tc_db()->prepare("SELECT bl_sub, name, email FROM tc_users WHERE bl_sub IN ($placeholders)");
            $stmt->execute(\array_values($otherSubs));
            foreach ($stmt->fetchAll() as $r) { $otherMap[$r['bl_sub']] = $r; }
        }

        $DEMO_ACTIVITY = [];
        foreach ($rows as $r) {
            $other = $otherMap[$r['other_sub']] ?? null;
            $handle = $other !== null ? \tc_handle_for($other) : 'user';
            $DEMO_ACTIVITY[] = [
                'type'   => $r['direction'],
                'who'    => $handle,
                'note'   => $r['memo'],
                'amount' => ((int)$r['amount_cents']) / 100,
                'when'   => \tc_relative_time($r['created_at']),
                'status' => $r['status'],   // 'pending' lets the UI flag unaccepted requests
                'kind'   => $r['kind'],
            ];
        }

        // Recent contacts for the send/request chip rows.
        $contactsStmt = \tc_db()->prepare(
            "SELECT u.email
             FROM tc_contacts c
             JOIN tc_users u ON u.bl_sub = c.contact_sub
             WHERE c.bl_sub = ?
             ORDER BY c.last_seen_at DESC
             LIMIT 6"
        );
        $contactsStmt->execute([$blSub]);
        $DEMO_RECENTS = [];
        foreach ($contactsStmt->fetchAll() as $r) {
            $local = \strstr((string)$r['email'], '@', true);
            if ($local !== false && $local !== '') {
                $DEMO_RECENTS[] = \strtolower($local);
            }
        }
    } catch (\Throwable $e) {
        \error_log('[tangocash] data loader DB failure: ' . $e->getMessage());
        // Keep fallback values so the page still renders something.
    }
}

// ---------------------------------------------------------------
// Helpers used by the wallet / send / request templates.
// ---------------------------------------------------------------
function tc_money(float $amount): string {
    return '$' . \number_format($amount, 2);
}

function tc_initial(string $handle): string {
    return \strtoupper(\substr($handle, 0, 1));
}

/**
 * tc_relative_time — humanise a SQL timestamp into "2 hours ago" etc.
 * Defaults to the date for anything older than 7 days.
 */
function tc_relative_time(string $sqlTime): string {
    $ts = \strtotime($sqlTime);
    if ($ts === false) return $sqlTime;
    $delta = \time() - $ts;
    if ($delta < 60)         return 'just now';
    if ($delta < 3600)       return \intval($delta / 60) . ' min ago';
    if ($delta < 7200)       return '1 hour ago';
    if ($delta < 86400)      return \intval($delta / 3600) . ' hours ago';
    if ($delta < 172800)     return 'yesterday';
    if ($delta < 7 * 86400)  return \intval($delta / 86400) . ' days ago';
    return \date('M j', $ts);
}
