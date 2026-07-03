<?php
/**
 * dashboard.php — Protected-transfer demo.
 *
 * Showcases BrainLock Verify (per-action approval) wired into a normal
 * "send money" form. The user toggles a few knobs — amount, "require
 * BL above" threshold, security level, geo challenge — and watches the
 * PHP snippet beside the form update live so they see EXACTLY the
 * BrainLock::verifyAction() call their selections will trigger.
 *
 * Two send paths:
 *   - amount ≤ threshold → instant simulated success, no BL ceremony.
 *   - amount > threshold → POST mints a Verify session via the SDK,
 *     the user bounces to brainlock.id, completes the ceremony, then
 *     lands back at /auth/callback.php?intent=verify which redirects
 *     here with ?verified=<verification_id>.
 *
 * Recipient is hard-coded to a synthetic Tim Apple — no wallet movement
 * happens. The point is the protocol shape, not the bookkeeping.
 *
 * Auth: signed-in only. Anonymous visitors are bounced to the homepage.
 */
require __DIR__ . '/_demo_data.php';
if (\tc_current_user() === null) {
    \header('Location: /');
    exit;
}

$current = \tc_current_user();
// user_id passed to BrainLock MUST be the partner's stable per-user
// identifier — the SAME value we sent to BrainLock::connect() during
// sign-in. For TangoCash that's the tc_user_id cookie (minted in
// auth/start.php). The BL `sub` returned in the Connect identity bundle
// is BrainLock's own per-(vault, app) id and is NOT the right key here:
// passing it to verifyAction means BL can't find the existing binding
// and the ceremony degrades to a fresh Connect sign-in.
$tcUserId    = (string)($_COOKIE['tc_user_id'] ?? '');
$signed_in   = true;
$active_nav  = 'dashboard';
$page_title  = 'TangoCash — Protected Transfer';

// -----------------------------------------------------------------------
// POST handler — fire BrainLock::verifyAction with the user's choices.
// We only get here when the JS decides BL is needed (amount > threshold).
// On error, fall through to the page and surface a small notice.
// -----------------------------------------------------------------------
$flash_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amountCents = (int)($_POST['amount_cents'] ?? 0);
    $level       = (string)($_POST['security_level'] ?? 'secure');
    $requireGeo  = !empty($_POST['require_geo']);

    if (!\in_array($level, ['secure', 'elevated', 'maximum'], true)) {
        $level = 'secure';
    }
    if ($amountCents < 1 || $amountCents > 100_000_00) {
        $flash_error = 'Amount must be between $0.01 and $100,000.';
    } elseif ($tcUserId === '') {
        // Should be impossible — tc_user_id is set in auth/start.php
        // before Connect, and tc_current_user() above already required
        // a signed-in session. But fail loud rather than calling BL
        // with an empty user_id (which would degrade to a Connect).
        $flash_error = "Your session is missing tc_user_id. Sign out and back in to fix this.";
    } else {
        try {
            $amountDisplay = '$' . \number_format($amountCents / 100, 2);
            \BrainLock::verifyAction([
                'user_id'        => $tcUserId,
                'action'         => 'transfer_funds',
                'security_level' => $level,
                'require_geo'    => $requireGeo,
                // BrainLock Verify is generic by default. Partners
                // OPT INTO a richer consent panel by adding any of
                // these three optional keys to context:
                //   title        — short headline on the panel
                //   description  — one-paragraph body
                //   display      — labeled rows (label/value pairs)
                // Everything else in context stays encrypted at rest
                // for the receipt but doesn't drive the UI.
                'context'        => [
                    'title'       => 'Send ' . $amountDisplay,
                    'description' => 'You\'re sending money to Tim Apple via TangoCash.',
                    'display'     => [
                        ['label' => 'Amount',    'value' => $amountDisplay],
                        ['label' => 'Recipient', 'value' => 'Tim Apple'],
                        ['label' => 'Email',     'value' => 'tim.apple@example.com'],
                    ],
                    // Original keys retained for the verification
                    // receipt so the partner backend can key off the
                    // raw amount in cents instead of re-parsing it.
                    'recipient_email' => 'tim.apple@example.com',
                    'amount_cents'    => $amountCents,
                ],
            ]);
            // verifyAction() emits a 302 + exit on success. We only fall
            // through if it threw — the catch below handles that.
        } catch (\Throwable $e) {
            \error_log('[tangocash dashboard] verifyAction failed: ' . $e->getMessage());
            $flash_error = "We couldn't reach BrainLock to start the verification. Please try again.";
        }
    }
}

