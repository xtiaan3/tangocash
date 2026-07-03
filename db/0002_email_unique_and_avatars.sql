-- 0002_email_unique_and_avatars
--
-- Two shape changes to tc_users to support the one-shot avatar handoff
-- model (see brainlock-go/docs/AVATAR_HANDOFF.md when written):
--
--   1. email becomes the canonical uniqueness key. The old assumption
--      was that bl_sub ↔ user is 1:1 for life, but the JWT sub claim is
--      actually scoped to (app, developer_user_id) — and developer_user_id
--      is the tc_user_id cookie value, which rotates every time the user
--      runs ?dev_action=reset_all (or simply clears cookies). Each
--      rotation appears to BrainLock as a "new partner-user", so the
--      same human ended up with multiple tc_users rows. Pinning email
--      as the stable identifier mirrors how a normal B2C app would
--      dedupe new signups.
--
--   2. picture_url (the hot-link to brain-lock.sfo3.digitaloceanspaces.com)
--      is being replaced by two TC-owned columns: picture_full_url and
--      picture_thumb_url, both pointing into TC's own DO Spaces bucket
--      (tangocash-avatars). callback.php copies the avatar from
--      BrainLock's presigned URL on first signin only, never again. See
--      tc_cache_avatar() in _bootstrap.php.
--
-- Apply with:
--   mysql -h127.0.0.1 -P3306 -u tangocash -p tangocash < 0002_email_unique_and_avatars.sql

-- ------------------------------------------------------------
-- Dedupe existing rows. Keep the one row per email that has the most
-- recent last_signin_at; delete the rest. Cascades to wallets,
-- transactions, contacts via the existing FK ON DELETE CASCADE.
-- ------------------------------------------------------------
DELETE u FROM tc_users u
  JOIN (
    SELECT email, MAX(last_signin_at) AS keep_at
    FROM tc_users
    GROUP BY email
    HAVING COUNT(*) > 1
  ) k ON u.email = k.email AND u.last_signin_at < k.keep_at;

-- Edge case: ties on last_signin_at within the same email — pick the
-- row with the lexicographically smallest bl_sub among the tied set.
DELETE u FROM tc_users u
  JOIN (
    SELECT email, MIN(bl_sub) AS keep_sub
    FROM tc_users
    GROUP BY email
    HAVING COUNT(*) > 1
  ) k ON u.email = k.email AND u.bl_sub <> k.keep_sub;

-- ------------------------------------------------------------
-- Add the email uniqueness constraint. The PK stays on bl_sub for now —
-- email-as-PK would force a wide-row rewrite and break the existing FKs
-- in tc_wallets / tc_transactions / tc_contacts. A UNIQUE KEY on email
-- gives us the same dedupe-on-upsert semantics without that.
-- ------------------------------------------------------------
ALTER TABLE tc_users
    ADD UNIQUE KEY uniq_email (email);

-- ------------------------------------------------------------
-- Avatar columns. Both URLs point into tangocash-avatars on DO Spaces
-- under a per-user prefix derived from sha1(bl_sub) so the keys carry
-- no PII. picture_url (the legacy BrainLock hot-link column) is kept
-- for one migration cycle so we can prove the cutover before dropping.
-- ------------------------------------------------------------
ALTER TABLE tc_users
    ADD COLUMN picture_full_url  VARCHAR(512) DEFAULT NULL AFTER picture_url,
    ADD COLUMN picture_thumb_url VARCHAR(512) DEFAULT NULL AFTER picture_full_url;
