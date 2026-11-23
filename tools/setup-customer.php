<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/admin/bootstrap.php';
$columns=rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications'");
$names=array_column($columns,'COLUMN_NAME');
if(!in_array('read_by',$names,true))db()->exec('ALTER TABLE notifications ADD read_by JSON NULL');
if(!in_array('recipient_id',$names,true)) db()->exec('ALTER TABLE notifications ADD recipient_id INT NULL, ADD CONSTRAINT fk_notification_recipient FOREIGN KEY(recipient_id) REFERENCES users(user_id), ADD INDEX idx_recipient_created(recipient_id,created_at)');
if(!in_array('dedup_key',$names,true)) db()->exec('ALTER TABLE notifications ADD dedup_key VARCHAR(160) NULL UNIQUE');
db()->exec(file_get_contents(dirname(__DIR__).'/database/customer-migration.sql'));
foreach(['manual_gcash_enabled'=>'1','manual_gcash_instructions'=>'Scan the GCash QR, check the recipient and pay the exact reservation amount. Upload your successful payment screenshot for staff verification.','manual_bpi_enabled'=>'1','manual_bpi_instructions'=>'Scan the BPI QR using your banking app, check the recipient and pay the exact reservation amount. Upload your successful transfer screenshot for staff verification.','manual_maya_enabled'=>'0','manual_maya_instructions'=>''] as $key=>$value) query('INSERT IGNORE INTO system_settings (setting_key,setting_value) VALUES (?,?)',[$key,$value]);
query("INSERT INTO payment_methods (name) SELECT 'BPI' WHERE NOT EXISTS (SELECT 1 FROM payment_methods WHERE name='BPI')");
echo "Customer schema is ready. Existing accounts, bookings, and payments preserved.\n";
