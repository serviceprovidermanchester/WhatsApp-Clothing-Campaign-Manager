<?php
require __DIR__.'/functions.php';
$c=cfg();
if($_SERVER['REQUEST_METHOD']==='GET') {if(($_GET['hub_mode']??$_GET['hub.mode']??'')==='subscribe' && hash_equals($c['verify_token'],(string)($_GET['hub_verify_token']??$_GET['hub.verify_token']??''))){echo $_GET['hub_challenge']??$_GET['hub.challenge']??'';}else http_response_code(403);exit;}
$raw=file_get_contents('php://input');$signature=(string)($_SERVER['HTTP_X_HUB_SIGNATURE_256']??'');$expected='sha256='.hash_hmac('sha256',$raw,$c['app_secret']);if(!$signature||!hash_equals($expected,$signature)){http_response_code(403);exit;}
$data=json_decode($raw,true);$db=db();foreach(($data['entry']??[]) as $entry){foreach(($entry['changes']??[]) as $change){$value=$change['value']??[];
 foreach(($value['statuses']??[]) as $status){$mid=$status['id']??'';$st=$status['status']??'';if($mid&&in_array($st,['sent','delivered','read','failed'],true)){$db->prepare("UPDATE sends SET state=?,updated_at=datetime('now'),error=? WHERE message_id=?")->execute([$st,substr((string)($status['errors'][0]['title']??''),0,500),$mid]);}}
 foreach(($value['messages']??[]) as $msg){$from=norm_phone((string)($msg['from']??''));$body=(string)($msg['text']['body']??'');if($from){$db->prepare('INSERT INTO inbound(phone,body) VALUES(?,?)')->execute([$from,substr($body,0,3000)]);if(preg_match('/^\s*(stop|unsubscribe|opt\s*out)\s*$/i',$body))$db->prepare('UPDATE contacts SET opted_out=1 WHERE phone=?')->execute([$from]);}}
}}
http_response_code(200);echo 'OK';
