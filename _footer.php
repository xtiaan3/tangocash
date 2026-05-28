</main>

<footer class="tc_footer">

    <div class="tc_footer_top">
        <div class="tc_footer_brandline">
            <img src="/img/tangocash_menu_logo.png" alt="TangoCash" class="tc_footer_brand_logo">
            <p class="tc_footer_tagline">
                A fictional peer-to-peer wallet. The canonical reference for
                <a href="https://brainlock.id" target="_blank" rel="noopener">BrainLock</a> integration.
            </p>
        </div>
    </div>

    <div class="tc_footer_cols">

        <div class="tc_footer_col">
            <div class="tc_footer_col_h">TangoCash</div>
            <a href="/">Home</a>
            <a href="/wallet.php">Wallet (demo)</a>
            <a href="https://github.com/xtiaan3/tangocash" target="_blank" rel="noopener">Source on GitHub</a>
            <a href="#dev-lens">Developer Lens</a>
        </div>

        <div class="tc_footer_col">
            <div class="tc_footer_col_h">BrainLock</div>
            <a href="https://brainlock.id" target="_blank" rel="noopener">What is BrainLock?</a>
            <a href="https://brainlock.id/developer" target="_blank" rel="noopener">Developer portal</a>
            <a href="https://github.com/xtiaan3/brainlock-php" target="_blank" rel="noopener">PHP SDK</a>
            <a href="https://brainlock.id/blog" target="_blank" rel="noopener">Blog</a>
        </div>

        <div class="tc_footer_col">
            <div class="tc_footer_col_h">Build with us</div>
            <a href="#integration">8-line integration</a>
            <a href="#dev-lens">Inspect the protocol</a>
            <a href="https://brainlock.id/developer" target="_blank" rel="noopener">Get an API key</a>
            <a href="https://github.com/xtiaan3/tangocash/issues" target="_blank" rel="noopener">Open an issue</a>
        </div>

        <div class="tc_footer_col">
            <div class="tc_footer_col_h">Honest disclosures</div>
            <a href="#">Demo only — no real money</a>
            <a href="#">No accounts, no balances</a>
            <a href="#">Open source · MIT</a>
            <a href="#">Privacy policy</a>
        </div>

    </div>

    <div class="tc_footer_bottom">
        <div>© <?= date('Y') ?> TangoCash · Built to showcase BrainLock</div>
        <div class="tc_footer_made">
            Made with <span class="tc_footer_heart">♥</span> in San Diego
        </div>
    </div>

</footer>

<script src="/js/tangocash.js?v=<?= $js_v ?>"></script>
</body>
</html>
