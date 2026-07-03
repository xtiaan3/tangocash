<?php
/**
 * TangoCash homepage — V2 preview (wandr-inspired).
 *
 * Same content as index.php, restructured into numbered asymmetric
 * sections with tonal differentiation between panels. Loads
 * _header_v2.php (wandr-style top bar) and tangocash_v2.css (v2-only
 * styles); v1 index.php / _header.php / tangocash.css are untouched.
 *
 * To flip back to v1: visit /index.php directly. /index_v2.php is the
 * preview only; nothing else links to it.
 */
require __DIR__ . '/_demo_data.php';

// Same redirect-when-signed-in behaviour as v1 — homepage is the
// signed-out landing.
if (\tc_current_user() !== null) {
    \header('Location: /wallet.php');
    exit;
}

$page_title = 'TangoCash — sign in with BrainLock';
$signed_in  = false;
include __DIR__ . '/_header_v2.php';
?>

<!-- ==========================================================================
     Hero — oversized headline, no card chrome, persistent top-bar CTA
     means the hero CTA can breathe.
     ========================================================================== -->
<section class="tc_v2_hero">
    <div class="tc_v2_hero_inner">
        <div class="tc_v2_hero_eyebrow">Reference fintech for BrainLock</div>
        <h1 class="tc_v2_hero_h">Send. Receive. <em>Done.</em></h1>
        <p class="tc_v2_hero_sub">
            TangoCash is a peer-to-peer wallet for people who actually pay each other back.
            No password to forget — sign in with BrainLock and you're in.
        </p>
        <div class="tc_v2_hero_actions">
            <a class="tc_v2_hero_btn" href="/auth/start.php">
                <img src="/img/brainlock_logo_1024.png" alt="">
                Sign in with BrainLock
            </a>
            <a class="tc_v2_hero_btn tc_v2_hero_btn_ghost" href="#v2-integration">
                Show me the code
            </a>
        </div>
    </div>
</section>

<hr class="tc_v2_rule">

<!-- ==========================================================================
     01 / What is this?
     Single-column text-led panel, oversized number anchor, no card.
     ========================================================================== -->
<section class="tc_v2_section tc_v2_section_base" id="v2-what">
    <div class="tc_v2_section_inner">
        <div class="tc_v2_marker">
            <span class="tc_v2_marker_num">01</span>
            <span class="tc_v2_marker_label">/ What is this</span>
        </div>
        <h2 class="tc_v2_h tc_v2_h_wide">A working demo and a working reference, in the same place.</h2>
        <p class="tc_v2_lede">
            TangoCash is a fully functioning peer-to-peer wallet — sign in, send money, request money, see your
            balance. Every page also exposes the BrainLock plumbing underneath. Open the <strong>Developer Lens</strong>
            on any screen to watch the protocol move in real time. The source is on GitHub. Fork it. Ship your own.
        </p>
    </div>
</section>

<!-- ==========================================================================
     02 / The 8-line integration
     Asymmetric split: text on left, code block on right (Layout A).
     Sits on a tonal-lit panel so the code visual reads as the centerpiece.
     ========================================================================== -->
<section class="tc_v2_section tc_v2_section_lit" id="v2-integration">
    <div class="tc_v2_section_inner">
        <div class="tc_v2_marker">
            <span class="tc_v2_marker_num">02</span>
            <span class="tc_v2_marker_label">/ The 8-line integration</span>
        </div>

        <div class="tc_v2_split">
            <div>
                <h2 class="tc_v2_h">Eight lines of PHP.</h2>
                <p class="tc_v2_lede">
                    The entire integration. Drop it into your app, register a callback URL, and you have
                    passwordless identity working today. On the callback, one more line —
                    <code>$identity = BrainLock::verify($_GET['token']);</code> — returns the user's name,
                    email, and profile picture as a signed, verified JWT.
                </p>
            </div>

            <div class="tc_v2_visual">
                <div class="tc_codeblock">
                    <div class="tc_codeblock_head">
                        <span class="tc_codeblock_dot tc_codeblock_dot_red"></span>
                        <span class="tc_codeblock_dot tc_codeblock_dot_amber"></span>
                        <span class="tc_codeblock_dot tc_codeblock_dot_green"></span>
                        <span class="tc_codeblock_file">signin.php</span>
                        <button type="button" class="tc_codeblock_copy" data-copy-target="code-signin-v2">Copy</button>
                    </div>
                    <pre class="tc_codeblock_body"><code id="code-signin-v2">&lt;?php
require 'vendor/autoload.php';

BrainLock::configure([
    'api_key' =&gt; $_ENV['BRAINLOCK_API_KEY'],
    'app_id'  =&gt; $_ENV['BRAINLOCK_APP_ID'],
]);

