-- Run once on databases that already have the structured address columns.
-- Existing address/details values are combined before the legacy columns are removed.
ALTER TABLE user_addresses
    ADD COLUMN full_address VARCHAR(180) NOT NULL DEFAULT '' AFTER barangay;

UPDATE user_addresses
SET full_address = TRIM(BOTH ', ' FROM CONCAT_WS(', ', NULLIF(TRIM(details), ''), NULLIF(TRIM(address), '')));

ALTER TABLE user_addresses
    DROP COLUMN address,
    DROP COLUMN postal_code,
    DROP COLUMN details,
    MODIFY COLUMN full_address VARCHAR(180) NOT NULL;
