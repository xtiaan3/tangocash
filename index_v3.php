<?php
/**
 * TangoCash homepage — V3 preview (wandr.studio-faithful).
 *
 * 95% wandr.studio layout: white background, Inter sans, centered nav
 * with parens numbers AFTER labels, asymmetric numbered service panels,
 * "Driven by design — " style marquee tagline.
 * 5% TangoCash: brand gradient on numbers / asterisks / hover states /
 * the CTA pill / key headline emphasis.
 *
 * Standalone: only loads tangocash_v3.css. Does NOT inherit from v1 or v2.
 */
require __DIR__ . '/_demo_data.php';

if (\tc_current_user() !== null) {
    \header('Location: /wallet.php');
    exit;
}

$page_title = 'TangoCash — sign in with BrainLock';
$signed_in  = false;
include __DIR__ . '/_header_v3.php';
?>

<!-- ==========================================================================
     Hero
     ========================================================================== -->
<section class="tc_v3_hero">
    <div class="tc_v3_hero_inner">
        <div class="tc_v3_hero_eyebrow">
            <span class="tc_v3_hero_eyebrow_star">✱</span>
            <span>Reference fintech for BrainLock</span>
        </div>
        <h1 class="tc_v3_hero_h">Send. Receive. <em>Done.</em></h1>
        <p class="tc_v3_hero_sub">
            TangoCash is a peer-to-peer wallet for people who actually pay each other back.
            No password to forget — sign in with BrainLock and you're in.
        </p>
        <a class="tc_v3_hero_link" href="/auth/start.php">
            <span class="tc_v3_hero_link_star">✱</span>
            <span>Open the demo</span>
        </a>
    </div>
</section>

<!-- ==========================================================================
     Marquee — wandr's "Driven by design —" ticker, restated.
     ========================================================================== -->
<div class="tc_v3_marquee" aria-hidden="true">
    <div class="tc_v3_marquee_track">
        <?php for ($i = 0; $i < 8; $i++): ?>
            <span class="tc_v3_marquee_word">Send <em>money.</em> Skip the <em>password.</em></span>
            <span class="tc_v3_marquee_star">✱</span>
        <?php endfor; ?>
    </div>
</div>

<!-- ==========================================================================
     01 — What's this
     ========================================================================== -->
<section class="tc_v3_panel" id="v3-what">
    <div class="tc_v3_panel_inner">
        <div class="tc_v3_panel_num"><em>(01)</em></div>
        <div class="tc_v3_panel_text">
            <p class="tc_v3_panel_kicker">What's this</p>
            <h2 class="tc_v3_panel_h">A working demo<br><em>and a working reference,</em><br>in the same place.</h2>
            <p class="tc_v3_panel_body">
                TangoCash is a fully functioning peer-to-peer wallet — sign in, send money, request money,
                see your balance. Every page also exposes the BrainLock plumbing underneath. Open the
                <strong>Developer Lens</strong> on any screen to watch the protocol move in real time.
                The source is on GitHub. Fork it. Ship your own.
            </p>
            <ul class="tc_v3_panel_bullets">
                <li>Full P2P wallet — balances, send, request, history</li>
                <li>Real BrainLock integration end-to-end</li>
                <li>Source on GitHub, MIT, written for clarity</li>
            </ul>
        </div>
        <div class="tc_v3_panel_visual tc_v3_panel_visual_pad">
            <div class="tc_v3_stats">
                <div class="tc_v3_stat">
                    <div class="tc_v3_stat_n">8</div>
                    <div class="tc_v3_stat_l">Lines of PHP</div>
                </div>
                <div class="tc_v3_stat">
                    <div class="tc_v3_stat_n">0</div>
                    <div class="tc_v3_stat_l">Passwords</div>
                </div>
                <div class="tc_v3_stat">
                    <div class="tc_v3_stat_n">~600ms</div>
                    <div class="tc_v3_stat_l">Returning sign-in</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     02 — The 8-line integration
     ========================================================================== -->
<section class="tc_v3_panel" id="v3-integration">
    <div class="tc_v3_panel_inner">
        <div class="tc_v3_panel_num"><em>(02)</em></div>
        <div class="tc_v3_panel_text">
            <p class="tc_v3_panel_kicker">The integration</p>
            <h2 class="tc_v3_panel_h">Eight lines<br>of <em>PHP.</em></h2>
            <p class="tc_v3_panel_body">
                The entire integration. Drop it into your app, register a callback URL, and you have
                passwordless identity working today. On the callback, one more line —
                <code>$identity = BrainLock::verify($_GET['token']);</code> — returns the user's name,
                email, and profile picture as a signed, verified JWT.
            </p>
            <ul class="tc_v3_panel_bullets">
                <li>composer require brainlock/sdk</li>
                <li>configure, connect, verify — that's it</li>
                <li>Identity bundle: name, email, profile picture</li>
            </ul>
        </div>
        <div class="tc_v3_panel_visual">
            <div class="tc_v3_code">
                <div class="tc_v3_code_head">
                    <span class="tc_v3_code_dot tc_v3_code_dot_r"></span>
                    <span class="tc_v3_code_dot tc_v3_code_dot_a"></span>
                    <span class="tc_v3_code_dot tc_v3_code_dot_g"></span>
                    <span class="tc_v3_code_file">signin.php</span>
                </div>
