-- Pickleball Court Booking Database (3NF)
-- Written for MySQL / MariaDB; works in PostgreSQL with minor type changes
-- (AUTO_INCREMENT -> GENERATED ALWAYS AS IDENTITY).

-- ---------- Lookup tables ----------
CREATE TABLE surface_types (
    surface_type_id INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50) NOT NULL UNIQUE       -- Indoor, Outdoor, Covered
);

CREATE TABLE booking_statuses (
    status_id INT AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(30) NOT NULL UNIQUE             -- Pending, Confirmed, Cancelled, Completed, No-show
);

CREATE TABLE payment_methods (
    payment_method_id INT AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(50) NOT NULL UNIQUE     -- Cash, GCash, Card, Bank Transfer
);

-- ---------- Core entities ----------
CREATE TABLE users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    phone         VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE venues (
    venue_id INT AUTO_INCREMENT PRIMARY KEY,
    name     VARCHAR(100) NOT NULL,
    address  VARCHAR(255) NOT NULL,
    city     VARCHAR(100) NOT NULL,
    phone    VARCHAR(20),
    opens_at TIME NOT NULL,
    closes_at TIME NOT NULL
);

CREATE TABLE courts (
    court_id        INT AUTO_INCREMENT PRIMARY KEY,
    venue_id        INT NOT NULL,
    surface_type_id INT NOT NULL,
    court_name      VARCHAR(50) NOT NULL,             -- "Court 1"
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (venue_id, court_name),
    FOREIGN KEY (venue_id)        REFERENCES venues(venue_id),
    FOREIGN KEY (surface_type_id) REFERENCES surface_types(surface_type_id)
);

-- ---------- Pricing ----------
-- Rate depends on venue + day type + time window (peak / off-peak)
CREATE TABLE pricing_rules (
    pricing_rule_id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id        INT NOT NULL,
    day_type        ENUM('WEEKDAY','WEEKEND') NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    price_per_hour  DECIMAL(10,2) NOT NULL CHECK (price_per_hour >= 0),
    FOREIGN KEY (venue_id) REFERENCES venues(venue_id)
);

-- ---------- Bookings ----------
CREATE TABLE bookings (
    booking_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    court_id     INT NOT NULL,
    status_id    INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time   TIME NOT NULL,
    end_time     TIME NOT NULL,
    court_fee    DECIMAL(10,2) NOT NULL,              -- price locked in at booking time
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (end_time > start_time),
    -- Blocks exact duplicate starts; partial overlaps must be checked in the app
    -- or with a PostgreSQL EXCLUDE constraint (see note below).
    UNIQUE (court_id, booking_date, start_time),
    FOREIGN KEY (user_id)   REFERENCES users(user_id),
    FOREIGN KEY (court_id)  REFERENCES courts(court_id),
    FOREIGN KEY (status_id) REFERENCES booking_statuses(status_id)
);

-- ---------- Optional equipment rental ----------
CREATE TABLE equipment (
    equipment_id INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL UNIQUE,        -- Paddle, Ball set
    rental_price DECIMAL(10,2) NOT NULL CHECK (rental_price >= 0)
);

-- Junction table: many bookings <-> many equipment items
CREATE TABLE booking_equipment (
    booking_id   INT NOT NULL,
    equipment_id INT NOT NULL,
    quantity     INT NOT NULL CHECK (quantity > 0),
    unit_price   DECIMAL(10,2) NOT NULL,              -- price at time of booking
    PRIMARY KEY (booking_id, equipment_id),
    FOREIGN KEY (booking_id)   REFERENCES bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id)
);

-- ---------- Payments ----------
CREATE TABLE payments (
    payment_id        INT AUTO_INCREMENT PRIMARY KEY,
    booking_id        INT NOT NULL,
    payment_method_id INT NOT NULL,
    amount            DECIMAL(10,2) NOT NULL CHECK (amount > 0),
    paid_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reference_no      VARCHAR(100),
    FOREIGN KEY (booking_id)        REFERENCES bookings(booking_id),
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(payment_method_id)
);

-- ---------- Helpful indexes ----------
CREATE INDEX idx_bookings_user      ON bookings(user_id);
CREATE INDEX idx_bookings_court_day ON bookings(court_id, booking_date);
CREATE INDEX idx_payments_booking   ON payments(booking_id);

-- ---------- Seed data ----------
INSERT INTO surface_types (name)    VALUES ('Indoor'), ('Outdoor'), ('Covered');
INSERT INTO booking_statuses (name) VALUES ('Pending'), ('Confirmed'), ('Cancelled'), ('Completed'), ('No-show');
INSERT INTO payment_methods (name)  VALUES ('Cash'), ('GCash'), ('Card'), ('Bank Transfer');

-- ---------- Example: check availability for a court on a date ----------
-- Returns bookings that overlap the requested 18:00-19:00 slot (empty = available)
-- SELECT b.*
-- FROM bookings b
-- JOIN booking_statuses s ON s.status_id = b.status_id
-- WHERE b.court_id = 1
--   AND b.booking_date = '2026-11-15'
--   AND s.name IN ('Pending','Confirmed')
--   AND b.start_time < '19:00:00'
--   AND b.end_time   > '18:00:00';

-- PostgreSQL overlap protection (needs: CREATE EXTENSION btree_gist;):
-- ALTER TABLE bookings ADD CONSTRAINT no_overlap EXCLUDE USING gist (
--     court_id WITH =,
--     tsrange(booking_date + start_time, booking_date + end_time) WITH &&
-- );
