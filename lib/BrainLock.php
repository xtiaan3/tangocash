<?php
/**
 * BrainLock PHP SDK — single-file edition.
 * =============================================================================
 *
 * The official PHP integration for BrainLock Connect (passwordless sign-in)
 * and BrainLock Verify (per-action authorization). Zero hard dependencies —
 * uses the curl + openssl extensions that ship with PHP.
 *
 * # How BrainLock works with your site
 *
 * BrainLock is NOT a live dependency for your site. Every exchange across
 * the boundary is a one-shot handoff at a ceremony moment; outside those
 * moments your site runs without touching BrainLock. The SDK reflects
 * that — the entire call surface is:
 *
 *   - configure()             — once at boot
 *   - connect()               — kicks off Connect (redirect)
 *   - verifyConnectToken()    — validates the Connect callback JWT
 *   - verifyAction()          — kicks off Verify (redirect)
 *   - verifyActionToken()     — validates the Verify callback JWT
 *
 * There is deliberately NO method to pull your own app's brand assets,
 * NO method to refresh user identity, NO "sync from BrainLock" helper.
 *
 *   - Your app's logo/icon: uploaded in the dev portal to render BrainLock's
 *     consent chrome during a ceremony. Your site hosts its OWN copy of
 *     the same files for its own chrome. Same file lives in two places by
 *     design. If BrainLock is down, your site stays branded.
 *   - User identity: handed off once at Connect, your app owns the copy.
 *     The user changes their display name in your app, never in BrainLock.
 *   - User avatar specifically: 1h presigned URL in the JWT, download
 *     once, host locally. See docs/AVATAR_HANDOFF.md.
 *
 * If you find yourself reaching for a "refresh from BrainLock" pattern in
 * your render path, you've drifted into the OAuth-with-sync mental model —
 * that's not how BrainLock works.
 *
 * Canonical 8-line example:
 *
 *     require 'BrainLock.php';
 *
 *     BrainLock::configure([
 *         'api_key' => $_ENV['BRAINLOCK_API_KEY'],
 *     ]);
 *
 *     BrainLock::connect(['user_id' => session_id()]);
 *
 * And the callback:
 *
 *     $identity = BrainLock::verifyConnectToken($_GET['token']);
 *     // ['sub' => '...', 'first_name' => '...', 'last_name' => '...',
 *     //  'email' => '...', 'picture' => '...']
 *
 * On signature failure / expired token / malformed JWT, verifyConnectToken()
 * throws BrainLockException. On a structurally valid token whose user
 * simply failed the ceremony, it returns normally with verified: false.
 *
 * Connect always returns the same fixed identity bundle:
 *   - `sub`        BL user id for this app   (always — NOT permanent, can rotate)
 *   - `first_name` given name                (always)
 *   - `last_name`  family name               (always)
 *   - `email`      primary email             (always)
 *   - `picture`    avatar URL                (only when the user has set one)
 *
 * First and last are returned as SEPARATE fields so the partner can
 * render either alone or concatenate as they wish. No scopes. No
 * checkboxes. The bundle is the bundle.
 *
 * BrainLock Verify (per-action approval — e.g. "approve this $5,000 transfer"):
 *
 *     // On the action trigger (e.g. user clicks Send):
 *     BrainLock::verifyAction([
 *         'user_id'        => $currentUser->id,
 *         'action'         => 'transfer_funds',
 *         'security_level' => 'elevated',
 *         'context' => [
 *             // Consent panel content — what the user reads before tapping AUTHORIZE.
 *             // All three keys optional; empty-string == missing → defaults apply.
 *             // Full contract + philosophy: brainlock.id/developer/docs/api-v1#consent-panel
 *             'title'       => 'Send $5,000.00',
 *             'description' => "You're sending money to Tim Apple via TangoCash.",
 *             'display'     => [
 *                 ['label' => 'Amount',    'value' => '$5,000.00'],
 *                 ['label' => 'Recipient', 'value' => 'tim.apple@example.com'],
 *             ],
 *             // Receipt-only — passes through to the JWT, no UI effect.
 *             'amount_cents' => 500000,
 *             'recipient'    => 'tim.apple@example.com',
 *         ],
 *     ]);
 *
 *     // On the callback (every context key echoes back unchanged):
 *     $receipt = BrainLock::verifyActionToken($_GET['token']);
 *     // ['sub' => '...', 'action' => 'transfer_funds',
 *     //  'context' => ['title' => 'Send $5,000.00', 'description' => '...',
 *     //                'display' => [...], 'amount_cents' => 500000, ...],
 *     //  'verified' => true, 'verification_id' => 'verif_...']
 *
 * Verify is always a top-level redirect — no iframe / popup transport.
 *
 * Source: github.com/xtiaan3/brainlock-php
 */
/**
 * BrainLockException — thrown by every SDK method on signature failure,
 * expired tokens, malformed JWTs, missing config, network errors, etc.
 *
 * A token that is structurally valid but whose user failed the ceremony
 * does NOT throw — it returns normally with verified: false. Catch this
 * exception only for "the token / SDK call itself is broken" cases.
 */
class BrainLockException extends \RuntimeException
{
}

final class BrainLock
{
    public const VERSION = '0.5.0';

    /** Default origin of the BrainLock service. */
    private const DEFAULT_API_BASE = 'https://brainlock.id';

    /** Cookie name used to bind a sign-in session to this browser. */
    private const STATE_COOKIE = 'brainlock_state';

