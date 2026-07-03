-- 0001_initial — TangoCash MySQL schema.
--
-- Architecturally: TangoCash is a frontend that consumes BrainLock
-- Connect identities. Every TangoCash user is keyed on the JWT 'sub'
-- (the BrainLock subject identifier) which is stable per BrainLock vault
-- for the life of the account. We never invent our own user IDs — sub IS
-- the user ID. This guarantees magic-flash re-auth + cross-device login
-- map to the same row without any reconciliation work.
--
-- bl_user_id is the long-lived cookie UUID we send to BrainLock as the
-- partner-side `user_id` (what app_user_bindings keys on). It's recorded
-- here for traceability but not load-bearing — once a row exists with a
-- given bl_sub, that's the user forever.
--
-- Apply with:
--   mysql -h127.0.0.1 -P3306 -u tangocash -p tangocash < 0001_initial.sql

-- ------------------------------------------------------------
-- tc_users — profile + signin telemetry.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tc_users (
    bl_sub          VARCHAR(64)   PRIMARY KEY,
    bl_user_id      VARCHAR(64)   NOT NULL,
    name            VARCHAR(200)  NOT NULL,
    email           VARCHAR(320)  NOT NULL,
    picture_url     TEXT          NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_signin_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    signin_count    INT           NOT NULL DEFAULT 1,
    UNIQUE KEY uniq_bl_user_id (bl_user_id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- tc_wallets — one row per user, balance in cents (no floats).
-- Seeded with $500 on user creation by the upsert in callback.php.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tc_wallets (
    bl_sub          VARCHAR(64)   PRIMARY KEY,
    balance_cents   BIGINT        NOT NULL DEFAULT 50000,
    currency        CHAR(3)       NOT NULL DEFAULT 'USD',
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wallets_user
        FOREIGN KEY (bl_sub) REFERENCES tc_users(bl_sub) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- tc_transactions — send + request events between users.
--
-- Status lifecycle:
--   kind='send'    : status='completed' immediately on insert.
--   kind='request' : status='pending' on insert, → 'completed' when
--                    the recipient pays, → 'declined' if they refuse,
--                    → 'cancelled' if the sender retracts.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tc_transactions (
    id              BIGINT        PRIMARY KEY AUTO_INCREMENT,
    from_sub        VARCHAR(64)   NOT NULL,
    to_sub          VARCHAR(64)   NOT NULL,
    amount_cents    BIGINT        NOT NULL,
    kind            ENUM('send','request') NOT NULL,
    status          ENUM('pending','completed','declined','cancelled') NOT NULL DEFAULT 'completed',
    memo            VARCHAR(280)  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at    DATETIME      NULL,
    INDEX idx_from_time (from_sub, created_at DESC),
    INDEX idx_to_time   (to_sub,   created_at DESC),
    INDEX idx_status    (status),
    CONSTRAINT fk_tx_from FOREIGN KEY (from_sub) REFERENCES tc_users(bl_sub) ON DELETE CASCADE,
    CONSTRAINT fk_tx_to   FOREIGN KEY (to_sub)   REFERENCES tc_users(bl_sub) ON DELETE CASCADE,
    CONSTRAINT chk_amount_positive CHECK (amount_cents > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- tc_contacts — people you've transacted with. Populated implicitly
-- on every send/request so the send + request forms can offer
-- recent-contacts auto-complete without a separate "add contact" step.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tc_contacts (
    bl_sub          VARCHAR(64)   NOT NULL,
    contact_sub     VARCHAR(64)   NOT NULL,
    first_seen_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (bl_sub, contact_sub),
    CONSTRAINT fk_contacts_owner   FOREIGN KEY (bl_sub)      REFERENCES tc_users(bl_sub) ON DELETE CASCADE,
    CONSTRAINT fk_contacts_contact FOREIGN KEY (contact_sub) REFERENCES tc_users(bl_sub) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
