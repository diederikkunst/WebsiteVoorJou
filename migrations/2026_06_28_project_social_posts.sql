-- Migratie: opgeslagen / ingeplande social media posts per project
-- Draai dit eenmalig op de bestaande database.
-- Bijv.: mysql -u USER -p DBNAAM < migrations/2026_06_28_project_social_posts.sql

CREATE TABLE IF NOT EXISTS project_social_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    platform VARCHAR(30) NOT NULL,
    content TEXT NOT NULL,
    status ENUM('concept','ingepland','geplaatst','mislukt') NOT NULL DEFAULT 'concept',
    scheduled_at DATETIME NULL,
    posted_at DATETIME NULL,
    result_msg VARCHAR(255) NOT NULL DEFAULT '',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_due (status, scheduled_at),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
