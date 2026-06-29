-- Migratie: SEO-hulp per project
-- Draai dit eenmalig op de bestaande database.
-- Bijv.: mysql -u USER -p DBNAAM < migrations/2026_06_28_project_seo.sql

CREATE TABLE IF NOT EXISTS project_seo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL UNIQUE,
    focus_keyword VARCHAR(255) NOT NULL DEFAULT '',
    extra_keywords VARCHAR(500) NOT NULL DEFAULT '',
    meta_title VARCHAR(255) NOT NULL DEFAULT '',
    meta_description VARCHAR(500) NOT NULL DEFAULT '',
    target_audience VARCHAR(500) NOT NULL DEFAULT '',
    notes TEXT,
    checklist TEXT,            -- JSON: aangevinkte checklist-items
    scan_score INT NULL,
    scan_results TEXT,         -- JSON: resultaat van de laatste website-scan
    scanned_url VARCHAR(255) NOT NULL DEFAULT '',
    scanned_at DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
