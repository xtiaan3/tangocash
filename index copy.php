<?php
/**
 * TangoCash homepage — V6 preview (wandr.studio, the real one).
 *
 * Distinct from v2/v3:
 *   - Multi-pill nav (each item its own black capsule)
 *   - Cream background, condensed Anton display headlines
 *   - Two-tone HUGE hero ("FROM PASSWORDS AND PIN RESETS TO / PAYMENTS THAT JUST WORK")
 *   - Dark services slab with oversized numbered service names
 *   - Neon-gradient blob accent in panel 02 (overlap-style 3D substitute)
 *   - DRIVEN BY DESIGN — style marquee in soft gray
 *   - White-pill closing CTA with starburst
 */
require __DIR__ . '/_demo_data.php';

// =============================================================
// Dev / signout actions
// =============================================================
//
// Single canonical action surface: `?dev_action=<key>`. Each action is
// composed from a small set of orthogonal effects (drop PHP session,
// delete tc_users row, wipe cookies, unbind on BrainLock, redirect to
// BrainLock signout). The menu wires distinct test scenarios to these.
//
// On completion we redirect to /?dev_notice=<key> so the homepage can
// confirm the action with a flash banner.
//
// Backwards-compat: the legacy `?signout=1|clear|reset|hard` URLs are
// still recognised and forwarded to the equivalent dev_action so any
// stray hard-coded link keeps working.
$_action = '';
if (isset($_GET['dev_action']))    $_action = (string)$_GET['dev_action'];
elseif (isset($_GET['signout'])) {
    $_legacy = (string)$_GET['signout'];
    if ($_legacy === 'hard')   $_action = 'reset_all';
    elseif ($_legacy === 'reset') $_action = 'reset_all';
    elseif ($_legacy === 'clear') $_action = 'delete_user';
    else                          $_action = 'signout';
}

