<?php
declare(strict_types=1);

namespace App\Services;

use DomainException;

/** Pure calculation service: original answers are never modified. */
final class ScoringService
{
    /** @param array<string,string> $a */
    public function calculateFindrisc(array $a, array $rules): array
    {
        if (($a['diabetes_actual'] ?? null) === 'si') {
            return ['status'=>'not_applicable','total'=>null,'interpretation'=>'No aplicable: paciente con diabetes actual','items'=>[]];
        }
        $required = ['edad','peso_kg','altura_m','cintura_cm','sexo','actividad_fisica','fruta_verdura_diaria','hipertension_medicacion','hiperglucemia_previa','antecedentes_familiares','diabetes_actual'];
        $this->requireKeys($a, $required);
        $this->requireRuleKeys($rules, ['age','bmi','waist_male','waist_female','physical_activity','fruit_vegetables','antihypertensive','high_glucose','family_history','interpretation']);
        $age = $this->number($a['edad'], 'edad');
        $weight = $this->number($a['peso_kg'], 'peso');
        $height = $this->number($a['altura_m'], 'altura');
        if ($height <= 0 || $weight <= 0) throw new DomainException('Peso y altura deben ser mayores que cero.');
        $bmi = round($weight / ($height * $height), 1);
        $items = [];
        $items['age'] = $this->rangeScore($age, $rules['age']);
        $items['bmi'] = $this->rangeScore($bmi, $rules['bmi']);
        $waistRule = match ($a['sexo']) { 'hombre'=>$rules['waist_male'], 'mujer'=>$rules['waist_female'], default=>throw new DomainException('Sexo debe ser Hombre o Mujer.') };
        $items['waist'] = $this->rangeScore($this->number($a['cintura_cm'],'cintura'), $waistRule);
        $items['physical_activity'] = $this->choiceScore($a['actividad_fisica'], $rules['physical_activity']);
        $items['fruit_vegetables'] = $this->choiceScore($a['fruta_verdura_diaria'], $rules['fruit_vegetables']);
        $items['antihypertensive'] = $this->choiceScore($a['hipertension_medicacion'], $rules['antihypertensive']);
        $items['high_glucose'] = $this->choiceScore($a['hiperglucemia_previa'], $rules['high_glucose']);
        $items['family_history'] = $this->choiceScore($a['antecedentes_familiares'], $rules['family_history']);
        $total = array_sum($items);
        $interpretation = $this->interpret($total, $rules['interpretation']);
        return ['status'=>'calculated','total'=>$total,'interpretation'=>$interpretation,'items'=>$items,'derived'=>['bmi'=>$bmi]];
    }

    /** @param array<string,string> $a MEDAS response keys are medas_01...medas_14, each "si" or "no". */
    public function calculateMedas(array $a, array $rules): array
    {
        $this->requireRuleKeys($rules, ['items','interpretation']);
        if (count($rules['items']) !== 14) throw new DomainException('MEDAS debe tener exactamente 14 reglas configuradas.');
        $items=[]; $total=0;
        for ($i=1; $i<=14; $i++) {
            $code=sprintf('medas_%02d',$i);
            if (!array_key_exists($code,$a)) throw new DomainException("Falta respuesta requerida: {$code}.");
            $rule=$rules['items'][$code] ?? null;
            if (!is_array($rule) || !isset($rule['qualifying_answer'],$rule['score'])) throw new DomainException("Regla incompleta: {$code}.");
            $score = ((string)$a[$code] === (string)$rule['qualifying_answer']) ? (int)$rule['score'] : 0;
            if (!in_array($score,[0,1],true)) throw new DomainException("La regla {$code} debe puntuar 0 o 1.");
            $items[$code]=$score; $total += $score;
        }
        return ['status'=>'calculated','total'=>$total,'interpretation'=>$this->interpret($total,$rules['interpretation']),'items'=>$items];
    }

    private function number(string $value,string $label): float
    {
        $normalized=str_replace(',','.',$value);
        if ($normalized==='' || !is_numeric($normalized)) throw new DomainException("Valor numérico no válido para {$label}.");
        return (float)$normalized;
    }
    private function requireKeys(array $a,array $keys): void { foreach($keys as $k) if(!array_key_exists($k,$a)||$a[$k]==='') throw new DomainException("Falta una respuesta obligatoria: {$k}."); }
    private function requireRuleKeys(array $r,array $keys): void { foreach($keys as $k) if(!isset($r[$k])||!is_array($r[$k])) throw new DomainException("Configuración incompleta: {$k}."); }
    private function choiceScore(string $value,array $map): int { if(!array_key_exists($value,$map)) throw new DomainException('Respuesta sin regla de puntuación configurada.'); return (int)$map[$value]; }
    private function rangeScore(float $value,array $bands): int
    {
        foreach($bands as $b) {
            $min=$b['min']??null; $max=$b['max']??null;
            if (($min===null || ($b['min_inclusive']??true ? $value >= $min : $value > $min)) && ($max===null || ($b['max_inclusive']??false ? $value <= $max : $value < $max))) return (int)$b['score'];
        }
        throw new DomainException('El valor no está cubierto por los intervalos configurados.');
    }
    private function interpret(int $value,array $bands): string
    {
        foreach($bands as $b) if (($b['min']===null || $value >= $b['min']) && ($b['max']===null || $value <= $b['max'])) return (string)$b['label'];
        throw new DomainException('La puntuación no está cubierta por ninguna categoría de interpretación.');
    }
}
