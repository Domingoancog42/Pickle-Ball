-- Existing hosted installations: import once before uploading the guest checkout files.
-- Local installations can use tools/setup-guest-checkout.php (safe to rerun).
ALTER TABLE booking_checkout_holds
    ADD COLUMN guest_name VARCHAR(100) NULL,
    ADD COLUMN guest_email VARCHAR(150) NULL,
    ADD COLUMN guest_phone VARCHAR(20) NULL;
