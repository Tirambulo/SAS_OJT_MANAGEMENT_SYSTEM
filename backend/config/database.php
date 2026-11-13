<?php
require_once __DIR__ . '/config.php';

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $charset = DB_CHARSET;
    private $pdo;
    private $error;

    public function __construct() {
        $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->dbname . ";charset=" . $this->charset;
        $options = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (\PDOException $e) {
            throw new RuntimeException('Unable to connect to the application database.', 0, $e);
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
?>