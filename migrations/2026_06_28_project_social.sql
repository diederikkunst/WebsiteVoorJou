-- Migratie: Social media post-hulp per project (merkstem-instellingen)
-- Draai dit eenmalig op de bestaande database.
-- Bijv.: mysql -u USER -p DBNAAM < migrations/2026_06_28_project_social.sql

CREATE TABLE IF NOT EXISTS project_social (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL UNIQUE,
    business VARCHAR(500) NOT NULL DEFAULT '',     -- wat het bedrijf doet / aanbod
    audience VARCHAR(500) NOT NULL DEFAULT '',     -- doelgroep
    voice TEXT,                                    -- tone of voice
    pillars TEXT,                                  -- contentpijlers / thema's
    example_post TEXT,                             -- voorbeeldpost (referentiestem)
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
