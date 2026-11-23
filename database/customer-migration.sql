CREATE TABLE IF NOT EXISTS booking_change_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    requested_date DATE NOT NULL,
    requested_start TIME NOT NULL,
    requested_end TIME NOT NULL,
    request_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    customer_reason VARCHAR(1000) NOT NULL,
    admin_response VARCHAR(1000) NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    active_booking_id INT GENERATED ALWAYS AS (CASE WHEN request_status='Pending' THEN booking_id ELSE NULL END) STORED,
    UNIQUE(active_booking_id),
    INDEX(booking_id,created_at),
    FOREIGN KEY(booking_id) REFERENCES bookings(booking_id),
    FOREIGN KEY(reviewed_by) REFERENCES users(user_id),
    CHECK(requested_end>requested_start)
) ENGINE=InnoDB;