if ($_action !== '') {
    // Snapshot identity BEFORE destroying the session, since some
    // actions need bl_sub (DB delete) or user_id (BL unbind).
    $current  = \tc_current_user();
    $blSub    = $current['sub']   ?? '';
    $blEmail  = $current['email'] ?? '';
    $tcUid    = $_COOKIE['tc_user_id'] ?? '';

    // Helper closures for the orthogonal effects.
    $dropSession  = function () { \tc_sign_out(); };
    $deleteUser   = function () use ($blSub, $blEmail) {
        // Email is the stable key (see tc_delete_user docblock).
        // bl_sub is passed as a fallback in case the session had no email.
        if ($blEmail !== '' || $blSub !== '') {
            \tc_delete_user($blSub, $blEmail);
        }
    };
    $wipeCookies  = function () {
        foreach (\array_keys($_COOKIE) as $name) {
            \setcookie($name, '', [
                'expires'  => 1,
                'path'     => '/',
                'secure'   => true,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
    };
    $unbindOnBL = function () use ($tcUid) {
        if ($tcUid === '') return;
        // Server-to-server POST /v1/auth/unbind with the live API key.
        // Best-effort — log failure but don't block the action.
        $apiKey = \getenv('BRAINLOCK_API_KEY');
        if ($apiKey === false || $apiKey === '') return;
        $ch = \curl_init('https://brainlock.id/v1/auth/unbind');
        \curl_setopt_array($ch, [
            \CURLOPT_POST           => true,
            \CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            \CURLOPT_POSTFIELDS     => \json_encode(['user_id' => $tcUid]),
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT        => 6,
        ]);
        $resp = \curl_exec($ch);
        $code = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        \curl_close($ch);
        if ($code !== 200) {
            \error_log('[tangocash] BL unbind failed (HTTP ' . $code . '): ' . substr((string)$resp, 0, 200));
        }
    };

    // Compose each action from the effects above.
    switch ($_action) {
        case 'signout':
            $dropSession();
            $notice = 'signed_out';
            break;
        case 'clear_cookies':
            $dropSession();
            $wipeCookies();
            $notice = 'cookies_cleared';
            break;
        case 'delete_user':
            $dropSession();
            $deleteUser();
            $notice = 'user_deleted';
            break;
        case 'unbind_brainlock':
            $dropSession();
            $unbindOnBL();
            $notice = 'binding_removed';
            break;
        case 'signout_brainlock':
            $dropSession();
            // Redirect to BL's signout-and-return endpoint. It will
            // destroy the BL session then 302 the user back here with
            // the bl_signed_out notice.
            $return = 'https://tangocash.etonica.com/?dev_notice=bl_signed_out';
            \header('Location: https://brainlock.id/signout?return=' . \urlencode($return));
            exit;
        case 'reset_all':
            $dropSession();
            $deleteUser();
            $unbindOnBL();
            $wipeCookies();
            // The BrainLock session cookie lives on brainlock.id —
            // TangoCash's cookie wipe loop can't reach it. To make
            // "reset everything" actually mean everything, redirect
            // through brainlock.id/signout which destroys the BL
            // session-side cookie before bouncing back here.
            $return = 'https://tangocash.etonica.com/?dev_notice=reset_all';
            \header('Location: https://brainlock.id/signout?return=' . \urlencode($return));
            exit;
        default:
            $notice = '';
    }

    if ($notice === '') {
        \header('Location: /');
    } else {
        \header('Location: /?dev_notice=' . \urlencode($notice));
    }
    exit;
}

if (\tc_current_user() !== null) {
    \header('Location: /wallet.php');
    exit;
}

$page_title = 'TangoCash — sign in with BrainLock';
$signed_in  = false;
include __DIR__ . '/_header.php';
?>

<!-- ==========================================================================
     Hero
     ========================================================================== -->
<?php
/* Flash notice for soft sign-in restarts: callback.php redirects here
   with ?signin_notice=<reason> on expired / replayed sign-ins so we
   can prompt a silent retry. (The ?dev_notice flash was retired
   2026-05-30 — actions still set the param but no banner renders.) */
$signinNotice = isset($_GET['signin_notice']) ? (string)$_GET['signin_notice'] : '';
$signinNoticeMsg = '';
if ($signinNotice === 'session_expired') {
    $signinNoticeMsg = "That sign-in took longer than 10 minutes and expired. Hit sign-in again to try once more.";
} elseif ($signinNotice === 'session_already_resolved') {
    $signinNoticeMsg = "That sign-in link was already used. Hit sign-in again to start a fresh one.";
}

?>
<?php if ($signinNoticeMsg !== ''): ?>
<div class="tc_v6_flash" role="status">
    <span class="tc_v6_flash_star" aria-hidden="true">✱</span>
    <?= \htmlspecialchars($signinNoticeMsg) ?>
</div>
<?php endif; ?>

<section class="tc_v6_hero" style="padding: 2.8vw; padding-bottom:5.8vw;">
    <div class="tc_v6_hero_eyebrow">
        <img class="tc_v6_hero_eyebrow_icon" src="/img/star4.png?v=<?= @\filemtime(__DIR__ . '/img/star4.png') ?: 1 ?>" alt="">
        <span>TangoCash is a demo site to test the most advanced, secure authentication system in the world &mdash; BrainLock</span>
    </div>

    <h1 class="tc_v6_display tc_v6_display_solid">Pay your imaginary friends</h1>
    <h1 class="tc_v6_display tc_v6_display_mute">Test the BrainLock protocol</h1>

    <p class="tc_v6_hero_sub">
        A working <code>Sign in with BrainLock</code> reference. Pretend dollars; production-grade auth. Zero tracking, no data brokering, no Google or Meta in the middle &mdash; drops into any stack you&rsquo;re already using.
    </p>

    <div class="tc_v6_hero_cta_wrap">
        <a class="tc_signin_btn styling_black" href="/auth/start.php">
            <img class="tc_signin_btn_logo" src="/img/brainlock_logo_512.png?v=<?= @\filemtime(__DIR__ . '/img/brainlock_logo_512.png') ?: 1 ?>" alt="BrainLock">
            <span class="tc_signin_btn_text">Sign in with BrainLock</span>
        </a>
    </div>
</section>

<!-- ==========================================================================
     Dark services slab — the wandr signature: numbered panels with HUGE
     service names that crop the column.
     ========================================================================== -->
<section class="tc_v6_slab" id="v6-what">
    <div class="tc_v6_slab_inner">
        <div class="tc_v6_slab_kicker">
            <img class="tc_v6_slab_kicker_icon" src="/img/star4.png?v=<?= @\filemtime(__DIR__ . '/img/star4.png') ?: 1 ?>" alt="">
            <span>Two BrainLock products, demonstrated here</span>
        </div>

        <!-- 01 — BrainLock Connect -->
        <div class="tc_v6_panel">
            <div class="tc_v6_panel_num">(01)</div>
            <h2 class="tc_v6_panel_title"><span class="tc_v6_panel_title_top">BRAINLOCK</span><span class="tc_v6_color_purple">CONNECT</span></h2>
            <div class="tc_v6_panel_text">
                <p class="tc_v6_panel_body">
                    Passwordless sign-in for your app. Users authenticate with their BrainLock memory
                    challenges or biometrics; you get a signed identity bundle back &mdash; name, email,
                    profile picture &mdash; with no password to forget, no reset flow to build, and nothing
                    sold to advertisers in the middle.
                </p>
                <ul class="tc_v6_panel_bullets">
                    <li>Drop-in &ldquo;Sign in with BrainLock&rdquo; button</li>
                    <li>Signed identity bundle on every callback</li>
                    <li>Zero passwords for you or your users</li>
                </ul>
            </div>
        </div>

        <!-- 02 — BrainLock Verify -->
        <div class="tc_v6_panel" id="v6-integration">
            <div class="tc_v6_panel_num">(02)</div>
            <h2 class="tc_v6_panel_title"><span class="tc_v6_panel_title_top">BRAINLOCK</span><span class="tc_v6_color_pink">VERIFY</span></h2>
            <div class="tc_v6_panel_text">
                <p class="tc_v6_panel_body">
                    Step-up authentication for high-stakes actions inside your app. Need proof it&rsquo;s
                    still your user before a wire transfer, a password change, or a privileged settings
                    edit? Drop in BrainLock Verify and get a fresh proof-of-presence on demand &mdash;
                    no SMS codes, no email links, no Google in the middle.
                </p>
                <ul class="tc_v6_panel_bullets">
                    <li>Per-action memory or biometric challenge</li>
                    <li>Configurable security tiers per call</li>
                    <li>Pay-per-verify pricing &mdash; no plans, no minimums</li>
                </ul>
            </div>
        </div>

        <!-- 03 — Developer Lens -->
        <div class="tc_v6_panel" id="v6-dev-lens">
            <div class="tc_v6_panel_num">(03)</div>
            <h2 class="tc_v6_panel_title"><span class="tc_v6_panel_title_top">DEV</span><span class="tc_v6_color_orange">LENS</span></h2>
            <div class="tc_v6_panel_text">
                <p class="tc_v6_panel_body">
                    A side panel on every page. Click DEV on the right edge to slide it open. Six tabs let you
                    watch the BrainLock protocol move in real time, without context-switching to a docs site.
                </p>
                <ul class="tc_v6_panel_bullets">
                    <li>Activity stream + Code + JWT inspector</li>
                    <li>Every API call &mdash; copy as cURL</li>
                    <li>Ship-ready snippets organised by intent</li>
                </ul>
            </div>
        </div>

        <!-- 04 — Ship it -->
        <div class="tc_v6_panel" id="v6-try">
            <div class="tc_v6_panel_num">(04)</div>
            <h2 class="tc_v6_panel_title"><span class="tc_v6_panel_title_top">SHIP</span><span class="tc_v6_color_yellow">YOUR OWN</span></h2>
            <div class="tc_v6_panel_text">
                <p class="tc_v6_panel_body">
                    Fork the repo, register an app at brainlock.id/developer, swap the demo data for your own.
                    Most teams ship integration in an afternoon.
                </p>
                <ul class="tc_v6_panel_bullets">
                    <li>github.com/xtiaan3/tangocash</li>
                    <li>BrainLock developer portal &mdash; get keys, register callbacks</li>
                    <li>PHP 8.3 reference; SDKs in other languages on the roadmap</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     Code moment — light section, the actual eight lines in a single
     centered codecard for emphasis.
     ========================================================================== -->
<section class="tc_v6_closer">
<!--    <div class="tc_v6_closer_label">The eight lines ok brilliant code</div>-->
    <h2 class="tc_v6_display tc_v6_display_solid" style="font-size: clamp(34px, 3.6vw, 100px); max-width:none; margin-bottom:30px; color:var(--tc_v6_lite_blue);">just 8 lines of brilliant code</h2>
    <div class="tc_v6_codecard">
        <div class="tc_v6_codecard_head">
            <span class="tc_v6_codecard_dot tc_v6_codecard_dot_r"></span>
            <span class="tc_v6_codecard_dot tc_v6_codecard_dot_a"></span>
            <span class="tc_v6_codecard_dot tc_v6_codecard_dot_g"></span>
            <span class="tc_v6_codecard_file">signin.php</span>
        </div>
<pre class="tc_v6_codecard_body"><span class="c">&lt;?php</span>
<span class="k">require</span> <span class="s">'vendor/autoload.php'</span>;

<span class="v">BrainLock</span>::<span class="k">configure</span>([
    <span class="s">'api_key'</span>      =&gt; <span class="v">$_ENV</span>[<span class="s">'BRAINLOCK_API_KEY'</span>],
    <span class="s">'callback_url'</span> =&gt; <span class="s">'https://yourapp.com/auth/callback'</span>,
]);

<span class="v">BrainLock</span>::<span class="k">connect</span>([<span class="s">'user_id'</span> =&gt; <span class="k">session_id</span>()]);</pre>
    </div>

    <?php
        /* Language switcher beneath the codecard. State 1 = default
           (greyed mark), state 2 = active (colored). The selected one
           sticks in state 2; the others lift to state 2 on hover.
           Narrower than the codecard (max-width 600 vs 720) so the row
           reads as inset under the card. NOTE: the python file on disk
           is misspelled "pyphon" — keep the path verbatim. */
        $langs = [
            ['key' => 'php',    'label' => 'PHP',    'file' => 'code_php_'],
            ['key' => 'node',   'label' => 'Node',   'file' => 'code_node_'],
            ['key' => 'pyphon', 'label' => 'Python', 'file' => 'code_pyphon_'],
            ['key' => 'go',     'label' => 'Go',     'file' => 'code_go_'],
            ['key' => 'ruby',   'label' => 'Ruby',   'file' => 'code_ruby_'],
        ];
        $selectedKey = 'php';
    ?>
    <div class="tc_v6_codelangs" role="tablist" aria-label="Available SDK languages">
        <?php foreach ($langs as $L):
            $sel = $L['key'] === $selectedKey;
            $v1  = @\filemtime(__DIR__ . '/img/' . $L['file'] . '1.png') ?: 1;
            $v2  = @\filemtime(__DIR__ . '/img/' . $L['file'] . '2.png') ?: 1;
        ?>
            <span class="tc_v6_codelangs_item<?= $sel ? ' is_selected' : '' ?>"
                  role="tab" aria-selected="<?= $sel ? 'true' : 'false' ?>"
                  aria-label="<?= \htmlspecialchars($L['label']) ?>">
                <img class="tc_v6_codelangs_img tc_v6_codelangs_img_1"
                     src="/img/<?= $L['file'] ?>1.png?v=<?= $v1 ?>" alt="">
                <img class="tc_v6_codelangs_img tc_v6_codelangs_img_2"
                     src="/img/<?= $L['file'] ?>2.png?v=<?= $v2 ?>" alt="">
            </span>
        <?php endforeach; ?>
    </div>
</section>

<!-- ==========================================================================
     Final CTA
     ========================================================================== -->
<section class="is_last tc_v6_closer">
    <h2 class="tc_v6_display tc_v6_display_solid">Try the demo</h2>
    <h2 class="tc_v6_display" style="color:#fff">Ship your own</h2>

    <p class="tc_v6_hero_sub">
        Placeholder copy for the closer — a couple of sentences that nudge developers from "I just clicked through a demo" to "I'm going to drop this into my app." We'll iterate on this.
    </p>

    <div class="tc_v6_hero_cta_wrap">
        <a class="tc_signin_btn styling_black" href="/auth/start.php">
            <img class="tc_signin_btn_logo" src="/img/brainlock_logo_512.png?v=<?= @\filemtime(__DIR__ . '/img/brainlock_logo_512.png') ?: 1 ?>" alt="BrainLock">
            <span class="tc_signin_btn_text">Sign in with BrainLock</span>
        </a>
    </div>
</section>

<!-- ==========================================================================
     Footer
     ========================================================================== -->
<footer class="tc_v6_footer">
    <div class="tc_v6_footer_inner">
        <div>
            <strong>TangoCash</strong> &mdash; peer-to-peer wallet demo.<br>
            The canonical &ldquo;Sign in with BrainLock&rdquo; reference integration.
        </div>
        <div class="tc_v6_footer_right">
            <a href="https://github.com/xtiaan3/tangocash" target="_blank" rel="noopener">GitHub</a>
            &nbsp;&middot;&nbsp;
            <a href="https://brainlock.id" target="_blank" rel="noopener">BrainLock</a>
        </div>
    </div>
</footer>

</main></body></html>
