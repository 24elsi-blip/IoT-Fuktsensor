<?php
// db.php – anslutning till databasen

$host   = 'localhost';         // ofta 'localhost'
$db     = '24elsi';       // t.ex. '24elsi'
$user   = '24elsi';     // t.ex. '24elsi_user'
$pass   = 'Vesslan33!';        // t.ex. 'hemligt_lösen';

// Namn på tabell där vi lagrar fukt
$table  = 'fuktdata';          // skapa denna tabell i phpMyAdmin

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo "Database connection failed: " . $e->getMessage();
    exit;
}

/*
Exempel på SQL för att skapa tabellen (kör i phpMyAdmin):
--------------------------------------------------------
CREATE TABLE `fuktdata` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fukt` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/
