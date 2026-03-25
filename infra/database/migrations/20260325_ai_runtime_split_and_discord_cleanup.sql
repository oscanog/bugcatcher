SET @has_ai_runtime_config = (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ai_runtime_config'
);
SET @sql = IF(
  @has_ai_runtime_config = 0,
  "CREATE TABLE ai_runtime_config (
      id INT(11) NOT NULL AUTO_INCREMENT,
      is_enabled TINYINT(1) NOT NULL DEFAULT 1,
      default_provider_config_id INT(11) DEFAULT NULL,
      default_model_id INT(11) DEFAULT NULL,
      assistant_name VARCHAR(120) DEFAULT NULL,
      system_prompt TEXT DEFAULT NULL,
      created_by INT(11) NOT NULL,
      updated_by INT(11) DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT NULL,
      PRIMARY KEY (id),
      KEY idx_ai_runtime_config_created_by (created_by),
      KEY idx_ai_runtime_config_updated_by (updated_by),
      KEY idx_ai_runtime_config_provider (default_provider_config_id),
      KEY idx_ai_runtime_config_model (default_model_id),
      CONSTRAINT fk_ai_runtime_config_created_by
        FOREIGN KEY (created_by) REFERENCES users(id),
      CONSTRAINT fk_ai_runtime_config_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON DELETE SET NULL,
      CONSTRAINT fk_ai_runtime_config_provider
        FOREIGN KEY (default_provider_config_id) REFERENCES ai_provider_configs(id)
        ON DELETE SET NULL,
      CONSTRAINT fk_ai_runtime_config_model
        FOREIGN KEY (default_model_id) REFERENCES ai_models(id)
        ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_openclaw_runtime = (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'openclaw_runtime_config'
);
SET @has_openclaw_ai_columns = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'openclaw_runtime_config'
    AND COLUMN_NAME IN (
      'ai_chat_enabled',
      'ai_chat_default_provider_config_id',
      'ai_chat_default_model_id',
      'ai_chat_assistant_name',
      'ai_chat_system_prompt'
    )
);
SET @ai_runtime_empty = (
  SELECT COUNT(*)
  FROM ai_runtime_config
);
SET @sql = IF(
  @has_openclaw_runtime = 1 AND @has_openclaw_ai_columns = 5 AND @ai_runtime_empty = 0,
  "SELECT 1",
  IF(
    @has_openclaw_runtime = 1 AND @has_openclaw_ai_columns = 5,
    "INSERT INTO ai_runtime_config
        (
          is_enabled,
          default_provider_config_id,
          default_model_id,
          assistant_name,
          system_prompt,
          created_by,
          updated_by,
          created_at,
          updated_at
        )
      SELECT ai_chat_enabled,
             ai_chat_default_provider_config_id,
             ai_chat_default_model_id,
             ai_chat_assistant_name,
             ai_chat_system_prompt,
             created_by,
             updated_by,
             created_at,
             updated_at
      FROM openclaw_runtime_config
      ORDER BY id DESC
      LIMIT 1",
    "SELECT 1"
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_openclaw_requests_fk = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'openclaw_requests'
    AND CONSTRAINT_NAME = 'fk_openclaw_requests_discord_user_link'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql = IF(
  @has_openclaw_requests_fk = 1,
  "ALTER TABLE openclaw_requests DROP FOREIGN KEY fk_openclaw_requests_discord_user_link",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_openclaw_requests_link_column = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'openclaw_requests'
    AND COLUMN_NAME = 'discord_user_link_id'
);
SET @sql = IF(
  @has_openclaw_requests_link_column = 1,
  "ALTER TABLE openclaw_requests MODIFY COLUMN discord_user_link_id INT(11) DEFAULT NULL",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_openclaw_discord_token = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'openclaw_runtime_config'
    AND COLUMN_NAME = 'encrypted_discord_bot_token'
);
SET @sql = IF(
  @has_openclaw_discord_token = 1,
  "ALTER TABLE openclaw_runtime_config DROP COLUMN encrypted_discord_bot_token",
  "SELECT 1"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_column = 'ai_chat_enabled';
SET @has_column = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'openclaw_runtime_config'
    AND COLUMN_NAME = @drop_column
);
SET @sql = IF(@has_column = 1, CONCAT('ALTER TABLE openclaw_runtime_config DROP COLUMN ', @drop_column), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_column = 'ai_chat_default_provider_config_id';
SET @has_column = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'openclaw_runtime_config'
    AND COLUMN_NAME = @drop_column
);
SET @sql = IF(@has_column = 1, CONCAT('ALTER TABLE openclaw_runtime_config DROP COLUMN ', @drop_column), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_column = 'ai_chat_default_model_id';
SET @has_column = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'openclaw_runtime_config'
    AND COLUMN_NAME = @drop_column
);
SET @sql = IF(@has_column = 1, CONCAT('ALTER TABLE openclaw_runtime_config DROP COLUMN ', @drop_column), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_column = 'ai_chat_assistant_name';
SET @has_column = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'openclaw_runtime_config'
    AND COLUMN_NAME = @drop_column
);
SET @sql = IF(@has_column = 1, CONCAT('ALTER TABLE openclaw_runtime_config DROP COLUMN ', @drop_column), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_column = 'ai_chat_system_prompt';
SET @has_column = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'openclaw_runtime_config'
    AND COLUMN_NAME = @drop_column
);
SET @sql = IF(@has_column = 1, CONCAT('ALTER TABLE openclaw_runtime_config DROP COLUMN ', @drop_column), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS discord_channel_bindings;
DROP TABLE IF EXISTS discord_user_links;
