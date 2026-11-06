<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

use App\Core\{Database,Security};
use App\Services\{AuthService,QuestionnaireService,ScoringService,ValidationException};

Security::boot();
function db(): PDO { return Database::connection(); }
function closeExpiredCampaigns(): void {
    $pdo=db();$pdo->beginTransaction();
    try{
        $ids=$pdo->query("SELECT id FROM campaigns WHERE state='active' AND ends_at IS NOT NULL AND ends_at<NOW() FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        $close=$pdo->prepare("UPDATE campaigns SET state='closed' WHERE id=? AND state='active' AND ends_at<NOW()");
        $audit=$pdo->prepare("INSERT INTO audit_log(user_id,action,entity_type,entity_id,result,metadata_json) VALUES(NULL,'campaign_auto_close','campaign',?,'success',?)");
        foreach($ids as $id){$close->execute([$id]);if($close->rowCount()===1)$audit->execute([(string)$id,json_encode(['reason'=>'end_date_reached'],JSON_THROW_ON_ERROR)]);}
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function pharmacySoe(int $pharmacyId): string {
    $q=db()->prepare('SELECT soe FROM pharmacies WHERE id=? AND enabled=1');$q->execute([$pharmacyId]);$soe=(string)($q->fetchColumn()?:'');
    if(!preg_match('/\\AOR-[0-9]{3}-F\\z/D',$soe))throw new DomainException('El SOE de la farmacia no tiene el formato OR-XXX-F. Solicita su corrección a administración.');
    return $soe;
}
function page(string $title, string $content): never {
    $user=Security::user();
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=Security::e($title)?></title><link rel="stylesheet" href="assets/app.css"></head><body><header class="top"><strong>Campaña Diabetes 2026</strong><?php if($user):?><nav class="mainnav"><?php if($user['role']==='admin'):?><a href="?r=admin/dashboard">Inicio</a><a href="?r=admin/campaigns">Campañas</a><a href="?r=admin/questions">Preguntas</a><a href="?r=admin/rules">Reglas</a><a href="?r=admin/tsi">TSI</a><a href="?r=admin/pharmacies">Farmacias</a><a href="?r=admin/users">Usuarios</a><a href="?r=admin/records">Registros</a><a href="?r=admin/incidents">Incidencias</a><a href="?r=admin/stats">Estadísticas</a><a href="?r=admin/export">Exportar</a><a href="?r=admin/dictionary">Diccionario</a><a href="?r=admin/audit">Auditoría</a><?php else:?><a href="?r=home">Inicio</a><a href="?r=records">Mis registros</a><?php endif?></nav><span><?=Security::e($user['username'])?> · <?=Security::e($user['role'])?></span><form method="post" action="?r=logout"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><button class="link">Cerrar sesión</button></form><?php endif?></header><main class="wrap"><h1><?=Security::e($title)?></h1><?=$content?></main></body></html><?php exit;
}
function flash(string $msg,string $kind='info'): string { return '<div class="notice '.Security::e($kind).'">'.Security::e($msg).'</div>'; }
function activeCampaign(): ?array {
    $q=db()->query("SELECT id,name,state,starts_at,ends_at,config_version FROM campaigns WHERE state='active' AND starts_at IS NOT NULL AND ends_at IS NOT NULL AND starts_at<=NOW() AND ends_at>=NOW() ORDER BY id DESC LIMIT 1");
    return $q->fetch() ?: null;
}
function renderField(array $q,array $values=[],array $errors=[]): string {
    $code=(string)$q['code'];$value=(string)($values[$code]??'');$required=(int)$q['required']===1?' required':'';
    $html='<label class="field"><span>'.Security::e(($q['n']!==null?$q['n'].'. ':'').$q['prompt']).'</span>';
    if($q['type']==='single_choice'){
        $html.='<select name="'.Security::e($code).'"'.$required.'><option value="">Selecciona…</option>';
        foreach(($q['options']??[]) as $option=>$label) if(is_string($label)) $html.='<option value="'.Security::e($option).'"'.($value===(string)$option?' selected':'').'>'.Security::e($label).'</option>';
        if(($q['validation']['options_source']??'')==='tsi_categories'){
            foreach(db()->query('SELECT code,label FROM tsi_categories WHERE enabled=1 ORDER BY position,code')->fetchAll() as $option)$html.='<option value="'.Security::e($option['code']).'"'.($value===$option['code']?' selected':'').'>'.Security::e($option['code'].' — '.$option['label']).'</option>';
        }
        $html.='</select>';
    }else{
        $type=match($q['type']){'integer','decimal'=>'number',default=>'text'};$step=$q['type']==='decimal'?'any':($q['type']==='integer'?'1':null);
        $html.='<input name="'.Security::e($code).'" type="'.$type.'"'.($step?' step="'.$step.'"':'').$required.' value="'.Security::e($value).'">';
    }
    if(isset($errors[$code]))$html.='<small class="error">'.Security::e($errors[$code]).'</small>';
    return $html.'</label>';
}
$r=(string)($_GET['r']??'');
if(str_starts_with($r,'admin-'))$r='admin/'.substr($r,6);
try {
    closeExpiredCampaigns();
    if($r==='login'){
        if($_SERVER['REQUEST_METHOD']==='POST'){
            Security::verifyCsrf();
            if((new AuthService())->login(trim((string)($_POST['username']??'')),(string)($_POST['password']??''))){header('Location: ?r=home');exit;}
            $msg='Usuario o contraseña incorrectos, cuenta deshabilitada o acceso temporalmente bloqueado.';
        }
        $content=isset($_GET['disabled'])?flash('La cuenta está deshabilitada o ya no tiene acceso.','error'):(isset($msg)?flash($msg,'error'):'');
        $content.='<form method="post" class="card"><input type="hidden" name="_csrf" value="'.Security::e(Security::csrf()).'"><label class="field">Usuario<input name="username" autocomplete="username" required></label><label class="field">Contraseña<input type="password" name="password" autocomplete="current-password" required></label><button>Iniciar sesión</button></form>';
        page('Acceso a la campaña',$content);
    }
    if($r==='logout'&&$_SERVER['REQUEST_METHOD']==='POST'){Security::verifyCsrf();(new AuthService())->logout();header('Location: ?r=login');exit;}

    $u=Security::requireLogin();
    if(!empty($u['must_change_password'])&&$r!=='password') {header('Location: ?r=password');exit;}
    if($r==='password'){
        $msg='';
        if($_SERVER['REQUEST_METHOD']==='POST'){
            Security::verifyCsrf();
            try{(new AuthService())->changeOwnPassword((int)$u['id'],(string)($_POST['current_password']??''),(string)($_POST['new_password']??''));header('Location: ?r=home&password_changed=1');exit;}
            catch(PDOException $e){error_log((string)$e);$msg='No se pudo cambiar la contraseña por un problema interno. Contacta con administración.';}
            catch(Throwable $e){$msg=$e->getMessage();}
        }
        $content=isset($msg)&&$msg!==''?flash($msg,'error'):'';
        $content.='<form method="post" class="card"><input type="hidden" name="_csrf" value="'.Security::e(Security::csrf()).'"><p>La contraseña debe tener al menos 14 caracteres.</p><label class="field">Contraseña actual<input type="password" name="current_password" autocomplete="current-password"'.(!empty($u['must_change_password'])?'':' required').'></label><label class="field">Nueva contraseña<input type="password" name="new_password" minlength="14" maxlength="4096" autocomplete="new-password" required></label><button>Guardar contraseña</button></form>';
        page('Cambiar contraseña',$content);
    }
    if(str_starts_with($r,'admin/')){
        Security::requireRole('admin');
        (new \App\Controllers\AdminController())->dispatch($r,$u);
    }
    if($r===''||$r==='home'){
        if($u['role']==='admin'){
            header('Location: ?r=admin/dashboard');exit;
        }
        Security::requireRole('pharmacy');$pharmacySoe=pharmacySoe((int)$u['pharmacy_id']);$c=activeCampaign();
        $content=isset($_GET['password_changed'])?flash('Contraseña actualizada.'):'';
        $content.='<section class="card"><p>Farmacia: '.Security::e($pharmacySoe).'</p>';
        if($c)$content.='<p>Campaña: <strong>'.Security::e($c['name']).'</strong> · Activa · Versión '.(int)$c['config_version'].'</p><p><a class="button" href="?r=new">Registrar paciente</a> <a href="?r=records">Ver mis registros</a></p>';
        else $content.='<p>No hay campaña activa dentro de unas fechas de inicio y fin definidas.</p>';
        $content.='</section>';page('Panel de farmacia',$content);
    }

    if($u['role']!=='pharmacy'){http_response_code(403);page('Acceso no permitido',flash('Esta acción solo está disponible para farmacias.','error'));}
    Security::requireRole('pharmacy');
    $pharmacySoe=pharmacySoe((int)$u['pharmacy_id']);

    if($r==='new'||$r==='edit'){
        $values=[];$errors=[];$warnings=[];$observations=[];$recordId=null;$record=null;$campaign=null;
        if($r==='new')$campaign=activeCampaign();
        else{
            $recordId=(int)($_GET['id']??0);
            $q=db()->prepare("SELECT pr.*,c.name AS campaign_name,c.state AS campaign_state,c.starts_at,c.ends_at,c.config_version AS current_config_version FROM patient_records pr JOIN campaigns c ON c.id=pr.campaign_id WHERE pr.id=? AND pr.pharmacy_id=? AND pr.record_status='valid'");
            $q->execute([$recordId,$u['pharmacy_id']]);$record=$q->fetch();
            if(!$record){http_response_code(404);page('Registro no encontrado',flash('No se encontró un registro válido de esta farmacia.','error'));}
            if($record['campaign_state']!=='active'||empty($record['starts_at'])||empty($record['ends_at'])||strtotime($record['starts_at'])>time()||strtotime($record['ends_at'])<time()||(int)$record['config_version']!==(int)$record['current_config_version']){page('Corrección no disponible',flash('La corrección no está permitida con el estado actual de la campaña/configuración.','warn'));}
            $campaign=['id'=>(int)$record['campaign_id'],'config_version'=>(int)$record['config_version'],'name'=>$record['campaign_name']];
            $a=db()->prepare('SELECT question_code,answer_value FROM record_answers WHERE record_id=?');$a->execute([$recordId]);foreach($a as $row)$values[$row['question_code']]=$row['answer_value'];$values['patient_code']=$record['patient_code'];
        }
        if(!$campaign)page('Registro no disponible',flash('No hay campaña activa con fechas definidas.','warn'));
        $service=new QuestionnaireService();$questions=$service->questions((int)$campaign['id'],(int)$campaign['config_version']);
        if($_SERVER['REQUEST_METHOD']==='POST'){
            Security::verifyCsrf();$values=$_POST;
            try{
                $validation=$service->validate($_POST,(int)$campaign['id'],(int)$campaign['config_version']);$answers=$validation['answers'];$warnings=$validation['warnings'];$observations=$validation['observations'];
                $patient=trim((string)($_POST['patient_code']??''));if(!preg_match('/\\A[0-9]{1,64}\\z/D',$patient))throw new DomainException('Introduce únicamente dígitos para el número correlativo.');
                $patient=ltrim($patient,'0');if($patient==='')throw new DomainException('El número correlativo debe ser mayor que cero.');
                $pdo=db();$pdo->beginTransaction();
                $cq=$pdo->prepare("SELECT * FROM campaigns WHERE id=? AND state='active' AND starts_at IS NOT NULL AND ends_at IS NOT NULL AND starts_at<=NOW() AND ends_at>=NOW() FOR UPDATE");$cq->execute([$campaign['id']]);$freshCampaign=$cq->fetch();
                if(!$freshCampaign){$pdo->rollBack();closeExpiredCampaigns();throw new DomainException('La campaña ya no está activa o ha superado su fecha de cierre.');}
                if((int)$freshCampaign['config_version']!==(int)$campaign['config_version'])throw new DomainException('La configuración de campaña cambió. Vuelve a cargar el formulario.');
                if($r==='edit'){
                    $check=$pdo->prepare("SELECT id,patient_code,revision_number FROM patient_records WHERE id=? AND pharmacy_id=? AND record_status='valid' AND config_version=? FOR UPDATE");$check->execute([$recordId,$u['pharmacy_id'],$campaign['config_version']]);$previous=$check->fetch();if(!$previous)throw new DomainException('El registro ya no está disponible para corrección.');
                    $oldA=$pdo->prepare('SELECT question_code,answer_value FROM record_answers WHERE record_id=?');$oldA->execute([$recordId]);$oldAnswers=[];foreach($oldA as $oldRow)$oldAnswers[$oldRow['question_code']]=$oldRow['answer_value'];
                    $oldC=$pdo->prepare('SELECT scale,outcome_status,total_score,interpretation,rule_version,item_scores_json,is_current,calculated_at FROM record_calculations WHERE record_id=? ORDER BY id');$oldC->execute([$recordId]);$oldCalcs=$oldC->fetchAll();$nextRevision=(int)$previous['revision_number']+1;
                    $rev=$pdo->prepare('INSERT INTO record_revisions(record_id,revision_number,changed_by,old_patient_code,old_answers_json,old_calculations_json) VALUES(?,?,?,?,?,?)');$rev->execute([$recordId,$nextRevision,$u['id'],$previous['patient_code'],json_encode($oldAnswers,JSON_THROW_ON_ERROR),json_encode($oldCalcs,JSON_THROW_ON_ERROR)]);
                    $pdo->prepare('UPDATE record_calculations SET is_current=0 WHERE record_id=? AND is_current=1')->execute([$recordId]);
                    $up=$pdo->prepare('UPDATE patient_records SET patient_code=?,updated_by=?,revision_number=? WHERE id=? AND pharmacy_id=?');$up->execute([$patient,$u['id'],$nextRevision,$recordId,$u['pharmacy_id']]);$pdo->prepare('DELETE FROM record_answers WHERE record_id=?')->execute([$recordId]);$id=$recordId;$action='record_update';
                }else{
                    $ins=$pdo->prepare('INSERT INTO patient_records(campaign_id,config_version,pharmacy_id,patient_code,created_by,updated_by) VALUES(?,?,?,?,?,?)');$ins->execute([$campaign['id'],$campaign['config_version'],$u['pharmacy_id'],$patient,$u['id'],$u['id']]);$id=(int)$pdo->lastInsertId();$action='record_create';
                }
                $ans=$pdo->prepare('INSERT INTO record_answers(record_id,question_code,answer_value) VALUES(?,?,?)');foreach($answers as $code=>$val)$ans->execute([$id,$code,$val]);
                $rule=$pdo->prepare('SELECT scale,rule_json FROM score_rules WHERE campaign_id=? AND config_version=?');$rule->execute([$campaign['id'],$campaign['config_version']]);$config=[];foreach($rule as $rr)$config[$rr['scale']]=json_decode($rr['rule_json'],true,512,JSON_THROW_ON_ERROR);
                $scorer=new ScoringService();$find=$scorer->calculateFindrisc($answers,$config['FINDRISC']??[]);$medas=$scorer->calculateMedas($answers,$config['MEDAS']??[]);
                $calc=$pdo->prepare('INSERT INTO record_calculations(record_id,scale,outcome_status,total_score,interpretation,rule_version,item_scores_json) VALUES(?,?,?,?,?,?,?)');
                foreach(['FINDRISC'=>$find,'MEDAS'=>$medas] as $scale=>$result)$calc->execute([$id,$scale,$result['status'],$result['total'],$result['interpretation'],$campaign['config_version'],json_encode($result['items'],JSON_THROW_ON_ERROR)]);
                foreach($warnings as $field=>$warning){$flag=$pdo->prepare("INSERT INTO record_incidents(record_id,created_by,incident_type,description,status) VALUES(?,?,? ,?,'open')");$flag->execute([$id,$u['id'],'validation_warning',$field.': '.$warning]);}
                foreach($observations as $field=>$observation){$flag=$pdo->prepare("INSERT INTO record_incidents(record_id,created_by,incident_type,description,status) VALUES(?,?,? ,?,'open')");$flag->execute([$id,$u['id'],'validation_observation',$field.': '.$observation]);}
                $audit=$pdo->prepare('INSERT INTO audit_log(user_id,action,entity_type,entity_id,result,metadata_json,ip_address) VALUES(?,?,?,?,?,?,?)');$audit->execute([$u['id'],$action,'patient_record',(string)$id,'success',json_encode(['campaign_id'=>$campaign['id'],'config_version'=>$campaign['config_version'],'warning_fields'=>array_keys($warnings),'observation_fields'=>array_keys($observations)],JSON_THROW_ON_ERROR),substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)]);
                $pdo->commit();header('Location: ?r=records&saved=1');exit;
            }catch(ValidationException $e){if(db()->inTransaction())db()->rollBack();$errors=$e->errors;$warnings=$e->warnings;$observations=$e->observations;$values=array_merge($values,$e->answers);$msg=$e->getMessage();}
            catch(DomainException $e){if(db()->inTransaction())db()->rollBack();$msg=$e->getMessage();}
            catch(Throwable $e){if(db()->inTransaction())db()->rollBack();error_log((string)$e);$msg=$e instanceof PDOException&&$e->getCode()==='23000'?'Ese código ya está usado por esta farmacia en la campaña.':'No se pudo guardar el registro. Contacta con administración.';}
        }
        $content=isset($msg)?flash($msg,'error'):'';foreach($warnings as $code=>$warning)$content.=flash('Revisión sugerida — '.$code.': '.$warning,'warn');foreach($observations as $code=>$observation)$content.=flash('Observación — '.$code.': '.$observation,'info');
        if($r==='edit')$content.=flash('Identificador completo actual: '.$pharmacySoe.'-'.$record['patient_code']);
        $content.='<form method="post" class="card"><input type="hidden" name="_csrf" value="'.Security::e(Security::csrf()).'"><p>SOE de esta farmacia: <strong>'.Security::e($pharmacySoe).'</strong>. Identificador completo: <strong>'.Security::e($pharmacySoe).'-número</strong>.</p><label class="field"><span>Número correlativo positivo del paciente (solo dígitos, por ejemplo 1)</span><input name="patient_code" inputmode="numeric" pattern="[0-9]{1,64}" required value="'.Security::e($values['patient_code']??'').'"></label><div class="formgrid">';
        foreach($questions as $q)$content.=renderField($q,$values,$errors);$content.='</div><button>Guardar registro</button> <a href="?r=records">Cancelar</a></form>';
        page($r==='edit'?'Corregir registro':'Nuevo registro',$content);
    }
    if($r==='view'){
        $id=(int)($_GET['id']??0);$q=db()->prepare('SELECT pr.*,p.soe,c.name AS campaign_name FROM patient_records pr JOIN pharmacies p ON p.id=pr.pharmacy_id JOIN campaigns c ON c.id=pr.campaign_id WHERE pr.id=? AND pr.pharmacy_id=?');$q->execute([$id,$u['pharmacy_id']]);$record=$q->fetch();
        if(!$record){http_response_code(404);page('Registro no encontrado',flash('No se encontró un registro de esta farmacia.','error'));}
        $answers=db()->prepare('SELECT q.position,q.prompt,a.question_code,a.answer_value FROM record_answers a LEFT JOIN questions q ON q.campaign_id=? AND q.config_version=? AND q.code=a.question_code WHERE a.record_id=? ORDER BY q.position,a.question_code');$answers->execute([$record['campaign_id'],$record['config_version'],$id]);
        $scores=db()->prepare('SELECT scale,outcome_status,total_score,interpretation,rule_version,is_current,calculated_at FROM record_calculations WHERE record_id=? ORDER BY scale,is_current DESC,id DESC');$scores->execute([$id]);
        $content='<section class="card"><p><strong>Identificador:</strong> '.Security::e($record['soe'].'-'.$record['patient_code']).' · <strong>Campaña:</strong> '.Security::e($record['campaign_name']).' · <strong>Estado:</strong> '.Security::e($record['record_status']).'</p><h2>Respuestas registradas (solo lectura)</h2>';
        foreach($answers as $a)$content.='<div class="row"><strong>'.Security::e(($a['position']?$a['position'].'. ':'').($a['prompt']??$a['question_code'])).'</strong><span>'.Security::e($a['answer_value']).'</span></div>';
        $content.='<h2>Resultados</h2>';foreach($scores as $s){$label=$s['outcome_status']==='not_applicable'?'No aplicable':(string)$s['total_score'].' — '.(string)$s['interpretation'];$content.='<div class="row"><span>'.Security::e($s['scale']).': '.Security::e($label).' · '.((int)$s['is_current']===1?'Vigente':'Histórico').'</span><small>Versión '.(int)$s['rule_version'].' · '.Security::e($s['calculated_at']).'</small></div>';}
        $content.='<p><a href="?r=records">Volver a mis registros</a></p></section>';page('Detalle del registro',$content);
    }
    if($r==='records'){
        $q=db()->prepare('SELECT pr.id,pr.patient_code,pr.record_status,pr.created_at,p.soe FROM patient_records pr JOIN pharmacies p ON p.id=pr.pharmacy_id WHERE pr.pharmacy_id=? ORDER BY pr.created_at DESC');$q->execute([$u['pharmacy_id']]);$rows=$q->fetchAll();
        $cq=db()->prepare('SELECT rc.record_id,rc.scale,rc.outcome_status,rc.total_score,rc.interpretation FROM record_calculations rc JOIN patient_records pr ON pr.id=rc.record_id WHERE pr.pharmacy_id=? AND rc.is_current=1 ORDER BY rc.id');$cq->execute([$u['pharmacy_id']]);$currentScores=[];foreach($cq as $score)$currentScores[(int)$score['record_id']][$score['scale']]=$score;
        $content=isset($_GET['saved'])?flash('Registro guardado correctamente.'):'';$content.='<section class="card"><p><a class="button" href="?r=new">Nuevo registro</a></p><p>El listado muestra únicamente puntuaciones vigentes; las históricas se conservan para trazabilidad.</p>';
        if(!$rows)$content.='<p>Aún no hay registros.</p>';
        foreach($rows as $row){$id=(int)$row['id'];$complete=$row['soe'].'-'.$row['patient_code'];$content.='<div class="row"><span>'.Security::e($complete).' · '.Security::e($row['created_at']).' · '.Security::e($row['record_status']).'</span><span>';
            foreach(['FINDRISC','MEDAS'] as $scale){$score=$currentScores[$id][$scale]??null;if($score){$label=$score['outcome_status']==='not_applicable'?'No aplicable':(string)$score['total_score'].' — '.(string)$score['interpretation'];$content.=Security::e($scale.': '.$label).' · ';}}
            $content.='</span><a href="?r=view&id='.$id.'">Consultar'.($row['record_status']==='valid'?' / corregir':'').'</a>';if($row['record_status']==='valid')$content.='<a href="?r=edit&id='.$id.'">Corregir</a>';$content.='</div>';}
        $content.='</section>';page('Mis registros',$content);
    }
    http_response_code(404);page('Página no encontrada',flash('La ruta solicitada no existe.','error'));
}catch(Throwable $e){error_log((string)$e);http_response_code(500);page('Error',flash(getenv('APP_DEBUG')==='true'?$e->getMessage():'No se pudo completar la operación. Contacta con administración.','error'));}
