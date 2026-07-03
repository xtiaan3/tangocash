<?php
/**
 * profile.php — signed-in user's profile.
 *
 * Reads identity straight from the BrainLock Connect bundle stashed in
 * the PHP session by auth/callback.php — no TangoCash-side user table
 * in this demo. BrainLock provided the name/email/picture once at
 * sign-in; from this point on TangoCash owns its own copy of those
 * fields per the one-shot identity-handoff contract (see project memory
 * "principles-master-rules" Rule 1). BrainLock does not push updates
 * downstream, and a partner would surface its own edit UI for any
 * fields the user can change.
 *
 * The "balance" is a fixed demo number. TangoCash never moves real money;
 * the figure exists so the page reads like a real wallet profile and the
 * BrainLock Verify dashboard has a plausible-looking account behind it.
 */
require __DIR__ . '/_demo_data.php';
$current = \tc_current_user();
if ($current === null) {
    \header('Location: /');
    exit;
}

$first   = (string)($current['first_name'] ?? '');
$last    = (string)($current['last_name']  ?? '');
$email   = (string)($current['email']      ?? '');

// Avatar rendering: use TangoCash's LOCAL cached copy (downloaded once
// at signin from the JWT's ~1h presigned URL — see auth/callback.php
// + _avatars.php). Reading the session's `picture` directly would
// render the original presigned URL, which expires roughly an hour
// after signin and then breaks. Per the avatar-handoff contract,
// BrainLock is NOT a CDN for partner avatars; TangoCash owns its copy
// after first download. See docs/AVATAR_HANDOFF.md.
$picture = '';
try {
    $stmt = \tc_db()->prepare('SELECT picture_full_url, picture_thumb_url FROM tc_users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row && !empty($row['picture_full_url'])) {
        $picture = (string)$row['picture_full_url'];
    } elseif ($row && !empty($row['picture_thumb_url'])) {
        $picture = (string)$row['picture_thumb_url'];
    }
} catch (\Throwable $e) {
    \error_log('[tangocash profile] avatar lookup failed: ' . $e->getMessage());
}

$fullName = trim($first . ' ' . $last);
if ($fullName === '') {
    $fullName = (string)($current['name'] ?? 'TangoCash User');
    // Best-effort first/last split for the row display so we still
    // surface them as two distinct fields even when the SDK only
    // returned a single combined name.
    if ($first === '' && $last === '') {
        $parts = preg_split('/\s+/', $fullName, 2);
        $first = $parts[0] ?? '';
        $last  = $parts[1] ?? '';
    }
}

// Fixed demo balance. Non-round so it reads like a real account.
$balanceCents   = 4234018; // $42,340.18
$balanceDollars = '$' . number_format($balanceCents / 100, 2);

$page_title = 'Profile — TangoCash';
$signed_in  = true;
$active_nav = 'profile';
include __DIR__ . '/_header.php';
?>

<main class="tc_profile_main">

  <section class="tc_profile_card">

    <div class="tc_profile_avatar_wrap">
      <?php if ($picture !== ''): ?>
      <img src="<?= htmlspecialchars($picture) ?>" alt="" class="tc_profile_avatar">
      <?php else: ?>
      <div class="tc_profile_avatar tc_profile_avatar_initial"><?= htmlspecialchars(strtoupper(substr($fullName, 0, 1) ?: '?')) ?></div>
      <?php endif; ?>
    </div>

    <h1 class="tc_profile_name"><?= htmlspecialchars($fullName) ?></h1>
    <?php if ($email !== ''): ?>
    <div class="tc_profile_email"><?= htmlspecialchars($email) ?></div>
    <?php endif; ?>

    <div class="tc_profile_balance">
      <div class="tc_profile_balance_label">Available balance</div>
      <div class="tc_profile_balance_value"><?= $balanceDollars ?></div>
    </div>

    <dl class="tc_profile_rows">
      <?php if ($first !== ''): ?>
      <div class="tc_profile_row">
        <dt>First name</dt>
        <dd><?= htmlspecialchars($first) ?></dd>
      </div>
      <?php endif; ?>
      <?php if ($last !== ''): ?>
      <div class="tc_profile_row">
        <dt>Last name</dt>
        <dd><?= htmlspecialchars($last) ?></dd>
      </div>
      <?php endif; ?>
      <?php if ($email !== ''): ?>
      <div class="tc_profile_row">
        <dt>Email</dt>
        <dd><?= htmlspecialchars($email) ?></dd>
      </div>
      <?php endif; ?>
    </dl>

    <p class="tc_profile_footnote">
      Established by <a href="https://brainlock.id" target="_blank" rel="noopener">BrainLock</a> at sign-in.
      TangoCash holds its own copy from that point — BrainLock does not push updates downstream.
    </p>

  </section>

</main>

<?php include __DIR__ . '/_footer.php'; ?>
