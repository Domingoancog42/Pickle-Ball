<?php
require_once __DIR__.'/bootstrap.php';
customer_handler(function(array $user):array {
    $id=(int)$user['user_id'];
    if($_SERVER['REQUEST_METHOD']==='GET')return ['rating'=>one('SELECT score,comment FROM customer_ratings WHERE user_id=?',[$id])];
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ApiError('Method not allowed.',405);
    $data=input();$score=filter_var($data['score']??null,FILTER_VALIDATE_INT);
    if($score===false||$score<1||$score>5)throw new ApiError('Choose a rating from 1 to 5 stars.');
    $comment=text_value($data,'comment',1000,false);
    query('INSERT INTO customer_ratings (user_id,score,comment) VALUES (?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score),comment=VALUES(comment),updated_at=CURRENT_TIMESTAMP',[$id,$score,$comment]);
    return ['success'=>true];
});
