ALTER TABLE api_tokens
  MODIFY token CHAR(64) NULL,
  ADD COLUMN token_hash CHAR(64) NULL AFTER token,
  ADD UNIQUE KEY uq_api_tokens_token_hash(token_hash);

UPDATE api_tokens SET token_hash = SHA2(token, 256) WHERE token IS NOT NULL AND token_hash IS NULL;
UPDATE api_tokens SET token = NULL WHERE token_hash IS NOT NULL;

ALTER TABLE api_tokens
  MODIFY token_hash CHAR(64) NOT NULL;
