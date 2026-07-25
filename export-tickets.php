<?php
require __DIR__.'/app/bootstrap.php';
require_permission('export_tickets');
$format=$_GET['format']??'csv';
if(!in_array($format,['csv','xlsx'],true)){http_response_code(400);exit('Unsupported export format.');}
$tickets=db()->query("SELECT t.*,d.name department,a.full_name assignee FROM tickets t JOIN departments d ON d.id=t.department_id LEFT JOIN users a ON a.id=t.assignee_id WHERE t.deleted_at IS NULL ORDER BY t.updated_at DESC")->fetchAll();
$headers=['Status','Subject','Department','Category','Subcategory','Date Created','Date Updated','Date Closed','Assignee','Employee'];
$status=['open'=>'Open','in_progress'=>'In Progress','pending'=>'Pending','closed'=>'Closed'];$rows=[];
foreach($tickets as $ticket)$rows[]=[
    $status[$ticket['status']]??$ticket['status'],$ticket['subject'],$ticket['department'],$ticket['category'],$ticket['subcategory']??'',
    $ticket['created_at'],$ticket['updated_at'],$ticket['closed_at']??'',$ticket['assignee']??'Unassigned',$ticket['employee_name'],
];
function export_csv_value(string $value):string{return preg_match('/^[=+\-@]/',$value)?"'".$value:$value;}
function xlsx_xml(string $value):string{return htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8');}
function xlsx_col(int $column):string{$name='';while($column>0){$column--;$name=chr(65+$column%26).$name;$column=intdiv($column,26);}return $name;}
function xlsx_sheet(array $headers,array $rows):string{$xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';foreach(array_merge([$headers],$rows) as $rowNumber=>$row){$xml.='<row r="'.($rowNumber+1).'">';foreach($row as $column=>$value)$xml.='<c r="'.xlsx_col($column+1).($rowNumber+1).'" t="inlineStr"><is><t xml:space="preserve">'.xlsx_xml((string)$value).'</t></is></c>';$xml.='</row>';}return $xml.'</sheetData></worksheet>';}
if($format==='csv'){
    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="cng-jamesons-tickets.csv"');
    $output=fopen('php://output','w');fputcsv($output,$headers);foreach($rows as $row)fputcsv($output,array_map(fn($value)=>export_csv_value((string)$value),$row));fclose($output);exit;
}
if(!class_exists('ZipArchive')){http_response_code(500);exit('Excel export is not available on this server.');}
$file=tempnam(sys_get_temp_dir(),'cng-export-');$zip=new ZipArchive();
if($file===false||$zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){http_response_code(500);exit('Unable to prepare the Excel export.');}
$zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
$zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Tickets" sheetId="1" r:id="rId1"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
$zip->addFromString('xl/worksheets/sheet1.xml',xlsx_sheet($headers,$rows));$zip->close();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="cng-jamesons-tickets.xlsx"');header('Content-Length: '.filesize($file));readfile($file);unlink($file);
