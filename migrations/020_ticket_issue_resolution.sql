ALTER TABLE tickets
  ADD COLUMN issue TEXT NULL AFTER subject,
  ADD COLUMN resolution TEXT NULL AFTER description;
