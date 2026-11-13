USE releasekalender;

-- Example data based on the Confluence "Releases SURF Access" export.

INSERT INTO releases (release_nr, application, version, previous_version, description,
                       deploy_tag, environments_tag, dependencies, db_changes, testers,
                       responsible,
                       changelog, notes, deployment_steps, created_by) VALUES
(722, 'eduID', '8.5.2', '8.5.1', 'CSP update eduID voor SRAM', NULL, NULL, NULL, NULL,
 'Peter', 'dev-user', NULL, NULL, NULL, 'Peter'),
(721, 'mujina', '10.1.2', '10.1.0', 'Mujina 10.1.2', '721', '721', NULL, NULL,
 'Mustafa / Bas', 'bas', 'Update uid to be multivalued and optional',
 NULL,
 './scripts/repo-update 721\n./scripts/appdeploy test2 mujina-idp\n./scripts/appdeploy test mujina-idp', 'Bas'),
(719, 'manage', '9.10', '9.9', 'Manage change via API call', NULL, NULL, NULL, NULL,
 'Bas', 'bas', NULL, NULL, NULL, 'Bas'),
(718, 'stepup-gateway', '6.1.2', '6.1.1', 'Stepup-Gateway security fix for SD-139176', NULL, NULL, NULL, NULL,
 'Pieter', 'pieter', 'Security fix for SD-139176', NULL, NULL, 'Pieter'),
(717, 'manage', '9.10', '9.9', 'Manage 9.10 (+SRAM fixes + remember your wayf)', '717', '717', 'Release 705 (Engineblock 7.3)', NULL,
 'Bas', 'bas', 'SRAM fixes, remember-your-wayf', 'Patch release for 717 to acc and test', NULL, 'Bas'),
(716, 'stepup-middleware', '4.2.1', '4.2.0', 'Stepup-Middleware security fix voor SD-139172', NULL, NULL, NULL, NULL,
 'Peter', 'peter', 'Stepup-Selfservice security fix voor SD-139172', NULL, NULL, 'Peter'),
(715, 'stepup-ra', '7.1.0', '7.0.4', 'Stepup-RA 7.1.0', NULL, NULL, NULL, NULL,
 'Peter', 'dev-user', NULL, NULL, NULL, 'Peter'),
(712, 'eduID', '8.4.5', '8.4.4', 'eduID 8.4.5 hotfix voor inlog mfa zonder bestaande eduID account', NULL, NULL, NULL, NULL,
 'Peter / Mustafa', 'peter', 'Hotfix for MFA login without existing eduID account', NULL, NULL, 'Peter'),
(708, 'mujina', '10.1.0', '10.0.11', 'Mujina 10.1.0 (alleen naar test)', NULL, NULL, NULL, NULL,
 'Peter', 'peter', NULL, NULL, './scripts/repo-update 708\n./scripts/appdeploy test mujina-idp', 'Peter'),
(720, 'platform', 'nvt', 'nvt', 'Nieuw cert voor elk and monitoring', NULL, NULL, NULL, NULL,
 'Bas', 'chantal', NULL, NULL, NULL, 'Chantal'),
