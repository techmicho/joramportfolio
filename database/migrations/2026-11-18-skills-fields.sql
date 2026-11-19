-- Run once on databases created from the original portfolio schema, where
-- `skills` has only name, category, sort_order, and created_at columns.
-- Do not run this migration if these columns already exist.

ALTER TABLE skills
    ADD COLUMN description TEXT NULL AFTER category,
    ADD COLUMN level_label VARCHAR(50) NOT NULL DEFAULT '' AFTER description,
    ADD COLUMN level_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER level_label;

UPDATE skills SET description = '' WHERE description IS NULL;

ALTER TABLE skills
    MODIFY COLUMN description TEXT NOT NULL;
