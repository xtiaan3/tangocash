<?php
require __DIR__ . '/_demo_data.php';
if (\tc_current_user() === null) {
    \header('Location: /');
    exit;
}
$page_title = 'Profile — TangoCash';
$signed_in  = true;
$active_nav = 'profile';
include __DIR__ . '/_header.php';
?>

<section class="tc_profile">

    <div class="tc_profile_card">
        <img src="<?= htmlspecialchars($DEMO_USER['picture']) ?>" alt="" class="tc_profile_avatar">
        <h1 class="tc_profile_name"><?= htmlspecialchars($DEMO_USER['first_name'] . ' ' . $DEMO_USER['last_name']) ?></h1>
        <div class="tc_profile_handle">@<?= htmlspecialchars($DEMO_USER['handle']) ?></div>
        <div class="tc_profile_email"><?= htmlspecialchars($DEMO_USER['email']) ?></div>
    </div>

    <div class="tc_profile_meta">
        <h2 class="tc_section_h">From BrainLock</h2>
        <p class="tc_profile_explainer">
            Your name, email, and profile picture come straight from BrainLock when you sign in.
            TangoCash never stores a password — there isn't one. To change any of this, update it
            in your <a href="https://brainlock.id/myprofile" target="_blank" rel="noopener">BrainLock profile</a>;
            it'll be reflected here the next time you sign in.
        </p>

        <div class="tc_kv">
            <div class="tc_kv_row"><span class="tc_kv_k">Name</span> <span class="tc_kv_v"><?= htmlspecialchars($DEMO_USER['first_name'] . ' ' . $DEMO_USER['last_name']) ?></span></div>
            <div class="tc_kv_row"><span class="tc_kv_k">Email</span> <span class="tc_kv_v"><?= htmlspecialchars($DEMO_USER['email']) ?></span></div>
            <div class="tc_kv_row"><span class="tc_kv_k">Picture</span> <span class="tc_kv_v">From BrainLock</span></div>
            <div class="tc_kv_row"><span class="tc_kv_k">TangoCash handle</span> <span class="tc_kv_v">@<?= htmlspecialchars($DEMO_USER['handle']) ?></span></div>
        </div>
    </div>

</section>

<?php include __DIR__ . '/_footer.php'; ?>
