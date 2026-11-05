CREATE TABLE IF NOT EXISTS bs_mixed_events (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(160) NOT NULL,
  event_date  DATE         NOT NULL,
  status      ENUM('draft','running','done') NOT NULL DEFAULT 'draft',
  is_default  TINYINT(1)   NOT NULL DEFAULT 0,
  created     DATETIME     NOT NULL,
  is_test     TINYINT(1)   NOT NULL DEFAULT 0,
  court_count SMALLINT UNSIGNED NOT NULL DEFAULT 4,
  mode        ENUM('americano','mexicano') NOT NULL DEFAULT 'americano',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_mixed_participants (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id  INT UNSIGNED NOT NULL,
  uid       INT UNSIGNED DEFAULT NULL,
  label     VARCHAR(120) NOT NULL,
  gender    ENUM('m','f') DEFAULT NULL,
  itn       DECIMAL(3,1) DEFAULT NULL,
  birth_date DATE DEFAULT NULL,
  age       TINYINT UNSIGNED DEFAULT NULL,
  status    ENUM('active','paused') NOT NULL DEFAULT 'active',
  no_extra_match TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY event_uid (event_id, uid),
  KEY event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_mixed_rounds (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id         INT UNSIGNED NOT NULL,
  round_no         SMALLINT UNSIGNED NOT NULL,
  group_size_mode  ENUM('4_only','4_and_5') NOT NULL DEFAULT '4_only',
  distribution     ENUM('random','itn','itn_balanced','age','ladies_apart') NOT NULL DEFAULT 'random',
  status           ENUM('proposed','confirmed','completed') NOT NULL DEFAULT 'proposed',
  PRIMARY KEY (id),
  UNIQUE KEY event_round (event_id, round_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_mixed_round_players (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  round_id        INT UNSIGNED NOT NULL,
  participant_id  INT UNSIGNED NOT NULL,
  group_no        TINYINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY round_participant (round_id, participant_id),
  KEY round_group (round_id, group_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_mixed_avoid_pairs (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id        INT UNSIGNED NOT NULL,
  participant_a   INT UNSIGNED NOT NULL,
  participant_b   INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY event_pair (event_id, participant_a, participant_b),
  KEY event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_mixed_members (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  label   VARCHAR(120) NOT NULL,
  gender  ENUM('m','f') DEFAULT NULL,
  itn     DECIMAL(3,1) DEFAULT NULL,
  birth_date DATE DEFAULT NULL,
  PRIMARY KEY (id),
  KEY label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_mixed_matches (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  round_id    INT UNSIGNED NOT NULL,
  group_no    TINYINT UNSIGNED NOT NULL,
  match_no    TINYINT UNSIGNED NOT NULL,
  team_a_p1   INT UNSIGNED NOT NULL,
  team_a_p2   INT UNSIGNED NOT NULL,
  team_b_p1   INT UNSIGNED NOT NULL,
  team_b_p2   INT UNSIGNED NOT NULL,
  games_a     TINYINT UNSIGNED DEFAULT NULL,
  games_b     TINYINT UNSIGNED DEFAULT NULL,
  is_extra    TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY round_group (round_id, group_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_mixed_event_grants (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED NOT NULL,
  uid      INT UNSIGNED NOT NULL,
  role     ENUM('score','admin') NOT NULL,
  created  DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY event_uid (event_id, uid),
  KEY event_id (event_id),
  KEY uid (uid),
  CONSTRAINT fk_mixed_grant_user FOREIGN KEY (uid) REFERENCES bs_users (uid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_mixed_schema_versions (
  version INT UNSIGNED NOT NULL,
  applied DATETIME NOT NULL,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO bs_mixed_schema_versions (version, applied) VALUES (20260803, NOW());