BrainLock::connect(['user_id' =&gt; session_id()]);</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     03 / Developer Lens
     Text-led intro + 3-col grid. Section sits on shadow tone for the
     darkest beat in the page — the section the developer audience lingers on.
     ========================================================================== -->
<section class="tc_v2_section tc_v2_section_shadow" id="v2-dev-lens">
    <div class="tc_v2_section_inner">
        <div class="tc_v2_marker">
            <span class="tc_v2_marker_num">03</span>
            <span class="tc_v2_marker_label">/ Developer Lens</span>
        </div>

        <h2 class="tc_v2_h tc_v2_h_wide">A side panel on every page. The protocol, live.</h2>
        <p class="tc_v2_lede">
            Click <strong>DEV</strong> on the right edge of any TangoCash page to slide it open.
            Six tabs let you watch the BrainLock protocol move in real time:
        </p>

        <div class="tc_v2_lens">
            <div class="tc_v2_lens_item">
                <div class="tc_v2_lens_kicker">Activity</div>
                <h3 class="tc_v2_lens_h">Watch it happen.</h3>
                <p class="tc_v2_lens_p">Every click streams an event into the panel. "Clicked Sign In → opened popup → received JWT." Color-coded. Live.</p>
            </div>
            <div class="tc_v2_lens_item">
                <div class="tc_v2_lens_kicker">Code</div>
                <h3 class="tc_v2_lens_h">See the source.</h3>
                <p class="tc_v2_lens_p">The exact PHP that ran for this page. Syntax-highlighted. One copy button per snippet. No tabbing to GitHub.</p>
            </div>
            <div class="tc_v2_lens_item">
                <div class="tc_v2_lens_kicker">JWT</div>
                <h3 class="tc_v2_lens_h">Decode the token.</h3>
                <p class="tc_v2_lens_p">The raw JWT, the decoded header, the payload claims. Click any field to copy. Signature verification status front and center.</p>
            </div>
            <div class="tc_v2_lens_item">
                <div class="tc_v2_lens_kicker">Requests</div>
                <h3 class="tc_v2_lens_h">Every API call.</h3>
                <p class="tc_v2_lens_p">Every BrainLock API call this session — request body, response, timing. One-click "Copy as cURL" on any row.</p>
            </div>
            <div class="tc_v2_lens_item">
                <div class="tc_v2_lens_kicker">Try this</div>
                <h3 class="tc_v2_lens_h">Snippets that ship.</h3>
                <p class="tc_v2_lens_p">Copy-paste blocks organized by intent. "Add Sign in to my app." "Verify the token in Node." Lots of them.</p>
            </div>
            <div class="tc_v2_lens_item">
                <div class="tc_v2_lens_kicker">Docs</div>
                <h3 class="tc_v2_lens_h">Right where you need them.</h3>
                <p class="tc_v2_lens_p">Curated deep links into the BrainLock developer portal for the bits the demo can't cover.</p>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     04 / Three ways in
     Asymmetric layout — one lead card on the left + two stacked smaller
     cards on the right — instead of three identical buckets.
     Back to base tone; CTA momentum lifts from the previous shadow band.
     ========================================================================== -->
<section class="tc_v2_section tc_v2_section_base" id="v2-try">
    <div class="tc_v2_section_inner">
        <div class="tc_v2_marker">
            <span class="tc_v2_marker_num">04</span>
            <span class="tc_v2_marker_label">/ Three ways in</span>
        </div>
        <h2 class="tc_v2_h">Pick the one that fits the hour.</h2>

        <div class="tc_v2_ways">
            <a class="tc_v2_way tc_v2_way_lead" href="/auth/start.php">
                <div class="tc_v2_way_kicker">Try the demo</div>
                <div class="tc_v2_way_h">Sign in with BrainLock</div>
                <p class="tc_v2_way_meta">Land in the wallet. Send fake money. Open the Dev Lens. Five minutes end-to-end.</p>
                <div class="tc_v2_way_arrow">→</div>
            </a>

            <div class="tc_v2_ways_stack">
                <a class="tc_v2_way" href="https://github.com/xtiaan3/tangocash" target="_blank" rel="noopener">
                    <div class="tc_v2_way_kicker">Clone the source</div>
                    <div class="tc_v2_way_h">github.com/xtiaan3/tangocash</div>
                    <p class="tc_v2_way_meta">PHP 8.3. Read it in an hour. Ship your own.</p>
                    <div class="tc_v2_way_arrow">↗</div>
                </a>
                <a class="tc_v2_way" href="https://brainlock.id/developer" target="_blank" rel="noopener">
                    <div class="tc_v2_way_kicker">Get an API key</div>
                    <div class="tc_v2_way_h">BrainLock developer portal</div>
                    <p class="tc_v2_way_meta">Register your app, get keys, ship integration in an afternoon.</p>
                    <div class="tc_v2_way_arrow">↗</div>
                </a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/_footer.php'; ?>
