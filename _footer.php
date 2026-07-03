</main>

<!-- Minimal black footer. Same shape as the inline footer the homepage
     uses — the SINGLE canonical footer for every TC page (dashboard,
     profile, future). Brand line on the left, two links on the right.
     If we ever want a fuller footer, evolve THIS file; do not duplicate.
-->
<footer class="tc_v6_footer">
    <div class="tc_v6_footer_inner">
        <div>
            <strong>TangoCash</strong> &mdash; peer-to-peer wallet demo.<br>
            The canonical &ldquo;Sign in with BrainLock&rdquo; reference integration.
        </div>
        <div class="tc_v6_footer_right">
            <a href="/privacy">Privacy</a>
            &nbsp;&middot;&nbsp;
            <a href="/terms">Terms</a>
            &nbsp;&middot;&nbsp;
            <a href="https://github.com/xtiaan3/brainlock-php" target="_blank" rel="noopener">PHP SDK</a>
            &nbsp;&middot;&nbsp;
            <a href="https://github.com/xtiaan3/tangocash" target="_blank" rel="noopener">Source</a>
            &nbsp;&middot;&nbsp;
            <a href="https://brainlock.id" target="_blank" rel="noopener">BrainLock</a>
        </div>
    </div>
</footer>

<script src="/js/tangocash.js?v=<?= $js_v ?? @filemtime(__DIR__ . '/js/tangocash.js') ?: time() ?>"></script>
</body>
</html>
