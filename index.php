<?php
require __DIR__ . '/_demo_data.php';

// In v0 there's no real auth — the "Sign in with BrainLock" button just
// hops to /wallet.php which renders the signed-in view. Real flow:
//   button click → BrainLock::connect() → popup → JWT → wallet.php
$page_title = 'TangoCash — sign in with BrainLock';
$signed_in  = false;
include __DIR__ . '/_header.php';
?>

<!-- ==========================================================================
     Hero
     ========================================================================== -->
<section class="tc_hero">
    <div class="tc_hero_inner">
        <div class="tc_hero_eyebrow">Reference fintech for BrainLock</div>
        <h1 class="tc_hero_h">Send. Receive. Done.</h1>
        <p class="tc_hero_sub">
            TangoCash is a peer-to-peer wallet for people who actually pay each other back.
            No password to forget — sign in with BrainLock and you're in.
        </p>

        <button class="styling_light btn_1" style="max-width:400px" onclick="window.location='/wallet.php'">
            <img src="/img/brainlock_logo_1024.png" alt="">
            Sign in with BrainLock
        </button>

        <div class="tc_hero_chips">
            <a href="#what"        class="tc_chip">What is this?</a>
            <a href="#integration" class="tc_chip">Show me the code</a>
            <a href="#dev-lens"    class="tc_chip">Open the Dev Lens</a>
        </div>
    </div>
</section>

<!-- ==========================================================================
     "What is this?" — one screen, one idea
     ========================================================================== -->
<section class="tc_band" id="what">
    <div class="tc_band_inner">
        <div class="tc_kicker">For developers</div>
        <h2 class="tc_band_h">A working demo and a working reference, in the same place.</h2>
        <p class="tc_band_sub">
            TangoCash is a fully functioning peer-to-peer wallet — sign in, send money, request money, see your balance.
            Every page also exposes the BrainLock plumbing underneath. Open the <strong>Developer Lens</strong> on any
            screen to watch the protocol move in real time. The source is on GitHub. Fork it. Ship your own.
        </p>
    </div>
</section>

<!-- ==========================================================================
     The 8-line integration
     ========================================================================== -->
<section class="tc_band tc_band_alt" id="integration">
    <div class="tc_band_inner">
        <div class="tc_kicker">Sign in with BrainLock</div>
        <h2 class="tc_band_h">Eight lines of PHP.</h2>
        <p class="tc_band_sub">
            This is the entire integration. Drop it into your app, register a callback URL, and you have
            passwordless identity working today.
        </p>

        <div class="tc_codeblock">
            <div class="tc_codeblock_head">
                <span class="tc_codeblock_dot tc_codeblock_dot_red"></span>
                <span class="tc_codeblock_dot tc_codeblock_dot_amber"></span>
                <span class="tc_codeblock_dot tc_codeblock_dot_green"></span>
                <span class="tc_codeblock_file">signin.php</span>
                <button type="button" class="tc_codeblock_copy" data-copy-target="code-signin">Copy</button>
            </div>
            <pre class="tc_codeblock_body"><code id="code-signin">&lt;?php
require 'vendor/autoload.php';

BrainLock::configure([
    'api_key'      =&gt; $_ENV['BRAINLOCK_API_KEY'],
    'callback_url' =&gt; 'https://yourapp.com/auth/callback',
]);

BrainLock::connect(['user_id' =&gt; session_id()]);</code></pre>
        </div>

        <p class="tc_band_meta">
            On the callback URL, one more line — <code>$identity = BrainLock::verify($_GET['token']);</code> —
            returns the user's name, email, and profile picture as a signed, verified JWT. That's the whole integration.
        </p>
    </div>
</section>

<!-- ==========================================================================
     Developer Lens preview
     ========================================================================== -->
<section class="tc_band" id="dev-lens">
    <div class="tc_band_inner">
        <div class="tc_kicker">Open it on any page</div>
        <h2 class="tc_band_h">The Developer Lens.</h2>
        <p class="tc_band_sub">
            Every page on TangoCash has a hidden side panel — click <strong>DEV</strong> on the right edge to slide it
            open. Six tabs let you watch the BrainLock protocol move in real time:
        </p>

        <div class="tc_lens_grid">
            <div class="tc_lens_card">
                <div class="tc_lens_kicker">Activity</div>
                <h3 class="tc_lens_h">Watch it happen.</h3>
                <p>Every click on the page streams an event into the panel. "Clicked Sign In → opened popup → received JWT." Color-coded. Live.</p>
            </div>
            <div class="tc_lens_card">
                <div class="tc_lens_kicker">Code</div>
                <h3 class="tc_lens_h">See the source.</h3>
                <p>The exact PHP that ran for this page. Syntax-highlighted. One copy button per snippet. No tabbing to GitHub.</p>
            </div>
            <div class="tc_lens_card">
                <div class="tc_lens_kicker">JWT</div>
                <h3 class="tc_lens_h">Decode the token.</h3>
                <p>The raw JWT, the decoded header, the payload claims. Click any field to copy. Signature verification status front and center.</p>
            </div>
            <div class="tc_lens_card">
                <div class="tc_lens_kicker">Requests</div>
                <h3 class="tc_lens_h">Every API call.</h3>
                <p>Every BrainLock API call this session — request body, response, timing. One-click "Copy as cURL" on any row.</p>
            </div>
            <div class="tc_lens_card">
                <div class="tc_lens_kicker">Try this</div>
                <h3 class="tc_lens_h">Snippets that ship.</h3>
                <p>Copy-paste blocks organized by intent. "Add Sign in to my app." "Verify the token in Node." "Handle account-switching." Lots of them.</p>
            </div>
            <div class="tc_lens_card">
                <div class="tc_lens_kicker">Docs</div>
                <h3 class="tc_lens_h">Right where you need them.</h3>
                <p>Curated deep links into the BrainLock developer portal for the bits the demo can't cover.</p>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     CTAs
     ========================================================================== -->
<section class="tc_band tc_band_alt">
    <div class="tc_band_inner tc_cta_block">
        <h2 class="tc_band_h">Three ways in.</h2>
        <div class="tc_cta_grid">
            <a class="tc_cta_card" href="/wallet.php">
                <div class="tc_cta_kicker">Try the demo</div>
                <div class="tc_cta_h">Sign in with BrainLock</div>
                <div class="tc_cta_meta">Land in the wallet. Send fake money. Open the Dev Lens.</div>
                <div class="tc_cta_arrow">→</div>
            </a>
            <a class="tc_cta_card" href="https://github.com/xtiaan3/tangocash" target="_blank" rel="noopener">
                <div class="tc_cta_kicker">Clone the source</div>
                <div class="tc_cta_h">github.com/xtiaan3/tangocash</div>
                <div class="tc_cta_meta">PHP 8.3. Read it in an hour. Ship your own.</div>
                <div class="tc_cta_arrow">↗</div>
            </a>
            <a class="tc_cta_card" href="https://brainlock.id/developer" target="_blank" rel="noopener">
                <div class="tc_cta_kicker">Get an API key</div>
                <div class="tc_cta_h">BrainLock developer portal</div>
                <div class="tc_cta_meta">Register your app, get keys, ship integration in an afternoon.</div>
                <div class="tc_cta_arrow">↗</div>
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/_footer.php'; ?>
