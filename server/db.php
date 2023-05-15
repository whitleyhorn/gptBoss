<?php

$db = (function(){
    $host = $_ENV['MYSQLHOST'];
    $port = $_ENV['MYSQLPORT'];
    $db   = $_ENV['MYSQLDATABASE'];
    $user = $_ENV['MYSQLUSER'];
    $pass = $_ENV['MYSQLPASSWORD'];
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset;port=$port";
    try {
        return new \PDO($dsn, $user, $pass);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
})();

