ALTER TABLE users
  ADD COLUMN approval_status ENUM('approved','pending','rejected') NOT NULL DEFAULT 'approved' AFTER is_active,
  ADD KEY idx_users_approval_status (approval_status);
