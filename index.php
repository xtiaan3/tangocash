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
            // Wiping tc_user_id without telling BrainLock leaves an
            // orphaned app_user_bindings row pointing at a user_id we
            // will never use again — next sign-in mints a fresh id
            // and writes ANOTHER row. Over many dev cycles those
            // orphans pile up. Unbinding here keeps the BL-side state
            // honest: when the partner has no record of this user_id,
            // BL shouldn't either.
            $dropSession();
            $unbindOnBL();
            $wipeCookies();
            $notice = 'cookies_cleared';
            break;
        case 'delete_user':
            // Same rationale as clear_cookies — once we've nuked the
            // tc_users row, the BL binding pointing at the same
            // user_id is dead weight. Unbind to keep both sides
            // consistent.
            $dropSession();
            $deleteUser();
            $unbindOnBL();
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

// Homepage is visitable in both states. Logged-in users see the same
// marketing content with the header's signed-in chrome (Home / Dashboard
// / Profile + user chip on the right). Auto-redirect was removed
// 2026-06-08; the post-signin destination is /dashboard via auth/callback.
$page_title = 'TangoCash — sign in with BrainLock';
$signed_in  = (\tc_current_user() !== null);
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

    <h1 class="tc_v6_display tc_v6_display_solid">Authorize authenticate</h1>
    <h1 class="tc_v6_display tc_v6_display_mute">Test the BrainLock protocol</h1>

    <p class="tc_v6_hero_sub">
        A working <code>Sign in with BrainLock</code>, <code>2FA</code> and <code>MFA</code> reference. Pretend dollars; production-grade auth. Zero tracking, no data brokering, no passwords, no SMS, no Google or Meta in the middle &mdash; drops into any stack you&rsquo;re already using.
    </p>

    <?php if (!$signed_in): ?>
    <div class="tc_v6_hero_cta_wrap">
        <a class="tc_signin_btn styling_black" href="/auth/start">
            <img class="tc_signin_btn_logo" src="/img/brainlock_logo_512.png?v=<?= @\filemtime(__DIR__ . '/img/brainlock_logo_512.png') ?: 1 ?>" alt="BrainLock">
            <span class="tc_signin_btn_text">Sign in with BrainLock</span>
        </a>
    </div>
    <?php endif; ?>
</section>


<!-- ==========================================================================
     Final CTA
     ========================================================================== -->
<section class="is_last tc_v6_closer">
    <h2 class="tc_v6_display tc_v6_display_solid">drops in</h2>
    <h2 class="tc_v6_display" style="color:#fff">like oauth</h2>

    <p class="tc_v6_hero_sub">
        If you've ever wired up "Sign in with Google" or any OAuth flow, this will feel exactly the same. Three lines pulls in the SDK, points it at your callback, and starts a sign-in session. PHP, Node, Python, Go and Ruby ship as one-file drop-ins; OpenAPI spec for everything else. About fifteen minutes from API key to live sign-in.
    </p>
    
    <div class="tc_v6_codecard">
        <div class="tc_v6_codecard_head">
            <span class="tc_v6_codecard_dot tc_v6_codecard_dot_r"></span>
            <span class="tc_v6_codecard_dot tc_v6_codecard_dot_a"></span>
            <span class="tc_v6_codecard_dot tc_v6_codecard_dot_g"></span>
            <span class="tc_v6_codecard_file">signin.php</span>
        </div>
<pre class="tc_v6_codecard_body"><span class="c">&lt;?php</span>
<span class="k">require</span> <span class="s">'BrainLock.php'</span>;

<span class="v">BrainLock</span>::<span class="k">configure</span>([<span class="s">'api_key'</span> =&gt; <span class="v">$_ENV</span>[<span class="s">'BRAINLOCK_API_KEY'</span>]]);

<span class="c">// A stable per-user ID you control — the SAME value every visit,</span>
<span class="c">// so BrainLock keeps this person tied to their vault.</span>
<span class="v">BrainLock</span>::<span class="k">connect</span>([<span class="s">'user_id'</span> =&gt; <span class="v">$yourUserId</span>]);</pre>
    </div>

    <?php
        /* Language switcher beneath the codecard. Mask-based SVG icons
           sourced from the BrainLock asset set so this row matches the
           developer-portal sidebar exactly. Default: white at ~40%
           opacity. Selected / hover: the language's brand color, full
           opacity. Only PHP is implemented today — others sit alongside
           as a coming-soon hint. */
        $langs = [
            ['key' => 'php',    'label' => 'PHP',    'svg' => 'php-brands-solid-full.svg'],
            ['key' => 'node',   'label' => 'Node',   'svg' => 'node-js-brands-solid-full.svg'],
            ['key' => 'python', 'label' => 'Python', 'svg' => 'python-brands-solid-full.svg'],
            ['key' => 'go',     'label' => 'Go',     'svg' => 'golang-brands-solid-full.svg'],
            ['key' => 'ruby',   'label' => 'Ruby',   'svg' => 'gem-solid-full.svg'],
        ];
        $selectedKey = 'php';
    ?>
    <div class="tc_v6_codelangs" role="tablist" aria-label="Available SDK languages">
        <?php foreach ($langs as $L):
            $sel = $L['key'] === $selectedKey;
            $v   = @\filemtime(__DIR__ . '/img/lang/' . $L['svg']) ?: 1;
        ?>
            <span class="tc_v6_codelangs_item tc_v6_codelangs_<?= $L['key'] ?><?= $sel ? ' is_selected' : '' ?>"
                  role="tab" aria-selected="<?= $sel ? 'true' : 'false' ?>"
                  aria-label="<?= \htmlspecialchars($L['label']) ?>">
                <span class="tc_v6_codelangs_mark"
                      style="--tc_lang_mask: url('/img/lang/<?= $L['svg'] ?>?v=<?= $v ?>');"></span>
                <span class="tc_v6_codelangs_label"><?= \htmlspecialchars($L['label']) ?></span>
            </span>
        <?php endforeach; ?>
    </div>

    <?php if (!$signed_in): ?>
    <div class="tc_v6_hero_cta_wrap">
        <a class="tc_signin_btn styling_black" href="/auth/start">
            <img class="tc_signin_btn_logo" src="/img/brainlock_logo_512.png?v=<?= @\filemtime(__DIR__ . '/img/brainlock_logo_512.png') ?: 1 ?>" alt="BrainLock">
            <span class="tc_signin_btn_text">Sign in with BrainLock</span>
        </a>
    </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/_footer.php'; ?>
