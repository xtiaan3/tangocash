<?php
/**
 * TangoCash bootstrap. Every page requires this once at the top — it
 * starts the PHP session and loads the BrainLock SDK with our app
 * config.
 *
 * Why session_start happens here (and not in _header.php): pages call
 * tc_current_user() before any HTML is sent, so the session must be
 * live before we know whether to render signed-in vs signed-out
 * chrome.
 */

if (\session_status() !== \PHP_SESSION_ACTIVE) {
    \session_name('tc_session');
    \session_set_cookie_params([
        'lifetime' => 0,         // session cookie — dies with the browser
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    \session_start();
}

require_once __DIR__ . '/lib/BrainLock.php';
require_once __DIR__ . '/_avatars.php';

\BrainLock::configure([
    'api_key'  => \getenv('BRAINLOCK_API_KEY')  ?: ($_ENV['BRAINLOCK_API_KEY']  ?? ''),
    'api_base' => \getenv('BRAINLOCK_API_BASE') ?: ($_ENV['BRAINLOCK_API_BASE'] ?? 'https://brainlock.id'),
    'mode'     => 'redirect',
]);
// callback_url used to live here — retired 2026-07-03. BrainLock now
// looks up the app's registered callback server-side via the API key.
// One source of truth: brainlock.id/developer.

// Disconnect-webhook enforcement. When BrainLock fires /auth/disconnect
// for a user it sets tc_users.force_signout_at to NOW(). Every request
// from a signed-in session checks whether that timestamp is newer than
// the moment THIS session was issued at (bl_signed_in_at); if so, the
// session is forcibly destroyed and the user is dropped on the
// signed-out homepage. Effect: a Remove on brainlock.id/connections
// kicks the user out of TangoCash on EVERY device, not just the
// browser they clicked Remove from.
//
// Cheap check: one indexed lookup keyed by bl_sub. Skipped for visitors
// who aren't signed in and for the webhook handler itself (which sets
// the flag and would otherwise loop on it).
if (!empty($_SESSION['bl_user']['sub'])
    && (($_SERVER['SCRIPT_NAME'] ?? '') !== '/auth/disconnect.php')) {
    try {
        // Pull as a unix timestamp so we sidestep any PHP-vs-MySQL
        // timezone interpretation entirely. Both sides of the
        // comparison are then unix-seconds from PHP's perspective.
        $stmt = \tc_db()->prepare('SELECT UNIX_TIMESTAMP(force_signout_at) AS force_unix FROM tc_users WHERE bl_sub = ? LIMIT 1');
        $stmt->execute([$_SESSION['bl_user']['sub']]);
        $row = $stmt->fetch();
        if ($row && !empty($row['force_unix'])) {
            $forceAt   = (int) $row['force_unix'];
            $signedAt  = (int) ($_SESSION['bl_signed_in_at'] ?? 0);
            if ($forceAt > $signedAt) {
                \tc_sign_out();
                // Redirect to home so the next page render uses the
                // signed-out chrome. Skipped for XHR/JSON requests so
                // they get a clean 401 instead of an HTML body.
                $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
                if (\strpos($accept, 'application/json') !== false) {
                    \http_response_code(401);
                    \header('Content-Type: application/json');
                    echo \json_encode(['error' => 'signed_out_remotely']);
                } else {
                    \header('Location: /?signed_out_remotely=1');
                }
                exit;
            }
        }
    } catch (\Throwable $e) {
        \error_log('[tangocash bootstrap] force_signout_at check failed: ' . $e->getMessage());
        // Fail-open: a transient DB error shouldn't lock everyone out.
    }
}

/**
 * Returns the currently signed-in user (BrainLock identity) or null.
 * Shape matches BrainLock::verify() output:
 *   ['sub', 'name', 'email', 'picture'?, 'verified', 'biometric_used', ...]
 */
function tc_current_user(): ?array {
    if (!empty($_SESSION['bl_user']) && \is_array($_SESSION['bl_user'])) {
        return $_SESSION['bl_user'];
    }
    return null;
}

/** Sign-out by destroying the session. */
function tc_sign_out(): void {
    $_SESSION = [];
    if (\session_status() === \PHP_SESSION_ACTIVE) {
        \session_destroy();
    }
    \setcookie('tc_session', '', [
        'expires'  => 1,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Lazy PDO singleton for the tc_* tables. Env vars (TC_DB_*) live in the
 * PHP-FPM pool config so they're never in source control. Throws PDOException
 * on connect failure — callers should let that bubble in dev, or catch it
 * and render an "unavailable" page in production.
 */
function tc_db(): \PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = \getenv('TC_DB_HOST') ?: '127.0.0.1';
    $port = \getenv('TC_DB_PORT') ?: '3306';
    $name = \getenv('TC_DB_NAME') ?: 'tangocash';
    $user = \getenv('TC_DB_USER') ?: 'tangocash';
    $pass = \getenv('TC_DB_PASS') ?: '';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new \PDO($dsn, $user, $pass, [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES   => false,
        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);
    return $pdo;
}

/**
 * tc_get_user — fetch a tc_users row by its bl_sub (= the BrainLock JWT
 * subject identifier). Returns null if the user hasn't been created yet
 * (first signin still pending).
 */
function tc_get_user(string $blSub): ?array {
    $stmt = tc_db()->prepare('SELECT * FROM tc_users WHERE bl_sub = ? LIMIT 1');
    $stmt->execute([$blSub]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * tc_get_wallet — fetch the wallet for a user. Auto-creates with the
 * seed balance if missing (defensive — every tc_users row should already
 * have a wallet created at signin, but this prevents NULL-balance bugs
 * if a row was inserted out of band).
 */
function tc_get_wallet(string $blSub): array {
    $stmt = tc_db()->prepare('SELECT * FROM tc_wallets WHERE bl_sub = ? LIMIT 1');
    $stmt->execute([$blSub]);
    $row = $stmt->fetch();
    if ($row) return $row;
    tc_db()->prepare('INSERT INTO tc_wallets (bl_sub) VALUES (?)')->execute([$blSub]);
    $stmt->execute([$blSub]);
    return $stmt->fetch();
}

/**
 * tc_lookup_user — resolves a recipient input string (typed into a send
 * or request form) to a tc_users row. Accepts:
 *   - "tim.apple@example.com"  → exact email match
 *   - "@tim_apple"             → email local-part match (stripped leading @)
 *   - "tim_apple"              → same
 * Returns null if nothing matches.
 */
function tc_lookup_user(string $input): ?array {
    $input = \trim($input);
    if ($input === '' || \strlen($input) > 320) return null;
    if ($input[0] === '@') $input = \substr($input, 1);
    if (\strpos($input, '@') !== false) {
        $stmt = tc_db()->prepare('SELECT * FROM tc_users WHERE email = ? LIMIT 1');
        $stmt->execute([$input]);
    } else {
        $stmt = tc_db()->prepare('SELECT * FROM tc_users WHERE email LIKE ? ORDER BY signin_count DESC LIMIT 1');
        $stmt->execute([$input . '@%']);
    }
    return $stmt->fetch() ?: null;
}

/**
 * tc_parse_amount — parse a user-entered amount string into cents.
 * Accepts "$12.50", "12.50", "12", "1,234.56". Returns null on invalid
 * input. Negative or zero amounts are rejected.
 */
function tc_parse_amount(string $input): ?int {
    $stripped = \trim(\str_replace(['$', ',', ' '], '', $input));
    if (!\preg_match('/^\d+(\.\d{1,2})?$/', $stripped)) return null;
    $cents = (int) \round(\floatval($stripped) * 100);
    return $cents > 0 ? $cents : null;
}

/**
 * tc_handle_for — generates a display @handle for a user from their email
 * local-part. Lower-cased, used in activity feed and profile UI. Returns
 * "user" as a defensive fallback for malformed emails.
 */
function tc_handle_for(array $userRow): string {
    $email = (string)($userRow['email'] ?? '');
    $localPart = \strstr($email, '@', true);
    if ($localPart === false || $localPart === '') return 'user';
    return \strtolower($localPart);
}

/**
 * tc_delete_user — wipes a user's entire TangoCash footprint:
 *   - tc_users row (FK CASCADE handles tc_wallets, tc_transactions
 *     where they're sender or recipient, and tc_contacts).
 *   - both bucket objects in tangocash-avatars (best-effort).
 *
 * Lookup precedence is email-first → bl_sub fallback. Why: bl_sub is the
 * JWT subject and rotates whenever the user resets cookies (the partner
 * developer_user_id changes), but email is stable. After migration 0002
 * the canonical key is email; bl_sub is now just a stored attribute,
 * not the identity. Resetting from a fresh-cookie session against an
 * old row would never match by bl_sub.
 *
 * Used by the dev_action surface in index.php. Idempotent — safe to
 * call when nothing matches (no-op). Errors are logged, never thrown,
 * because the signout handler shouldn't blow up if the DB wipe fails.
 */
function tc_delete_user(string $blSub, string $emailAddr = ''): void {
    if ($blSub === '' && $emailAddr === '') return;

    // Resolve to the canonical row (and its stored bl_sub) BEFORE we
    // delete anything. Prefer email — see header comment.
    $rowSub = '';
    try {
        if ($emailAddr !== '') {
            $stmt = \tc_db()->prepare('SELECT bl_sub FROM tc_users WHERE email = ? LIMIT 1');
            $stmt->execute([$emailAddr]);
            $rowSub = (string)($stmt->fetchColumn() ?: '');
        }
        if ($rowSub === '' && $blSub !== '') {
            // No email or no match — fall back to whatever sub the session had.
            $stmt = \tc_db()->prepare('SELECT bl_sub FROM tc_users WHERE bl_sub = ? LIMIT 1');
            $stmt->execute([$blSub]);
            $rowSub = (string)($stmt->fetchColumn() ?: '');
        }
    } catch (\Throwable $e) {
        \error_log('[tangocash] tc_delete_user lookup failed: ' . $e->getMessage());
    }

    // The bucket key is sha1(bl_sub_at_time_of_upload). The session sub
    // may have rotated since upload, so always nuke based on the bl_sub
    // we actually stored in the DB row. If nothing resolved, skip the
    // bucket call — anything we'd delete would be guesswork.
    if ($rowSub !== '') {
        try { tc_delete_avatars($rowSub); }
        catch (\Throwable $e) { \error_log('[tangocash] tc_delete_avatars failed: ' . $e->getMessage()); }
    }

    try {
        if ($emailAddr !== '') {
            \tc_db()->prepare('DELETE FROM tc_users WHERE email = ?')->execute([$emailAddr]);
        } elseif ($blSub !== '') {
            \tc_db()->prepare('DELETE FROM tc_users WHERE bl_sub = ?')->execute([$blSub]);
        }
    } catch (\Throwable $e) {
        \error_log('[tangocash] tc_delete_user failed: ' . $e->getMessage());
    }
}