// Verify-ceremony receipts no longer land here — auth/callback.php
// stashes the receipt in $_SESSION['last_receipt'] and routes to
// /receipt for a dedicated landing page.

require __DIR__ . '/_header.php';
?>

<main class="tc_dashboard_main">

  <section class="tc_dashboard_hero">
    <h1 class="tc_dashboard_h1">Protected Transfer</h1>
    <p class="tc_dashboard_sub">
      A working demo of <strong>BrainLock Verify</strong> guarding a money
      transfer. Change the knobs on the right and watch the SDK call
      update live. Hit <em>Send money</em> to run it for real.
    </p>
  </section>

  <?php if ($flash_error !== ''): ?>
  <div class="tc_dashboard_flash tc_dashboard_flash_err">
    <span class="tc_dashboard_flash_icon">!</span>
    <div><?= \htmlspecialchars($flash_error) ?></div>
  </div>
  <?php endif; ?>

  <form id="tc_dashboard_form" class="tc_dashboard_grid" method="POST" action="/dashboard">

    <!-- ============= Left column: the "send" form ============= -->
    <div class="tc_dashboard_col">

      <!-- Recipient -->
      <div class="tc_dashboard_card">
        <div class="tc_dashboard_card_label">Sending to</div>
        <div class="tc_dashboard_recipient">
          <div class="tc_dashboard_avatar">TA</div>
          <div class="tc_dashboard_recipient_meta">
            <div class="tc_dashboard_recipient_name">Tim Apple</div>
            <div class="tc_dashboard_recipient_email">tim.apple@example.com</div>
          </div>
        </div>
      </div>

      <!-- Amount -->
      <div class="tc_dashboard_card">
        <label class="tc_dashboard_card_label" for="tc_dash_amount">Amount to send</label>
        <div class="tc_dashboard_amount_wrap">
          <span class="tc_dashboard_amount_currency">$</span>
          <input id="tc_dash_amount" name="amount_display"
                 class="tc_dashboard_amount_input"
                 type="text" inputmode="decimal" autocomplete="off"
                 value="5000.00">
        </div>
      </div>

      <!-- Settings -->
      <div class="tc_dashboard_card">
        <div class="tc_dashboard_card_label">BrainLock Verify settings</div>

        <!-- Threshold -->
        <div class="tc_dashboard_field">
          <label class="tc_dashboard_field_label" for="tc_dash_threshold">
            Require BrainLock above
            <span id="tc_dash_threshold_display" class="tc_dashboard_field_value">$2500</span>
          </label>
          <input id="tc_dash_threshold" type="range"
                 min="0" max="5000" step="50" value="2500"
                 class="tc_dashboard_slider">
        </div>

        <!-- Security level -->
        <div class="tc_dashboard_field">
          <div class="tc_dashboard_field_label">Security level</div>
          <div class="tc_dashboard_level_row" role="radiogroup" aria-label="Security level">
            <label class="tc_dashboard_level">
              <input type="radio" name="security_level" value="secure" checked>
              <span class="tc_dashboard_level_chip">Secure</span>
            </label>
            <label class="tc_dashboard_level">
              <input type="radio" name="security_level" value="elevated">
              <span class="tc_dashboard_level_chip">Elevated</span>
            </label>
            <label class="tc_dashboard_level">
              <input type="radio" name="security_level" value="maximum">
              <span class="tc_dashboard_level_chip">Maximum</span>
            </label>
          </div>
        </div>

        <!-- Geo -->
        <div class="tc_dashboard_field">
          <label class="tc_dashboard_check">
            <input type="checkbox" name="require_geo" id="tc_dash_geo">
            <span class="tc_dashboard_check_box"></span>
            <span class="tc_dashboard_check_label">
              Include geo challenge
              <span class="tc_dashboard_check_hint">Adds a place-of-memory step to the ceremony.</span>
            </span>
          </label>
        </div>

      </div>

      <!-- Hidden fields populated by JS at submit -->
      <input type="hidden" name="amount_cents" id="tc_dash_amount_cents" value="500000">

      <!-- Send button — label flips between simulated / BL-protected -->
      <button type="submit" class="tc_dashboard_send" id="tc_dash_send">
        <span class="tc_dashboard_send_label">Send money</span>
      </button>

    </div>

    <!-- ============= Right column: live code preview ============= -->
    <div class="tc_dashboard_col tc_dashboard_col_code">

      <div class="tc_dashboard_card_label">Live SDK call</div>

      <div class="tc_v6_codecard tc_dashboard_codecard">
        <div class="tc_v6_codecard_head">
          <span class="tc_v6_codecard_dot tc_v6_codecard_dot_r"></span>
          <span class="tc_v6_codecard_dot tc_v6_codecard_dot_a"></span>
          <span class="tc_v6_codecard_dot tc_v6_codecard_dot_g"></span>
          <span class="tc_v6_codecard_file">send.php</span>
        </div>
        <pre class="tc_v6_codecard_body" id="tc_dash_code"></pre>
      </div>

      <p class="tc_dashboard_codenote" id="tc_dash_codenote"></p>
    </div>

  </form>

