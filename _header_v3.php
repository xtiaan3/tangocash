<?php
/**
 * V3 (wandr.studio-faithful) shared header — preview only.
 *
 * Goals: reproduce wandr.studio's top bar layout precisely:
 *   - Logo (icon + wordmark) far left
 *   - Centered nav with numbers AFTER labels in parens: "Home (01)"
 *   - Pill CTA on far right with icon
 *   - Light/white background, title-case sans-serif, tight letter-spacing
 *
 * TangoCash's neon brand orange/pink/purple appears as accent colour
 * on hover, the parenthetical numbers, asterisks, and the CTA pill —
 * a 95% wandr, 5% TangoCash treatment.
 *
 * Vars: $page_title, $signed_in, $active_nav. Same as _header.php.
 * Completely standalone — does NOT load tangocash.css; uses
 * tangocash_v3.css only.
 */
$page_title = $page_title ?? 'TangoCash';
$signed_in  = $signed_in  ?? false;
$active_nav = $active_nav ?? '';

$css_v3 = @filemtime(__DIR__ . '/css/tangocash_v3.css') ?: time();
$js_v   = @filemtime(__DIR__ . '/js/tangocash.js')      ?: time();

if ($signed_in) {
    $nav = [
        ['l' => 'Home',    'n' => '01', 'href' => '/index_v3.php', 'key' => ''],
        ['l' => 'Wallet',  'n' => '02', 'href' => '/wallet.php',   'key' => 'wallet'],
        ['l' => 'Send',    'n' => '03', 'href' => '/send.php',     'key' => 'send'],
        ['l' => 'Request', 'n' => '04', 'href' => '/request.php',  'key' => 'request'],
        ['l' => 'Profile', 'n' => '05', 'href' => '/profile.php',  'key' => 'profile'],
    ];
} else {
    $nav = [
        ['l' => 'Home',         'n' => '01', 'href' => '#v3-top',        'key' => ''],
        ['l' => 'What\'s this', 'n' => '02', 'href' => '#v3-what',        'key' => ''],
        ['l' => 'The code',     'n' => '03', 'href' => '#v3-integration', 'key' => ''],
        ['l' => 'Dev Lens',     'n' => '04', 'href' => '#v3-dev-lens',    'key' => ''],
        ['l' => 'Try it',       'n' => '05', 'href' => '#v3-try',         'key' => ''],
    ];
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="/css/tangocash_v3.css?v=<?= $css_v3 ?>">
</head>
<body class="<?= $signed_in ? 'is_signed_in' : 'is_signed_out' ?> tc_v3_page" id="v3-top">

<header class="tc_v3_header">
    <a class="tc_v3_brand" href="<?= $signed_in ? '/wallet.php' : '/index_v3.php' ?>">
        <span class="tc_v3_brand_mark" aria-hidden="true">✱</span>
        <span class="tc_v3_brand_word">TANGOCASH<span class="tc_v3_brand_sub">— P2P WALLET</span></span>
    </a>

    <nav class="tc_v3_nav" aria-label="Primary">
        <?php foreach ($nav as $item): ?>
            <a class="tc_v3_nav_item <?= ($active_nav !== '' && $active_nav === $item['key']) ? 'is_active' : '' ?>"
               href="<?= htmlspecialchars($item['href']) ?>">
                <span class="tc_v3_nav_label"><?= htmlspecialchars($item['l']) ?></span>
                <span class="tc_v3_nav_num">(<?= $item['n'] ?>)</span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="tc_v3_right">
        <?php if ($signed_in): ?>
            <?php $u = \tc_current_user(); $fn = $u['first_name'] ?? $u['username'] ?? 'You'; ?>
            <a class="tc_v3_chip" href="/profile.php">
                <span class="tc_v3_chip_dot"></span>
                <?= htmlspecialchars($fn) ?>
            </a>
        <?php else: ?>
            <a class="tc_v3_cta" href="/auth/start.php">
                <span class="tc_v3_cta_label">Sign in with BrainLock</span>
                <span class="tc_v3_cta_icon" aria-hidden="true">→</span>
            </a>
        <?php endif; ?>

        <button type="button" class="tc_v3_burger" id="tc_v3_burger_btn"
                aria-label="Open menu" aria-controls="tc_v3_drawer" aria-expanded="false">
            <span></span><span></span>
        </button>
    </div>
</header>

<!-- Mobile drawer — exact wandr "secondary nav" idea. Mirrors the top
     bar items + the developer-resources block. -->
<div class="tc_v3_drawer_back" id="tc_v3_drawer_back" aria-hidden="true"></div>
<aside class="tc_v3_drawer" id="tc_v3_drawer" aria-label="Site menu" aria-hidden="true">
    <button type="button" class="tc_v3_drawer_close" id="tc_v3_drawer_close" aria-label="Close menu">×</button>

    <nav class="tc_v3_drawer_nav">
        <?php foreach ($nav as $item): ?>
            <a class="tc_v3_drawer_item" href="<?= htmlspecialchars($item['href']) ?>">
                <span><?= htmlspecialchars($item['l']) ?></span>
                <span class="tc_v3_drawer_num">(<?= $item['n'] ?>)</span>
            </a>
        <?php endforeach; ?>

        <div class="tc_v3_drawer_section_label">For developers</div>
        <a class="tc_v3_drawer_item tc_v3_drawer_item_quiet" target="_blank" rel="noopener" href="https://github.com/xtiaan3/tangocash">Source on GitHub <span class="tc_v3_drawer_ext">↗</span></a>
        <a class="tc_v3_drawer_item tc_v3_drawer_item_quiet" target="_blank" rel="noopener" href="https://github.com/xtiaan3/brainlock-php">BrainLock PHP SDK <span class="tc_v3_drawer_ext">↗</span></a>
        <a class="tc_v3_drawer_item tc_v3_drawer_item_quiet" target="_blank" rel="noopener" href="https://brainlock.id/developer">Developer docs <span class="tc_v3_drawer_ext">↗</span></a>
    </nav>

    <div class="tc_v3_drawer_foot">Demo only. Not a real wallet.</div>
</aside>

<script>
// Mobile drawer toggle — tiny, inline so the v3 page is fully self-contained.
(function () {
  var btn = document.getElementById('tc_v3_burger_btn');
  var back = document.getElementById('tc_v3_drawer_back');
  var drawer = document.getElementById('tc_v3_drawer');
  var close = document.getElementById('tc_v3_drawer_close');
  if (!btn || !back || !drawer || !close) return;
  function open()  { drawer.classList.add('is_open'); back.classList.add('is_open'); document.body.classList.add('tc_v3_drawer_open'); btn.setAttribute('aria-expanded','true'); }
  function shut()  { drawer.classList.remove('is_open'); back.classList.remove('is_open'); document.body.classList.remove('tc_v3_drawer_open'); btn.setAttribute('aria-expanded','false'); }
  btn.addEventListener('click', open);
  close.addEventListener('click', shut);
  back.addEventListener('click', shut);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') shut(); });
  drawer.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', shut); });
})();
</script>

<main class="tc_v3_main">