<pre class="tc_v3_code_body"><span class="c">&lt;?php</span>
<span class="k">require</span> <span class="s">'vendor/autoload.php'</span>;

<span class="v">BrainLock</span>::<span class="k">configure</span>([
    <span class="s">'api_key'</span>      =&gt; <span class="v">$_ENV</span>[<span class="s">'BRAINLOCK_API_KEY'</span>],
    <span class="s">'callback_url'</span> =&gt; <span class="s">'https://yourapp.com/auth/callback'</span>,
]);

<span class="v">BrainLock</span>::<span class="k">connect</span>([<span class="s">'user_id'</span> =&gt; <span class="k">session_id</span>()]);</pre>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     03 — Developer Lens
     ========================================================================== -->
<section class="tc_v3_panel" id="v3-dev-lens">
    <div class="tc_v3_panel_inner">
        <div class="tc_v3_panel_num"><em>(03)</em></div>
        <div class="tc_v3_panel_text">
            <p class="tc_v3_panel_kicker">Developer Lens</p>
            <h2 class="tc_v3_panel_h">A side panel<br>on every page.<br><em>The protocol, live.</em></h2>
            <p class="tc_v3_panel_body">
                Click <strong>DEV</strong> on the right edge of any TangoCash page to slide it open.
                Six tabs let you watch the BrainLock protocol move in real time, without context-switching
                to a docs site.
            </p>
        </div>
        <div class="tc_v3_panel_visual tc_v3_panel_visual_pad">
            <div class="tc_v3_lens">
                <div class="tc_v3_lens_item">
                    <div class="tc_v3_lens_kicker">Activity</div>
                    <h3 class="tc_v3_lens_h">Watch it happen.</h3>
                    <p class="tc_v3_lens_p">Every click streams an event into the panel. Color-coded. Live.</p>
                </div>
                <div class="tc_v3_lens_item">
                    <div class="tc_v3_lens_kicker">Code</div>
                    <h3 class="tc_v3_lens_h">See the source.</h3>
                    <p class="tc_v3_lens_p">The exact PHP that ran for this page. Syntax-highlighted.</p>
                </div>
                <div class="tc_v3_lens_item">
                    <div class="tc_v3_lens_kicker">JWT</div>
                    <h3 class="tc_v3_lens_h">Decode the token.</h3>
                    <p class="tc_v3_lens_p">Header, claims, signature status. Click any field to copy.</p>
                </div>
                <div class="tc_v3_lens_item">
                    <div class="tc_v3_lens_kicker">Requests</div>
                    <h3 class="tc_v3_lens_h">Every API call.</h3>
                    <p class="tc_v3_lens_p">Body, response, timing. One-click "Copy as cURL".</p>
                </div>
                <div class="tc_v3_lens_item">
                    <div class="tc_v3_lens_kicker">Try this</div>
                    <h3 class="tc_v3_lens_h">Snippets that ship.</h3>
                    <p class="tc_v3_lens_p">Copy-paste blocks organised by intent.</p>
                </div>
                <div class="tc_v3_lens_item">
                    <div class="tc_v3_lens_kicker">Docs</div>
                    <h3 class="tc_v3_lens_h">Right here.</h3>
                    <p class="tc_v3_lens_p">Curated links into the BrainLock developer portal.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     04 — Closing CTA (dark slab — the one moment of full-bleed contrast)
     ========================================================================== -->
<section class="tc_v3_closer" id="v3-try">
    <div class="tc_v3_closer_inner">
        <div>
            <div class="tc_v3_closer_num"><em>(04)</em>&nbsp;&nbsp;<span style="opacity:0.55;">/ Try it</span></div>
            <h2 class="tc_v3_closer_h">Three ways in.<br><em>Pick one.</em></h2>
        </div>
        <div class="tc_v3_closer_links">
            <a class="tc_v3_closer_link" href="/auth/start.php">
                <span>Sign in with BrainLock &middot; open the demo</span>
                <span>✱ →</span>
            </a>
            <a class="tc_v3_closer_link" href="https://github.com/xtiaan3/tangocash" target="_blank" rel="noopener">
                <span>Clone the source &middot; github.com/xtiaan3/tangocash</span>
                <span>↗</span>
            </a>
            <a class="tc_v3_closer_link" href="https://brainlock.id/developer" target="_blank" rel="noopener">
                <span>Get an API key &middot; BrainLock developer portal</span>
                <span>↗</span>
            </a>
        </div>
    </div>
</section>

<!-- ==========================================================================
     Footer
     ========================================================================== -->
<footer class="tc_v3_footer">
    <div class="tc_v3_footer_inner">
        <div>
            <strong>TangoCash</strong> — peer-to-peer wallet demo.<br>
            The canonical "Sign in with BrainLock" reference integration.
        </div>
        <div class="tc_v3_footer_right">
            <a href="https://github.com/xtiaan3/tangocash" target="_blank" rel="noopener">GitHub</a>
            &nbsp;·&nbsp;
            <a href="https://brainlock.id" target="_blank" rel="noopener">BrainLock</a>
            &nbsp;·&nbsp;
            <a href="/index.php">v1 design</a>
            &nbsp;·&nbsp;
            <a href="/index_v2.php">v2 design</a>
        </div>
    </div>
</footer>

</main></body></html>
