-- 0005_rekey_to_pairwise_subject — move TangoCash identity from cookie-first
-- to identity-first (BrainLock's stable pairwise-per-app subject).
--
-- Background: tc_users.bl_sub used to hold the JWT `sub`, which was the
-- tc_user_id COOKIE echoed back — so identity was pinned to a per-browser
-- cookie, and migration 0002 pinned `email` as a UNIQUE backstop. BrainLock
-- now mints `sub` as a stable, opaque, pairwise-per-(app,vault) subject
-- ("blsub_…", 46 chars). callback.php now reconciles on bl_sub = that subject.
--
-- Two consequences for the schema:
--
--   1. The stored bl_sub values are the OLD cookie-derived ones; every user's
--      subject is a brand-new value on their next sign-in. Demo data is
--      disposable, so we TRUNCATE the whole graph and let everyone re-onboard
--      (wallets re-seed to $500 via the existing INSERT IGNORE). This avoids a
--      fragile dual-key backfill of an unmappable old→new subject.
--
--   2. `uniq_email` (added in 0002) MUST be dropped. Under identity-first two
--      different vaults may legitimately share an email (e.g. family), each
--      with its own subject → its own row. Leaving uniq_email would throw a
--      duplicate-key error on the second vault's INSERT and re-break the
--      "two accounts, no wall" guarantee at the DB layer. We revert to a
--      plain non-unique index for lookups.
--
-- bl_sub stays VARCHAR(64) PK — the 46-char "blsub_…" subject fits.

-- 1. Truncate the FK graph (children first is unnecessary with the guard,
--    but we disable checks to TRUNCATE parents cleanly).
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE tc_contacts;
TRUNCATE TABLE tc_transactions;
TRUNCATE TABLE tc_wallets;
TRUNCATE TABLE tc_users;
SET FOREIGN_KEY_CHECKS = 1;

-- 2. Email is no longer the identity — drop its UNIQUE constraint. The plain
--    lookup index `idx_email` (created in 0001 for tc_lookup_user) survives
--    the drop, so we do NOT re-add it here (that would be a duplicate-key
--    error on any install that ran 0001).
ALTER TABLE tc_users DROP INDEX uniq_email;
