-- ============================================================
--  KLINIK — Script SQL COMPLET (Version corrigée)
--  Base de données hospitalière — Toutes tables + FK + Index
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS klinik_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE klinik_db;

-- ============================================================
--  DROP des tables existantes (ordre inverse des dépendances)
-- ============================================================
DROP TABLE IF EXISTS paiements_mobile;
DROP TABLE IF EXISTS paiements;
DROP TABLE IF EXISTS factures;
DROP TABLE IF EXISTS ordonnances;
DROP TABLE IF EXISTS examens;
DROP TABLE IF EXISTS consultations;
DROP TABLE IF EXISTS rendez_vous;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS medecins;
DROP TABLE IF EXISTS utilisateurs;
DROP VIEW IF EXISTS vue_activite_recente;

-- ============================================================
--  TABLE: utilisateurs (tous les comptes du système)
-- ============================================================
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('admin','medecin','patient','laborantin','caissier') NOT NULL,
    telephone VARCHAR(20) DEFAULT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    derniere_connexion DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_utilisateurs_role (role),
    INDEX idx_utilisateurs_email (email),
    INDEX idx_utilisateurs_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: medecins (profil complémentaire pour role=medecin)
-- ============================================================
CREATE TABLE medecins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL UNIQUE,
    specialite VARCHAR(100) DEFAULT NULL,
    numero_ordre VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_medecins_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: patients (profil complémentaire pour role=patient)
