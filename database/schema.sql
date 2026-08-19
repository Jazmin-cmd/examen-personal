CREATE DATABASE IF NOT EXISTS examen_personas CHARACTER SET utf8mb4;
USE examen_personas;

CREATE TABLE personas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    nro_documento VARCHAR(20) NOT NULL UNIQUE,
    fecha_nacimiento DATE NOT NULL,
    foto_frente VARCHAR(255) NOT NULL,
    foto_dorso VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE busquedas_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    termino VARCHAR(255),
    cantidad_resultados INT,
    ip_origen VARCHAR(45),
    geo_pais VARCHAR(100),
    geo_ciudad VARCHAR(100),
    geo_proveedor VARCHAR(150),
    geo_lat DECIMAL(10,7),
    geo_lon DECIMAL(10,7),
    telegram_enviado BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);