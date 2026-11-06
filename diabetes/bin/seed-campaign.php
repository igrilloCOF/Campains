<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$db=Database::connection();$root=dirname(__DIR__);$qcfg=json_decode((string)file_get_contents($root.'/config/questionnaire.json'),true,512,JSON_THROW_ON_ERROR);$rules=json_decode((string)file_get_contents($root.'/config/scoring.example.json'),true,512,JSON_THROW_ON_ERROR);
$db->beginTransaction();
try{
 $q=$db->prepare('SELECT id FROM campaigns WHERE name=? LIMIT 1');$q->execute(['Campaña Diabetes 2026']);
 if($q->fetch())throw new RuntimeException('Ya existe una campaña Diabetes 2026; no se sobrescribió.');
 $db->prepare("INSERT INTO campaigns(name,state,config_version) VALUES('Campaña Diabetes 2026','preparation',1)")->execute();$id=(int)$db->lastInsertId();
 $snapshot=json_encode(['questionnaire'=>$qcfg,'scoring'=>$rules],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$hash=hash('sha256',$snapshot);
 $db->prepare("INSERT INTO campaign_config_versions(campaign_id,version,state,snapshot_sha256,change_note) VALUES(?,1,'draft',?,'Plantilla inicial aprobada por organizador')")->execute([$id,$hash]);
 $qi=$db->prepare('INSERT INTO questions(campaign_id,config_version,code,prompt,response_type,required,position,options_json,validation_json,is_builtin) VALUES(?,1,?,?,?,?,?,?,?,1)');
 foreach($qcfg['questions'] as $i=>$question){
  $type=match($question['type']){'integer'=>'integer','decimal'=>'decimal','single_choice'=>'single_choice',default=>'text'};
  $options=$question['options']??[];$validation=['required'=>true];
  if(($question['options_source']??'')==='tsi_categories'){$options=[];$validation['options_source']='tsi_categories';}
  $qi->execute([$id,$question['code'],$question['prompt'],$type,1,$i+1,json_encode($options,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($validation,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
 }
 $ri=$db->prepare('INSERT INTO score_rules(campaign_id,config_version,scale,rule_code,title,rule_json,position) VALUES(?,1,?,?,?,?,?)');
 $ri->execute([$id,'FINDRISC','bundle','Reglas FINDRISC',json_encode($rules['findrisc'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),1]);
 $ri->execute([$id,'MEDAS','bundle','Reglas MEDAS',json_encode($rules['medas'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),2]);
 $tsi=$db->prepare('INSERT IGNORE INTO tsi_categories(code,label,enabled,position) VALUES(?,?,1,?)');foreach(['001','002','003','004','005','006'] as $i=>$code)$tsi->execute([$code,$code,$i+1]);
 $db->commit();echo "Campaña de preparación creada (ID {$id}), configuración v1 guardada en MariaDB. Fechas pendientes.\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
