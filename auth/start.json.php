<?php
/**
 * Sign-in entry point — JSON variant.
 *
 * Returns the BrainLock auth URL as JSON so the client-side popup
 * launcher (in js/tangocash.js) can redirect an already-open popup
 * window to it. The reason this exists alongside /auth/start.php:
 * browser popup blockers fire when window.open() is called from JS
 * that ran AFTER navigation. The robust pattern is:
 *
 *   1. User clicks Sign In on /
 *   2. JS opens popup synchronously (consumes the user gesture)
 *   3. JS fetches this endpoint
 *   4. JS sets popup.location.href = response.url
 *
 * That way window.open never blocks — the popup is born during the click.
 */
require __DIR__ . '/../_bootstrap.php';

\header('Content-Type: application/json');
\header('Cache-Control: no-store');

try {
    $session = \BrainLock::startSession([
        'user_id' => \session_id(),
    ]);
    echo \json_encode($session);
} catch (\Throwable $e) {
    \http_response_code(500);
    echo \json_encode(['error' => $e->getMessage()]);
}
