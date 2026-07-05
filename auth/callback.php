<?php
/**
 * Sign-in callback. BrainLock redirects here with ?token=<JWT>&status=…
 * after a completed (or failed) ceremony. We verify the token, stash
 * the identity in the session, and land the user on the wallet.
 *
 * This file is the canonical reference for how a partner SHOULD handle
 * the (status, reason) matrix BrainLock emits — see
 * brainlock-go/docs/API_V1.md "Callback outcomes". Each case is handled
 * distinctly so the user gets the right UX for the actual failure:
 *
 *   status=success                         → verify JWT, sign in, → /wallet.php
 *   status=denied  reason=user_denied      → silent /, they meant to cancel
 *   status=failed  reason=session_expired  → silent / with flash banner
 *   status=failed  reason=session_already_resolved → silent / with flash banner
 *   status=failed  reason=challenge_failed → friendly inline retry page
 *   status=failed  reason=account_switch   → distinct copy + retry
 *   anything else                          → fallback using error_description
 *
 * We never blame the user. We never show a stack-trace style message.
 */
require __DIR__ . '/../_bootstrap.php';

$status   = isset($_GET['status'])            ? (string)$_GET['status']            : '';
$token    = isset($_GET['token'])             ? (string)$_GET['token']             : '';
$reason   = isset($_GET['reason'])            ? (string)$_GET['reason']            : '';
$errDesc  = isset($_GET['error_description']) ? (string)$_GET['error_description'] : '';
$intent   = isset($_GET['intent'])            ? (string)$_GET['intent']            : 'connect';
$verifID  = isset($_GET['verification_id'])   ? (string)$_GET['verification_id']   : '';

/**
 * Render the inline retry page. Used for failures where the user is
 * mid-flow and the right next action is "try again" (challenge failed,
 * account switch, anything we don't have specific copy for).
 */
function tc_callback_retry_page(string $headline, string $body): void {
    \http_response_code(400);
    \header('Content-Type: text/html; charset=utf-8');
    $cssV = @\filemtime(__DIR__ . '/../css/tangocash.css') ?: \time();
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<title>Sign-in didn\'t complete — TangoCash</title>'
       . '<link rel="stylesheet" href="/css/tangocash.css?v=' . $cssV . '">'
       . '</head><body class="tc_v6_page is_signed_out">'
       . '<main class="tc_v6_main"><section class="tc_v6_hero">'
       . '<div class="tc_v6_hero_eyebrow"><span class="tc_v6_hero_eyebrow_star">✱</span><span>Sign-in</span></div>'
       . '<h1 class="tc_v6_display tc_v6_display_solid">' . \htmlspecialchars($headline) . '</h1>'
       . '<p class="tc_v6_hero_sub">' . \htmlspecialchars($body) . '</p>'
       . '<div class="tc_v6_hero_cta_wrap">'
       . '<a class="tc_v6_cta_white" href="/auth/start"><span>Try again</span><span class="tc_v6_cta_white_star">✱</span></a>'
       . '</div>'
       . '</section></main></body></html>';
    exit;
}

/**
 * tc_callback_error_copy — pick user-facing copy based on WHY the
 * BrainLock SDK rejected the token. The SDK collapses everything into
 * BrainLockException, so we pattern-match the message and branch. Real
 * partners should copy this helper — the pre-2026-07-04 version showed
 * "didn't pass our signature check" for EVERY failure, including plain
 * "the token expired because the user let the tab sit for 20 minutes"
 * (blaming the user's crypto for their own idle timeout is the exact
 * anti-pattern our brief explicitly warns against).
 *
 * $ceremony is 'connect' or 'verify' — lets us tune the copy so a
 * Verify failure doesn't sound like a Connect failure.
 *
 * @return array{0:string,1:string} [$headline, $body]
 */
