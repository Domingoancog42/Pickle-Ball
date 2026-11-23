<?php
require_once __DIR__.'/admin/bootstrap.php';
try {
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    $summary=one('SELECT COUNT(*) AS total,ROUND(AVG(score),1) AS average FROM customer_ratings');
    $pages=max(1,(int)ceil((int)$summary['total']/4));
    $requested=filter_var($_GET['page']??1,FILTER_VALIDATE_INT);
    $page=min($pages,max(1,$requested?:1));$offset=($page-1)*4;
    $reviews=rows('SELECT r.score,r.comment,r.updated_at,u.full_name,u.avatar_path FROM customer_ratings r JOIN users u ON u.user_id=r.user_id ORDER BY r.updated_at DESC,r.user_id DESC LIMIT ? OFFSET ?',[4,$offset]);
    foreach($reviews as &$review){$parts=preg_split('/\s+/u',trim($review['full_name']));$review['name']=$parts[0].(count($parts)>1?' '.mb_substr(end($parts),0,1).'.':'');unset($review['full_name']);}
    json_response(['total'=>(int)$summary['total'],'average'=>$summary['average']===null?null:(float)$summary['average'],'reviews'=>$reviews,'page'=>$page,'pages'=>$pages]);
}catch(ApiError $e){json_response(['error'=>$e->getMessage()],$e->status);}
catch(Throwable $e){error_log((string)$e);json_response(['error'=>'Ratings could not be loaded. Please try again.'],500);}
