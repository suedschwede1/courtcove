CREATE TABLE IF NOT EXISTS bs_pyramid_ladders (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title                 VARCHAR(160) NOT NULL,
  season                SMALLINT UNSIGNED NOT NULL,
  category              VARCHAR(40) DEFAULT NULL,
  status                ENUM('setup','running','closed') NOT NULL DEFAULT 'setup',
  itn_default           DECIMAL(3,1) NOT NULL DEFAULT 10.0,
  days_to_play          SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  winner_protected_days SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  loser_cooldown_days   SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  is_default            TINYINT(1) NOT NULL DEFAULT 0,
  rules_version         VARCHAR(40) DEFAULT NULL,
  created               DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY season_category (season, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_pyramid_players (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ladder_id INT UNSIGNED NOT NULL,
  uid       INT UNSIGNED DEFAULT NULL,
  label     VARCHAR(120) NOT NULL,
  itn       DECIMAL(3,1) DEFAULT NULL,
  itn_rated DECIMAL(3,1) NOT NULL,
  position  SMALLINT UNSIGNED DEFAULT NULL,
  status    ENUM('waiting','entering','active','paused','removed') NOT NULL DEFAULT 'waiting',
  gender    ENUM('m','f') DEFAULT NULL,
  age       TINYINT UNSIGNED DEFAULT NULL,
  phone     VARCHAR(40) DEFAULT NULL,
  joined    DATETIME NOT NULL,
  note      VARCHAR(160) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ladder_uid (ladder_id, uid),
  UNIQUE KEY ladder_position (ladder_id, position),
  KEY ladder_status (ladder_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_pyramid_challenges (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ladder_id      INT UNSIGNED NOT NULL,
  challenger_id  INT UNSIGNED NOT NULL,
  opponent_id    INT UNSIGNED NOT NULL,
  challenger_pos SMALLINT UNSIGNED DEFAULT NULL,
  opponent_pos   SMALLINT UNSIGNED DEFAULT NULL,
  status         ENUM('open','scheduled','played','declined','withdrawn','forfeited','expired')
                 NOT NULL DEFAULT 'open',
  created        DATETIME NOT NULL,
  deadline       DATE DEFAULT NULL,
  scheduled_at   DATETIME DEFAULT NULL,
  score          VARCHAR(40) DEFAULT NULL,
  winner_id      INT UNSIGNED DEFAULT NULL,
  entered_uid    INT UNSIGNED DEFAULT NULL,
  entered        DATETIME DEFAULT NULL,
  decline_reason ENUM('other_match','injury') DEFAULT NULL,
  admin_note     VARCHAR(160) DEFAULT NULL,
  resolved       DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY ladder_status (ladder_id, status),
  KEY challenger (challenger_id),
  KEY opponent (opponent_id),
  KEY ladder_deadline (ladder_id, deadline)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_pyramid_events (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ladder_id    INT UNSIGNED NOT NULL,
  challenge_id INT UNSIGNED DEFAULT NULL,
  type         ENUM('challenge','schedule','result','join','admin') NOT NULL,
  actor_uid    INT UNSIGNED DEFAULT NULL,
  payload      TEXT NOT NULL,
  created      DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ladder_created (ladder_id, created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_pyramid_grants (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ladder_id INT UNSIGNED NOT NULL,
  uid       INT UNSIGNED NOT NULL,
  role      ENUM('admin') NOT NULL DEFAULT 'admin',
  created   DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ladder_uid (ladder_id, uid),
  KEY ladder_id (ladder_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_pyramid_schema_versions (
  version INT UNSIGNED NOT NULL,
  applied DATETIME NOT NULL,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bs_pyramid_schema_versions VALUES (20260803, '2026-08-03 00:00:00');
