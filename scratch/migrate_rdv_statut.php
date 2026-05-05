<?php
require_once 'config/database.php';
try {
    $pdo = getDB();
    $pdo->exec("ALTER TABLE rendez_vous MODIFY COLUMN statut ENUM('en_attente','en_attente_paiement','confirme','termine','annule') NOT NULL DEFAULT 'en_attente'");
    echo "Migration réussie: Statut 'en_attente_paiement' ajouté.";
} catch (PDOException $e) {
    echo "Erreur migration: " . $e->getMessage();
}
