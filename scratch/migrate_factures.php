<?php
require_once 'config/database.php';
try {
    $pdo = getDB();
    $pdo->exec("ALTER TABLE factures ADD COLUMN IF NOT EXISTS rendez_vous_id INT DEFAULT NULL;");
    $pdo->exec("ALTER TABLE factures DROP FOREIGN KEY IF EXISTS fk_factures_rdv;");
    $pdo->exec("ALTER TABLE factures ADD CONSTRAINT fk_factures_rdv FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous(id) ON DELETE SET NULL;");
    echo "Migration réussie: Colonne rendez_vous_id ajoutée à la table factures.";
} catch (PDOException $e) {
    echo "Erreur lors de la migration: " . $e->getMessage();
}
