<?php
/**
 * V2 (wandr-inspired) shared header — preview only.
 *
 * Differences from _header.php:
 *   - All-caps, letter-spaced, numbered nav items
 *   - Persistent right-side CTA button (SIGN IN WITH BRAINLOCK signed-out;
 *     profile chip signed-in) — gives the top bar a real call-to-action
 *     even when the hero is scrolled off
 *   - Same hamburger drawer (unchanged) for the long-tail dev links
 *
 * Same vars as _header.php: $page_title, $signed_in, $active_nav.
 * Reuses the existing drawer markup — only the top bar changes.
 */
$page_title = $page_title ?? 'TangoCash';
$signed_in  = $signed_in  ?? false;
$active_nav = $active_nav ?? '';

$css_v   = @filemtime(__DIR__ . '/css/tangocash.css')    ?: time();
$css_v2  = @filemtime(__DIR__ . '/css/tangocash_v2.css') ?: time();
$js_v    = @filemtime(__DIR__ . '/js/tangocash.js')      ?: time();

// Signed-out homepage nav — anchor links to the page's own sections, so
// the numbered prefix gives the bar that editorial-rhythm feel even on
// a marketing page. Signed-in nav uses the real app routes.
if ($signed_in) {
    $nav = [
        ['n' => '01', 'l' => 'Wallet',  'href' => '/wallet.php',  'key' => 'wallet'],
        ['n' => '02', 'l' => 'Send',    'href' => '/send.php',    'key' => 'send'],
        ['n' => '03', 'l' => 'Request', 'href' => '/request.php', 'key' => 'request'],
        ['n' => '04', 'l' => 'Profile', 'href' => '/profile.php', 'key' => 'profile'],
    ];
} else {
    $nav = [
        ['n' => '01', 'l' => "What's this",  'href' => '#v2-what',        'key' => ''],
        ['n' => '02', 'l' => 'The code',     'href' => '#v2-integration', 'key' => ''],
        ['n' => '03', 'l' => 'Dev Lens',     'href' => '#v2-dev-lens',    'key' => ''],
        ['n' => '04', 'l' => 'Try it',       'href' => '#v2-try',         'key' => ''],
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
    <link rel="stylesheet" href="/css/tangocash.css?v=<?= $css_v ?>">
    <link rel="stylesheet" href="/css/tangocash_v2.css?v=<?= $css_v2 ?>">
</head>
<body class="<?= $signed_in ? 'is_signed_in' : 'is_signed_out' ?> tc_v2_page">

<div class="tc_atmosphere" aria-hidden="true"></div>

<header class="tc_v2_header">
    <a class="tc_v2_brand" href="<?= $signed_in ? '/wallet.php' : '/index_v2.php' ?>">
        <img src="/img/tangocash_menu_logo.png" alt="TangoCash" class="tc_v2_brand_logo">
    </a>

    <nav class="tc_v2_nav" aria-label="Primary">
        <?php foreach ($nav as $item): ?>
            <a class="tc_v2_nav_item <?= ($active_nav !== '' && $active_nav === $item['key']) ? 'is_active' : '' ?>"
               href="<?= htmlspecialchars($item['href']) ?>">
                <span class="tc_v2_nav_num"><?= $item['n'] ?></span>
                <span class="tc_v2_nav_label"><?= htmlspecialchars($item['l']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="tc_v2_right">
        <?php if ($signed_in): ?>
            <?php $u = \tc_current_user(); $fn = $u['first_name'] ?? $u['username'] ?? 'You'; ?>
            <a class="tc_v2_chip" href="/profile.php">
                <span class="tc_v2_chip_dot"></span>
                <?= htmlspecialchars($fn) ?>
            </a>
        <?php else: ?>
            <a class="tc_v2_cta" href="/auth/start.php">
                <img src="/img/brainlock_logo_1024.png" alt="" class="tc_v2_cta_icon">
                Sign in with BrainLock
            </a>
        <?php endif; ?>

        <button type="button" class="tc_hamburger tc_v2_hamburger" id="tc_hamburger_btn"
                aria-label="Open menu" aria-controls="tc_drawer" aria-expanded="false">
            <span class="tc_hamburger_line"></span>
            <span class="tc_hamburger_line"></span>
            <span class="tc_hamburger_line"></span>
        </button>
    </div>
</header>

<!-- Slide-out drawer — unchanged from v1, still the "everything else" menu. -->
<div class="tc_drawer_backdrop" id="tc_drawer_backdrop" aria-hidden="true"></div>
<aside class="tc_drawer" id="tc_drawer" aria-label="Site menu" aria-hidden="true">

    <div class="tc_drawer_head">
        <div class="tc_drawer_brand">
            <img src="/img/tangocash_menu_logo.png" alt="TangoCash" class="tc_drawer_logo">
        </div>
        <button type="button" class="tc_drawer_close" id="tc_drawer_close" aria-label="Close menu">
            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none">
                <path d="M6 6l12 12M6 18L18 6"/>
            </svg>
        </button>
    </div>

    <nav class="tc_drawer_nav" aria-label="Site">
        <?php if ($signed_in): ?>
            <div class="tc_drawer_section">
                <div class="tc_drawer_label">Account</div>
                <a href="/wallet.php"  class="tc_drawer_item <?= $active_nav==='wallet'  ? 'is_active' : '' ?>">Wallet</a>
                <a href="/send.php"    class="tc_drawer_item <?= $active_nav==='send'    ? 'is_active' : '' ?>">Send money</a>
                <a href="/request.php" class="tc_drawer_item <?= $active_nav==='request' ? 'is_active' : '' ?>">Request money</a>
                <a href="/profile.php" class="tc_drawer_item <?= $active_nav==='profile' ? 'is_active' : '' ?>">Profile</a>
            </div>
        <?php else: ?>
            <div class="tc_drawer_section">
                <div class="tc_drawer_label">Get started</div>
                <a href="/auth/start.php" class="tc_drawer_item">Sign in with BrainLock</a>
                <a href="#v2-what"        class="tc_drawer_item">What is TangoCash?</a>
                <a href="#v2-integration" class="tc_drawer_item">The 8-line integration</a>
                <a href="#v2-dev-lens"    class="tc_drawer_item">Developer Lens</a>
            </div>
        <?php endif; ?>

        <div class="tc_drawer_section">
            <div class="tc_drawer_label">For developers</div>
            <a href="https://github.com/xtiaan3/tangocash"      target="_blank" rel="noopener" class="tc_drawer_item">Source on GitHub <span class="tc_drawer_ext">↗</span></a>
            <a href="https://github.com/xtiaan3/brainlock-php"  target="_blank" rel="noopener" class="tc_drawer_item">BrainLock PHP SDK <span class="tc_drawer_ext">↗</span></a>
            <a href="https://brainlock.id/developer"            target="_blank" rel="noopener" class="tc_drawer_item">BrainLock developer docs <span class="tc_drawer_ext">↗</span></a>
            <a href="https://brainlock.id"                      target="_blank" rel="noopener" class="tc_drawer_item">What is BrainLock? <span class="tc_drawer_ext">↗</span></a>
        </div>

        <?php if ($signed_in): ?>
            <div class="tc_drawer_section">
                <a href="/?signout=1"    class="tc_drawer_item tc_drawer_item_quiet">Sign out</a>
                <a href="/?signout=hard" class="tc_drawer_item tc_drawer_item_quiet">Sign out + clear (fresh start)</a>
            </div>
        <?php endif; ?>
    </nav>

    <div class="tc_drawer_foot">
        Demo only. Not a real wallet.
    </div>
</aside>

<main class="tc_main">
