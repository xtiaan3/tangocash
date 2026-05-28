<?php
/**
 * Shared header. Renders the top nav. The page passes:
 *   $page_title    — the <title>
 *   $signed_in     — bool; controls which nav variant renders
 *   $active_nav    — 'wallet' | 'send' | 'request' | 'profile' (for highlight)
 */
$page_title = $page_title ?? 'TangoCash';
$signed_in  = $signed_in  ?? false;
$active_nav = $active_nav ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="description" content="TangoCash — a peer-to-peer wallet demo. The canonical reference for 'Sign in with BrainLock' integration.">
    <link rel="icon" type="image/png" href="/img/logo.png">
    <link rel="stylesheet" href="/css/tangocash.css?v=1">
</head>
<body class="<?= $signed_in ? 'is_signed_in' : 'is_signed_out' ?>">

<header class="tc_header">
    <a class="tc_brand" href="<?= $signed_in ? '/wallet.php' : '/' ?>">
        <img src="/img/logo.png" alt="TangoCash" class="tc_brand_logo">
        <span class="tc_brand_name">TangoCash</span>
    </a>

    <?php if ($signed_in): ?>
        <nav class="tc_nav">
            <a href="/wallet.php"  class="tc_nav_item <?= $active_nav==='wallet'  ? 'is_active' : '' ?>">Wallet</a>
            <a href="/send.php"    class="tc_nav_item <?= $active_nav==='send'    ? 'is_active' : '' ?>">Send</a>
            <a href="/request.php" class="tc_nav_item <?= $active_nav==='request' ? 'is_active' : '' ?>">Request</a>
            <a href="/profile.php" class="tc_nav_item <?= $active_nav==='profile' ? 'is_active' : '' ?>">Profile</a>
        </nav>
        <a href="/?signout=1" class="tc_signout">Sign out</a>
    <?php endif; ?>
</header>

<main class="tc_main">
