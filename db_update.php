<?php
require_once __DIR__ . '/config/database.php';
$pdo = getDB();

try {
    $pdo->exec("ALTER TABLE paiements_mobile MODIFY provider ENUM('wave','orange_money','mtn_momo','moov_money','cash') NOT NULL");
    echo "paiements_mobile updated.\n";
} catch(Exception $e) { echo "Error 1: " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE paiements ADD validated_by INT DEFAULT NULL");
    echo "paiements validated_by added.\n";
} catch(Exception $e) { echo "Error 2: " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE paiements ADD validated_at TIMESTAMP NULL");
    echo "paiements validated_at added.\n";
} catch(Exception $e) { echo "Error 3: " . $e->getMessage() . "\n"; }
