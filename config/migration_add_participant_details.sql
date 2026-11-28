-- Registration/profile: campus-first selection and classification-specific details.
--   institution  : 'Pangasinan State University' or the name of the participant's school
--   campus       : PSU campus (NULL for non-PSU participants)
--   designation  : employee position / designation (Faculty accounts), e.g. Department Chair
-- department is widened because some full program names are close to 100 characters.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS institution VARCHAR(150) NULL DEFAULT 'Pangasinan State University' AFTER account_type,
    ADD COLUMN IF NOT EXISTS campus VARCHAR(100) NULL AFTER institution,
    ADD COLUMN IF NOT EXISTS designation VARCHAR(150) NULL AFTER faculty_rank,
    MODIFY department VARCHAR(150) NULL;
