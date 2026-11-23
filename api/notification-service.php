<?php
// Each notification keeps an atomic per-account read map, without a join table.
function notification_read_path(int $userId): string { return '$."'.$userId.'"'; }
function notification_mark_read(string $where,array $params,int $userId): void {
    query("UPDATE notifications SET read_by=JSON_INSERT(COALESCE(read_by,JSON_OBJECT()),?,DATE_FORMAT(NOW(),'%Y-%m-%d %H:%i:%s')) WHERE $where",[notification_read_path($userId),...$params]);
}
