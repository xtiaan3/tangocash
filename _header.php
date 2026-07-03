<?php
/**
 * V6 — same as V5 except the menu chrome. ONE single black pill bar
 * across the top with a 2px white border — everything (brand, nav,
 * Sign-in, burger) lives INSIDE the same pill, no separate capsules.
 *
 * Vars: $page_title, $signed_in, $active_nav.
 * Loads tangocash.css (the canonical TC stylesheet — formerly named
 * tangocash_v6.css before the v6 design was promoted to default on
 * 2026-05-30).
 */
$page_title = $page_title ?? 'TangoCash';
$signed_in  = $signed_in  ?? false;
$active_nav = $active_nav ?? '';

$css_v = @filemtime(__DIR__ . '/css/tangocash.css') ?: time();

if ($signed_in) {
    $nav = [
        ['l' => 'Home',      'href' => '/',          'key' => 'home'],
        ['l' => 'Dashboard', 'href' => '/dashboard', 'key' => 'dashboard'],
        ['l' => 'Profile',   'href' => '/profile',   'key' => 'profile'],
    ];
} else {
    // Anonymous landing: brand + Sign-in only. No top-nav links, no
    // burger, no drawer — none of the dev/menu chrome is relevant
    // until the visitor signs in.
    $nav = [];
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="description" content="TangoCash — a peer-to-peer wallet demo. The canonical 'Sign in with BrainLock' reference integration.">
    <link rel="icon" type="image/png" href="/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Anton&display=swap">
    <link rel="stylesheet" href="/css/tangocash.css?v=<?= $css_v ?>">
</head>
<body class="<?= $signed_in ? 'is_signed_in' : 'is_signed_out' ?> tc_v6_page" id="v6-top">

<header class="tc_v6_bar">
    <a class="tc_v6_brand" href="<?= $signed_in ? '/dashboard' : '/' ?>">
        <img class="tc_v6_brand_logo" src="/img/tc_tangocash_logo.png" alt="TangoCash">
    </a>

    <nav class="tc_v6_nav" aria-label="Primary">
        <?php foreach ($nav as $item): ?>
            <a class="tc_v6_link <?= ($active_nav !== '' && $active_nav === $item['key']) ? 'is_active' : '' ?>"
               href="<?= htmlspecialchars($item['href']) ?>">
                <?= htmlspecialchars($item['l']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="tc_v6_right">
        <?php if ($signed_in): ?>
            <?php
                // The "signed-in" pill mirrors the sign-in button's pill so
                // the bar reads the same shape in both states. The BL logo
                // slot becomes the user's TC-cached avatar; the "Sign in"
                // slot becomes their first name. No avatar → text only,
                // same pill width.
                $u       = \tc_current_user();
                $email   = (string)($u['email'] ?? '');
                $sub     = (string)($u['sub']   ?? '');
                // BL Connect returns first_name + last_name as separate
                // bundle fields. Fall back to splitting the legacy `name`
                // for any rare session that pre-dates the split.
                $first   = \trim((string)($u['first_name'] ?? ''));
                if ($first === '') {
                    $first = \trim(\strtok((string)($u['name'] ?? ''), ' ')) ?: 'You';
                }
                // Look up the local cached avatar. Prefer bl_sub since
                // it's always present in the session bundle, even when
                // BL doesn't return an email (e.g. accounts where the
                // primary email isn't surfaced to partners). Fall back
                // to email for any legacy session that pre-dates the
                // sub being captured into $_SESSION['bl_user'].
                $avatar = '';
                try {
                    $row = null;
                    if ($sub !== '') {
                        $stmt = \tc_db()->prepare('SELECT picture_thumb_url, picture_full_url FROM tc_users WHERE bl_sub = ? LIMIT 1');
                        $stmt->execute([$sub]);
                        $row = $stmt->fetch();
                    }
                    if (!$row && $email !== '') {
                        $stmt = \tc_db()->prepare('SELECT picture_thumb_url, picture_full_url FROM tc_users WHERE email = ? LIMIT 1');
                        $stmt->execute([$email]);
                        $row = $stmt->fetch();
                    }
                    if ($row) {
                        $avatar = (string)($row['picture_thumb_url'] ?: $row['picture_full_url'] ?: '');
                    }
                } catch (\Throwable $e) {
                    \error_log('[tangocash header] avatar lookup failed: ' . $e->getMessage());
                }
            ?>
            <a class="tc_signin_btn tc_signin_btn_compact tc_signin_btn_user styling_black" href="/profile">
                <?php if ($avatar !== ''): ?>
                    <img class="tc_signin_btn_logo tc_signin_btn_avatar" src="<?= htmlspecialchars($avatar) ?>" alt="">
                <?php endif; ?>
                <span class="tc_signin_btn_text"><?= htmlspecialchars($first) ?></span>
            </a>
        <?php else: ?>
            <a class="tc_signin_btn tc_signin_btn_compact styling_black" href="/auth/start">
                <img class="tc_signin_btn_logo" src="/img/brainlock_logo_512.png?v=<?= @filemtime(__DIR__ . '/img/brainlock_logo_512.png') ?: 1 ?>" alt="BrainLock">
                <span class="tc_signin_btn_text">Sign in</span>
            </a>
        <?php endif; ?>

        <?php if ($signed_in): ?>
        <button type="button" class="tc_v6_burger" id="tc_v6_burger_btn"
                aria-label="Open menu" aria-controls="tc_v6_drawer" aria-expanded="false">
            <span></span><span></span>
        </button>
        <?php endif; ?>
    </div>
</header>

<?php if ($signed_in): ?>
<div class="tc_v6_drawer_back" id="tc_v6_drawer_back" aria-hidden="true"></div>
<aside class="tc_v6_drawer" id="tc_v6_drawer" aria-label="Site menu" aria-hidden="true">
    <button type="button" class="tc_v6_drawer_close" id="tc_v6_drawer_close" aria-label="Close menu">×</button>
    <nav class="tc_v6_drawer_nav">
        <?php foreach ($nav as $item): ?>
            <a class="tc_v6_drawer_item" href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['l']) ?></a>
        <?php endforeach; ?>
        <!-- Sign out — same standard nav-item styling as Home/Dashboard/
             Profile above. Lives right after the primary nav so it's
             the next obvious thing after the user-facing pages, not
             buried below the developer-testing block. -->
        <a class="tc_v6_drawer_item" href="/?dev_action=signout">Sign out</a>
        <div class="tc_v6_drawer_section_label">For developers</div>
        <a class="tc_v6_drawer_item tc_v6_drawer_item_quiet" target="_blank" rel="noopener" href="https://github.com/xtiaan3/tangocash">Source on GitHub ↗</a>
        <a class="tc_v6_drawer_item tc_v6_drawer_item_quiet" target="_blank" rel="noopener" href="https://github.com/xtiaan3/brainlock-php">BrainLock PHP SDK ↗</a>
        <a class="tc_v6_drawer_item tc_v6_drawer_item_quiet" target="_blank" rel="noopener" href="https://brainlock.id/developer">Developer docs ↗</a>

        <div class="tc_v6_drawer_section_label">Developer testing</div>
        <a class="tc_v6_drawer_item tc_v6_drawer_dev" href="/?dev_action=clear_cookies">
            <span class="tc_v6_drawer_dev_title">Clear TC cookies &amp; session</span>
            <span class="tc_v6_drawer_dev_desc">Drops the PHP session and wipes every TangoCash-set cookie (including <code>tc_user_id</code>). BrainLock binding and your DB row are untouched — but the next sign-in will mint a new <code>tc_user_id</code>, which BrainLock sees as a fresh partner-user → memory challenge.</span>
        </a>
        <?php if ($signed_in): ?>
        <a class="tc_v6_drawer_item tc_v6_drawer_dev" href="/?dev_action=delete_user">
            <span class="tc_v6_drawer_dev_title">Delete TC user row from database</span>
            <span class="tc_v6_drawer_dev_desc">Removes your <code>tc_users</code> row and cascades to wallet, transactions, and contacts. Keeps cookies and the BrainLock binding. Next sign-in magic-flashes to a fresh $500 wallet.</span>
        </a>
        <?php endif; ?>
        <a class="tc_v6_drawer_item tc_v6_drawer_dev" href="/?dev_action=unbind_brainlock">
            <span class="tc_v6_drawer_dev_title">Remove BrainLock app binding</span>
            <span class="tc_v6_drawer_dev_desc">Calls <code>POST /v1/auth/unbind</code> on BrainLock with the current <code>tc_user_id</code>, deleting the <code>app_user_bindings</code> row. Forces fresh consent + challenge on the next sign-in even though your cookie is unchanged.</span>
        </a>
        <a class="tc_v6_drawer_item tc_v6_drawer_dev" href="/?dev_action=signout_brainlock">
            <span class="tc_v6_drawer_dev_title">Sign out of BrainLock too</span>
            <span class="tc_v6_drawer_dev_desc">Redirects to <code>brainlock.id/signout</code> to destroy the BL session, then back here. Trusted-device cookie stays, so the next sign-in lands on the trusted-PIN screen.</span>
        </a>
        <a class="tc_v6_drawer_item tc_v6_drawer_dev" href="/?dev_action=reset_all">
            <span class="tc_v6_drawer_dev_title">Reset everything</span>
            <span class="tc_v6_drawer_dev_desc">All of the above in one click: drop session, delete DB row (if any), unbind on BrainLock, wipe every cookie. You become a brand-new user on a brand-new browser.</span>
        </a>
    </nav>
</aside>
<?php endif; ?>

<script>
(function () {
  var btn = document.getElementById('tc_v6_burger_btn');
  var back = document.getElementById('tc_v6_drawer_back');
  var drawer = document.getElementById('tc_v6_drawer');
  var close = document.getElementById('tc_v6_drawer_close');
  if (!btn || !back || !drawer || !close) return;
  // iOS Safari needs position:fixed on body to actually stop touch
  // scrolling — overflow:hidden alone is ignored. position:fixed resets
  // the scroll position, so we capture it on open and restore on close.
  var lockedY = 0;
  function open()  {
    lockedY = window.scrollY || window.pageYOffset || 0;
    document.body.style.setProperty('--tc_v6_lock_y', '-' + lockedY + 'px');
    drawer.classList.add('is_open');
    back.classList.add('is_open');
    document.body.classList.add('tc_v6_drawer_open');
    btn.setAttribute('aria-expanded','true');
  }
  function shut()  {
    drawer.classList.remove('is_open');
    back.classList.remove('is_open');
    document.body.classList.remove('tc_v6_drawer_open');
    document.body.style.removeProperty('--tc_v6_lock_y');
    if (lockedY) { window.scrollTo(0, lockedY); lockedY = 0; }
    btn.setAttribute('aria-expanded','false');
  }
  btn.addEventListener('click', open);
  close.addEventListener('click', shut);
  back.addEventListener('click', shut);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') shut(); });
  drawer.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', shut); });
})();
</script>

<main class="tc_v6_main">
