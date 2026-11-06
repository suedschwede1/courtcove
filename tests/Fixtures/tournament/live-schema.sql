-- The live bs_tournament* shape: the three CREATEs of the old tournament_schema.sql and every tournament
-- ALTER/CREATE of the later steps 02, 04 (ALTER only), 05, 06, 07, 08, 09, 11 and 12, in that order.
-- DDL only (no INSERT or UPDATE), every column comment removed.

CREATE TABLE IF NOT EXISTS bs_tournaments (
  tid         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(160)  NOT NULL,
  season      SMALLINT UNSIGNED NOT NULL,
  status      ENUM('setup','drawn','running','finished') NOT NULL DEFAULT 'setup',
  itn_default DECIMAL(3,1) NOT NULL DEFAULT 10.0,
  created     DATETIME     NOT NULL,
  PRIMARY KEY (tid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_tournament_players (
  tpid      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tid       INT UNSIGNED NOT NULL,
  uid       INT UNSIGNED DEFAULT NULL,
  label     VARCHAR(120) NOT NULL,
  itn       DECIMAL(3,1) DEFAULT NULL,
  itn_rated DECIMAL(3,1) NOT NULL,
  seed      SMALLINT UNSIGNED DEFAULT NULL,
  grp       CHAR(1)      DEFAULT NULL,
  grp_pos   TINYINT UNSIGNED DEFAULT NULL,
  phone     VARCHAR(40)  DEFAULT NULL,
  note      VARCHAR(160) DEFAULT NULL,
  PRIMARY KEY (tpid),
  UNIQUE KEY tid_uid (tid, uid),
  KEY tid_grp (tid, grp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_tournament_matches (
  tmid          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tid           INT UNSIGNED NOT NULL,
  phase         VARCHAR(16)  NOT NULL,
  grp           CHAR(1)      DEFAULT NULL,
  slot          SMALLINT UNSIGNED DEFAULT NULL,
  tpid_a        INT UNSIGNED DEFAULT NULL,
  tpid_b        INT UNSIGNED DEFAULT NULL,
  deadline      DATE         DEFAULT NULL,
  played        DATE         DEFAULT NULL,
  score         VARCHAR(40)  DEFAULT NULL,
  winner        INT UNSIGNED DEFAULT NULL,
  entered_uid   INT UNSIGNED DEFAULT NULL,
  entered       DATETIME     DEFAULT NULL,
  confirmed_uid INT UNSIGNED DEFAULT NULL,
  confirmed     DATETIME     DEFAULT NULL,
  PRIMARY KEY (tmid),
  KEY tid_phase (tid, phase, grp),
  KEY tid_deadline (tid, deadline)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 02
ALTER TABLE bs_tournament_matches
  ADD COLUMN s1a TINYINT UNSIGNED DEFAULT NULL AFTER score,
  ADD COLUMN s1b TINYINT UNSIGNED DEFAULT NULL AFTER s1a,
  ADD COLUMN s2a TINYINT UNSIGNED DEFAULT NULL AFTER s1b,
  ADD COLUMN s2b TINYINT UNSIGNED DEFAULT NULL AFTER s2a,
  ADD COLUMN mtba TINYINT UNSIGNED DEFAULT NULL AFTER s2b,
  ADD COLUMN mtbb TINYINT UNSIGNED DEFAULT NULL AFTER mtba,
  ADD COLUMN sets_a  TINYINT UNSIGNED DEFAULT NULL AFTER mtbb,
  ADD COLUMN sets_b  TINYINT UNSIGNED DEFAULT NULL AFTER sets_a,
  ADD COLUMN games_a SMALLINT UNSIGNED DEFAULT NULL AFTER sets_b,
  ADD COLUMN games_b SMALLINT UNSIGNED DEFAULT NULL AFTER games_a,
  ADD COLUMN retired ENUM('none','ret_a','ret_b','wo_a','wo_b') NOT NULL DEFAULT 'none' AFTER games_b,
  ADD COLUMN played_time TIME DEFAULT NULL AFTER played,
  ADD COLUMN court ENUM('1','2','3') DEFAULT NULL AFTER played_time,
  ADD COLUMN mkey VARCHAR(12) DEFAULT NULL AFTER phase,
  ADD KEY tid_mkey (tid, mkey);

ALTER TABLE bs_tournament_players
  ADD COLUMN points_adjust TINYINT NOT NULL DEFAULT 0 AFTER grp_pos;

ALTER TABLE bs_tournaments
  ADD COLUMN points_per_win TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER itn_default,
  ADD COLUMN tiebreak_scope ENUM('tied','all') NOT NULL DEFAULT 'tied' AFTER points_per_win,
  ADD COLUMN runnerup_mode ENUM('drop_last','points','per_match','lot') NOT NULL DEFAULT 'drop_last'
      AFTER tiebreak_scope;

-- 04
ALTER TABLE bs_tournaments
  MODIFY COLUMN runnerup_mode ENUM('drop_last','points','per_match','lot')
      NOT NULL DEFAULT 'drop_last',
  ADD COLUMN format MEDIUMTEXT DEFAULT NULL AFTER status;

-- 05
ALTER TABLE bs_tournament_matches
  MODIFY COLUMN phase VARCHAR(16) NOT NULL,
  ADD COLUMN rnd TINYINT UNSIGNED DEFAULT NULL AFTER grp,
  ADD KEY tid_phase_rnd (tid, phase, rnd, slot);

CREATE TABLE IF NOT EXISTS bs_tournament_lines (
  tlid        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tid         INT UNSIGNED NOT NULL,
  phase       VARCHAR(16)  NOT NULL,
  line        SMALLINT UNSIGNED NOT NULL,
  kind        ENUM('seed','bye','pool') NOT NULL,
  seed_no     TINYINT UNSIGNED DEFAULT NULL,
  pool        VARCHAR(16)  DEFAULT NULL,
  placeholder VARCHAR(16)  DEFAULT NULL,
  tpid        INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (tlid),
  UNIQUE KEY tid_phase_line (tid, phase, line),
  KEY tid_phase_tpid (tid, phase, tpid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 06
ALTER TABLE bs_tournament_players
  ADD COLUMN gender ENUM('m','w') DEFAULT NULL AFTER label;

-- 07
ALTER TABLE bs_tournaments
  ADD COLUMN lot_orders MEDIUMTEXT DEFAULT NULL AFTER format;

-- 08
ALTER TABLE bs_tournament_matches
  DROP KEY tid_mkey,
  ADD UNIQUE KEY tid_mkey (tid, mkey);

-- 09
ALTER TABLE bs_tournaments
  ADD COLUMN is_test TINYINT(1) NOT NULL DEFAULT 0 AFTER season,
  ADD COLUMN cloned_from INT UNSIGNED DEFAULT NULL AFTER is_test;

-- 11
ALTER TABLE bs_tournament_players
  ADD COLUMN partner_tpid INT UNSIGNED DEFAULT NULL AFTER phone,
  ADD KEY partner_tpid (partner_tpid);

-- 12
ALTER TABLE bs_tournaments
  ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD KEY active (active);
