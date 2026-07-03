-- 0003_split_name_into_first_last
--
-- BrainLock's Connect identity bundle now returns `first_name` and
-- `last_name` as separate fields (decided 2026-06-01). TangoCash should
-- store them separately so display code can render either alone or
-- concatenate as it wishes — and so a future "edit profile" surface
-- can edit them independently without splitting strings.
--
-- The legacy `name` column is kept (populated as first + last joined)
-- so any historic queries don't break in this single migration; a
-- follow-up can drop it once nothing reads it.
--
-- Apply with:
--   mysql -h127.0.0.1 -P3306 -u tangocash -p tangocash < 0003_split_name_into_first_last.sql

ALTER TABLE tc_users
    ADD COLUMN first_name VARCHAR(120) DEFAULT NULL AFTER bl_user_id,
    ADD COLUMN last_name  VARCHAR(120) DEFAULT NULL AFTER first_name;

-- Best-effort backfill of existing rows: split the legacy `name` on the
-- first space. Anything more nuanced (multi-word given names, hyphenated
-- surnames, single-word names) is left to the user to correct via the
-- future profile editor. Single-word names land in first_name only.
UPDATE tc_users
   SET first_name = SUBSTRING_INDEX(name, ' ', 1),
       last_name  = TRIM(SUBSTRING(name, LENGTH(SUBSTRING_INDEX(name, ' ', 1)) + 1))
 WHERE name IS NOT NULL AND name <> '';
