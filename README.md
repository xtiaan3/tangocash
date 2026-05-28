# TangoCash

A reference fintech demo for the [BrainLock](https://brainlock.id) identity platform.

TangoCash is a fictional peer-to-peer wallet built in PHP. It exists to demonstrate how a partner site integrates "Sign in with BrainLock" end-to-end — from the sign-in popup to the JWT verification on your server.

If you're a developer evaluating BrainLock for your own application, **this is the canonical reference**. Clone it, run it locally, study how it's wired.

> **Status: under construction.** This repo is the reference site for BrainLock Connect. Things will move and break until the `v1.0` tag.

## What it does

- "Sign in with BrainLock" — popup flow, no leaving the page
- Peer-to-peer wallet: send money, request money, see balance and recent activity
- Profile page showing the identity returned from BrainLock (name + email + picture)

## Stack

- PHP 8.3
- Postgres
- nginx
- [`brainlock-php`](https://github.com/xtiaan3/brainlock-php) (the official PHP SDK)

## Running locally

Instructions coming with `v1.0`.

## License

MIT — see [LICENSE](LICENSE).
