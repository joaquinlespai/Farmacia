CREATE TABLE IF NOT EXISTS medicamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    laboratorio TEXT NOT NULL,
    categoria TEXT NOT NULL,
    precio REAL NOT NULL CHECK (precio >= 0 AND precio <= 10000000),
    stock INTEGER NOT NULL CHECK (stock >= 0),
    fecha_vencimiento TEXT NOT NULL CHECK (fecha_vencimiento >= '2026-01-01'),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO medicamentos (nombre, laboratorio, categoria, precio, stock, fecha_vencimiento)
VALUES
    ('Paracetamol 500 mg', 'Laboratorio Chile', 'Analgésico', 1990, 45, '2027-04-15'),
    ('Ibuprofeno 400 mg', 'PharmaVida', 'Antiinflamatorio', 2490, 30, '2027-08-20'),
    ('Loratadina 10 mg', 'MediSur', 'Antialérgico', 3290, 22, '2028-01-10');


CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    rol TEXT NOT NULL DEFAULT 'admin',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS historial (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER,
    usuario_nombre TEXT NOT NULL,
    accion TEXT NOT NULL,
    entidad TEXT NOT NULL,
    registro_id INTEGER,
    detalle TEXT NOT NULL,
    ip_host_cliente TEXT NOT NULL DEFAULT 'No disponible',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TRIGGER IF NOT EXISTS medicamentos_precio_limite_insert
BEFORE INSERT ON medicamentos
WHEN NEW.precio < 0 OR NEW.precio > 10000000
BEGIN
    SELECT RAISE(ABORT, 'El precio no puede superar los 10.000.000 de pesos.');
END;

CREATE TRIGGER IF NOT EXISTS medicamentos_precio_limite_update
BEFORE UPDATE ON medicamentos
WHEN NEW.precio < 0 OR NEW.precio > 10000000
BEGIN
    SELECT RAISE(ABORT, 'El precio no puede superar los 10.000.000 de pesos.');
END;

CREATE TRIGGER IF NOT EXISTS medicamentos_fecha_vencimiento_insert
BEFORE INSERT ON medicamentos
WHEN NEW.fecha_vencimiento < '2026-01-01' OR NEW.fecha_vencimiento > date('now', '+2 years')
BEGIN
    SELECT RAISE(ABORT, 'La fecha de vencimiento debe estar entre 2026 y máximo 2 años desde hoy.');
END;

CREATE TRIGGER IF NOT EXISTS medicamentos_fecha_vencimiento_update
BEFORE UPDATE ON medicamentos
WHEN NEW.fecha_vencimiento < '2026-01-01' OR NEW.fecha_vencimiento > date('now', '+2 years')
BEGIN
    SELECT RAISE(ABORT, 'La fecha de vencimiento debe estar entre 2026 y máximo 2 años desde hoy.');
END;
