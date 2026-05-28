<?php
require __DIR__ . '/_demo_data.php';

// In v0 there's no real auth — the "Sign in with BrainLock" button just
// hops to /wallet.php which renders the signed-in view. Real flow:
//   button click → BrainLock::connect() → popup → JWT → wallet.php
$page_title = 'TangoCash — sign in with BrainLock';
$signed_in  = false;
include __DIR__ . '/_header.php';
?>

<section class="tc_hero">
    <div class="tc_hero_inner">
        <img src="/img/logo.png" alt="" class="tc_hero_logo">
        <h1 class="tc_hero_h">Send. Receive. Done.</h1>
        <p class="tc_hero_sub">
            TangoCash is a peer-to-peer wallet for the people who actually pay each other back.
            No password to forget — sign in with BrainLock and you're in.
        </p>

        <a href="/wallet.php" class="tc_btn tc_btn_primary tc_btn_xl">
            <img src="/img/logo.png" class="tc_btn_icon" alt="">
            Sign in with BrainLock
        </a>

        <p class="tc_hero_legal">
            By signing in, you agree to TangoCash's
            <a href="#">terms</a> and <a href="#">privacy policy</a>.
        </p>
    </div>
</section>

<section class="tc_pitch">
    <div class="tc_pitch_card">
        <div class="tc_pitch_kicker">No password</div>
        <h2 class="tc_pitch_h">One sign-in. Everywhere.</h2>
        <p>
            BrainLock authenticates you the way a friend would — by something only you would remember,
            not a string you typed once and forgot. No phishing, no SMS codes, no password reuse.
        </p>
    </div>
    <div class="tc_pitch_card">
        <div class="tc_pitch_kicker">Reference build</div>
        <h2 class="tc_pitch_h">Open source.</h2>
        <p>
            TangoCash is the canonical example for developers integrating BrainLock. The
            <a href="https://github.com/xtiaan3/tangocash" target="_blank" rel="noopener">source is on GitHub</a> —
            clone it, study it, ship your own.
        </p>
    </div>
    <div class="tc_pitch_card">
        <div class="tc_pitch_kicker">Demo only</div>
        <h2 class="tc_pitch_h">No real money.</h2>
        <p>
            Every balance, transaction, and recipient on TangoCash is fictional. This site exists
            to demonstrate the BrainLock developer experience end to end.
        </p>
    </div>
</section>

<?php include __DIR__ . '/_footer.php'; ?>
