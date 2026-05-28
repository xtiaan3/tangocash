<?php
/**
 * Shared header. Renders the sticky top bar + the right-side hamburger
 * panel. The page passes:
 *   $page_title    — the <title>
 *   $signed_in     — bool; controls which nav variant renders
 *   $active_nav    — 'wallet' | 'send' | 'request' | 'profile' (for highlight)
 */
$page_title = $page_title ?? 'TangoCash';
$signed_in  = $signed_in  ?? false;
$active_nav = $active_nav ?? '';

// Cache-bust by file mtime so the CSS / JS auto-revs on every edit.
// `?? time()` is a safety net for the (impossible) case where the file
// vanishes; never actually fires in practice.
$css_v = @filemtime(__DIR__ . '/css/tangocash.css') ?: time();
$js_v  = @filemtime(__DIR__ . '/js/tangocash.js')  ?: time();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="description" content="TangoCash — a peer-to-peer wallet demo. The canonical 'Sign in with BrainLock' reference integration.">
    <link rel="icon" type="image/png" href="/img/logo.png">
    <link rel="stylesheet" href="/css/tangocash.css?v=<?= $css_v ?>">
</head>
<body class="<?= $signed_in ? 'is_signed_in' : 'is_signed_out' ?>">

<!-- Atmospheric blurred-blob background. Fixed to viewport, lives behind
     everything else, doesn't move with scroll. Three blobs of brand color
     at low opacity over a deep-navy base. -->
<div class="tc_atmosphere" aria-hidden="true"></div>

<!-- Sticky top bar. Logo left, nav center (signed-in only), hamburger
     right. Frosted glass backdrop. -->
<header class="tc_header">
    <a class="tc_brand" href="<?= $signed_in ? '/wallet.php' : '/' ?>">
        <img src="/img/tangocash_menu_logo.png" alt="TangoCash" class="tc_brand_logo">
    </a>

    <?php if ($signed_in): ?>
        <nav class="tc_nav" aria-label="Primary">
            <a href="/wallet.php"  class="tc_nav_item <?= $active_nav==='wallet'  ? 'is_active' : '' ?>">Wallet</a>
            <a href="/send.php"    class="tc_nav_item <?= $active_nav==='send'    ? 'is_active' : '' ?>">Send</a>
            <a href="/request.php" class="tc_nav_item <?= $active_nav==='request' ? 'is_active' : '' ?>">Request</a>
            <a href="/profile.php" class="tc_nav_item <?= $active_nav==='profile' ? 'is_active' : '' ?>">Profile</a>
        </nav>
    <?php endif; ?>

    <!-- Hamburger trigger. Always present, opens the slide-out menu. -->
    <button type="button" class="tc_hamburger" id="tc_hamburger_btn" aria-label="Open menu" aria-controls="tc_drawer" aria-expanded="false">
        <span class="tc_hamburger_line"></span>
        <span class="tc_hamburger_line"></span>
        <span class="tc_hamburger_line"></span>
    </button>
</header>

<!-- Slide-out drawer (right side). Hidden by default; .is_open animates
     the panel in. Backdrop click + Escape both close. -->
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
                <a href="/wallet.php" class="tc_drawer_item">Sign in with BrainLock</a>
                <a href="#what" class="tc_drawer_item">What is TangoCash?</a>
                <a href="#integration" class="tc_drawer_item">The 8-line integration</a>
                <a href="#dev-lens" class="tc_drawer_item">Developer Lens</a>
            </div>
        <?php endif; ?>

        <div class="tc_drawer_section">
            <div class="tc_drawer_label">For developers</div>
            <a href="https://github.com/xtiaan3/tangocash" target="_blank" rel="noopener" class="tc_drawer_item">
                Source on GitHub
                <span class="tc_drawer_ext">↗</span>
            </a>
            <a href="https://github.com/xtiaan3/brainlock-php" target="_blank" rel="noopener" class="tc_drawer_item">
                BrainLock PHP SDK
                <span class="tc_drawer_ext">↗</span>
            </a>
            <a href="https://brainlock.id/developer" target="_blank" rel="noopener" class="tc_drawer_item">
                BrainLock developer docs
                <span class="tc_drawer_ext">↗</span>
            </a>
            <a href="https://brainlock.id" target="_blank" rel="noopener" class="tc_drawer_item">
                What is BrainLock?
                <span class="tc_drawer_ext">↗</span>
            </a>
        </div>

        <?php if ($signed_in): ?>
            <div class="tc_drawer_section">
                <a href="/?signout=1" class="tc_drawer_item tc_drawer_item_quiet">Sign out</a>
            </div>
        <?php endif; ?>

    </nav>

    <div class="tc_drawer_foot">
        Demo only. Not a real wallet.
    </div>

</aside>

<main class="tc_main">
