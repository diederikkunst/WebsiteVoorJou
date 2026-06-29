-- Migratie: afbeelding-URL bij social posts (nodig voor Instagram, optioneel voor Facebook)
-- Draai dit eenmalig op de bestaande database.
-- Bijv.: mysql -u USER -p DBNAAM < migrations/2026_06_28_social_posts_image.sql

ALTER TABLE project_social_posts
    ADD COLUMN image_url VARCHAR(500) NOT NULL DEFAULT '' AFTER content;
