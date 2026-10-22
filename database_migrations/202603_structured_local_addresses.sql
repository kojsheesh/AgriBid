-- Run once on existing databases. Fresh installs already include these columns
-- in database_schema.sql.
ALTER TABLE user_addresses
    ADD COLUMN city VARCHAR(60) NOT NULL DEFAULT '' AFTER label,
    ADD COLUMN barangay VARCHAR(80) NOT NULL DEFAULT '' AFTER city;

ALTER TABLE buyer_rooms
    ADD COLUMN city VARCHAR(60) NOT NULL DEFAULT '' AFTER description,
    ADD COLUMN barangay VARCHAR(80) NOT NULL DEFAULT '' AFTER city,
    ADD KEY idx_buyer_rooms_city_status_deadline (city, status, deadline);

-- Keep recognizable legacy requests visible in the Binangonan farmer feed.
UPDATE buyer_rooms
SET city = 'Binangonan'
WHERE city = ''
  AND delivery_location LIKE '%Binangonan%';
