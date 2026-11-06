<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$username=trim((string)($argv[1]??''));if($username===''||strlen($username)>120){fwrite(STDERR,"Uso: php bin/create-admin.php NOMBRE_USUARIO\n");exit(2);}
fwrite(STDOUT,'Contraseña nueva (mínimo 14 caracteres): ');
if(strncasecmp(PHP_OS,'WIN',3)!==0){system('stty -echo');}$password=trim((string)fgets(STDIN));if(strncasecmp(PHP_OS,'WIN',3)!==0){system('stty echo');}fwrite(STDOUT,"\n");
if(strlen($password)<14){fwrite(STDERR,"La contraseña debe tener al menos 14 caracteres.\n");exit(2);}
$db=Database::connection();$q=$db->prepare("INSERT INTO users(role,username,password_hash,enabled) VALUES('admin',?,?,1)");try{$q->execute([$username,password_hash($password,PASSWORD_DEFAULT)]);fwrite(STDOUT,"Administrador creado. Guarde las credenciales en un gestor seguro y elimine cualquier copia de la contraseña.\n");}catch(PDOException $e){fwrite(STDERR,"No se pudo crear la cuenta; verifique que el usuario no exista y la base de datos esté configurada.\n");exit(1);}finally{unset($password);}