</main>

<script>
(function () {
  var $amount      = document.getElementById('tc_dash_amount');
  var $amountCents = document.getElementById('tc_dash_amount_cents');
  var $threshold   = document.getElementById('tc_dash_threshold');
  var $thrDisplay  = document.getElementById('tc_dash_threshold_display');
  var $levelRadios = document.querySelectorAll('input[name="security_level"]');
  var $geo         = document.getElementById('tc_dash_geo');
  var $send        = document.getElementById('tc_dash_send');
  var $sendLabel   = $send.querySelector('.tc_dashboard_send_label');
  var $code        = document.getElementById('tc_dash_code');
  var $codenote    = document.getElementById('tc_dash_codenote');
  var $form        = document.getElementById('tc_dashboard_form');

  function parseAmount(str) {
    if (str === '' || str == null) return 0;
    var clean = String(str).replace(/[^0-9.]/g, '');
    var f = parseFloat(clean);
    if (isNaN(f) || f < 0) return 0;
    return Math.round(f * 100);
  }
  function fmtDollars(cents) {
    return '$' + (cents / 100).toFixed(2);
  }
  function selectedLevel() {
    for (var i = 0; i < $levelRadios.length; i++) {
      if ($levelRadios[i].checked) return $levelRadios[i].value;
    }
    return 'secure';
  }

  // Mirrors EXACTLY what dashboard.php fires on POST, plus the button
  // label / hidden cents sync. Single source of truth for "what does
  // each selector do" — the code preview is the demo's whole point.
  function renderCode() {
    var cents     = parseAmount($amount.value);
    var threshold = parseAmount($threshold.value + '.00');
    var level     = selectedLevel();
    var geo       = $geo.checked;
    var needsBL   = cents > threshold;

    if (!needsBL) {
      $code.innerHTML =
        '<span class="c">// ' + fmtDollars(cents) + ' is below the threshold —</span>\n' +
        '<span class="c">// no BrainLock ceremony needed.</span>\n' +
        '\n' +
        '<span class="v">$wallet</span>-&gt;<span class="k">debit</span>(\n' +
        '    <span class="s">\'tim.apple@example.com\'</span>,\n' +
        '    <span class="s">' + cents + '</span> <span class="c">// cents</span>\n' +
        ');';
      $codenote.textContent =
        'Tip: raise the amount above ' + fmtDollars(threshold) +
        ' to trigger the BrainLock Verify branch.';
    } else {
      var geoLine = geo
        ? '    <span class="s">\'require_geo\'</span>    =&gt; <span class="v">true</span>,\n'
        : '';
      var amountStr = fmtDollars(cents);
      $code.innerHTML =
        '<span class="c">// ' + amountStr + ' is above the threshold —</span>\n' +
        '<span class="c">// hand off to BrainLock before the debit.</span>\n' +
        '\n' +
        '<span class="v">BrainLock</span>::<span class="k">verifyAction</span>([\n' +
        '    <span class="s">\'user_id\'</span>        =&gt; <span class="v">$user</span>-&gt;id,\n' +
        '    <span class="s">\'action\'</span>         =&gt; <span class="s">\'transfer_funds\'</span>,\n' +
        '    <span class="s">\'security_level\'</span> =&gt; <span class="s">\'' + level + '\'</span>,\n' +
        geoLine +
        '    <span class="s">\'context\'</span>        =&gt; [\n' +
        '        <span class="s">\'title\'</span>       =&gt; <span class="s">\'Send ' + amountStr + '\'</span>,\n' +
        '        <span class="s">\'description\'</span> =&gt; <span class="s">\'You\\\'re sending money to Tim Apple via TangoCash.\'</span>,\n' +
        '        <span class="s">\'display\'</span>     =&gt; [\n' +
        '            [<span class="s">\'label\'</span> =&gt; <span class="s">\'Amount\'</span>,    <span class="s">\'value\'</span> =&gt; <span class="s">\'' + amountStr + '\'</span>],\n' +
        '            [<span class="s">\'label\'</span> =&gt; <span class="s">\'Recipient\'</span>, <span class="s">\'value\'</span> =&gt; <span class="s">\'Tim Apple\'</span>],\n' +
        '            [<span class="s">\'label\'</span> =&gt; <span class="s">\'Email\'</span>,     <span class="s">\'value\'</span> =&gt; <span class="s">\'tim.apple@example.com\'</span>],\n' +
        '        ],\n' +
        '        <span class="s">\'recipient_email\'</span> =&gt; <span class="s">\'tim.apple@example.com\'</span>,\n' +
        '        <span class="s">\'amount_cents\'</span>    =&gt; <span class="s">' + cents + '</span>,\n' +
        '    ],\n' +
        ']);';
      $codenote.textContent =
        'On submit, the SDK redirects you to BrainLock to complete a ' +
        level + (geo ? ' + geo' : '') + ' ceremony.';
    }

    $sendLabel.textContent = needsBL ? 'Sign with BrainLock & Send' : 'Send money';
    $send.classList.toggle('is_bl', needsBL);
    $amountCents.value = String(cents);
  }

  $amount.addEventListener('input', renderCode);
  $threshold.addEventListener('input', function () {
    $thrDisplay.textContent = '$' + $threshold.value;
    renderCode();
  });
  for (var i = 0; i < $levelRadios.length; i++) {
    $levelRadios[i].addEventListener('change', renderCode);
  }
  $geo.addEventListener('change', renderCode);
  $amount.addEventListener('blur', function () {
    var cents = parseAmount($amount.value);
    $amount.value = (cents / 100).toFixed(2);
    renderCode();
  });

  // Below threshold = simulated success inline (no POST). Above = let
  // the form POST through to PHP which fires BrainLock::verifyAction.
  $form.addEventListener('submit', function (e) {
    var cents     = parseAmount($amount.value);
    var threshold = parseAmount($threshold.value + '.00');
    if (cents <= threshold) {
      e.preventDefault();
      simulatedSuccess(cents);
    }
  });

  function simulatedSuccess(cents) {
    var flash = document.createElement('div');
    flash.className = 'tc_dashboard_flash tc_dashboard_flash_ok';
    flash.innerHTML =
      '<span class="tc_dashboard_flash_icon">✓</span>' +
      '<div><strong>Sent.</strong> $' + (cents / 100).toFixed(2) +
      ' to Tim Apple. <span class="tc_dashboard_flash_id">No BrainLock — amount below threshold.</span></div>';
    $form.parentNode.insertBefore(flash, $form);
    flash.scrollIntoView({behavior: 'smooth', block: 'center'});
    setTimeout(function () { flash.classList.add('is_fading'); }, 4500);
    setTimeout(function () { flash.remove(); }, 6000);
  }

  renderCode();
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
