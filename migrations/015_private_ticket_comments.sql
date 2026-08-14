ALTER TABLE ticket_comments ADD COLUMN visibility ENUM('shared','assignees','departments') NOT NULL DEFAULT 'shared' AFTER body;
