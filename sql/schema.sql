CREATE DATABASE IF NOT EXISTS releasekalender
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE releasekalender;

CREATE TABLE IF NOT EXISTS releases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  release_nr INT NOT NULL UNIQUE,
  application VARCHAR(64) NOT NULL,
  version VARCHAR(64) NOT NULL,
  previous_version VARCHAR(64) NULL,
  description TEXT NULL,
  deploy_tag VARCHAR(64) NULL,
  environments_tag VARCHAR(64) NULL,
  dependencies TEXT NULL,
  db_changes TEXT NULL,
  testers VARCHAR(255) NULL,
  changelog TEXT NULL,
  notes TEXT NULL,
  deployment_steps TEXT NULL,
  rollback TEXT NULL,
  created_by VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS release_environments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  release_id INT NOT NULL,
  environment VARCHAR(32) NOT NULL,
  planned_date DATE NULL,
  deployed_at DATE NULL,
  note VARCHAR(255) NULL,
  UNIQUE KEY uniq_release_env (release_id, environment),
  CONSTRAINT fk_release_env_release
    FOREIGN KEY (release_id) REFERENCES releases(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Whether the application is installed on the environment (checkbox in the form)
ALTER TABLE release_environments ADD COLUMN IF NOT EXISTS installed TINYINT(1) NOT NULL DEFAULT 0;

-- User responsible for a release (references users.sub)
ALTER TABLE releases ADD COLUMN IF NOT EXISTS responsible VARCHAR(255) NULL AFTER testers;

-- All users that have ever logged in (filled at OIDC login)
CREATE TABLE IF NOT EXISTS users (
  sub VARCHAR(255) PRIMARY KEY,
  nickname VARCHAR(255) NOT NULL,
  name VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  last_login TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  first_seen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Example users for the seed data (the dev-user is the local developer
-- account created by the dev bypass login) 
INSERT IGNORE INTO users (sub, nickname, name, email) VALUES
('dev-user', 'Local developer', 'Local developer', 'dev@localhost'),
('peter',    'Peter',    'Peter',    'peter@example.org'),
('mustafa',  'Mustafa',  'Mustafa',  'mustafa@example.org'),
('bas',      'Bas',      'Bas',      'bas@example.org'),
('pieter',   'Pieter',   'Pieter',   'pieter@example.org'),
('chantal',  'Chantal',  'Chantal',  'chantal@example.org'),
('martin',   'Martin',   'Martin',   'martin@example.org'),
('prolo',    'Prolo',    'Prolo',    'prolo@example.org');

-- Fill responsible for example releases created before this column existed
UPDATE releases SET responsible = CASE release_nr
    WHEN 721 THEN 'bas'
    WHEN 719 THEN 'bas'
    WHEN 718 THEN 'pieter'
    WHEN 717 THEN 'bas'
    WHEN 716 THEN 'peter'
    WHEN 712 THEN 'peter'
    WHEN 708 THEN 'peter'
    WHEN 720 THEN 'chantal'
    WHEN 697 THEN 'peter'
  END
  WHERE responsible IS NULL
    AND release_nr IN (721, 719, 718, 717, 716, 712, 708, 720, 697);

-- Example data: assign the local developer account to some releases,
-- so the "Mine" responsible filter can be tested. This runs on every
-- start and resets these example rows.
UPDATE releases SET responsible = 'dev-user'
  WHERE release_nr IN (722, 715, 690);

-- Backfill installed for environments that were already deployed
UPDATE release_environments SET installed = 1
  WHERE deployed_at IS NOT NULL AND installed = 0;

-- Rollback column for releases created before it existed
ALTER TABLE releases ADD COLUMN IF NOT EXISTS rollback TEXT NULL AFTER deployment_steps;

-- Environment list changed to the deploy pipeline (test2, test, accrood,
-- accblauw, 4eset, prodblauw, prodrood). Convert existing rows: TEST2/TEST
-- are renamed, ACC splits into accrood + accblauw, PROD into prodblauw +
-- prodrood. MGNT and other unknown environments are removed.
UPDATE release_environments SET environment='test2' WHERE environment='TEST2';
UPDATE release_environments SET environment='test' WHERE environment='TEST';
INSERT IGNORE INTO release_environments (release_id, environment, planned_date, deployed_at, note, installed)
  SELECT release_id, 'accblauw', planned_date, deployed_at, note, installed
  FROM release_environments WHERE environment='ACC';
UPDATE release_environments SET environment='accrood' WHERE environment='ACC';
INSERT IGNORE INTO release_environments (release_id, environment, planned_date, deployed_at, note, installed)
  SELECT release_id, 'prodblauw', planned_date, deployed_at, note, installed
  FROM release_environments WHERE environment='PROD';
UPDATE release_environments SET environment='prodrood' WHERE environment='PROD';
DELETE FROM release_environments
  WHERE environment NOT IN ('test2', 'test', 'accrood', 'accblauw', '4eset', 'prodblauw', 'prodrood');

-- Example data: rollback text for one of the example releases
UPDATE releases SET rollback =
  'Roll back to 10.1.0 on TEST:
   ./scripts/appdeploy test mujina-idp
   (checkout the previous release tag first)'
  WHERE release_nr = 721;

-- Order of the planned-date items within a release
ALTER TABLE release_environments ADD COLUMN IF NOT EXISTS position INT NOT NULL DEFAULT 0;
UPDATE release_environments SET position = CASE environment
    WHEN 'test2' THEN 10
    WHEN 'test' THEN 20
    WHEN 'accrood' THEN 30
    WHEN 'accblauw' THEN 40
    WHEN '4eset' THEN 50
    WHEN 'prodblauw' THEN 60
    WHEN 'prodrood' THEN 70
    ELSE 80
  END
  WHERE position = 0;
