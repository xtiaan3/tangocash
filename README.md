# TangoCash

A reference fintech demo for the [BrainLock](https://brainlock.id) identity platform.

TangoCash is a fictional peer-to-peer wallet built in PHP. It exists to demonstrate how a partner site integrates BrainLock Connect (sign-in) and BrainLock Verify (per-action approval) end-to-end — from the redirect handoff to JWT verification on your server.

If you're a developer evaluating BrainLock for your own application, **this is the canonical reference**. Clone it, run it locally, study how it's wired.

## What it does

- **Connect** — "Sign in with BrainLock" identity handoff. One redirect, one JWT, one signed-in user.
- **Verify** — per-action approval ceremony for high-stakes actions (the Send Money flow on `/dashboard` is the worked example). Consent panel + memory-challenge ceremony + cryptographically signed receipt.
- Profile page showing the identity returned from BrainLock (name + email + picture).
- Receipt page showing the full BrainLock Verify audit-log payload.

## Stack

- PHP 8.3
- Postgres
- nginx
- [`brainlock-php`](https://github.com/xtiaan3/brainlock-php) (the official PHP SDK)

## License

MIT — see [LICENSE](LICENSE).
