CREATE DATABASE IF NOT EXISTS logistica
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE logistica;

CREATE TABLE IF NOT EXISTS produtos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    unidade VARCHAR(10) NOT NULL DEFAULT 'un',
    peso DECIMAL(10,3) NOT NULL DEFAULT 0,
    descricao TEXT
);