<?php
require __DIR__.'/report-types-service.php';
try {
    require_admin();
    if($_SERVER['REQUEST_METHOD']!=='GET')throw new ApiError('Method not allowed.',405);
    $report=selected_report($_GET);$from=$report['from'];$to=$report['to'];$type=$report['type'];
    $items=$report['items'];$fields=$report['columns'];
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="pikol-'.$type.'-'.$from.'-'.$to.'.csv"');
    $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,array_values($fields),',','"','');
    foreach($items as $item) { $values=[]; foreach($fields as $key=>$label) { $value=(string)($item[$key] ?? ''); if(preg_match('/^[\s]*[=+@\-\t\r\n]/u',$value)) $value="'".$value; $values[]=$value; } fputcsv($out,$values,',','"',''); }
    fclose($out);
} catch(ApiError $e) { json_response(['error'=>$e->getMessage()],$e->status); }
catch(Throwable $e) { error_log((string)$e); json_response(['error'=>'The report could not be exported.'],500); }