(697, 'eduID', '8.5.1', '8.4.3', 'eduID 8.5.1', '697', '697', NULL, NULL,
 'Peter / Mustafa', 'peter',
 'Myconext (eduID) 8.5.0 (changes compared to 8.4.3):
  - Upgraded to Spring Boot 4 (#1263)
  - Revert Localicious and restore translation method to pre-localicious state (#984) & (#1299)
  - Add support for refeds IAP ACR values (#1032)
  - Replace global uid values for EPPN with the targeted eduID (#648), disabled feature flag
  - Improve validation for destructive operation when MFA is enabled (#580)
  - Performance improvements for MongoDB (#1269) & (#1268)
  - Improve error messages when an incorrect SMS OTP was entered (#1276)',
 'Diensten die een uid of eppn ontvangen van eduID worden op de uitzonderingslijst feature_force_global_uid_entities geplaatst.',
 './scripts/repo-update 697\n./scripts/appdeploy test myconext\n./scripts/appdeploy accblauw myconext\n./scripts/appdeploy accrood myconext', 'Peter'),
(690, 'platform', 'nvt', 'nvt', 'Mongo naar 5 full nodes', NULL, NULL, NULL, NULL,
 'Chantal', 'dev-user', NULL, NULL, NULL, 'Chantal');

INSERT INTO release_environments (release_id, environment, planned_date, deployed_at, note, installed, position)
SELECT r.id, v.environment, v.planned_date, v.deployed_at, v.note, v.installed, v.pos
FROM releases r
JOIN (
  SELECT 722 AS nr, 'prodblauw' AS environment, '2026-12-09' AS planned_date, NULL AS deployed_at, NULL AS note, 0 AS installed, 60 AS pos UNION ALL
  SELECT 722, 'prodrood',  '2026-12-09', NULL, NULL, 0, 70 UNION ALL
  SELECT 722, 'LB 50/50',  '2026-12-09', NULL, NULL, 0, 80 UNION ALL
  SELECT 721, 'test2',    '2026-11-04', '2026-11-04', NULL, 1, 10 UNION ALL
  SELECT 721, 'test',     '2026-11-05', '2026-11-05', 'Teruggedraaid naar 10.1.0', 1, 20 UNION ALL
  SELECT 721, 'accrood',  '2026-11-10', NULL, NULL, 0, 30 UNION ALL
  SELECT 721, 'accblauw', '2026-11-10', NULL, NULL, 0, 40 UNION ALL
  SELECT 721, 'prodblauw', '2026-11-12', NULL, NULL, 0, 60 UNION ALL
  SELECT 721, 'prodrood',  '2026-11-12', NULL, NULL, 0, 70 UNION ALL
  SELECT 721, 'LB 50/50',  '2026-11-12', NULL, NULL, 0, 80 UNION ALL
  SELECT 719, 'prodblauw', '2026-11-02', '2026-11-02', NULL, 1, 60 UNION ALL
  SELECT 719, 'prodrood',  '2026-11-02', '2026-11-02', NULL, 1, 70 UNION ALL
  SELECT 719, 'LB 50/50',  '2026-11-02', '2026-11-02', NULL, 1, 80 UNION ALL
  SELECT 718, 'prodblauw', '2026-11-02', '2026-11-02', NULL, 1, 60 UNION ALL
  SELECT 718, 'prodrood',  '2026-11-02', '2026-11-02', NULL, 1, 70 UNION ALL
  SELECT 718, 'LB 50/50',  '2026-11-02', '2026-11-02', NULL, 1, 80 UNION ALL
  SELECT 717, 'prodblauw', '2026-11-09', '2026-11-09', NULL, 1, 60 UNION ALL
  SELECT 717, 'prodrood',  '2026-11-09', '2026-11-09', NULL, 1, 70 UNION ALL
  SELECT 717, 'test',     '2026-11-12', '2026-11-12', 'Patch release 717.1', 1, 20 UNION ALL
  SELECT 717, 'LB 50/50',  '2026-11-09', '2026-11-09', NULL, 1, 80 UNION ALL
  SELECT 716, 'prodblauw', '2026-10-30', '2026-10-30', NULL, 1, 60 UNION ALL
  SELECT 716, 'prodrood',  '2026-10-30', '2026-10-30', NULL, 1, 70 UNION ALL
  SELECT 716, 'LB 50/50',  '2026-10-30', '2026-10-30', NULL, 1, 80 UNION ALL
  SELECT 715, 'prodblauw', '2026-11-12', NULL, NULL, 0, 60 UNION ALL
  SELECT 715, 'prodrood',  '2026-11-12', NULL, NULL, 0, 70 UNION ALL
  SELECT 715, 'LB 50/50',  '2026-11-12', NULL, NULL, 0, 80 UNION ALL
  SELECT 712, 'prodblauw', '2026-10-23', '2026-10-23', NULL, 1, 60 UNION ALL
  SELECT 712, 'prodrood',  '2026-10-23', '2026-10-23', NULL, 1, 70 UNION ALL
  SELECT 712, 'LB 50/50',  '2026-10-23', '2026-10-23', NULL, 1, 80 UNION ALL
  SELECT 708, 'test',     '2026-10-21', '2026-10-21', NULL, 1, 20 UNION ALL
  SELECT 697, 'test',     '2026-10-28', '2026-10-28', NULL, 1, 20 UNION ALL
  SELECT 697, 'accrood',  '2026-10-30', '2026-10-30', NULL, 1, 30 UNION ALL
  SELECT 697, 'accblauw', '2026-10-30', '2026-10-30', NULL, 1, 40 UNION ALL
  SELECT 697, 'prodblauw', '2026-11-02', '2026-11-02', NULL, 1, 60 UNION ALL
  SELECT 697, 'prodrood',  '2026-11-02', '2026-11-02', NULL, 1, 70 UNION ALL
  SELECT 697, 'LB 50/50',  '2026-11-02', '2026-11-02', NULL, 1, 80 UNION ALL
  SELECT 690, 'accrood',  '2026-11-19', NULL, NULL, 0, 30 UNION ALL
  SELECT 690, 'accblauw', '2026-11-19', NULL, NULL, 0, 40
) v ON v.nr = r.release_nr;

-- Rollback example (release 721 was rolled back to 10.1.0 on TEST)
UPDATE releases SET rollback =
  'Roll back to 10.1.0 on TEST:
   ./scripts/appdeploy test mujina-idp
   (checkout the 721 tag of the previous release first)'
  WHERE release_nr = 721;
