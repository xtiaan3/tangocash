<?php
require __DIR__ . '/_demo_data.php';
if (\tc_current_user() === null) {
    \header('Location: /');
    exit;
}
$page_title = 'Wallet — TangoCash';
$signed_in  = true;
$active_nav = 'wallet';
include __DIR__ . '/_header.php';
?>

<section class="tc_wallet">

    <!-- Big balance card. The number is the moment — generous, alive, the
         only thing on screen this size. -->
    <div class="tc_balance_card">
        <div class="tc_balance_label">Balance</div>
        <div class="tc_balance_amount"><?= tc_money($DEMO_USER['balance']) ?></div>
        <div class="tc_balance_actions">
            <a href="/send.php"    class="tc_btn tc_btn_primary">Send</a>
            <a href="/request.php" class="tc_btn tc_btn_secondary">Request</a>
        </div>
    </div>

    <!-- Activity feed. -->
    <div class="tc_activity">
        <h2 class="tc_section_h">Recent activity</h2>
        <ul class="tc_activity_list">
            <?php foreach ($DEMO_ACTIVITY as $tx): ?>
                <li class="tc_activity_row">
                    <div class="tc_activity_avatar"><?= tc_initial($tx['who']) ?></div>
                    <div class="tc_activity_body">
                        <div class="tc_activity_main">
                            <?= $tx['type'] === 'received' ? 'From' : 'To' ?>
                            <span class="tc_activity_handle">@<?= htmlspecialchars($tx['who']) ?></span>
                        </div>
                        <?php if (!empty($tx['note'])): ?>
                            <div class="tc_activity_note"><?= htmlspecialchars($tx['note']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="tc_activity_meta">
                        <div class="tc_activity_amount tc_amount_<?= $tx['type'] ?>">
                            <?= $tx['type'] === 'received' ? '+' : '−' ?><?= tc_money($tx['amount']) ?>
                        </div>
                        <div class="tc_activity_when"><?= htmlspecialchars($tx['when']) ?></div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

</section>

<?php include __DIR__ . '/_footer.php'; ?>
