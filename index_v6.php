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

if (\tc_current_user() !== null) {
    \header('Location: /wallet.php');
    exit;
}

$page_title = 'TangoCash — sign in with BrainLock';
$signed_in  = false;
include __DIR__ . '/_header_v6.php';
?>

<!-- ==========================================================================
     Hero
     ========================================================================== -->
<section class="tc_v6_hero">
    <div class="tc_v6_hero_eyebrow">
        <span class="tc_v6_hero_eyebrow_star">✱</span>
        <span>Reference fintech for BrainLock</span>
    </div>

    <h1 class="tc_v6_display tc_v6_display_solid">From passwords and PIN resets to</h1>
    <h1 class="tc_v6_display tc_v6_display_mute">payments that just work.</h1>

    <p class="tc_v6_hero_sub">
        TangoCash is a peer-to-peer wallet for people who actually pay each other back.
        No password to forget — sign in with BrainLock and you're in.
    </p>

    <div class="tc_v6_hero_cta_wrap">
        <a class="tc_v6_cta_white" href="/auth/start.php">
            <span>Open the demo</span>
            <span class="tc_v6_cta_white_star">✱</span>
        </a>
    </div>
</section>

<!-- ==========================================================================
     Marquee
     ========================================================================== -->
<div class="tc_v6_marquee" aria-hidden="true">
    <div class="tc_v6_marquee_track">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <span class="tc_v6_marquee_word">Driven by simplicity</span>
            <span class="tc_v6_marquee_star">✱</span>
            <span class="tc_v6_marquee_word">Send. Receive. Done.</span>
            <span class="tc_v6_marquee_star">✱</span>
        <?php endfor; ?>
    </div>
</div>

<!-- ==========================================================================
     Dark services slab — the wandr signature: numbered panels with HUGE
     service names that crop the column.
     ========================================================================== -->
<section class="tc_v6_slab" id="v6-what">
    <div class="tc_v6_slab_inner">
        <div class="tc_v6_slab_kicker">
            <span class="tc_v6_slab_kicker_star">✱</span>
            <span>How TangoCash works &mdash; the four pieces</span>
        </div>

        <!-- 01 — What it is -->
        <div class="tc_v6_panel">
            <div class="tc_v6_panel_num">(01)</div>
            <h2 class="tc_v6_panel_title">P2P<br>WALLET</h2>
            <div class="tc_v6_panel_text">
                <p class="tc_v6_panel_body">
                    A fully functioning peer-to-peer wallet — sign in, send money, request money, see your
                    balance. Every page also exposes the BrainLock plumbing underneath; flip on the Developer
                    Lens to watch the protocol move in real time.
                </p>
                <ul class="tc_v6_panel_bullets">
                    <li>Balances, send, request, history</li>
                    <li>Real BrainLock integration end-to-end</li>
                    <li>Source on GitHub, MIT, written for clarity</li>
                </ul>
            </div>
        </div>

        <!-- 02 — Integration — with the floating neon blob over the column -->
        <div class="tc_v6_panel" id="v6-integration">
            <div class="tc_v6_panel_num">(02)</div>
            <h2 class="tc_v6_panel_title">EIGHT<br>LINES <em>OF PHP</em></h2>
            <div class="tc_v6_panel_blob" aria-hidden="true"></div>
            <div class="tc_v6_panel_text">
                <p class="tc_v6_panel_body">
                    The entire integration. Drop it into your app, register a callback URL, and you have
                    passwordless identity working today. One more line on the callback verifies the JWT and
                    returns the user's name, email, and profile picture.
                </p>
                <ul class="tc_v6_panel_bullets">
                    <li>composer require brainlock/sdk</li>
                    <li>configure, connect, verify — done</li>
                    <li>Signed identity bundle every time</li>
                </ul>
            </div>
        </div>

        <!-- 03 — Developer Lens -->
        <div class="tc_v6_panel" id="v6-dev-lens">
            <div class="tc_v6_panel_num">(03)</div>
            <h2 class="tc_v6_panel_title">DEV<br>LENS</h2>
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
            <h2 class="tc_v6_panel_title">SHIP<br><span class="tc_v6_panel_title_mute">YOUR OWN</span></h2>
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
    <div class="tc_v6_closer_label">✱ The eight lines</div>
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
</section>

<!-- ==========================================================================
     Marquee 2 — second beat
     ========================================================================== -->
<div class="tc_v6_marquee" aria-hidden="true">
    <div class="tc_v6_marquee_track">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <span class="tc_v6_marquee_word">No passwords. No resets.</span>
            <span class="tc_v6_marquee_star">✱</span>
            <span class="tc_v6_marquee_word">Just sign in.</span>
            <span class="tc_v6_marquee_star">✱</span>
        <?php endfor; ?>
    </div>
</div>

<!-- ==========================================================================
     Final CTA
     ========================================================================== -->
<section class="tc_v6_closer">
    <h2 class="tc_v6_display tc_v6_display_solid">Try the demo.</h2>
    <h2 class="tc_v6_display tc_v6_display_neon">Ship your own.</h2>
    <div class="tc_v6_hero_cta_wrap">
        <a class="tc_v6_cta_white" href="/auth/start.php">
            <span>Sign in with BrainLock</span>
            <span class="tc_v6_cta_white_star">✱</span>
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
            &nbsp;&middot;&nbsp;
            <a href="/index.php">v1</a>
            &nbsp;&middot;&nbsp;
            <a href="/index_v2.php">v2</a>
            &nbsp;&middot;&nbsp;
            <a href="/index_v3.php">v3</a>
            &nbsp;&middot;&nbsp;
            <a href="/index_v4.php">v4</a>
            &nbsp;&middot;&nbsp;
            <a href="/index_v5.php">v5</a>
        </div>
    </div>
</footer>

</main></body></html>
