<?php
declare(strict_types=1);

/* Instalador autónomo. No carga app/bootstrap.php. */
$root = dirname(__DIR__);
$envPath = $root . '/.env';
$lockPath = $root . '/storage/installed.lock';
if (is_file($envPath) || is_file($lockPath)) {
    http_response_code(403);
    exit('El instalador está bloqueado porque ya existe una configuración o instalación.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    $installSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $installHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $installSecure = $installSecure || str_starts_with($installHost, 'localhost') || str_starts_with($installHost, '127.0.0.1');
    session_set_cookie_params(['httponly'=>true,'secure'=>$installSecure,'samesite'=>'Strict','path'=>'/']);
    session_start();
}
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function post(string $key, string $default = ''): string { return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default; }
function dbConnection(string $host, string $port, string $name, string $user, string $password): PDO {
    if (!preg_match('/^[A-Za-z0-9_.:-]{1,255}$/', $host) || !preg_match('/^\d{1,5}$/', $port) || (int)$port < 1 || (int)$port > 65535 || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
        throw new RuntimeException('Revisa el host, puerto y nombre de base de datos.');
    }
    return new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
$checks = [
    'PHP 8.1 o superior' => PHP_VERSION_ID >= 80100,
    'Extensión PDO' => extension_loaded('pdo'),
    'Driver PDO MySQL/MariaDB' => extension_loaded('pdo_mysql'),
    'Extensión JSON' => extension_loaded('json'),
    'Generación criptográfica segura' => function_exists('random_bytes'),
    'Raíz del proyecto escribible (para .env)' => is_writable($root),
    'Directorio storage escribible' => is_dir($root . '/storage') && is_writable($root . '/storage'),
    'Esquema y configuraciones disponibles' => is_readable($root . '/database/schema.sql') && is_readable($root . '/config/questionnaire.json') && is_readable($root . '/config/scoring.example.json'),
];
$error = '';
$success = '';
$values = ['host' => post('host', '127.0.0.1'), 'port' => post('port', '3306'), 'name' => post('name'), 'user' => post('user'), 'admin' => post('admin'), 'email' => post('email'), 'app_url'=>post('app_url')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals((string)$_SESSION['install_csrf'], post('csrf'))) throw new RuntimeException('La sesión del formulario expiró; recarga e inténtalo de nuevo.');
        foreach ($checks as $ok) if (!$ok) throw new RuntimeException('No se cumplen los requisitos del servidor. Corrígelos antes de instalar.');
        $dbPassword = (string)($_POST['db_password'] ?? '');
        $adminPassword = (string)($_POST['admin_password'] ?? '');
        if ($values['admin'] === '' || strlen($values['admin']) > 120) throw new RuntimeException('Indica un nombre de administrador válido (máximo 120 caracteres).');
        if (strlen($adminPassword) < 14) throw new RuntimeException('La contraseña inicial debe tener al menos 14 caracteres.');
        if (preg_match('/[\r\n\x00]/', $dbPassword . $adminPassword . $values['user']) || str_contains($dbPassword, '"') || str_contains($dbPassword, "'") || str_contains($values['user'], '"') || str_contains($values['user'], "'")) throw new RuntimeException('Las credenciales contienen caracteres no admitidos por el formato de configuración.');
        if ($values['email'] !== '' && (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 254)) throw new RuntimeException('Indica un correo válido o deja el campo vacío.');
        if ($values['app_url'] !== '') {
            $parts = parse_url($values['app_url']);
            $local = is_array($parts) && in_array(strtolower((string)($parts['host']??'')), ['localhost','127.0.0.1'], true);
            if (!filter_var($values['app_url'], FILTER_VALIDATE_URL) || !is_array($parts) || (!in_array(strtolower((string)($parts['scheme']??'')), ['https'], true) && !$local)) throw new RuntimeException('La URL base debe usar HTTPS; HTTP solo se admite en localhost para desarrollo.');
        }

        $pdo = dbConnection($values['host'], $values['port'], $values['name'], $values['user'], $dbPassword);
        // No se permite ejecutar ninguna operación destructiva ni instalar sobre una BD ya usada.
        $tables = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchColumn();
        if ((int)$tables !== 0) throw new RuntimeException('La base de datos no está vacía. No se modificó. Para una base existente, sigue el flujo de actualización documentado; el instalador solo sirve para bases nuevas vacías.');

        $qcfg = json_decode((string)file_get_contents($root . '/config/questionnaire.json'), true, 512, JSON_THROW_ON_ERROR);
        $rules = json_decode((string)file_get_contents($root . '/config/scoring.example.json'), true, 512, JSON_THROW_ON_ERROR);
        if (!isset($qcfg['questions'], $rules['findrisc'], $rules['medas']) || !is_array($qcfg['questions']) || count($qcfg['questions']) !== 36) {
            throw new RuntimeException('Los archivos de configuración no tienen el formato esperado (34 preguntas originales, sexo y TSI).');
        }
        $codes = array_column($qcfg['questions'], 'code');
        if (count(array_unique($codes)) !== 36 || !in_array('sexo', $codes, true) || !in_array('tsi', $codes, true)) throw new RuntimeException('El cuestionario debe contener 34 preguntas originales más sexo y TSI.');
        $schema = (string)file_get_contents($root . '/database/schema.sql');
        // El esquema consolidado ya contiene los cambios estructurales de 002 y 003.
        // Se ejecutan sus sentencias individualmente; no se aplican migraciones encima.
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $schema);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement !== '') $pdo->exec($statement);
        }
        $pdo->beginTransaction();
        try {
            $pdo->exec("INSERT INTO schema_migrations(version) VALUES ('002_configuration_history_security'), ('003_current_calculation_marker')");
            $pdo->prepare("INSERT INTO campaigns(name,starts_at,ends_at,state,config_version) VALUES(?,NULL,NULL,'preparation',1)")->execute(['Campaña Diabetes 2026']);
            $campaignId = (int)$pdo->lastInsertId();
            $snapshot = json_encode(['questionnaire' => $qcfg, 'scoring' => $rules], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $pdo->prepare("INSERT INTO campaign_config_versions(campaign_id,version,state,created_by,snapshot_sha256,change_note) VALUES(?,1,'draft',NULL,?,'Plantilla inicial de instalación')")->execute([$campaignId, hash('sha256', $snapshot)]);

            $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
            if ($hash === false) throw new RuntimeException('No se pudo proteger la contraseña inicial.');
            $stmt = $pdo->prepare("INSERT INTO users(role,pharmacy_id,username,email,password_hash,must_change_password,enabled) VALUES('admin',NULL,?,?,?,0,1)");
            $stmt->execute([$values['admin'], $values['email'] !== '' ? $values['email'] : null, $hash]);

            $insertQuestion = $pdo->prepare('INSERT INTO questions(campaign_id,config_version,code,prompt,response_type,required,position,options_json,validation_json,is_builtin) VALUES(?,1,?,?,?,?,?,?,?,1)');
            foreach ($qcfg['questions'] as $i => $question) {
                if (!isset($question['code'], $question['prompt'], $question['type']) || !is_string($question['code']) || !is_string($question['prompt'])) throw new RuntimeException('Una pregunta del archivo de configuración no es válida.');
                $type = match ($question['type']) { 'integer' => 'integer', 'decimal' => 'decimal', 'single_choice' => 'single_choice', default => 'text' };
                $options = $question['options'] ?? [];
                $validation = ['required' => (bool)($question['required'] ?? true)];
                if (($question['options_source'] ?? '') === 'tsi_categories') { $options = []; $validation['options_source'] = 'tsi_categories'; }
                $insertQuestion->execute([$campaignId, $question['code'], $question['prompt'], $type, (int)($question['required'] ?? true), $i + 1, json_encode($options, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), json_encode($validation, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            }
            $insertRule = $pdo->prepare('INSERT INTO score_rules(campaign_id,config_version,scale,rule_code,title,rule_json,position) VALUES(?,1,?,?,?,?,?)');
            $insertRule->execute([$campaignId, 'FINDRISC', 'bundle', 'Reglas FINDRISC', json_encode($rules['findrisc'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 1]);
            $insertRule->execute([$campaignId, 'MEDAS', 'bundle', 'Reglas MEDAS', json_encode($rules['medas'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 2]);
            $insertTsi = $pdo->prepare('INSERT INTO tsi_categories(code,label,enabled,position) VALUES(?,?,1,?)');
            foreach (['001', '002', '003', '004', '005', '006'] as $i => $code) $insertTsi->execute([$code, $code, $i + 1]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        // La configuración aparece solo al completar esquema y seed; la escritura es atómica.
        $parts = $values['app_url'] !== '' ? parse_url($values['app_url']) : false;
        $sessionSecure = !(is_array($parts) && strtolower((string)($parts['scheme']??'')) === 'http' && in_array(strtolower((string)($parts['host']??'')), ['localhost','127.0.0.1'], true));
        $env = "APP_ENV=production\nAPP_DEBUG=false\nAPP_BASE_URL=" . $values['app_url'] . "\nAPP_KEY=" . bin2hex(random_bytes(32)) . "\n" .
            'DB_HOST=' . $values['host'] . "\nDB_PORT=" . $values['port'] . "\nDB_NAME=" . $values['name'] . "\nDB_USER=" . $values['user'] . "\nDB_PASSWORD=" . $dbPassword . "\nDB_CHARSET=utf8mb4\nSESSION_SECURE=" . ($sessionSecure?'true':'false') . "\nSESSION_SAMESITE=Lax\n";
        $tempEnv = tempnam($root, '.env-install-');
        if ($tempEnv === false || file_put_contents($tempEnv, $env, LOCK_EX) === false || !chmod($tempEnv, 0600) || !rename($tempEnv, $envPath)) {
            if ($tempEnv !== false && is_file($tempEnv)) @unlink($tempEnv);
            throw new RuntimeException('No se pudo guardar la configuración de forma segura. No se creó el bloqueo del instalador.');
        }
        if (!is_dir($root . '/storage')) throw new RuntimeException('No se pudo encontrar el directorio storage.');
        $lockTemp = tempnam($root . '/storage', '.installed-');
        if ($lockTemp === false || file_put_contents($lockTemp, "installed\n", LOCK_EX) === false || !chmod($lockTemp, 0600) || !rename($lockTemp, $lockPath)) {
            if ($lockTemp !== false && is_file($lockTemp)) @unlink($lockTemp);
            @unlink($envPath);
            throw new RuntimeException('No se pudo finalizar el bloqueo seguro del instalador.');
        }
        $success = 'Instalación completada. La configuración y las credenciales no se muestran. Elimina o bloquea el acceso web a este instalador.';
        unset($dbPassword, $adminPassword, $_POST['db_password'], $_POST['admin_password']);
    } catch (Throwable $e) {
        // Mensajes previstos son seguros; errores PDO/SQL y filesystem se sustituyen por texto genérico.
        $safe = ['La sesión del formulario expiró; recarga e inténtalo de nuevo.', 'No se cumplen los requisitos del servidor. Corrígelos antes de instalar.', 'Indica un nombre de administrador válido (máximo 120 caracteres).', 'La contraseña inicial debe tener al menos 14 caracteres.', 'Indica un correo válido o deja el campo vacío.', 'Los archivos de configuración no tienen el formato esperado (34 preguntas originales, sexo y TSI).', 'El cuestionario debe contener 34 preguntas originales más sexo y TSI.', 'No se pudo proteger la contraseña inicial.', 'No se pudo guardar la configuración de forma segura. No se creó el bloqueo del instalador.', 'No se pudo encontrar el directorio storage.', 'No se pudo finalizar el bloqueo seguro del instalador.'];
        $message = $e->getMessage();
        $error = (in_array($message, $safe, true) || str_starts_with($message, 'La base de datos no está vacía.') || str_starts_with($message, 'Revisa el host,')) ? $message : 'No se pudo completar la instalación. Verifica los datos y permisos; no se muestran detalles internos ni SQL.';
    }
}
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Instalación | Campaña Diabetes</title><style>
body{font:16px system-ui,sans-serif;background:#f3f6f8;color:#172b3a;margin:0}.wrap{max-width:780px;margin:3rem auto;padding:0 1rem}.card{background:white;padding:2rem;border-radius:12px;box-shadow:0 4px 24px #17324d12}h1{margin-top:0}h2{font-size:1.15rem;margin-top:2rem}ul{padding-left:1.3rem}.ok{color:#176b42}.bad{color:#a11}.notice{padding:1rem;border-radius:8px;background:#eef5fb;margin:1rem 0}.error{background:#fff0ef;color:#8d2118}.success{background:#e9f8ef;color:#155c32}label{display:block;margin:.9rem 0 .25rem}input{box-sizing:border-box;width:100%;padding:.7rem;border:1px solid #aab8c2;border-radius:6px;font:inherit}button{margin-top:1.5rem;padding:.8rem 1.2rem;background:#125b76;color:white;border:0;border-radius:7px;font:inherit;font-weight:600;cursor:pointer}small{color:#526575}.grid{display:grid;grid-template-columns:1fr 1fr;gap:0 1rem}@media(max-width:600px){.grid{grid-template-columns:1fr}}
</style></head><body><main class="wrap"><section class="card"><h1>Instalación de Campaña Diabetes</h1><p>Configuración inicial de una instalación nueva. Solo se admite una base de datos vacía; este asistente no actualiza instalaciones existentes.</p>
<?php if ($error !== ''): ?><div class="notice error" role="alert"><?=h($error)?></div><?php endif; ?><?php if ($success !== ''): ?><div class="notice success" role="status"><?=h($success)?></div><?php endif; ?>
<h2>Comprobaciones del servidor</h2><ul><?php foreach ($checks as $label => $ok): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?=h($label)?>: <?= $ok ? 'correcto' : 'no cumple' ?></li><?php endforeach; ?></ul>
<?php if ($success === ''): ?><form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=h((string)$_SESSION['install_csrf'])?>"><h2>Base de datos MariaDB/MySQL</h2><div class="grid"><div><label for="host">Host</label><input id="host" name="host" required value="<?=h($values['host'])?>"></div><div><label for="port">Puerto</label><input id="port" name="port" required value="<?=h($values['port'])?>"></div><div><label for="name">Base de datos ya creada y vacía</label><input id="name" name="name" required value="<?=h($values['name'])?>"></div><div><label for="user">Usuario DB</label><input id="user" name="user" required value="<?=h($values['user'])?>"></div></div><label for="password">Contraseña DB</label><input id="password" name="db_password" type="password" autocomplete="new-password" required><label for="app-url">URL base (opcional; HTTPS en alojamiento)</label><input id="app-url" name="app_url" type="url" value="<?=h($values['app_url'])?>" placeholder="https://dominio.ejemplo.es"><h2>Cuenta administradora inicial</h2><label for="admin">Nombre de usuario</label><input id="admin" name="admin" required maxlength="120" value="<?=h($values['admin'])?>"><label for="email">Correo (opcional)</label><input id="email" name="email" type="email" maxlength="254" value="<?=h($values['email'])?>"><label for="admin-password">Contraseña inicial (mínimo 14 caracteres)</label><input id="admin-password" name="admin_password" type="password" autocomplete="new-password" minlength="14" required><div class="notice">Antes de instalar se probará la conexión y se rechazará cualquier base que contenga tablas. No se borrarán datos. En alojamiento público usa HTTPS desde el primer acceso.</div><button type="submit">Probar conexión e instalar</button></form><?php endif; ?></section></main></body></html>
