-- Script MySQL pour App Magasin Lycée
-- Exécuter avec: mysql -u root -p < mysql-setup.sql

-- Créer la base de données
CREATE DATABASE IF NOT EXISTS app_magasin
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Créer l'utilisateur applicatif
CREATE USER IF NOT EXISTS 'appmagasin'@'localhost'
    IDENTIFIED BY 'AppMagasin@2024!';

-- Accorder les droits
GRANT ALL PRIVILEGES ON app_magasin.* TO 'appmagasin'@'localhost';
FLUSH PRIVILEGES;

-- Vérification
SHOW DATABASES LIKE 'app_magasin';
SELECT User, Host FROM mysql.user WHERE User = 'appmagasin';

-- Utiliser la base
USE app_magasin;
SELECT 'Base de données app_magasin prête.' AS Status;
