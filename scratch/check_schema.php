<?php
require_once 'config/database.php';
$pdo = getDB();
$stmt = $pdo->query("DESCRIBE factures");
print_r($stmt->fetchAll());
