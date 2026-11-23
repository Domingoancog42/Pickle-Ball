<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/admin/bootstrap.php';
query('CREATE TABLE IF NOT EXISTS customer_ratings (user_id INT NOT NULL PRIMARY KEY, score TINYINT UNSIGNED NOT NULL, comment VARCHAR(1000) NOT NULL DEFAULT "", created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, CONSTRAINT fk_rating_customer FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE, CONSTRAINT chk_rating_score CHECK (score BETWEEN 1 AND 5)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "Customer ratings ready.\n";
