<?php
require __DIR__.'/functions.php';
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
$c=cfg();date_default_timezone_set($c['timezone']);$db=db();
if(!$c['enable_live_sending']) {echo "DRY MODE. No API calls sent.\n";exit;}
// Exit if another worker is running. No repeat to same recipient in same campaign even on API ambiguity.
$lock=fopen(__DIR__.'/worker.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB)){exit("Another worker active.\n");}
$day=date('Y-m-d');$rate=(float)$c['estimated_pkr_per_message'];$budget=(float)$c['campaign_budget_pkr'];
$used=(float)$db->query("SELECT COALESCE(sum(cost_estimate),0) FROM sends WHERE state IN ('submitted','sent','delivered','read')")->fetchColumn();
foreach($db->query('SELECT * FROM campaigns WHERE running=1 ORDER BY id') as $campaign){
 $id=(int)$campaign['id'];$q=$db->prepare("SELECT count(*) FROM sends WHERE campaign_id=? AND attempted_at>=? AND attempted_at<?");$q->execute([$id,$day.' 00:00:00',date('Y-m-d',strtotime('+1 day')).' 00:00:00']);$done=(int)$q->fetchColumn();
 $allow=min((int)$campaign['daily_limit']-$done,(int)$c['max_per_run']);
 while($allow-->0){
  if($used+$rate>$budget){echo "Estimated budget guard reached.\n";exit;}
  $s=$db->prepare("SELECT id,phone FROM contacts WHERE consent=1 AND opted_out=0 AND id NOT IN (SELECT contact_id FROM sends WHERE campaign_id=?) ORDER BY id LIMIT 1");$s->execute([$id]);$contact=$s->fetch();if(!$contact)break;
  $db->prepare("INSERT OR IGNORE INTO sends(campaign_id,contact_id,state,attempted_at,updated_at) VALUES(?,?,'attempting',datetime('now'),datetime('now'))")->execute([$id,$contact['id']]);
  try{$r=send_template($contact['phone'],$campaign);}catch(Throwable $e){$r=['ok'=>false,'id'=>null,'error'=>$e->getMessage()];}
  $state=$r['ok']?'submitted':'failed';
  $db->prepare("UPDATE sends SET state=?,message_id=?,error=?,cost_estimate=?,updated_at=datetime('now') WHERE campaign_id=? AND contact_id=?")->execute([$state,$r['id'],substr($r['error']??'',0,500),$r['ok']?$rate:0,$id,$contact['id']]);
  if($r['ok'])$used+=$rate;
  echo "Campaign $id contact {$contact['id']} $state\n";
  if(!$r['ok']&&in_array((int)($r['http']??0),[401,403,429],true)){echo "API permission/rate error; halted.\n";exit;}
  usleep(250000);
 }
}
