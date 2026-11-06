<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use DomainException;
use PDO;

/** Read-only questionnaire runtime; JSON files are import templates only. */
final class QuestionnaireService
{
    public function __construct(private ?PDO $db = null) {}

    public function questions(int $campaignId, int $configVersion): array
    {
        $stmt=($this->db??Database::connection())->prepare('SELECT code,prompt,help_text,response_type,required,position,options_json,validation_json FROM questions WHERE campaign_id=? AND config_version=? ORDER BY position,id');
        $stmt->execute([$campaignId,$configVersion]);$rows=$stmt->fetchAll();
        if(!$rows)throw new DomainException('No existe un cuestionario configurado para esta campaña y versión.');
        foreach($rows as &$row){
            $row['options']=$row['options_json']?json_decode($row['options_json'],true,512,JSON_THROW_ON_ERROR):[];
            $row['validation']=$row['validation_json']?json_decode($row['validation_json'],true,512,JSON_THROW_ON_ERROR):[];
            $row['type']=$row['response_type'];$row['n']=in_array($row['code'],['sexo','tsi'],true)?null:(int)$row['position'];
            unset($row['options_json'],$row['validation_json'],$row['response_type']);
        }
        unset($row);return $rows;
    }

    /** @return array{answers:array<string,string>,warnings:array<string,string>,observations:array<string,string>} */
    public function validate(array $input,int $campaignId,int $configVersion): array
    {
        $questions=$this->questions($campaignId,$configVersion);$out=[];$errors=[];$warnings=[];$observations=[];
        foreach($questions as $q){
            $code=(string)$q['code'];$value=trim((string)($input[$code]??''));
            if($value===''){if((int)$q['required']===1)$errors[$code]='Este campo es obligatorio.';continue;}
            $validation=$q['validation'];
            if($q['type']==='single_choice'){
                if(($validation['options_source']??null)==='tsi_categories'){
                    $ts=($this->db??Database::connection())->prepare('SELECT 1 FROM tsi_categories WHERE code=? AND enabled=1');$ts->execute([$value]);if(!$ts->fetchColumn())$errors[$code]='Selecciona una categoría TSI habilitada.';
                }elseif(!array_key_exists($value,$q['options']))$errors[$code]='Selecciona una opción válida.';
            }elseif(in_array($q['type'],['integer','decimal'],true)){
                $normalized=str_replace(',','.',$value);
                if(!is_numeric($normalized)||!is_finite((float)$normalized))$errors[$code]='Introduce un valor numérico válido.';
                elseif($q['type']==='integer'&&filter_var($value,FILTER_VALIDATE_INT)===false)$errors[$code]='Introduce un número entero.';
                else{
                    $number=(float)$normalized;
                    if($number<0)$errors[$code]='El valor no puede ser negativo.';
                    else{
                        if(isset($validation['min'])&&$number<(float)$validation['min'])$this->classifyThreshold($code,'min',$validation,$errors,$warnings,$observations);
                        if(isset($validation['max'])&&$number>(float)$validation['max'])$this->classifyThreshold($code,'max',$validation,$errors,$warnings,$observations);
                    }
                }
            }
            $out[$code]=$value;
        }
        if($errors)throw new ValidationException($errors,$out,$warnings,$observations);
        return ['answers'=>$out,'warnings'=>$warnings,'observations'=>$observations];
    }

    private function classifyThreshold(string $code,string $bound,array $rule,array &$errors,array &$warnings,array &$observations): void
    {
        $severity=$rule[$bound.'_severity']??'block';$message=$rule[$bound.'_message']??'El valor queda fuera del intervalo configurado.';
        if($severity==='warning')$warnings[$code]=$message;
        elseif($severity==='observation')$observations[$code]=$message;
        else $errors[$code]=$message;
    }
}
