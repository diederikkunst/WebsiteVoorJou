-- Migratie: 'wachtwoord vergeten' — reset-token op users
-- Draai dit eenmalig op de database die de app gebruikt (h_000b391b_wvj).
-- Bijv.: mysql -u USER -p DBNAAM < migrations/2026_07_08_password_reset.sql

ALTER TABLE users
    ADD COLUMN password_reset_token VARCHAR(64) NULL AFTER email_verification_sent_at,
    ADD COLUMN password_reset_expires DATETIME NULL AFTER password_reset_token;