    /** JWKS cache TTL — public keys rotate rarely; 1h is plenty. */
    private const JWKS_TTL = 3600;

    private static array $config = [];
    private static ?array $jwksCache = null;
    private static int $jwksCachedAt = 0;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Configure the SDK. Call once per request, before connect() or verify().
     *
     * Required:
     *   api_key  — your developer API key (bl_live_… or bl_test_…)
     *
     * Optional:
     *   api_base — defaults to https://brainlock.id. Override for testing.
     *   mode     — 'redirect' (default) or 'iframe'. Verify is always
     *              redirect regardless of this setting. See connect()
     *              for the Connect-side difference.
     *
     * Note: callback_url used to live here. It no longer does — the
     * URL is registered ONCE at brainlock.id/developer and looked up
     * server-side per session-create call via the API key. Keeping
     * it in application config invited drift (typos, dev/prod
     * mismatches, stale values across deploys). One source of truth
     * now: the developer portal.
     *
     * @throws InvalidArgumentException on bad config.
     */
    public static function configure(array $config): void
    {
        if (empty($config['api_key']) || !\is_string($config['api_key'])) {
            throw new \InvalidArgumentException('BrainLock: api_key is required.');
        }
        if (!\preg_match('/^bl_(live|test)_[a-f0-9]+$/', $config['api_key'])) {
            throw new \InvalidArgumentException('BrainLock: api_key must look like "bl_live_…" or "bl_test_…".');
        }

        // Transport mode.
        //   'redirect' — DEFAULT. Full-page navigation to brainlock.id and
        //                back. Same browser window throughout. Works in
        //                every browser, zero setup, full session/device/
        //                biometric continuity for returning users. The
        //                "Sign in with Google"-style pattern that the
        //                whole industry settled on for good reasons.
        //                Pick this unless you have a specific reason not to.
        //   'iframe'   — EXPERIMENTAL. Render the BrainLock UI as a
        //                full-viewport iframe over the partner page via
        //                the SAME-ORIGIN PROXY transport (BrainLock::
        //                handleEmbed mounted at embed_path). Looks
        //                slicker for the *first* sign-in on a specific
        //                partner site, but breaks the cross-site SSO
        //                story (each partner gets its own partitioned
        //                BrainLock session). Use only if you understand
        //                the tradeoff. See docs/CONNECT_TRANSPORTS.md.
        $mode = $config['mode'] ?? 'redirect';
        if (!\in_array($mode, ['iframe', 'redirect'], true)) {
            throw new \InvalidArgumentException('BrainLock: mode must be "iframe" or "redirect".');
        }

        // embed_path — URL prefix on YOUR site where BrainLock::handleEmbed
        // is mounted. The iframe and all its XHR/form-POSTs go through
        // this prefix. Defaults to /_bl. You can change it but it must
        // be a single path segment.
        $embedPath = $config['embed_path'] ?? '/_bl';
        if (!\preg_match('#^/[A-Za-z0-9_-]+$#', $embedPath)) {
            throw new \InvalidArgumentException('BrainLock: embed_path must look like "/_bl" — single path segment, no trailing slash.');
        }

        self::$config = [
            'api_key'    => $config['api_key'],
            'api_base'   => \rtrim($config['api_base'] ?? self::DEFAULT_API_BASE, '/'),
            'mode'       => $mode,
            'embed_path' => $embedPath,
        ];
    }

