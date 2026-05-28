<?php
require __DIR__ . '/_demo_data.php';
$page_title = 'Request — TangoCash';
$signed_in  = true;
$active_nav = 'request';
include __DIR__ . '/_header.php';
?>

<section class="tc_form_section">

    <h1 class="tc_form_h">Request money</h1>

    <form class="tc_form" onsubmit="event.preventDefault(); alert('Demo only — request not actually sent.');">

        <label class="tc_field">
            <span class="tc_field_label">From</span>
            <input type="text" name="recipient" placeholder="@username" class="tc_input" autocomplete="off">
        </label>

        <div class="tc_recents">
            <?php foreach ($DEMO_RECENTS as $r): ?>
                <button type="button" class="tc_recent_chip">
                    <span class="tc_recent_avatar"><?= tc_initial($r) ?></span>
                    @<?= htmlspecialchars($r) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <label class="tc_field tc_field_amount">
            <span class="tc_field_label">Amount</span>
            <div class="tc_amount_wrap">
                <span class="tc_amount_currency">$</span>
                <input type="text" name="amount" placeholder="0" class="tc_input tc_amount_input" inputmode="decimal">
            </div>
        </label>

        <label class="tc_field">
            <span class="tc_field_label">For <span class="tc_field_optional">(optional)</span></span>
            <input type="text" name="note" placeholder="what's it for?" class="tc_input" maxlength="60">
        </label>

        <button type="submit" class="tc_btn tc_btn_primary tc_btn_full">Request</button>

        <p class="tc_form_legal">
            Demo only. No real request is sent. In production, the recipient gets a notification
            and confirms via BrainLock before money moves.
        </p>
    </form>

</section>

<?php include __DIR__ . '/_footer.php'; ?>
