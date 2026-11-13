USE sas_ojt_database;

INSERT INTO requirement_types (title, description, is_mandatory)
SELECT 'Resume / Curriculum Vitae', 'Current student resume or curriculum vitae.', 1
WHERE NOT EXISTS (SELECT 1 FROM requirement_types WHERE title = 'Resume / Curriculum Vitae');

INSERT INTO requirement_types (title, description, is_mandatory)
SELECT 'Endorsement Letter', 'Official college endorsement letter for the placement.', 1
WHERE NOT EXISTS (SELECT 1 FROM requirement_types WHERE title = 'Endorsement Letter');

INSERT INTO requirement_types (title, description, is_mandatory)
SELECT 'MOA / Training Agreement', 'Training agreement signed by the required parties.', 1
WHERE NOT EXISTS (SELECT 1 FROM requirement_types WHERE title = 'MOA / Training Agreement');

INSERT INTO requirement_types (title, description, is_mandatory)
SELECT 'Parental Consent Form', 'Signed parent or guardian consent form.', 1
WHERE NOT EXISTS (SELECT 1 FROM requirement_types WHERE title = 'Parental Consent Form');

INSERT INTO requirement_types (title, description, is_mandatory)
SELECT 'Medical Certificate', 'Current medical clearance for OJT.', 1
WHERE NOT EXISTS (SELECT 1 FROM requirement_types WHERE title = 'Medical Certificate');

INSERT INTO requirement_types (title, description, is_mandatory)
SELECT 'Final Accomplishment Report', 'Final report summarizing the student OJT experience.', 1
WHERE NOT EXISTS (SELECT 1 FROM requirement_types WHERE title = 'Final Accomplishment Report');