function tc_callback_error_copy(\Throwable $e, string $ceremony): array {
    $noun = ($ceremony === 'verify') ? 'authorization' : 'sign-in';
    $msg  = $e->getMessage();

    // Expired / not-yet-valid — the token clock ran out. Not the user's
    // fault; not a signature problem. Ask them to redo the ceremony.
    if (\str_contains($msg, 'expired') || \str_contains($msg, 'not yet valid')) {
        return [
            "Your {$noun} timed out",
            "That took long enough that BrainLock retired the token. Please try again — it only takes a moment."
        ];
    }

    // Audience / issuer / intent mismatch — configuration problem on
    // OUR side (wrong app_id / wrong endpoint / wrong ceremony). Never
    // frame as a user error.
    if (\str_contains($msg, 'audience mismatch')
        || \str_contains($msg, 'issuer mismatch')
        || \str_contains($msg, "expected '")   // intent mismatch
        || \str_contains($msg, 'missing the \'intent\'')
    ) {
        return [
            "We couldn't accept your {$noun}",
            "Something's off with our BrainLock setup. That's on us — please try again in a minute, and if it keeps happening, drop us a note."
        ];
    }

    // JWKS / network / any RuntimeException from http() — BrainLock
    // couldn't be reached or its keys couldn't be fetched. The user did
    // nothing wrong; retry usually clears it.
    if ($e instanceof \RuntimeException || \str_contains($msg, 'JWKS')) {
        return [
            "BrainLock is briefly unreachable",
            "We couldn't reach BrainLock to finish your {$noun}. Please try again — it's usually cleared up in a minute."
        ];
    }

    // Everything else (malformed / bad signature / missing kid) — the
    // token itself looks tampered or malformed. Rare in practice; only
    // real cases we've seen are the "back button after the flow already
    // completed" replay, which is closer to expired than to a signature
    // attack. Non-blaming default.
    return [
        "Your {$noun} didn't complete",
        "Something went wrong finishing your {$noun}. Please try again — if it keeps happening, drop us a note."
    ];
}

// ----- Non-success branches -----------------------------------------------

// User intentionally cancelled — silent return home. Not an error.
if ($status === 'denied' && $reason === 'user_denied') {
    \header('Location: /');
    exit;
}

// Expired or replayed — silent return home, no error page. The user
// either walked away from the sign-in tab (idle timeout) or hit this
// URL twice (replay). Neither is something they need a retry CTA for.
// Status is checked AFTER reason because BrainLock now maps
// session_expired and session_already_resolved to "failed" — but
// older partner builds may still see "denied" here, so we accept
// either to stay forward/backward compatible.
if ($reason === 'session_expired' || $reason === 'session_already_resolved') {
    \header('Location: /');
    exit;
}

// Memory challenge couldn't be passed — surface a friendly page with
// retry CTA. This is the one case where the user explicitly tried and
// the system said no.
if ($status === 'failed' && $reason === 'challenge_failed') {
    tc_callback_retry_page(
        "We couldn't verify it was you",
        "BrainLock didn't recognise your responses to the memory challenges. Give it another go — these things take practice."
    );
}

// Account mismatch — the BrainLock vault now signed in differs from the
// one we previously bound to this TangoCash account. Specific copy so
// the user understands why retry might not behave as expected.
if ($status === 'failed' && $reason === 'account_switch') {
    tc_callback_retry_page(
        "That's a different BrainLock account",
        "This TangoCash account is linked to a different BrainLock vault than the one you just signed into. Sign out of BrainLock and try again with the right account."
    );
}

// Generic failure / unknown reason — prefer the server-supplied
// error_description over our own guess. Never echo `reason` raw to the
// user; it's developer-facing.
if ($status === 'failed' || $status !== 'success') {
    $body = $errDesc !== ''
        ? $errDesc . ' Please try again.'
        : "Something went wrong on the BrainLock side. Please try again.";
    tc_callback_retry_page("Sign-in didn't complete", $body);
}

// No token even though status=success — shouldn't happen, but handle.
if ($token === '') {
    tc_callback_retry_page(
        'Sign-in token was missing',
        "We expected a sign-in token from BrainLock but didn't receive one. That's on us — please try again."
    );
}

