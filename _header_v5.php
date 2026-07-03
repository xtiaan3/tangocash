<?php
/**
 * V5 — same as V4 except the menu chrome. Faithful copy of wandr's
 * actual top bar:
 *   - Larger, chunkier brand pill with a clear vertical | separator
 *     between logo and subtitle
 *   - Right cluster of nav pills — each item its own capsule, sized
 *     to match the brand pill's vertical rhythm
 *   - Large hamburger circle at the right end
 *   - Sign-in CTA is the only neon-gradient pill (single brand moment)
 *
 * Vars: $page_title, $signed_in, $active_nav.
 * Standalone — only loads tangocash_v5.css.
 */
$page_title = $page_title ?? 'TangoCash';
$signed_in  = $signed_in  ?? false;
$active_nav = $active_nav ?? '';

$css_v5 = @filemtime(__DIR__ . '/css/tangocash_v5.css') ?: time();

if ($signed_in) {
    $nav = [
        ['l' => 'Wallet',  'href' => '/wallet.php',  'key' => 'wallet'],
        ['l' => 'Send',    'href' => '/send.php',    'key' => 'send'],
        ['l' => 'Request', 'href' => '/request.php', 'key' => 'request'],
        ['l' => 'Profile', 'href' => '/profile.php', 'key' => 'profile'],
    ];
} else {
    $nav = [
        ['l' => "What's this", 'href' => '#v5-what',        'key' => ''],
        ['l' => 'The code',    'href' => '#v5-integration', 'key' => ''],
        ['l' => 'Dev Lens',    'href' => '#v5-dev-lens',    'key' => ''],
        ['l' => 'Try it',      'href' => '#v5-try',         'key' => ''],
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Anton&display=swap">
    <link rel="stylesheet" href="/css/tangocash_v5.css?v=<?= $css_v5 ?>">
</head>
<body class="<?= $signed_in ? 'is_signed_in' : 'is_signed_out' ?> tc_v5_page" id="v5-top">

<header class="tc_v5_topbar">
    <a class="tc_v5_brandpill" href="<?= $signed_in ? '/wallet.php' : '/index_v5.php' ?>">
        <span class="tc_v5_brandpill_logo">
            <span class="tc_v5_brandpill_star" aria-hidden="true">✱</span>
            <span class="tc_v5_brandpill_word">TangoCash</span>
        </span>
        <span class="tc_v5_brandpill_sep" aria-hidden="true"></span>
        <span class="tc_v5_brandpill_sub">P2P&nbsp;WALLET</span>
    </a>

    <div class="tc_v5_cluster" aria-label="Primary">
        <nav class="tc_v5_nav">
            <?php foreach ($nav as $item): ?>
                <a class="tc_v5_pill <?= ($active_nav !== '' && $active_nav === $item['key']) ? 'is_active' : '' ?>"
                   href="<?= htmlspecialchars($item['href']) ?>">
                    <?= htmlspecialchars($item['l']) ?>
                </a>
            <?php endforeach; ?>

            <?php if ($signed_in): ?>
                <?php $u = \tc_current_user(); $fn = $u['first_name'] ?? $u['username'] ?? 'You'; ?>
                <a class="tc_v5_pill tc_v5_pill_chip" href="/profile.php">
                    <span class="tc_v5_pill_dot"></span>
                    <?= htmlspecialchars($fn) ?>
                </a>
            <?php else: ?>
                <a class="tc_v5_pill tc_v5_pill_cta" href="/auth/start.php">
                    Sign in <span class="tc_v5_pill_star" aria-hidden="true">✱</span>
                </a>
            <?php endif; ?>
        </nav>

        <button type="button" class="tc_v5_burger" id="tc_v5_burger_btn"
                aria-label="Open menu" aria-controls="tc_v5_drawer" aria-expanded="false">
            <span></span><span></span>
        </button>
    </div>
</header>

<div class="tc_v5_drawer_back" id="tc_v5_drawer_back" aria-hidden="true"></div>
<aside class="tc_v5_drawer" id="tc_v5_drawer" aria-label="Site menu" aria-hidden="true">
    <button type="button" class="tc_v5_drawer_close" id="tc_v5_drawer_close" aria-label="Close menu">×</button>
    <nav class="tc_v5_drawer_nav">
        <?php foreach ($nav as $item): ?>
            <a class="tc_v5_drawer_item" href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['l']) ?></a>
        <?php endforeach; ?>
        <?php if (!$signed_in): ?>
            <a class="tc_v5_drawer_item tc_v5_drawer_item_cta" href="/auth/start.php">Sign in with BrainLock ✱</a>
        <?php endif; ?>
        <div class="tc_v5_drawer_section_label">For developers</div>
        <a class="tc_v5_drawer_item tc_v5_drawer_item_quiet" target="_blank" rel="noopener" href="https://github.com/xtiaan3/tangocash">Source on GitHub ↗</a>
        <a class="tc_v5_drawer_item tc_v5_drawer_item_quiet" target="_blank" rel="noopener" href="https://github.com/xtiaan3/brainlock-php">BrainLock PHP SDK ↗</a>
        <a class="tc_v5_drawer_item tc_v5_drawer_item_quiet" target="_blank" rel="noopener" href="https://brainlock.id/developer">Developer docs ↗</a>
    </nav>
</aside>

<script>
(function () {
  var btn = document.getElementById('tc_v5_burger_btn');
  var back = document.getElementById('tc_v5_drawer_back');
  var drawer = document.getElementById('tc_v5_drawer');
  var close = document.getElementById('tc_v5_drawer_close');
  if (!btn || !back || !drawer || !close) return;
  function open()  { drawer.classList.add('is_open'); back.classList.add('is_open'); document.body.classList.add('tc_v5_drawer_open'); btn.setAttribute('aria-expanded','true'); }
  function shut()  { drawer.classList.remove('is_open'); back.classList.remove('is_open'); document.body.classList.remove('tc_v5_drawer_open'); btn.setAttribute('aria-expanded','false'); }
  btn.addEventListener('click', open);
  close.addEventListener('click', shut);
  back.addEventListener('click', shut);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') shut(); });
  drawer.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', shut); });
})();
</script>

<main class="tc_v5_main">
