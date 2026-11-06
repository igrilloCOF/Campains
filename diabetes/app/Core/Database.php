<?php
declare(strict_types=1);
namespace App\Core;
use PDO;
use RuntimeException;
final class Database {
 private static ?PDO $pdo=null;
 public static function connection(): PDO {
  if(self::$pdo) return self::$pdo;
  $host=getenv('DB_HOST')?:'127.0.0.1'; $port=getenv('DB_PORT')?:'3306'; $db=getenv('DB_NAME')?:''; $user=getenv('DB_USER')?:''; $pass=getenv('DB_PASSWORD')?:'';
  if($db===''||$user==='') throw new RuntimeException('Configura DB_NAME y DB_USER en el archivo de entorno.');
  $dsn="mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
  self::$pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
  return self::$pdo;
 }
}