// ----- Verify branch (per-action approval) --------------------------------
//
// TangoCash is primarily a Connect demo, but this callback is also the
// landing for ad-hoc Verify smoke tests. When the intent query param says
// 'verify', call verifyActionToken (NOT verifyConnectToken — they reject
// each other's tokens to prevent confusion at the partner side) and show
// a developer-friendly receipt page.
//
// Real partners would route this to whatever action handler matches the
// verify session's `action` field (e.g. 'transfer_funds' → /transfer/run).
if ($intent === 'verify') {
    try {
        $receipt = \BrainLock::verifyActionToken($token);
    } catch (\Throwable $e) {
        \error_log('[tangocash] BrainLock::verifyActionToken failed: ' . $e->getMessage());
        [$headline, $body] = tc_callback_error_copy($e, 'verify');
        tc_callback_retry_page($headline, $body);
    }
    // Verify ceremony complete — stash the full receipt in the PHP
    // session and route to the dedicated /receipt landing page. Works
    // for every action, not just transfer_funds; receipt.php renders
    // generically from whatever's in $receipt['context'].
    if (!empty($receipt['verified'])) {
        $_SESSION['last_receipt'] = $receipt;
        \session_write_close();
        \header('Location: /receipt');
        exit;
    }
    // Demo receipt page. A real partner would now run the protected
    // action keyed on $receipt['action'] + $receipt['context'].
    \header('Content-Type: text/html; charset=utf-8');
    \header('Cache-Control: no-store, must-revalidate');
    echo '<!doctype html><meta charset="utf-8"><title>BrainLock Verify — receipt</title>';
    echo '<style>body{font:14px/1.5 system-ui;max-width:680px;margin:60px auto;padding:0 20px;color:#111}';
    echo 'pre{background:#f3f4f6;padding:14px 16px;border-radius:8px;overflow-x:auto}';
    echo 'h1{font-size:22px;margin-bottom:6px}h2{font-size:16px;margin-top:24px}';
    echo '.ok{color:#0a8a2f;font-weight:600}.id{font-family:ui-monospace,monospace;color:#666;font-size:12px}</style>';
    echo '<h1>BrainLock Verify ceremony <span class="ok">' . ($receipt['verified'] ? '✓ verified' : '✗ not verified') . '</span></h1>';
    echo '<p class="id">verification_id: ' . \htmlspecialchars($receipt['verification_id'] ?? '(none)') . '</p>';
    echo '<h2>Receipt</h2><pre>' . \htmlspecialchars(\json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
    echo '<p><a href="/">← back to TangoCash</a></p>';
    exit;
}

// ----- Connect success branch ---------------------------------------------

try {
    $identity = \BrainLock::verifyConnectToken($token);
} catch (\Throwable $e) {
    \error_log('[tangocash] BrainLock::verifyConnectToken failed: ' . $e->getMessage());
    [$headline, $body] = tc_callback_error_copy($e, 'connect');
    tc_callback_retry_page($headline, $body);
}

if (empty($identity['sub'])) {
    tc_callback_retry_page(
        'Token verified, identity was empty',
        "BrainLock returned a verified token with no identity attached. That shouldn't happen — please try again."
    );
}

// -----------------------------------------------------------------------
// Persist the user to tc_users + ensure a wallet row exists.
//
// Email is now the canonical uniqueness key (migration 0002). Schema
// model:
//
//   - First signin for this email:    INSERT new row + name/email/avatar
//                                     captured here, wallet seeded.
//   - Subsequent signin for this email: UPDATE only last_signin_at +
//                                     signin_count. Name + avatar are
//                                     NOT overwritten — TangoCash owns
//                                     them from this point forward, per
//                                     the one-shot avatar handoff
//                                     contract. The user can edit them
//                                     in TC's profile UI.
//
// Avatar handling: the JWT's `picture` is a ~1h-TTL presigned URL into
// BrainLock's DO Spaces bucket. We have one chance to download it; if
// we miss the window the user just shows initials/placeholder. Subsequent
// signins re-issue the same presigned URL but we ignore it — our cached
// copy in tangocash-avatars is canonical.
// -----------------------------------------------------------------------
$blSub        = (string)$identity['sub'];
$blPictureURL = (string)($identity['picture']    ?? '');
$emailAddr    = (string)($identity['email']      ?? '');
$firstName    = (string)($identity['first_name'] ?? '');
$lastName     = (string)($identity['last_name']  ?? '');

// Was this email already on file BEFORE we touch anything? Determines
// whether to fire the one-shot avatar cache + capture the name.
$alreadyKnown      = false;
$cachedThumbOnFile = '';
try {
    $stmt = \tc_db()->prepare('SELECT picture_thumb_url FROM tc_users WHERE email = ? LIMIT 1');
    $stmt->execute([$emailAddr]);
    $existing = $stmt->fetch();
    if ($existing !== false) {
        $alreadyKnown      = true;
        $cachedThumbOnFile = (string)($existing['picture_thumb_url'] ?? '');
    }
} catch (\Throwable $e) { /* fall through; treated as new */ }

$avatarFull = null;
$avatarThumb = null;
// Cache the avatar on (1) brand-new email, OR (2) returning email that
// has no avatar on file yet. Case (2) covers users who first signed in
// before the avatar handoff existed, or where the original cache call
// failed silently — without this they'd never get an avatar in the
// signed-in pill no matter how many times they signed back in.
$needsAvatar = (!$alreadyKnown) || ($cachedThumbOnFile === '');
if ($needsAvatar && $blPictureURL !== '') {
    $cached = \tc_cache_avatar($blSub, $blPictureURL);
    if ($cached !== null) {
        $avatarFull  = $cached['full'];
        $avatarThumb = $cached['thumb'];
    }
}

try {
    $db = \tc_db();
    $db->beginTransaction();

    if ($alreadyKnown) {
        // Returning user: bump telemetry only. bl_sub stays whatever it
        // was on first signin — touching it would cascade-fail against
        // tc_wallets.fk_wallets_user (ON DELETE CASCADE only, no
        // ON UPDATE CASCADE). bl_user_id can spin freely since it's
        // standalone in tc_users; we don't update it here either since
        // the only consumer (BrainLock unbind) already reads it from
        // the cookie.
        //
        // Backfill the avatar columns when this returning user didn't
        // have one cached. Only writes the picture columns when we
        // actually have new values, so users who already have an
        // avatar on file aren't disturbed.
        if ($avatarThumb !== null || $avatarFull !== null) {
            $db->prepare(
                'UPDATE tc_users
                    SET last_signin_at  = NOW(),
                        signin_count    = signin_count + 1,
                        picture_full_url  = COALESCE(:full,  picture_full_url),
                        picture_thumb_url = COALESCE(:thumb, picture_thumb_url)
                  WHERE email = :email'
            )->execute([
                ':full'  => $avatarFull,
                ':thumb' => $avatarThumb,
                ':email' => $emailAddr,
            ]);
        } else {
            $db->prepare(
                'UPDATE tc_users
                    SET last_signin_at = NOW(),
                        signin_count = signin_count + 1
                  WHERE email = :email'
            )->execute([':email' => $emailAddr]);
        }
    } else {
        // First signin for this email — full INSERT with TC-cached avatar.
        // first_name + last_name come from BL as separate fields; we keep
        // them separate in tc_users too (legacy `name` is migration-0003).
        $db->prepare(
            'INSERT INTO tc_users
                (bl_sub, bl_user_id, first_name, last_name, name, email, picture_full_url, picture_thumb_url)
             VALUES (:sub, :uid, :first, :last, :full_name, :email, :full, :thumb)'
        )->execute([
            ':sub'       => $blSub,
            ':uid'       => $_COOKIE['tc_user_id'] ?? '',
            ':first'     => $firstName,
            ':last'      => $lastName,
            ':full_name' => trim($firstName . ' ' . $lastName), // legacy column
            ':email'     => $emailAddr,
            ':full'      => $avatarFull,
            ':thumb'     => $avatarThumb,
        ]);
    }

    // Wallet creation is idempotent — INSERT IGNORE so existing users
    // don't get their balance reset to $500 on every login.
    $db->prepare('INSERT IGNORE INTO tc_wallets (bl_sub) VALUES (?)')
       ->execute([$blSub]);

    $db->commit();
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    \error_log('[tangocash] callback DB upsert failed: ' . $e->getMessage());
    // Intentionally swallow — sign-in still proceeds via the session.
}

// Sign the user in for this session.
\session_regenerate_id(true); // privilege change — fresh session ID
$_SESSION['bl_user']         = $identity;
$_SESSION['bl_signed_in_at'] = \time();
\session_write_close(); // commit BEFORE the redirect headers go out

\header('Location: /dashboard');
exit;
