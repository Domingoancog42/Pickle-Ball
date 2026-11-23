-- Existing hosted installations: import once, with booking-addons-migration.sql.
ALTER TABLE bookings ADD COLUMN addons_total DECIMAL(10,2) NOT NULL DEFAULT 0;