    /**
     * handleEmbed — the same-origin proxy handler.
     *
     * Mount this BEFORE your normal routing so it captures any request
     * under your configured `embed_path` (defaults to /_bl). The
     * canonical four-line mount:
     *
     *     // At the top of your app's router / index.php:
     *     if (str_starts_with($_SERVER['REQUEST_URI'], '/_bl/')) {
     *         BrainLock::handleEmbed();
     *         exit;
     *     }
     *
     * What it does. Every request matching /_bl/<rest> is forwarded to
     * brainlock.id/<rest> with the original method, body, query string,
     * and most headers preserved. The response is streamed back to the
     * browser with status, headers, and body intact. From the browser's
     * perspective, every request looks like it's coming FROM your own
     * domain — which means cookies BrainLock sets are stored as
     * first-party cookies on your origin. No CHIPS, no Storage Access,
     * no version-sniffing — works in every modern browser.
     *
     * Latency cost: one extra hop. Typically 5–20ms on the wire for
     * partner→BrainLock, negligible against the user-perceived sign-in
     * time.
     */
    public static function handleEmbed(): void
    {
        self::ensureConfigured();

        $base    = self::$config['api_base'];
        $prefix  = self::$config['embed_path'];
        $reqURI  = $_SERVER['REQUEST_URI'] ?? '/';
        // Strip the embed_path prefix to get the upstream path+query.
        if (\strpos($reqURI, $prefix . '/') !== 0 && $reqURI !== $prefix) {
            \http_response_code(404);
            echo 'Not found';
            return;
        }
        $upstreamPath = \substr($reqURI, \strlen($prefix));
        if ($upstreamPath === '') $upstreamPath = '/';
        $upstreamURL  = $base . $upstreamPath;

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $body   = \in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? \file_get_contents('php://input') : null;

        // Headers to forward. Drop hop-by-hop and Host (which we rewrite).
        // Add proxy_base so BrainLock-side template can emit absolute
        // asset URLs and inject window.BL_API_BASE for client-side
        // fetch routing.
        $fwdHeaders = [];
        $skip = [
            'host' => true, 'connection' => true, 'content-length' => true,
            'transfer-encoding' => true, 'keep-alive' => true, 'upgrade' => true,
            'proxy-authorization' => true, 'proxy-authenticate' => true,
            'te' => true, 'trailers' => true,
        ];
        foreach ($_SERVER as $k => $v) {
            if (\strpos($k, 'HTTP_') !== 0) continue;
            $name = \strtolower(\str_replace('_', '-', \substr($k, 5)));
            if (isset($skip[$name])) continue;
            $fwdHeaders[] = $name . ': ' . $v;
        }
        // Tell brainlock-go this request came through a proxy at our prefix.
        $fwdHeaders[] = 'X-BL-Proxy-Base: ' . $prefix;
        // Force identity encoding from upstream — we body-rewrite HTML
        // responses inline, can't do that on gzipped bytes. Drop any
        // browser-provided Accept-Encoding from our forwarded list.
        $fwdHeaders = \array_values(\array_filter($fwdHeaders, function ($h) {
            return \stripos($h, 'accept-encoding:') !== 0;
        }));
        $fwdHeaders[] = 'Accept-Encoding: identity';
        // Surface the real client IP for audit / rate-limiting on
        // BrainLock's side. Preserves any existing forwarded chain.
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($clientIP !== '') {
            $existing = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            $fwdHeaders[] = 'X-Forwarded-For: ' . ($existing ? $existing . ', ' . $clientIP : $clientIP);
        }

        $ch = \curl_init();
        \curl_setopt_array($ch, [
            CURLOPT_URL            => $upstreamURL,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $fwdHeaders,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        if ($body !== null && $body !== false && $body !== '') {
            \curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw  = \curl_exec($ch);
        if ($raw === false) {
            \http_response_code(502);
            echo 'Upstream error: ' . \curl_error($ch);
            \curl_close($ch);
            return;
        }
        $code      = (int)\curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerLen = (int)\curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        \curl_close($ch);

        $headerRaw = \substr($raw, 0, $headerLen);
        $bodyRaw   = \substr($raw, $headerLen);

        \http_response_code($code);
        $contentType = '';
        // Walk response headers; passthrough Set-Cookie + most others, skip
        // hop-by-hop and CSP frame-ancestors (which we override below).
        $skipResp = [
            'connection' => true, 'transfer-encoding' => true, 'content-length' => true,
            'content-encoding' => true, 'keep-alive' => true, 'upgrade' => true,
        ];
        foreach (\preg_split('/\r?\n/', $headerRaw) as $line) {
            if ($line === '' || \strpos($line, ':') === false) continue;
            [$name, $value] = \explode(':', $line, 2);
            $name  = \trim($name);
            $value = \trim($value);
            $low   = \strtolower($name);
            if (isset($skipResp[$low])) continue;
            if ($low === 'content-type') {
                $contentType = \strtolower($value);
            }
            if ($low === 'content-security-policy') {
                // BrainLock's CSP allows framing only from a specific origin;
                // since the iframe now loads from US (same-origin), tighten
                // to 'self' so no other site can frame our proxied auth UI.
                \header("Content-Security-Policy: frame-ancestors 'self'");
                continue;
            }
            \header($name . ': ' . $value, \strtolower($name) !== 'set-cookie');
        }

        // HTML responses get root-relative href/src/action attributes
        // prefixed with the proxy path so assets resolve through the
        // proxy. CSS responses get url(/...) rewriting for the same
        // reason (icons referenced via background-image). Inline JS
        // strings ('/api/...' inside JS) are NOT rewritten here — they
        // get the BrainLock-side fetch/XHR/script monkey-patch.
        if (\strpos($contentType, 'text/html') !== false) {
            $bodyRaw = self::rewriteHTMLPaths($bodyRaw, $prefix);
        } elseif (\strpos($contentType, 'text/css') !== false) {
            $bodyRaw = self::rewriteCSSPaths($bodyRaw, $prefix);
        }
        echo $bodyRaw;
    }

    /**
     * Prefix root-relative URLs inside CSS `url(/...)` references.
     */
    private static function rewriteCSSPaths(string $css, string $prefix): string
    {
        return \preg_replace(
            '#\burl\(\s*(["\']?)/(?!/|' . \preg_quote(\ltrim($prefix, '/'), '#') . '/)#i',
            'url($1' . $prefix . '/',
            $css
        ) ?? $css;
    }

    /**
     * Prefix root-relative href/src/action attributes with the proxy
     * base. The negative lookahead skips `//` (protocol-relative) and
     * already-prefixed paths.
     */
    private static function rewriteHTMLPaths(string $html, string $prefix): string
    {
        // Single regex covers all three attributes.
        return \preg_replace(
            '#\b(href|src|action)\s*=\s*(["\'])/(?!/|' . \preg_quote(\ltrim($prefix, '/'), '#') . '/)#i',
            '$1=$2' . $prefix . '/',
            $html
        ) ?? $html;
    }


    /**
     * @internal Lower-level session-create helper retained for the same-origin
     * iframe transport (see {@see emitIframeOpener()}). Not part of the
     * public SDK surface and not covered by SemVer; signature and return
     * shape may change between minor versions. Use {@see connect()} or
     * {@see verifyAction()} from partner code.
     */
    public static function startSession(array $opts = []): array
    {
        self::ensureConfigured();
        if (empty($opts['user_id']) || !\is_string($opts['user_id'])) {
            throw new \InvalidArgumentException('BrainLock::startSession requires user_id.');
        }
        $state = $opts['state'] ?? \bin2hex(\random_bytes(16));
        $level = $opts['security_level'] ?? 'secure';
        if (!\in_array($level, ['secure', 'elevated', 'maximum'], true)) {
            throw new \InvalidArgumentException("BrainLock::startSession security_level must be 'secure', 'elevated', or 'maximum'.");
        }

        $resp = self::http(
            'POST',
            self::$config['api_base'] . '/v1/auth/session',
            [
                'user_id'        => $opts['user_id'],
                'security_level' => $level,
                'state'          => $state,
                'require_geo'    => !empty($opts['require_geo']),
            ],
            ['Authorization: Bearer ' . self::$config['api_key']]
        );
        if (empty($resp['redirect_url']) || empty($resp['session_id'])) {
            $msg = isset($resp['error']['message']) ? $resp['error']['message'] : 'unknown error';
            throw new \RuntimeException('BrainLock: failed to create auth session: ' . $msg);
        }
        \setcookie(self::STATE_COOKIE, $state, [
            'expires'  => \time() + 600,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        // Build the proxy-prefixed URL for iframe transport. The partner
        // mounts handleEmbed() at embed_path; the iframe loads via that
        // same-origin path so cookies are first-party. The full URL
        // (direct to brainlock.id) is kept for the redirect transport.
        $authPath  = \parse_url($resp['redirect_url'], PHP_URL_PATH)  ?: '/';
        $authQuery = \parse_url($resp['redirect_url'], PHP_URL_QUERY) ?: '';
        $iframeUrl = self::$config['embed_path'] . $authPath . ($authQuery ? '?' . $authQuery : '');
        $sep       = (\strpos($iframeUrl, '?') === false) ? '?' : '&';
        $iframeUrl .= $sep . 'embed=iframe&proxy_base=' . \urlencode(self::$config['embed_path']);

        return [
            'url'        => $resp['redirect_url'], // direct to brainlock.id (redirect mode)
            'iframe_url' => $iframeUrl,            // partner-origin proxy path (iframe mode)
            'session_id' => $resp['session_id'],
            'expires_at' => $resp['expires_at'] ?? null,
        ];
    }

    /**
     * Start a sign-in flow.
     *
     * Required:
     *   user_id — YOUR app's stable identifier for the user. BrainLock uses
     *             it to maintain a (your_app, user_id) → BrainLock vault
     *             binding so the same person always lands on the same row.
     *             For first-time users, you can pass session_id() and migrate
     *             on the callback once you mint your own user_id.
     *
     * Optional:
     *   security_level — 'secure' (default) | 'elevated' | 'maximum'
     *   state          — opaque CSRF/round-trip string. Auto-generated when omitted.
     *
     * Side effects (redirect mode, default):
     *   Sends a 302 to brainlock.id/auth/<sid>. The user leaves your domain
     *   for the ceremony; BrainLock redirects them back to the callback URL
     *   registered on your app (see brainlock.id/developer) with the result
     *   in the query string.
     *
     * Side effects (iframe mode):
     *   Emits a launcher page that runs a capability check and either opens
     *   the BrainLock auth UI inside a same-origin iframe (modern browsers
     *   with partitioned-cookies / CHIPS support) or falls back to a full-
     *   page redirect. The page is the entirety of the HTTP response — call
     *   from a dedicated handler.
     *
     * Both modes set a `brainlock_state` cookie containing the state value.
     * It's available for your own callback handler to read and compare
     * against ?state= for CSRF defense; the SDK itself just clears it after
     * a successful verify and does NOT enforce a comparison.
     */
    public static function connect(array $opts = []): void
    {
        self::ensureConfigured();

        if (empty($opts['user_id']) || !\is_string($opts['user_id'])) {
            throw new \InvalidArgumentException('BrainLock::connect requires user_id.');
        }
        $state = $opts['state'] ?? \bin2hex(\random_bytes(16));
        $level = $opts['security_level'] ?? 'secure';
        if (!\in_array($level, ['secure', 'elevated', 'maximum'], true)) {
            throw new \InvalidArgumentException("BrainLock::connect security_level must be 'secure', 'elevated', or 'maximum'.");
        }

        // Create the auth session on the BrainLock side.
        $resp = self::http(
            'POST',
            self::$config['api_base'] . '/v1/auth/session',
            [
                'user_id'        => $opts['user_id'],
                'security_level' => $level,
                'state'          => $state,
                'require_geo'    => !empty($opts['require_geo']),
            ],
            ['Authorization: Bearer ' . self::$config['api_key']]
        );
        if (empty($resp['redirect_url']) || empty($resp['session_id'])) {
            $msg = isset($resp['error']['message']) ? $resp['error']['message'] : 'unknown error';
            throw new \RuntimeException('BrainLock: failed to create auth session: ' . $msg);
        }

        // Bind the state to this browser so the callback can detect replays
        // and the popup can't be tricked into accepting someone else's token.
        \setcookie(self::STATE_COOKIE, $state, [
            'expires'  => \time() + 600,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (self::$config['mode'] === 'redirect') {
            \header('Location: ' . $resp['redirect_url']);
            exit;
        }

        // Iframe mode: emit a launcher page. The page does a client-side
        // capability check (browsers that don't support partitioned
        // cookies / CHIPS — chiefly Safari ≤ 17 — fall back to a
        // full-page redirect instead of the iframe). Modern browsers
        // get the in-page experience: TangoCash dims, BrainLock fills
        // the viewport, postMessage handoff on completion.
        self::emitIframeOpener($resp['redirect_url']);
    }

    /**
     * Verify a JWT received on the callback URL.
     *
     * Throws BrainLockException on any failure (bad signature, malformed
     * token, expired, or intent mismatch — the SDK rejects Verify tokens
     * here). Note: the SDK does NOT currently validate the `aud` claim or
     * the state cookie; if you need those defenses, layer them at your
     * callback handler. Returns the decoded identity claims on success:
     *
     *   [
     *     'sub'        => '<stable user id for this app>',
     *     'first_name' => 'Tim',
     *     'last_name'  => 'Apple',
     *     'email'      => 'tim.apple@example.com',
     *     'picture' => 'https://…/avatar.jpg',  // omitted when not set
     *     'verified'      => true,
     *     'biometric_used' => true|false,
     *     'session_id'    => '<bl_sess_…>',
     *     'iat'           => 1779971517,
     *     'exp'           => 1779971577,
     *   ]
     */
    public static function verifyConnectToken(string $token): array
    {
        $payload = self::verifyTokenCommon($token, 'connect', 'verifyConnectToken');

        // Flatten the identity bundle. The signed payload puts the bundle
        // under "profile"; we hoist its keys to the top level so callers
        // don't have to dig.
        $identity = [
            'sub'            => $payload['sub']        ?? '',
            'session_id'     => $payload['session_id'] ?? '',
            'verified'       => !empty($payload['verified']),
            'biometric_used' => !empty($payload['biometric_used']),
            'iat'            => $payload['iat'] ?? null,
            'exp'            => $payload['exp'] ?? null,
        ];
        // Verification id (top-level claim, NOT under profile) — the
        // audit-log cross-reference key. Always populated on a success
        // resolution; absent only on the rare audit-persist failure.
        if (!empty($payload['verification_id'])) {
            $identity['verification_id'] = $payload['verification_id'];
        }
        $profile = $payload['profile'] ?? [];
        foreach (['first_name', 'last_name', 'email', 'picture'] as $k) {
            if (!empty($profile[$k])) {
                $identity[$k] = $profile[$k];
            }
        }
        return $identity;
    }

    /**
     * Kick off a BrainLock Verify (per-action) ceremony.
     *
     * Required:
     *   user_id  — same identifier you'd pass to connect() / startSession();
     *              must already be bound to a BrainLock vault (you can't
     *              Verify a user who hasn't Connected first).
     *   action   — partner-supplied action key, e.g. 'transfer_funds',
     *              'change_password', 'reveal_seed_phrase'. ≤ 64 chars.
     *              Echoed back in the verified JWT so your callback can
     *              prove the user authorized THIS action, not a different
     *              one that hit the same code path.
     *
     * Optional:
     *   context        — JSON-serializable payload describing what's being
     *                    approved. Encrypted at rest on the BrainLock side,
     *                    echoed back unchanged in the JWT. Max 10KB after
     *                    serialization.
     *
     *                    THREE KEYS ARE RECOGNIZED BY THE CONSENT UI (all
     *                    optional). Treat these as user-facing copy — they
     *                    are what the user reads on BrainLock's consent
     *                    panel before tapping AUTHORIZE:
     *
     *                      'title'       — short headline. Missing/empty
     *                                      → "BrainLock Verify"
     *                      'description' — one-sentence body. Missing/empty
     *                                      → "You're verifying an action with <AppName>."
     *                      'display'     — array of ['label' => …, 'value' => …]
     *                                      rows the user should eyeball
     *                                      (amount, recipient, destination).
     *                                      No fallback; if you don't send
     *                                      rows, none render. Sweet spot:
     *                                      1–3 rows. 5+ usually means you
     *                                      should summarize in 'description'.
     *
     *                    Any other keys (amount_cents, recipient, your own
     *                    routing metadata) pass through verbatim to the
     *                    JWT receipt — for your downstream code, not the
     *                    consent UI.
     *
     *                    Full philosophy + worked examples:
     *                    https://brainlock.id/developer/docs/api-v1#consent-panel
     *   security_level — 'secure' (default) | 'elevated' | 'maximum'.
     *                    Default to 'secure' for routine confirmations;
     *                    'maximum' for genuinely irreversible actions.
     *   require_geo    — force at least one geographic challenge.
     *   state          — opaque CSRF/round-trip string.
     *
     * Side effects: top-level redirect (302) to the BrainLock auth landing.
     * Unlike connect(), Verify is ALWAYS redirect — no iframe / popup. A
     * per-action approval should never be silently embedded inside the
     * partner UI; the user is committing to something irreversible and
     * deserves the full handoff.
     */
    public static function verifyAction(array $opts = []): void
    {
        self::ensureConfigured();

        if (empty($opts['user_id']) || !\is_string($opts['user_id'])) {
            throw new \InvalidArgumentException('BrainLock::verifyAction requires user_id.');
        }
        if (empty($opts['action']) || !\is_string($opts['action'])) {
            throw new \InvalidArgumentException('BrainLock::verifyAction requires action.');
        }
        if (\strlen($opts['action']) > 64) {
            throw new \InvalidArgumentException('BrainLock::verifyAction: action must be 64 characters or less.');
        }
        $state = $opts['state'] ?? \bin2hex(\random_bytes(16));
        $level = $opts['security_level'] ?? 'secure';
        if (!\in_array($level, ['secure', 'elevated', 'maximum'], true)) {
            throw new \InvalidArgumentException("BrainLock::verifyAction security_level must be 'secure', 'elevated', or 'maximum'.");
        }
        $context = $opts['context'] ?? null;
        if ($context !== null && !\is_array($context)) {
            throw new \InvalidArgumentException('BrainLock::verifyAction context must be an associative array.');
        }

        $payload = [
            'user_id'        => $opts['user_id'],
            'intent'         => 'verify',
            'action'         => $opts['action'],
            'security_level' => $level,
            'state'          => $state,
            'require_geo'    => !empty($opts['require_geo']),
        ];
        if ($context !== null) {
            $payload['context'] = $context;
        }

        $resp = self::http(
            'POST',
            self::$config['api_base'] . '/v1/auth/session',
            $payload,
            ['Authorization: Bearer ' . self::$config['api_key']]
        );
        if (empty($resp['redirect_url']) || empty($resp['session_id'])) {
            $msg = isset($resp['error']['message']) ? $resp['error']['message'] : 'unknown error';
            throw new \RuntimeException('BrainLock: failed to create verify session: ' . $msg);
        }

        \setcookie(self::STATE_COOKIE, $state, [
            'expires'  => \time() + 600,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Always redirect — Verify ignores the global mode config.
        // See memory:project_verify_redirect_only for the rationale.
        \header('Location: ' . $resp['redirect_url']);
        exit;
    }

    /**
     * Verify a JWT returned from a Verify (per-action) callback.
     *
     * Throws BrainLockException on any failure (bad signature, expired,
     * intent mismatch, etc.). Returns the action receipt on success:
     *
     *   [
     *     'sub'             => '<your user_id>',
     *     'session_id'      => '<bl_sess_…>',
     *     'verification_id' => '<verif_…>',           // audit-log id
     *     'verified'        => true,
     *     'biometric_used'  => true|false,
     *     'action'          => 'transfer_funds',      // echo of what you sent
     *     'context'         => [...],                 // echo of what you sent
     *     'iat'             => 1779971517,
     *     'exp'             => 1779971577,
     *   ]
     *
     * No identity-bundle fields (no first_name/last_name/email/picture) —
     * Verify is per-action approval, not identity exchange. You already
     * know who the user is when you call verifyAction(); the receipt
     * proves they approved this specific action right now.
     */
    public static function verifyActionToken(string $token): array
    {
        $payload = self::verifyTokenCommon($token, 'verify', 'verifyActionToken');

        $receipt = [
            'sub'            => $payload['sub']        ?? '',
            'session_id'     => $payload['session_id'] ?? '',
            'verified'       => !empty($payload['verified']),
            'biometric_used' => !empty($payload['biometric_used']),
            'iat'            => $payload['iat'] ?? null,
            'exp'            => $payload['exp'] ?? null,
            'action'         => $payload['action'] ?? '',
        ];
        if (!empty($payload['verification_id'])) {
            $receipt['verification_id'] = $payload['verification_id'];
        }
        if (isset($payload['context']) && \is_array($payload['context'])) {
            $receipt['context'] = $payload['context'];
        }
        return $receipt;
    }

    /**
     * Shared JWT verification — signature, algorithm, expiry, intent.
     * Returns the decoded payload on success. Throws BrainLockException
     * on any failure. The caller is responsible for hoisting whichever
     * payload claims it cares about into the public return shape.
     *
     * $expectIntent — '' to accept any intent; 'connect' or 'verify' to
     * enforce. Tokens are required to carry an `intent` claim — a missing
     * claim throws BrainLockException.
     */
    private static function verifyTokenCommon(string $token, string $expectIntent, string $callerName): array
    {
        self::ensureConfigured();
        if ($token === '') {
            throw new \BrainLockException("BrainLock::{$callerName}: empty token.");
        }

        // Decode the three JWT segments.
        $parts = \explode('.', $token);
        if (\count($parts) !== 3) {
            throw new \BrainLockException("BrainLock::{$callerName}: malformed token.");
        }
        [$rawHeader, $rawPayload, $rawSig] = $parts;
        $header  = self::jsonDecodeSegment($rawHeader);
        $payload = self::jsonDecodeSegment($rawPayload);
        $sig     = self::base64UrlDecode($rawSig);

        // Algorithm guard. The BrainLock JWKS only signs RS256.
        if (($header['alg'] ?? '') !== 'RS256') {
            throw new \BrainLockException("BrainLock::{$callerName}: unexpected alg \"" . ($header['alg'] ?? '') . "\".");
        }
        $kid = $header['kid'] ?? '';
        if ($kid === '') {
            throw new \BrainLockException("BrainLock::{$callerName}: token missing kid.");
        }

        // Resolve the kid against the JWKS.
        $publicKey = self::publicKeyFor($kid);

        // Verify the RSA-SHA256 signature over header.payload (raw bytes).
        $signed = $rawHeader . '.' . $rawPayload;
        $verifyResult = \openssl_verify($signed, $sig, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verifyResult !== 1) {
            throw new \BrainLockException("BrainLock::{$callerName}: signature mismatch.");
        }

        // Standard claim checks.
        $now = \time();
        if (!isset($payload['exp']) || $payload['exp'] < $now) {
            throw new \BrainLockException("BrainLock::{$callerName}: token expired.");
        }
        if (isset($payload['nbf']) && $payload['nbf'] > $now + 10) {
            throw new \BrainLockException("BrainLock::{$callerName}: token not yet valid.");
        }

        // Intent enforcement. Crucial: prevents a Connect-issued JWT from
        // being accepted by verifyActionToken (and vice versa). A missing
        // intent claim is a token-shape failure — every BrainLock-minted
        // token carries one.
        if ($expectIntent !== '') {
            if (empty($payload['intent']) || !\is_string($payload['intent'])) {
                throw new \BrainLockException(
                    "BrainLock::{$callerName}: token is missing the 'intent' claim."
                );
            }
            $gotIntent = $payload['intent'];
            if ($gotIntent !== $expectIntent) {
                throw new \BrainLockException(
                    "BrainLock::{$callerName}: token intent is '{$gotIntent}', expected '{$expectIntent}'. " .
                    "You may be calling the wrong verify* method for this token."
                );
            }
        }

        // State-cookie cross-check. Both connect() and verifyAction() set
        // the state cookie; the JWT's session_id is opaque, but we can at
        // least defeat naive replay by clearing the cookie now.
        if (isset($_COOKIE[self::STATE_COOKIE])) {
            \setcookie(self::STATE_COOKIE, '', [
                'expires'  => 1,
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        return $payload;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private static function ensureConfigured(): void
    {
        if (empty(self::$config['api_key'])) {
            throw new \RuntimeException('BrainLock: call BrainLock::configure(…) first.');
        }
    }

    /**
     * Emit the iframe-launcher page. Inline HTML+JS, no external assets.
     *
     * In proxy mode (the default), the iframe loads from a path on YOUR
     * own site (e.g. /_bl/auth/<sid>?embed=iframe) — same-origin to the
     * top-level page so cookies behave as first-party. This works in
     * every modern browser including Safari.
     *
     * The iframe URL is built by rewriting the BrainLock-provided
     * authUrl: scheme/host are dropped, path/query preserved, prefixed
     * with the partner's embed_path. The proxy on the partner side
     * (BrainLock::handleEmbed) then forwards each request to
     * brainlock.id transparently.
     */
    private static function emitIframeOpener(string $authUrl): void
    {
        $authPath    = \parse_url($authUrl, PHP_URL_PATH);
        $authQuery   = \parse_url($authUrl, PHP_URL_QUERY);
        $proxyURL    = self::$config['embed_path'] . $authPath . ($authQuery ? '?' . $authQuery : '');
        $iframeUrl   = $proxyURL . (\strpos($proxyURL, '?') === false ? '?' : '&') . 'embed=iframe';
        $redirectUrl = $authUrl; // direct nav to brainlock.id, no proxy involved
        // postMessage origin: in proxy mode the iframe is on OUR origin,
        // so the message comes from window.location.origin. Compute at
        // emit time so the JS can compare exactly.
        $proxyOrigin = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
        $proxyOrigin .= '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $iframeUrlJs   = \json_encode($iframeUrl,   JSON_UNESCAPED_SLASHES);
        $redirectUrlJs = \json_encode($redirectUrl, JSON_UNESCAPED_SLASHES);
        $apiOriginJs   = \json_encode($proxyOrigin, JSON_UNESCAPED_SLASHES);

        \header('Cache-Control: no-store, must-revalidate');
        \header('Content-Type: text/html; charset=utf-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signing in with BrainLock…</title>
    <style>
        html, body { margin: 0; height: 100%; background: #0a0e1f; color: #f5f3fb; font: 15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        .bl_loader_wrap { height: 100%; display: grid; place-items: center; padding: 24px; text-align: center; }
        .bl_loader_inner { max-width: 360px; }
        .bl_loader_spinner { width: 36px; height: 36px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.15); border-top-color: #ff3d8b; animation: bl_spin 700ms linear infinite; margin: 0 auto 18px; }
        .bl_loader_h { font-size: 16px; font-weight: 600; margin: 0 0 6px; }
        .bl_loader_sub { font-size: 13px; opacity: 0.65; margin: 0; }
        @keyframes bl_spin { to { transform: rotate(360deg); } }
        #bl_iframe { position: fixed; inset: 0; width: 100vw; height: 100vh; border: 0; z-index: 9999; background: transparent; opacity: 0; transition: opacity 220ms ease; }
    </style>
</head>
<body>
<div class="bl_loader_wrap" id="bl_fallback_ui">
    <div class="bl_loader_inner">
        <div class="bl_loader_spinner"></div>
        <p class="bl_loader_h">Signing in with BrainLock…</p>
        <p class="bl_loader_sub">Just a moment.</p>
    </div>
</div>
<script>
(function () {
    var IFRAME_URL   = $iframeUrlJs;
    var REDIRECT_URL = $redirectUrlJs;
    var SAME_ORIGIN  = $apiOriginJs;

    // Proxy-mode iframe: same-origin to this page (which is on the
    // partner's domain). Cookies behave as first-party. No capability
    // checking needed — works on every browser.
    var iframe = document.createElement('iframe');
    iframe.id = 'bl_iframe';
    iframe.title = 'Sign in with BrainLock';
    iframe.src = IFRAME_URL;
    document.body.appendChild(iframe);
    requestAnimationFrame(function () { iframe.style.opacity = '1'; });

    window.addEventListener('message', function (event) {
        if (event.origin !== SAME_ORIGIN) return;
        var data = event.data || {};
        if (data.type !== 'brainlock:auth' || !data.url) return;
        window.location.href = data.url;
    });
})();
</script>
</body>
</html>
HTML;
        exit;
    }

    /** RSA public key (as openssl resource) for a given kid, fetched from JWKS. */
    private static function publicKeyFor(string $kid)
    {
        $jwks = self::fetchJwks();
        $jwk = null;
        foreach ($jwks as $candidate) {
            if (($candidate['kid'] ?? '') === $kid) {
                $jwk = $candidate;
                break;
            }
        }
        if ($jwk === null) {
            throw new \BrainLockException('BrainLock JWKS: kid "' . $kid . '" not in JWKS.');
        }
        if (($jwk['kty'] ?? '') !== 'RSA') {
            throw new \BrainLockException('BrainLock JWKS: unexpected key type for kid.');
        }
        $n = self::base64UrlDecode($jwk['n']);
        $e = self::base64UrlDecode($jwk['e']);
        $pem = self::rsaJwkToPem($n, $e);
        $key = \openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new \BrainLockException('BrainLock JWKS: could not import public key.');
        }
        return $key;
    }

    /** Fetch + cache the JWKS in static memory for JWKS_TTL seconds. */
    private static function fetchJwks(): array
    {
        if (self::$jwksCache !== null && (\time() - self::$jwksCachedAt) < self::JWKS_TTL) {
            return self::$jwksCache;
        }
        $url = self::$config['api_base'] . '/v1/.well-known/jwks.json';
        $resp = self::http('GET', $url, null, []);
        if (empty($resp['keys']) || !\is_array($resp['keys'])) {
            throw new \BrainLockException('BrainLock JWKS: endpoint returned no keys.');
        }
        self::$jwksCache = $resp['keys'];
        self::$jwksCachedAt = \time();
        return self::$jwksCache;
    }

    /**
     * Convert a JWK RSA (n, e) pair to PEM that openssl can import.
     * Builds a minimal SubjectPublicKeyInfo DER and wraps it in PEM headers.
     */
    private static function rsaJwkToPem(string $n, string $e): string
    {
        $modulus  = self::derUnsignedInteger($n);
        $exponent = self::derUnsignedInteger($e);
        $rsaKey   = self::derSequence($modulus . $exponent);
        // OID for rsaEncryption: 1.2.840.113549.1.1.1
        $rsaOid   = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01" . "\x05\x00";
        $algIdent = self::derSequence($rsaOid);
        $bitStr   = "\x03" . self::derLength(\strlen($rsaKey) + 1) . "\x00" . $rsaKey;
        $spki     = self::derSequence($algIdent . $bitStr);
        $pem      = "-----BEGIN PUBLIC KEY-----\n"
                  . \chunk_split(\base64_encode($spki), 64, "\n")
                  . "-----END PUBLIC KEY-----\n";
        return $pem;
    }

    private static function derUnsignedInteger(string $bytes): string
    {
        // ASN.1 INTEGER must be two's-complement positive; prepend 0x00 if
        // the high bit is set so it isn't read as negative.
        if (\strlen($bytes) > 0 && (\ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . self::derLength(\strlen($bytes)) . $bytes;
    }

    private static function derSequence(string $contents): string
    {
        return "\x30" . self::derLength(\strlen($contents)) . $contents;
    }

    private static function derLength(int $len): string
    {
        if ($len < 0x80) {
            return \chr($len);
        }
        $bytes = '';
        while ($len > 0) {
            $bytes = \chr($len & 0xff) . $bytes;
            $len >>= 8;
        }
        return \chr(0x80 | \strlen($bytes)) . $bytes;
    }

    private static function jsonDecodeSegment(string $segment): array
    {
        $json = self::base64UrlDecode($segment);
        $data = \json_decode($json, true);
        if (!\is_array($data)) {
            throw new \BrainLockException('BrainLock: could not JSON-decode token segment.');
        }
        return $data;
    }

    private static function base64UrlDecode(string $s): string
    {
        $s = \strtr($s, '-_', '+/');
        $pad = \strlen($s) % 4;
        if ($pad) $s .= \str_repeat('=', 4 - $pad);
        $out = \base64_decode($s, true);
        if ($out === false) {
            // Throws BrainLockException (not RuntimeException) so callers
            // wrapping verifyConnectToken/verifyActionToken in a single
            // BrainLockException catch don't have to special-case decode
            // failures. The SDK's public promise is: every token-parse
            // path throws BrainLockException on failure.
            throw new \BrainLockException('BrainLock: base64url decode failed.');
        }
        return $out;
    }

    /** Minimal curl-based JSON HTTP client. */
    private static function http(string $method, string $url, ?array $body, array $headers): array
    {
        $ch = \curl_init();
        \curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $hdrs = $headers;
        if ($body !== null) {
            \curl_setopt($ch, CURLOPT_POSTFIELDS, \json_encode($body));
            $hdrs[] = 'Content-Type: application/json';
        }
        $hdrs[] = 'User-Agent: brainlock-php/' . self::VERSION;
        \curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);

        $raw  = \curl_exec($ch);
        $err  = \curl_error($ch);
        $code = (int)\curl_getinfo($ch, CURLINFO_HTTP_CODE);
        \curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('BrainLock: HTTP ' . $method . ' ' . $url . ' failed: ' . $err);
        }
        $data = \json_decode($raw, true);
        if (!\is_array($data)) {
            throw new \RuntimeException('BrainLock: ' . $method . ' ' . $url . ' returned non-JSON (HTTP ' . $code . '): ' . \substr($raw, 0, 200));
        }
        if ($code >= 400) {
            // Return the error body so callers (currently only connect())
            // can surface the BrainLock-provided message verbatim.
            return $data;
        }
        return $data;
    }
}
