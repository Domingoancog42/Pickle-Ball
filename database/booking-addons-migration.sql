CREATE TABLE IF NOT EXISTS booking_addon_products (
 product_id INT AUTO_INCREMENT PRIMARY KEY,
 product_key VARCHAR(50) NOT NULL UNIQUE,
 name VARCHAR(100) NOT NULL,
 category ENUM('equipment','food') NOT NULL,
 unit_price DECIMAL(10,2) NOT NULL,
 image_path VARCHAR(255) NOT NULL,
 is_active BOOLEAN NOT NULL DEFAULT TRUE,
 example_price BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS booking_addon_items (
 booking_id INT NOT NULL,
 product_id INT NOT NULL,
 name VARCHAR(100) NOT NULL,
 quantity INT NOT NULL,
 unit_price DECIMAL(10,2) NOT NULL,
 PRIMARY KEY(booking_id,product_id),
 FOREIGN KEY(booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
 FOREIGN KEY(product_id) REFERENCES booking_addon_products(product_id)
) ENGINE=InnoDB;
INSERT IGNORE INTO booking_addon_products(product_key,name,category,unit_price,image_path) VALUES
 ('grip-tape','Grip tape','equipment',75,'assets/images/addons/grip-tape.png'),
 ('paddle','Paddle','equipment',150,'assets/images/addons/paddle.png'),
 ('clear-edge-tape','Clear edge tape','equipment',100,'assets/images/addons/clear-edge-tape.png'),
 ('water','Water','food',25,'assets/images/addons/water.png'),
 ('soft-drinks','Soft drinks','food',40,'assets/images/addons/soft-drinks.png');
