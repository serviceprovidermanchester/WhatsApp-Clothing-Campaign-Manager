<?php
require_once __DIR__.'/db.php';
function h(string $s): string { return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function check_csrf(): void { if(!hash_equals(csrf(),(string)($_POST['csrf']??''))) {http_response_code(403);exit('Invalid CSRF token');} }
function norm_phone(string $raw): ?string { $s=preg_replace('/\D+/','',$raw); if(str_starts_with($s,'00')) $s=substr($s,2); if(str_starts_with($s,'03')&&strlen($s)===11) $s='92'.substr($s,1); if(strlen($s)<10||strlen($s)>15) return null; return $s; }
function auth(): void { if(empty($_SESSION['ok'])) {http_response_code(401);header('Location: ?');exit;} }
function send_template(string $phone, array $campaign): array {
 $c=cfg(); $components=[];
 if($campaign['image_url']!=='') $components[]=['type'=>'header','parameters'=>[['type'=>'image','image'=>['link'=>$campaign['image_url']]]]];
 if($campaign['website_url']!=='') $components[]=['type'=>'body','parameters'=>[['type'=>'text','text'=>$campaign['website_url']]]];
 $payload=['messaging_product'=>'whatsapp','to'=>$phone,'type'=>'template','template'=>['name'=>$campaign['template_name'],'language'=>['code'=>$campaign['language']]]];
 if($components) $payload['template']['components']=$components;
 $ch=curl_init('https://graph.facebook.com/'.rawurlencode($c['graph_version']).'/'.rawurlencode($c['phone_number_id']).'/messages');
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$c['access_token'],'Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);
 $response=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
 $data=json_decode((string)$response,true) ?: [];
 return ['ok'=>$http>=200&&$http<300&&!empty($data['messages'][0]['id']), 'id'=>$data['messages'][0]['id']??null,'error'=>($data['error']['message']??$err?:'HTTP '.$http),'http'=>$http];
}
