ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal' AFTER status;
