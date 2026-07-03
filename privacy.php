<?php
/**
 * privacy.php — TangoCash Privacy Policy (demo).
 *
 * Short intentionally. TangoCash is a reference integration for
 * "Sign in with BrainLock" — it doesn't have its own user database
 * of any operational significance. What identity data exists flows
 * through BrainLock's policies. This page is honest about that and
 * links to BrainLock's real Privacy Policy for the substantive terms.
 *
 * Reachable at /privacy via the nginx rewrite in the site conf.
 */
require __DIR__ . '/_demo_data.php';
$page_title = 'Privacy Policy — TangoCash';
require __DIR__ . '/_header.php';
?>

<main class="tc_legal_main">

  <section class="tc_legal_hero">
    <h1 class="tc_legal_h1">Privacy Policy</h1>
    <p class="tc_legal_updated">Last updated: <?= date('F j, Y') ?></p>
  </section>

  <section class="tc_legal_body">

    <div class="tc_legal_note">
      <strong>Heads up — TangoCash is a demo.</strong> This site is the reference integration
      for <a href="https://brainlock.id" target="_blank" rel="noopener">BrainLock Connect</a>.
      It's here so developers can see the sign-in flow end-to-end; it's not
      a real financial product. Because identity + PII are handled by BrainLock,
      not TangoCash, our Privacy Policy is essentially BrainLock's Privacy Policy.
    </div>

    <h2>What we collect</h2>
    <p>
      When you sign in with BrainLock we receive three fields from your BrainLock
      account: your <strong>name</strong>, your <strong>email</strong>, and (if you've
      uploaded one) your <strong>profile picture</strong>. That's it. TangoCash does not
      collect payment card numbers, real bank routing information, government-issued
      IDs, or any other sensitive data — the site is a demo, no money moves.
    </p>

    <h2>Where the data lives</h2>
    <p>
      TangoCash stores a minimal row per signed-in user (email, name, avatar URL,
      last-signin timestamp, a stable per-app user identifier). Everything else —
      the identity itself, your PIN, your memory challenges, your biometric
      credentials — lives on BrainLock and never touches TangoCash servers.
    </p>

    <h2>Who we share it with</h2>
    <p>
      Nobody. TangoCash doesn't sell, transfer, syndicate, or license your data.
      No analytics beacons, no advertising SDKs, no third-party trackers.
    </p>

    <h2>How to remove your data</h2>
    <p>
      From the top-right menu, <strong>Sign out and delete demo account</strong>
      wipes the TangoCash row for your account. To also remove your BrainLock
      identity, use the Delete Account flow on
      <a href="https://brainlock.id" target="_blank" rel="noopener">brainlock.id</a>.
    </p>

    <h2>BrainLock's full policy</h2>
    <p>
      The substantive privacy terms — encryption, retention, access-control, breach
      notification, jurisdiction — are BrainLock's:
      <a href="https://brainlock.id/privacy-policy" target="_blank" rel="noopener">brainlock.id/privacy-policy</a>.
    </p>

    <h2>Contact</h2>
    <p>
      Questions about TangoCash as a demo:
      <a href="mailto:hello@brainlock.id">hello@brainlock.id</a>.
    </p>

  </section>

</main>

<?php require __DIR__ . '/_footer.php'; ?>