-- ============================================================
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL UNIQUE,
    date_naissance DATE DEFAULT NULL,
    sexe ENUM('M','F') DEFAULT NULL,
    groupe_sanguin VARCHAR(5) DEFAULT NULL,
    adresse TEXT DEFAULT NULL,
    ville VARCHAR(100) DEFAULT NULL,
    medecin_traitant_id INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (medecin_traitant_id) REFERENCES medecins(id) ON DELETE SET NULL,
    INDEX idx_patients_utilisateur (utilisateur_id),
    INDEX idx_patients_medecin (medecin_traitant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: rendez_vous
-- ============================================================
CREATE TABLE rendez_vous (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    medecin_id INT NOT NULL,
    date_rdv DATETIME NOT NULL,
    motif TEXT DEFAULT NULL,
    statut ENUM('en_attente','confirme','termine','annule') NOT NULL DEFAULT 'en_attente',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (medecin_id) REFERENCES medecins(id) ON DELETE CASCADE,
    INDEX idx_rdv_date (date_rdv),
    INDEX idx_rdv_statut (statut),
    INDEX idx_rdv_patient (patient_id),
    INDEX idx_rdv_medecin (medecin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: consultations
-- ============================================================
CREATE TABLE consultations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rendez_vous_id INT DEFAULT NULL UNIQUE,
    medecin_id INT NOT NULL,
    patient_id INT NOT NULL,
    diagnostic TEXT DEFAULT NULL,
    prescription TEXT DEFAULT NULL,
    observations TEXT DEFAULT NULL,
    date_consult DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous(id) ON DELETE SET NULL,
    FOREIGN KEY (medecin_id) REFERENCES medecins(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_consult_date (date_consult),
    INDEX idx_consult_medecin (medecin_id),
    INDEX idx_consult_patient (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: examens
-- ============================================================
CREATE TABLE examens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL,
    laborantin_id INT DEFAULT NULL,
    type_examen VARCHAR(150) NOT NULL,
    resultat TEXT DEFAULT NULL,
    statut ENUM('en_attente','en_cours','transmis') NOT NULL DEFAULT 'en_attente',
    priorite ENUM('normale','urgente') NOT NULL DEFAULT 'normale',
    date_demande TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_resultat DATETIME DEFAULT NULL,

    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
    FOREIGN KEY (laborantin_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_examen_statut (statut),
    INDEX idx_examen_consultation (consultation_id),
    INDEX idx_examen_laborantin (laborantin_id),
    INDEX idx_examen_priorite (priorite)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: ordonnances
-- ============================================================
CREATE TABLE ordonnances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL,
    medecin_id INT NOT NULL,
    patient_id INT NOT NULL,
    contenu TEXT NOT NULL,
    date_ordonnance DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
    FOREIGN KEY (medecin_id) REFERENCES medecins(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_ordonnance_consultation (consultation_id),
    INDEX idx_ordonnance_patient (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: factures
-- ============================================================
CREATE TABLE factures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    consultation_id INT DEFAULT NULL,
    caissier_id INT DEFAULT NULL,
    montant_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    statut ENUM('impayee','payee','partielle') NOT NULL DEFAULT 'impayee',
    date_facture TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE SET NULL,
    FOREIGN KEY (caissier_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_facture_statut (statut),
    INDEX idx_facture_patient (patient_id),
    INDEX idx_facture_consultation (consultation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: paiements (protégée — pas de ON DELETE CASCADE)
-- ============================================================
CREATE TABLE paiements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facture_id INT NOT NULL,
    caissier_id INT NOT NULL,
    montant_paye DECIMAL(10,2) NOT NULL,
    mode_paiement ENUM('especes','carte','mobile_money','cheque') NOT NULL DEFAULT 'especes',
    reference VARCHAR(100) DEFAULT NULL,
    date_paiement TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (facture_id) REFERENCES factures(id),
    FOREIGN KEY (caissier_id) REFERENCES utilisateurs(id),
    INDEX idx_paiement_date (date_paiement),
    INDEX idx_paiement_facture (facture_id),
    INDEX idx_paiement_mode (mode_paiement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: paiements_mobile (logs des transactions Mobile Money)
-- ============================================================
CREATE TABLE paiements_mobile (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paiement_id INT DEFAULT NULL,
    facture_id INT NOT NULL,
    provider ENUM('wave','orange_money','mtn_momo','moov_money') NOT NULL,
    telephone VARCHAR(20) NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    statut ENUM('initie','en_cours','succes','echec','expire') NOT NULL DEFAULT 'initie',
    webhook_data JSON DEFAULT NULL,
    erreur TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (paiement_id) REFERENCES paiements(id) ON DELETE SET NULL,
    FOREIGN KEY (facture_id) REFERENCES factures(id),
    INDEX idx_pm_statut (statut),
    INDEX idx_pm_provider (provider),
    INDEX idx_pm_transaction (transaction_id),
    INDEX idx_pm_facture (facture_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: notifications
-- ============================================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    titre VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    lue TINYINT(1) NOT NULL DEFAULT 0,
    lien VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_notif_user (utilisateur_id),
    INDEX idx_notif_lue (lue),
    INDEX idx_notif_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: messages (messagerie interne)
-- ============================================================
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expediteur_id INT NOT NULL,
    destinataire_id INT NOT NULL,
    sujet VARCHAR(200) NOT NULL DEFAULT '',
    contenu TEXT NOT NULL,
    lu TINYINT(1) NOT NULL DEFAULT 0,
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (expediteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (destinataire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE SET NULL,
    INDEX idx_msg_destinataire (destinataire_id),
    INDEX idx_msg_expediteur (expediteur_id),
    INDEX idx_msg_lu (lu),
    INDEX idx_msg_parent (parent_id),
    INDEX idx_msg_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  VUE: activité récente (7 derniers jours)
-- ============================================================
CREATE VIEW vue_activite_recente AS
SELECT 
    DATE(c.date_consult) AS jour,
    COUNT(DISTINCT c.id) AS nb_consultations,
    COUNT(DISTINCT e.id) AS nb_examens,
    COUNT(DISTINCT f.id) AS nb_factures
FROM consultations c
LEFT JOIN examens e ON c.id = e.consultation_id
LEFT JOIN factures f ON c.id = f.consultation_id
WHERE c.date_consult >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(c.date_consult)
ORDER BY jour DESC;

-- ============================================================
--  TRIGGER: Marquer RDV comme terminé après consultation
-- ============================================================
DELIMITER //
CREATE TRIGGER trg_after_consultation_insert
AFTER INSERT ON consultations
FOR EACH ROW
BEGIN
    IF NEW.rendez_vous_id IS NOT NULL THEN
        UPDATE rendez_vous 
        SET statut = 'termine' 
        WHERE id = NEW.rendez_vous_id;
    END IF;
END //
DELIMITER ;

-- ============================================================
--  TRIGGER: Notification au patient après création de facture
-- ============================================================
DELIMITER //
CREATE TRIGGER trg_after_facture_insert
AFTER INSERT ON factures
FOR EACH ROW
BEGIN
    DECLARE v_user_id INT;
    SELECT utilisateur_id INTO v_user_id FROM patients WHERE id = NEW.patient_id LIMIT 1;
    IF v_user_id IS NOT NULL THEN
        INSERT INTO notifications (utilisateur_id, titre, message, type, lien)
        VALUES (v_user_id, 'Nouvelle facture', 
                CONCAT('Une facture de ', FORMAT(NEW.montant_total, 0), ' FCFA a été créée.'),
                'warning', 'mes-factures.php');
    END IF;
END //
DELIMITER ;

-- ============================================================
--  TRIGGER: Notification au médecin quand examen transmis
-- ============================================================
DELIMITER //
CREATE TRIGGER trg_after_examen_transmis
AFTER UPDATE ON examens
FOR EACH ROW
BEGIN
    DECLARE v_medecin_user_id INT;
    IF NEW.statut = 'transmis' AND OLD.statut != 'transmis' THEN
        SELECT m.utilisateur_id INTO v_medecin_user_id 
        FROM consultations c 
        JOIN medecins m ON c.medecin_id = m.id 
        WHERE c.id = NEW.consultation_id LIMIT 1;
        
        IF v_medecin_user_id IS NOT NULL THEN
            INSERT INTO notifications (utilisateur_id, titre, message, type, lien)
            VALUES (v_medecin_user_id, 'Résultat d''examen disponible',
                    CONCAT('Le résultat de l''examen "', NEW.type_examen, '" est disponible.'),
                    'success', 'examens.php');
        END IF;
    END IF;
END //
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  COMPTES TEST (mot de passe: "password" pour tous)
-- ============================================================
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, telephone) VALUES
('ADMIN',  'Klinik',  'admin@klinik.ci',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',      '0700000001'),
('KONE',   'Ibrahim', 'medecin@klinik.ci', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'medecin',    '0700000002'),
('DIALLO', 'Aminata', 'patient@klinik.ci', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient',    '0700000003'),
('TOURE',  'Moussa',  'labo@klinik.ci',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'laborantin', '0700000004'),
('BAMBA',  'Fatou',   'caisse@klinik.ci',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'caissier',   '0700000005');

INSERT INTO medecins (utilisateur_id, specialite, numero_ordre) VALUES
(2, 'Cardiologie', 'ORD-2024-002');

INSERT INTO patients (utilisateur_id, date_naissance, sexe, groupe_sanguin, ville, medecin_traitant_id) VALUES
(3, '1990-05-15', 'F', 'A+', 'Abidjan', 1);

-- Notification de bienvenue pour l'admin
INSERT INTO notifications (utilisateur_id, titre, message, type) VALUES
(1, 'Bienvenue sur KLINIK', 'Votre plateforme hospitalière est prête. Configurez vos départements et ajoutez des utilisateurs.', 'info');

-- ============================================================
--  FIN DU SCRIPT — klinik_db
-- ============================================================