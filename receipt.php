<?php
/**
 * receipt.php — dedicated landing page for a completed BrainLock Verify
 * ceremony.
 *
 * auth/callback.php stashes the verifyActionToken() result in
 * $_SESSION['last_receipt'] and redirects here. The receipt persists
 * for the duration of the PHP session so the user can revisit /receipt
 * directly without re-running the ceremony.
 *
 * Auth: signed-in only. Anonymous → home. No receipt in session → /dashboard.
 */
require __DIR__ . '/_demo_data.php';
if (\tc_current_user() === null) {
    \header('Location: /');
    exit;
}

$receipt = $_SESSION['last_receipt'] ?? null;
if (!\is_array($receipt) || empty($receipt['verification_id'])) {
    // No receipt to show — bounce to the dashboard, which is the action
    // surface that produces receipts.
    \header('Location: /dashboard');
    exit;
}

// -----------------------------------------------------------------------
// Pull display fields out of the receipt + its context payload. Receipt
// is the SDK's curated subset of the JWT (see lib/BrainLock.php
// ::verifyActionToken). Context is the partner-supplied payload from
// the original verifyAction call.
// -----------------------------------------------------------------------
$verificationId = (string)($receipt['verification_id'] ?? '');
$action         = (string)($receipt['action']          ?? '');
$verified       = !empty($receipt['verified']);
$biometricUsed  = !empty($receipt['biometric_used']);
$iat            = (int)($receipt['iat']                ?? 0);

$context        = isset($receipt['context']) && \is_array($receipt['context']) ? $receipt['context'] : [];
$title          = (string)($context['title']       ?? '');
$description    = (string)($context['description'] ?? '');
$display        = (isset($context['display']) && \is_array($context['display'])) ? $context['display'] : [];

// The partner-supplied title/description are written present-tense
// because the consent panel during the ceremony asks the user to
// authorize the action. By the time we render the receipt the action
// has already happened — flip the copy to past tense so it reads
// naturally as a record of what was done.
if (\str_starts_with($title, 'Send ')) {
    $title = 'Sent ' . \substr($title, 5);
}
$description = \str_replace(
    "You're sending money to",
    'You sent money to',
    $description
);

// Humanize the action key for the header. Generic snake_case → "Snake case".
$actionLabel = $action !== '' ? \ucfirst(\str_replace(['_', '-'], ' ', $action)) : 'Action';

// Timestamp — show absolute local time. iat is UNIX seconds (UTC).
$timestamp = $iat > 0 ? \date('M j, Y \a\t g:i A T', $iat) : 'just now';

$page_title = 'Receipt — TangoCash';
$signed_in  = true;
$active_nav = 'dashboard';  // highlight Dashboard since that's where this came from
include __DIR__ . '/_header.php';
?>

<main class="tc_receipt_main">

  <section class="tc_receipt_hero">
    <div class="tc_receipt_check" id="tc_receipt_lottie_check"></div>
    <h1 class="tc_receipt_h1"><?= $verified ? 'Authorized' : 'Verification incomplete' ?></h1>
    <p class="tc_receipt_sub">
      <?php if ($verified): ?>
      Your action was approved with BrainLock and a signed receipt was returned to TangoCash.
      <?php else: ?>
      The verification ceremony did not complete. No action was taken.
      <?php endif; ?>
    </p>
  </section>

  <div class="tc_receipt_card">

    <?php if ($title !== ''): ?>
    <div class="tc_receipt_title"><?= \htmlspecialchars($title) ?></div>
    <?php endif; ?>

    <?php if ($description !== ''): ?>
    <p class="tc_receipt_desc"><?= \htmlspecialchars($description) ?></p>
    <?php endif; ?>

    <?php if (!empty($display)): ?>
    <dl class="tc_receipt_rows">
      <?php foreach ($display as $row):
          if (!\is_array($row)) continue;
          $label = (string)($row['label'] ?? '');
          $value = (string)($row['value'] ?? '');
          if ($label === '' && $value === '') continue;
      ?>
        <dt><?= \htmlspecialchars($label) ?></dt>
        <dd><?= \htmlspecialchars($value) ?></dd>
      <?php endforeach; ?>
    </dl>
    <?php endif; ?>

    <div class="tc_receipt_divider"></div>

    <dl class="tc_receipt_meta">
      <dt>Action</dt>
      <dd><?= \htmlspecialchars($actionLabel) ?></dd>

      <dt>Method</dt>
      <dd><?= $biometricUsed ? 'Biometric + memory challenge' : 'Memory challenge' ?></dd>

      <dt>Time</dt>
      <dd><?= \htmlspecialchars($timestamp) ?></dd>

      <dt>Status</dt>
      <dd>
        <?php if ($verified): ?>
        <span class="tc_receipt_status_ok">Verified</span>
        <?php else: ?>
        <span class="tc_receipt_status_err">Not verified</span>
        <?php endif; ?>
      </dd>

      <dt>Verification ID</dt>
      <dd class="tc_receipt_id" title="Use this to look up the full audit record via GET /v1/auth/verifications/{id}"><?= \htmlspecialchars($verificationId) ?></dd>
    </dl>

    <p class="tc_receipt_signed">
      Cryptographically signed receipt from
      <a href="https://brainlock.id" target="_blank" rel="noopener">BrainLock</a>.
    </p>

  </div>

  <div class="tc_receipt_cta">
    <a class="tc_receipt_back_btn" href="/dashboard">Return to dashboard</a>
  </div>

</main>

<?php if ($verified): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
<script>
  (function () {
    if (!window.lottie) return;
    var el = document.getElementById('tc_receipt_lottie_check');
    if (!el) return;
    window.lottie.loadAnimation({
      container: el,
      renderer: 'svg',
      loop: false,
      autoplay: true,
      path: '/assets/animations/green_checkmark.json'
    });
  })();
</script>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
