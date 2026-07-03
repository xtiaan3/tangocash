<?php
/**
 * terms.php — TangoCash Terms of Service (demo).
 *
 * Short intentionally. TangoCash is a reference integration for
 * "Sign in with BrainLock" — no financial services are actually
 * being provided. This page is honest about that and points to
 * BrainLock's real Terms of Service for the substantive terms.
 *
 * Reachable at /terms via the nginx rewrite in the site conf.
 */
require __DIR__ . '/_demo_data.php';
$page_title = 'Terms of Service — TangoCash';
require __DIR__ . '/_header.php';
?>

<main class="tc_legal_main">

  <section class="tc_legal_hero">
    <h1 class="tc_legal_h1">Terms of Service</h1>
    <p class="tc_legal_updated">Last updated: <?= date('F j, Y') ?></p>
  </section>

  <section class="tc_legal_body">

    <div class="tc_legal_note">
      <strong>Heads up — TangoCash is a demo.</strong> This site is the reference integration
      for <a href="https://brainlock.id" target="_blank" rel="noopener">BrainLock Connect</a>
      &mdash; a working example of "Sign in with BrainLock." It looks like a payments
      app because the flow needs realistic stakes to be worth showing, but no money
      moves. No accounts are created, no funds are transferred, no obligations are
      incurred. Because TangoCash isn't a real service, our terms are largely
      BrainLock's terms — read those for the substantive language.
    </div>

    <h2>What TangoCash actually does</h2>
    <p>
      TangoCash exists to demonstrate BrainLock Connect (identity sign-in) and
      BrainLock Verify (per-action confirmation) working end-to-end in a realistic
      partner app. You can sign in, view a dashboard, initiate a "send" that
      triggers a Verify ceremony, and see receipts. All monetary values are
      simulated. There is no wallet, no ledger, no counterparty.
    </p>

    <h2>Acceptable use</h2>
    <p>
      Use the site for exploring BrainLock's integration surface — its sign-in
      flow, its Verify flow, its callback contract. Don't attempt to exploit it,
      scrape it wholesale, or use it to attack BrainLock itself. Standard "don't
      be a jerk" clause; this is a demo, not a sandbox for adversarial testing.
    </p>

    <h2>No warranties</h2>
    <p>
      TangoCash is provided as-is with no warranty of any kind. It may be
      unavailable, incomplete, or occasionally broken — that's the nature of a
      running demo. Don't rely on it for anything real; anything TangoCash tells
      you about a transfer is illustrative only.
    </p>

    <h2>No financial services</h2>
    <p>
      TangoCash is not a money transmitter, not a payment processor, not a bank,
      not a broker-dealer, not a wallet, not an exchange. It does not hold, move,
      or represent any actual value. Any regulatory framework covering real
      financial services does not apply to this demo.
    </p>

    <h2>Termination</h2>
    <p>
      Either party may end the "relationship" at any time — you by clicking
      Sign out and delete demo account (top-right menu), us by taking the site
      down. Neither side owes the other anything.
    </p>

    <h2>BrainLock's full terms</h2>
    <p>
      The substantive obligations — around identity, authentication, dispute
      resolution, governing law — are BrainLock's:
      <a href="https://brainlock.id/terms-of-service" target="_blank" rel="noopener">brainlock.id/terms-of-service</a>.
    </p>

    <h2>Contact</h2>
    <p>
      Questions about TangoCash as a demo:
      <a href="mailto:hello@brainlock.id">hello@brainlock.id</a>.
    </p>

  </section>

</main>

<?php require __DIR__ . '/_footer.php'; ?>
