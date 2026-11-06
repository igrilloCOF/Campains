<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\ScoringService;

function sameValue(mixed $expected,mixed $actual,string $case): void {
    if($expected!==$actual)throw new RuntimeException($case.': esperado '.var_export($expected,true).', recibido '.var_export($actual,true));
}
function caseFindrisc(array $overrides,array $cfg,ScoringService $service): array {
    $answers=['diabetes_actual'=>'no','edad'=>'40','peso_kg'=>'70','altura_m'=>'1.70','cintura_cm'=>'90','sexo'=>'hombre','actividad_fisica'=>'si','fruta_verdura_diaria'=>'diario','hipertension_medicacion'=>'no','hiperglucemia_previa'=>'no','antecedentes_familiares'=>'ninguno'];
    return $service->calculateFindrisc(array_replace($answers,$overrides),$cfg);
}
$all=json_decode((string)file_get_contents(dirname(__DIR__).'/config/scoring.example.json'),true,512,JSON_THROW_ON_ERROR);
$rules=$all['findrisc'];$service=new ScoringService();

// EDAD: comprobaciones independientes en las fronteras aprobadas.
sameValue(0,caseFindrisc(['edad'=>'44'],$rules,$service)['items']['age'],'edad 44');
sameValue(2,caseFindrisc(['edad'=>'45'],$rules,$service)['items']['age'],'edad 45');
sameValue(2,caseFindrisc(['edad'=>'54'],$rules,$service)['items']['age'],'edad 54');
sameValue(3,caseFindrisc(['edad'=>'55'],$rules,$service)['items']['age'],'edad 55');
sameValue(3,caseFindrisc(['edad'=>'64'],$rules,$service)['items']['age'],'edad 64');
sameValue(4,caseFindrisc(['edad'=>'65'],$rules,$service)['items']['age'],'edad 65 (>64)');

// IMC: altura 1m permite representar sin redondeo cada corte decimal configurado.
sameValue(0,caseFindrisc(['peso_kg'=>'24.9','altura_m'=>'1'],$rules,$service)['items']['bmi'],'IMC 24.9');
sameValue(1,caseFindrisc(['peso_kg'=>'25','altura_m'=>'1'],$rules,$service)['items']['bmi'],'IMC 25');
sameValue(1,caseFindrisc(['peso_kg'=>'29.9','altura_m'=>'1'],$rules,$service)['items']['bmi'],'IMC 29.9');
sameValue(3,caseFindrisc(['peso_kg'=>'30','altura_m'=>'1'],$rules,$service)['items']['bmi'],'IMC 30');
sameValue(3,caseFindrisc(['peso_kg'=>'30.04','altura_m'=>'1'],$rules,$service)['items']['bmi'],'IMC 30.04 redondea a 30.0');

// CINTURA HOMBRE: sin arrays con clave float; 102 es un caso explícito.
sameValue(0,caseFindrisc(['cintura_cm'=>'93.9'],$rules,$service)['items']['waist'],'cintura hombre 93.9');
sameValue(3,caseFindrisc(['cintura_cm'=>'94'],$rules,$service)['items']['waist'],'cintura hombre 94');
sameValue(3,caseFindrisc(['cintura_cm'=>'102'],$rules,$service)['items']['waist'],'cintura hombre 102');
sameValue(4,caseFindrisc(['cintura_cm'=>'102.01'],$rules,$service)['items']['waist'],'cintura hombre 102.01');

// CINTURA MUJER.
sameValue(0,caseFindrisc(['sexo'=>'mujer','cintura_cm'=>'79.9'],$rules,$service)['items']['waist'],'cintura mujer 79.9');
sameValue(3,caseFindrisc(['sexo'=>'mujer','cintura_cm'=>'80'],$rules,$service)['items']['waist'],'cintura mujer 80');
sameValue(3,caseFindrisc(['sexo'=>'mujer','cintura_cm'=>'88'],$rules,$service)['items']['waist'],'cintura mujer 88');
sameValue(4,caseFindrisc(['sexo'=>'mujer','cintura_cm'=>'88.01'],$rules,$service)['items']['waist'],'cintura mujer 88.01');

// Casos discretos de puntuación e inaplicabilidad por diabetes actual.
$r=caseFindrisc(['actividad_fisica'=>'no','fruta_verdura_diaria'=>'no_diario','hipertension_medicacion'=>'si_medicacion','hiperglucemia_previa'=>'si','antecedentes_familiares'=>'primer_grado'],$rules,$service);
sameValue(15,$r['total'],'suma FINDRISC conocida');
sameValue('Riesgo alto',$r['interpretation'],'categoría FINDRISC 15');
$r=caseFindrisc(['diabetes_actual'=>'si'],$rules,$service);
sameValue('not_applicable',$r['status'],'diabetes actual estado');sameValue(null,$r['total'],'diabetes actual sin puntuación numérica');

// MEDAS: todos los ítems, corte de interpretación y opciones no favorables.
$medasRules=$all['medas'];$answers=[];for($i=1;$i<=14;$i++)$answers[sprintf('medas_%02d',$i)]='si';
$r=$service->calculateMedas($answers,$medasRules);sameValue(14,$r['total'],'MEDAS máximo');sameValue('Buena adherencia a la dieta mediterránea',$r['interpretation'],'MEDAS 14');
foreach(['medas_10','medas_11','medas_12','medas_13','medas_14'] as $code)$answers[$code]='no';
$r=$service->calculateMedas($answers,$medasRules);sameValue(9,$r['total'],'MEDAS corte 9');sameValue('Buena adherencia a la dieta mediterránea',$r['interpretation'],'MEDAS 9');
$answers['medas_09']='no';$r=$service->calculateMedas($answers,$medasRules);sameValue(8,$r['total'],'MEDAS corte 8');sameValue('Baja adherencia a la dieta mediterránea',$r['interpretation'],'MEDAS 8');

echo "Pruebas de puntuación superadas.\n";
