-- ==============================================================
--  BASE DE DATOS FACTURACIÓN - MySQL/MariaDB 10.4+
--  Conversión completa desde SQL Server / PostgreSQL
--  Incluye: Tablas, Triggers, Stored Procedures, Datos y RBAC
-- ==============================================================

-- ================================================================
-- CREAR Y SELECCIONAR BASE DE DATOS
-- ================================================================
CREATE DATABASE IF NOT EXISTS bdfacturas_mariadb_local
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bdfacturas_mariadb_local;

-- Forzar UTF-8 en la conexión para que los INSERT guarden bien las tildes
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ================================================================
-- ELIMINAR OBJETOS EXISTENTES (para poder re-ejecutar el script)
-- ================================================================
DROP PROCEDURE IF EXISTS sp_insertar_factura_y_productosporfactura;
DROP PROCEDURE IF EXISTS sp_consultar_factura_y_productosporfactura;
DROP PROCEDURE IF EXISTS sp_listar_facturas_y_productosporfactura;
DROP PROCEDURE IF EXISTS sp_actualizar_factura_y_productosporfactura;
DROP PROCEDURE IF EXISTS sp_borrar_factura_y_productosporfactura;
DROP PROCEDURE IF EXISTS sp_anular_factura;
-- Limpiar nombres viejos (versiones anteriores del script)
DROP PROCEDURE IF EXISTS crear_factura_con_detalle;
DROP PROCEDURE IF EXISTS consultar_factura_con_detalle;
DROP PROCEDURE IF EXISTS actualizar_factura_con_detalle;
DROP PROCEDURE IF EXISTS eliminar_factura_con_detalle;
DROP PROCEDURE IF EXISTS crear_usuario_con_roles;
DROP PROCEDURE IF EXISTS actualizar_usuario_con_roles;
DROP PROCEDURE IF EXISTS eliminar_usuario_con_roles;
DROP PROCEDURE IF EXISTS actualizar_roles_usuario;
DROP PROCEDURE IF EXISTS consultar_usuario_con_roles;
DROP PROCEDURE IF EXISTS listar_usuarios_con_roles;
DROP PROCEDURE IF EXISTS verificar_acceso_ruta;
DROP PROCEDURE IF EXISTS listar_rutarol;
DROP PROCEDURE IF EXISTS crear_rutarol;
DROP PROCEDURE IF EXISTS eliminar_rutarol;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS productosporfactura;
DROP TABLE IF EXISTS factura;
DROP TABLE IF EXISTS producto;
DROP TABLE IF EXISTS rutarol;
DROP TABLE IF EXISTS rol_usuario;
DROP TABLE IF EXISTS vendedor;
DROP TABLE IF EXISTS cliente;
DROP TABLE IF EXISTS ruta;
DROP TABLE IF EXISTS rol;
DROP TABLE IF EXISTS usuario;
DROP TABLE IF EXISTS empresa;
DROP TABLE IF EXISTS persona;
SET FOREIGN_KEY_CHECKS = 1;

-- ================================================================
-- TABLAS BASE (sin foreign keys)
-- ================================================================
CREATE TABLE empresa (
    codigo VARCHAR(10) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    CONSTRAINT pk_empresa PRIMARY KEY (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE persona (
    codigo VARCHAR(10) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    CONSTRAINT pk_persona PRIMARY KEY (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE producto (
    codigo VARCHAR(10) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    stock INT NOT NULL,
    valorunitario DECIMAL(18,2) NOT NULL,
    CONSTRAINT pk_producto PRIMARY KEY (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rol (
    id INT AUTO_INCREMENT NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    CONSTRAINT pk_rol PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ruta (
    id INT AUTO_INCREMENT NOT NULL,
    ruta VARCHAR(100) NOT NULL,
    descripcion VARCHAR(200) NOT NULL,
    CONSTRAINT pk_ruta PRIMARY KEY (id),
    CONSTRAINT uq_ruta UNIQUE (ruta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuario (
    email VARCHAR(100) NOT NULL,
    contrasena VARCHAR(200) NOT NULL,
    CONSTRAINT pk_usuario PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TABLAS DEPENDIENTES (con foreign keys)
-- ================================================================
CREATE TABLE cliente (
    id INT AUTO_INCREMENT NOT NULL,
    credito DECIMAL(18,2) NOT NULL DEFAULT 0,
    fkcodpersona VARCHAR(10) NOT NULL,
    fkcodempresa VARCHAR(10),
    CONSTRAINT pk_cliente PRIMARY KEY (id),
    CONSTRAINT fk_cliente_persona FOREIGN KEY (fkcodpersona) REFERENCES persona(codigo),
    CONSTRAINT fk_cliente_empresa FOREIGN KEY (fkcodempresa) REFERENCES empresa(codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vendedor (
    id INT AUTO_INCREMENT NOT NULL,
    carnet INT NOT NULL,
    direccion VARCHAR(100) NOT NULL,
    fkcodpersona VARCHAR(10) NOT NULL,
    CONSTRAINT pk_vendedor PRIMARY KEY (id),
    CONSTRAINT fk_vendedor_persona FOREIGN KEY (fkcodpersona) REFERENCES persona(codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE factura (
    numero INT AUTO_INCREMENT NOT NULL,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(18,2) NOT NULL DEFAULT 0,
    estado VARCHAR(10) NOT NULL DEFAULT 'activa',
    fkidcliente INT NOT NULL,
    fkidvendedor INT NOT NULL,
    CONSTRAINT pk_factura PRIMARY KEY (numero),
    CONSTRAINT fk_factura_cliente FOREIGN KEY (fkidcliente) REFERENCES cliente(id),
    CONSTRAINT fk_factura_vendedor FOREIGN KEY (fkidvendedor) REFERENCES vendedor(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE productosporfactura (
    fknumfactura INT NOT NULL,
    fkcodproducto VARCHAR(10) NOT NULL,
    cantidad INT NOT NULL,
    subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
    CONSTRAINT pk_productosporfactura PRIMARY KEY (fknumfactura, fkcodproducto),
    CONSTRAINT fk_prodfact_factura FOREIGN KEY (fknumfactura) REFERENCES factura(numero) ON DELETE CASCADE,
    CONSTRAINT fk_prodfact_producto FOREIGN KEY (fkcodproducto) REFERENCES producto(codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rol_usuario (
    fkemail VARCHAR(100) NOT NULL,
    fkidrol INT NOT NULL,
    CONSTRAINT pk_rol_usuario PRIMARY KEY (fkemail, fkidrol),
    CONSTRAINT fk_rolusuario_usuario FOREIGN KEY (fkemail) REFERENCES usuario(email),
    CONSTRAINT fk_rolusuario_rol FOREIGN KEY (fkidrol) REFERENCES rol(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rutarol (
    fkidruta INT NOT NULL,
    fkidrol INT NOT NULL,
    CONSTRAINT pk_rutarol PRIMARY KEY (fkidruta, fkidrol),
    CONSTRAINT fk_rutarol_ruta FOREIGN KEY (fkidruta) REFERENCES ruta(id) ON DELETE CASCADE,
    CONSTRAINT fk_rutarol_rol FOREIGN KEY (fkidrol) REFERENCES rol(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TRIGGERS: Actualizar totales de factura y stock de producto
-- MariaDB no soporta expresiones en SIGNAL MESSAGE_TEXT,
-- se usa variable local v_msg para construir el mensaje.
-- ================================================================
DELIMITER $$

-- Trigger BEFORE INSERT: calcular subtotal y validar/descontar stock
CREATE TRIGGER trg_prodfact_before_insert
BEFORE INSERT ON productosporfactura
FOR EACH ROW
BEGIN
    DECLARE v_precio DECIMAL(18,2);
    DECLARE v_stock INT;
    DECLARE v_msg VARCHAR(500);

    SELECT valorunitario, stock INTO v_precio, v_stock
    FROM producto WHERE codigo = NEW.fkcodproducto;

    -- Validar stock suficiente
    IF v_stock < NEW.cantidad THEN
        SET v_msg = CONCAT('Stock insuficiente para producto ', NEW.fkcodproducto,
            '. Stock disponible: ', v_stock, ', cantidad solicitada: ', NEW.cantidad);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    -- Calcular subtotal
    SET NEW.subtotal = NEW.cantidad * v_precio;

    -- Descontar stock
    UPDATE producto SET stock = stock - NEW.cantidad WHERE codigo = NEW.fkcodproducto;
END$$

-- Trigger AFTER INSERT: recalcular total de la factura
CREATE TRIGGER trg_prodfact_after_insert
AFTER INSERT ON productosporfactura
FOR EACH ROW
BEGIN
    UPDATE factura
    SET total = (SELECT COALESCE(SUM(subtotal), 0) FROM productosporfactura WHERE fknumfactura = NEW.fknumfactura)
    WHERE numero = NEW.fknumfactura;
END$$

-- Trigger BEFORE UPDATE: recalcular subtotal y ajustar stock
CREATE TRIGGER trg_prodfact_before_update
BEFORE UPDATE ON productosporfactura
FOR EACH ROW
BEGIN
    DECLARE v_precio DECIMAL(18,2);
    DECLARE v_stock INT;
    DECLARE v_msg VARCHAR(500);

    SELECT valorunitario INTO v_precio FROM producto WHERE codigo = NEW.fkcodproducto;
    SELECT stock INTO v_stock FROM producto WHERE codigo = NEW.fkcodproducto;

    -- Validar stock suficiente (considerando la devolucion del stock anterior)
    IF v_stock + OLD.cantidad < NEW.cantidad THEN
        SET v_msg = CONCAT('Stock insuficiente para producto ', NEW.fkcodproducto,
            '. Stock disponible: ', v_stock + OLD.cantidad, ', cantidad solicitada: ', NEW.cantidad);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    -- Recalcular subtotal
    SET NEW.subtotal = NEW.cantidad * v_precio;

    -- Ajustar stock: devolver old.cantidad y descontar new.cantidad
    UPDATE producto
    SET stock = stock + OLD.cantidad - NEW.cantidad
    WHERE codigo = NEW.fkcodproducto;
END$$

-- Trigger AFTER UPDATE: recalcular total de la factura
CREATE TRIGGER trg_prodfact_after_update
AFTER UPDATE ON productosporfactura
FOR EACH ROW
BEGIN
    UPDATE factura
    SET total = (SELECT COALESCE(SUM(subtotal), 0) FROM productosporfactura WHERE fknumfactura = NEW.fknumfactura)
    WHERE numero = NEW.fknumfactura;
END$$

-- Trigger BEFORE DELETE: restaurar stock del producto
CREATE TRIGGER trg_prodfact_before_delete
BEFORE DELETE ON productosporfactura
FOR EACH ROW
BEGIN
    UPDATE producto
    SET stock = stock + OLD.cantidad
    WHERE codigo = OLD.fkcodproducto;
END$$

-- Trigger AFTER DELETE: recalcular total de la factura
CREATE TRIGGER trg_prodfact_after_delete
AFTER DELETE ON productosporfactura
FOR EACH ROW
BEGIN
    UPDATE factura
    SET total = (SELECT COALESCE(SUM(subtotal), 0) FROM productosporfactura WHERE fknumfactura = OLD.fknumfactura)
    WHERE numero = OLD.fknumfactura;
END$$

DELIMITER ;

-- ============================================================
-- PROCEDIMIENTOS ALMACENADOS - FACTURAS Y PRODUCTOS POR FACTURA
-- Los resultados se retornan via parámetro OUT tipo JSON
-- ============================================================
DELIMITER $$

-- ------------------------------------------------------------
-- 1. SP INSERTAR FACTURA Y PRODUCTOSPORFACTURA
-- Recibe: id cliente, id vendedor, y un JSON array de productos
-- Retorna: JSON con la factura creada y sus productos
-- Nota: El trigger se encarga de calcular subtotal, descontar
--       stock y actualizar total factura.
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "sp_insertar_factura_y_productosporfactura",
--     "p_fkidcliente": 1, "p_fkidvendedor": 1,
--     "p_productos": "[{\"codigo\":\"PR001\",\"cantidad\":2},{\"codigo\":\"PR003\",\"cantidad\":3}]",
--     "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE sp_insertar_factura_y_productosporfactura(
    IN p_fkidcliente INT,
    IN p_fkidvendedor INT,
    IN p_productos JSON,
    IN p_minimo_detalle INT,
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_numfactura INT;
    DECLARE v_index INT DEFAULT 0;
    DECLARE v_count INT;
    DECLARE v_codproducto VARCHAR(10);
    DECLARE v_cantidad INT;
    DECLARE v_minimo INT;
    DECLARE v_factura_json TEXT;
    DECLARE v_productos_json TEXT;
    DECLARE v_msg VARCHAR(500);

    SET v_minimo = COALESCE(NULLIF(p_minimo_detalle, 0), 1);

    IF p_productos IS NULL OR JSON_LENGTH(p_productos) < v_minimo THEN
        SET v_msg = CONCAT('La factura requiere minimo ', v_minimo, ' producto(s).');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    -- Crear la factura con total 0 (el trigger actualiza el total)
    INSERT INTO factura (fkidcliente, fkidvendedor, total)
    VALUES (p_fkidcliente, p_fkidvendedor, 0);

    SET v_numfactura = LAST_INSERT_ID();
    SET v_count = JSON_LENGTH(p_productos);

    -- Recorrer cada producto del JSON e insertar detalle
    -- El trigger calcula subtotal, descuenta stock y actualiza total
    WHILE v_index < v_count DO
        SET v_codproducto = JSON_UNQUOTE(JSON_EXTRACT(p_productos, CONCAT('$[', v_index, '].codigo')));
        SET v_cantidad = JSON_EXTRACT(p_productos, CONCAT('$[', v_index, '].cantidad'));

        INSERT INTO productosporfactura (fknumfactura, fkcodproducto, cantidad, subtotal)
        VALUES (v_numfactura, v_codproducto, v_cantidad, 0);

        SET v_index = v_index + 1;
    END WHILE;

    -- Retornar resultado como JSON
    SELECT CONCAT(
        '{"numero":', f.numero,
        ',"fecha":"', DATE_FORMAT(f.fecha, '%Y-%m-%dT%H:%i:%s'), '"',
        ',"total":', f.total,
        ',"estado":"', f.estado, '"',
        ',"fkidcliente":', f.fkidcliente,
        ',"fkidvendedor":', f.fkidvendedor, '}'
    ) INTO v_factura_json
    FROM factura f WHERE f.numero = v_numfactura;

    SELECT CONCAT('[', COALESCE(GROUP_CONCAT(
        JSON_OBJECT(
            'codigo_producto', pf.fkcodproducto,
            'nombre_producto', pr.nombre,
            'cantidad', pf.cantidad,
            'valorunitario', pr.valorunitario,
            'subtotal', pf.subtotal
        )
    ), ''), ']')
    INTO v_productos_json
    FROM productosporfactura pf
    JOIN producto pr ON pr.codigo = pf.fkcodproducto
    WHERE pf.fknumfactura = v_numfactura;

    SET p_resultado = CONCAT('{"factura":', v_factura_json, ',"productos":', COALESCE(v_productos_json, '[]'), '}');
END$$

-- ------------------------------------------------------------
-- 2. SP CONSULTAR FACTURA Y PRODUCTOSPORFACTURA
-- Consulta una factura por número con detalle de productos,
-- nombre del cliente y nombre del vendedor
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "sp_consultar_factura_y_productosporfactura",
--     "p_numero": 1, "p_resultado": "" }
-- ------------------------------------------------------------
CREATE PROCEDURE sp_consultar_factura_y_productosporfactura(
    IN p_numero INT,
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_detalle_json TEXT;
    DECLARE v_msg VARCHAR(500);

    IF NOT EXISTS (SELECT 1 FROM factura WHERE numero = p_numero) THEN
        SET v_msg = CONCAT('Factura ', p_numero, ' no existe');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    SELECT CONCAT('[', COALESCE(GROUP_CONCAT(
        JSON_OBJECT(
            'codigo_producto', d.fkcodproducto,
            'nombre_producto', p.nombre,
            'cantidad', d.cantidad,
            'valorunitario', p.valorunitario,
            'subtotal', d.subtotal
        )
    ), ''), ']')
    INTO v_detalle_json
    FROM productosporfactura d
    INNER JOIN producto p ON p.codigo = d.fkcodproducto
    WHERE d.fknumfactura = p_numero;

    SELECT CONCAT(
        '{"factura":{"numero":', f.numero,
        ',"fecha":"', DATE_FORMAT(f.fecha, '%Y-%m-%dT%H:%i:%s'), '"',
        ',"total":', f.total,
        ',"estado":"', f.estado, '"',
        ',"fkidcliente":', f.fkidcliente,
        ',"nombre_cliente":"', pc.nombre, '"',
        ',"fkidvendedor":', f.fkidvendedor,
        ',"nombre_vendedor":"', pv.nombre, '"',
        '},"productos":', COALESCE(v_detalle_json, '[]'),
        '}'
    ) INTO p_resultado
    FROM factura f
    JOIN cliente c ON c.id = f.fkidcliente
    JOIN persona pc ON pc.codigo = c.fkcodpersona
    JOIN vendedor v ON v.id = f.fkidvendedor
    JOIN persona pv ON pv.codigo = v.fkcodpersona
    WHERE f.numero = p_numero;
END$$

-- ------------------------------------------------------------
-- 3. SP LISTAR FACTURAS Y PRODUCTOSPORFACTURA
-- Lista todas las facturas con sus productos, cliente y vendedor
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "sp_listar_facturas_y_productosporfactura",
--     "p_resultado": "" }
-- ------------------------------------------------------------
CREATE PROCEDURE sp_listar_facturas_y_productosporfactura(
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_result TEXT DEFAULT '';
    DECLARE v_factura TEXT;
    DECLARE v_detalle TEXT;
    DECLARE v_done INT DEFAULT FALSE;
    DECLARE v_numero INT;
    DECLARE cur CURSOR FOR SELECT numero FROM factura ORDER BY numero;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_numero;
        IF v_done THEN LEAVE read_loop; END IF;

        SELECT CONCAT('[', COALESCE(GROUP_CONCAT(
            JSON_OBJECT(
                'codigo_producto', d.fkcodproducto,
                'nombre_producto', p.nombre,
                'cantidad', d.cantidad,
                'valorunitario', p.valorunitario,
                'subtotal', d.subtotal
            )
        ), ''), ']')
        INTO v_detalle
        FROM productosporfactura d
        INNER JOIN producto p ON p.codigo = d.fkcodproducto
        WHERE d.fknumfactura = v_numero;

        SELECT CONCAT(
            '{"numero":', f.numero,
            ',"fecha":"', DATE_FORMAT(f.fecha, '%Y-%m-%dT%H:%i:%s'), '"',
            ',"total":', f.total,
            -- El estado se agregó en la v2: el listado no lo traía, y sin él
            -- la pantalla no podía distinguir una factura anulada de una
            -- activa. Los demas procedimientos ya lo devolvian.
            ',"estado":"', f.estado, '"',
            ',"fkidcliente":', f.fkidcliente,
            ',"nombre_cliente":"', pc.nombre, '"',
            ',"fkidvendedor":', f.fkidvendedor,
            ',"nombre_vendedor":"', pv.nombre, '"',
            ',"productos":', COALESCE(v_detalle, '[]'),
            '}'
        )
        INTO v_factura
        FROM factura f
        JOIN cliente c ON c.id = f.fkidcliente
        JOIN persona pc ON pc.codigo = c.fkcodpersona
        JOIN vendedor v ON v.id = f.fkidvendedor
        JOIN persona pv ON pv.codigo = v.fkcodpersona
        WHERE f.numero = v_numero;

        IF v_result != '' THEN SET v_result = CONCAT(v_result, ','); END IF;
        SET v_result = CONCAT(v_result, v_factura);
    END LOOP;
    CLOSE cur;

    IF v_result = '' THEN
        SET p_resultado = '[]';
    ELSE
        SET p_resultado = CONCAT('[', v_result, ']');
    END IF;
END$$

-- ------------------------------------------------------------
-- 4. SP ACTUALIZAR FACTURA Y PRODUCTOSPORFACTURA
-- Reemplaza los productos de una factura existente.
-- Nota: El trigger se encarga de restaurar stock (DELETE),
--       descontar stock (INSERT) y recalcular subtotales/total.
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "sp_actualizar_factura_y_productosporfactura",
--     "p_numero": 1, "p_fkidcliente": 2, "p_fkidvendedor": 1,
--     "p_productos": "[{\"codigo\":\"PR002\",\"cantidad\":1},{\"codigo\":\"PR004\",\"cantidad\":5}]",
--     "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE sp_actualizar_factura_y_productosporfactura(
    IN p_numero INT,
    IN p_fkidcliente INT,
    IN p_fkidvendedor INT,
    IN p_productos JSON,
    IN p_minimo_detalle INT,
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_index INT DEFAULT 0;
    DECLARE v_count INT;
    DECLARE v_codproducto VARCHAR(10);
    DECLARE v_cantidad INT;
    DECLARE v_minimo INT;
    DECLARE v_factura_json TEXT;
    DECLARE v_productos_json TEXT;
    DECLARE v_estado VARCHAR(10);
    DECLARE v_msg VARCHAR(500);

    IF NOT EXISTS (SELECT 1 FROM factura WHERE numero = p_numero) THEN
        SET v_msg = CONCAT('Factura ', p_numero, ' no existe');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    -- Guarda agregada en la v2. Sin ella se podia REEMPLAZAR el detalle de
    -- una factura ya anulada: la factura seguia marcada 'anulada' pero el
    -- stock se volvia a mover, y el total cambiaba. Una factura anulada es
    -- historia: no se edita. (sp_anular_factura ya tenia esta misma guarda;
    -- a este procedimiento se le habia quedado.)
    SELECT estado INTO v_estado FROM factura WHERE numero = p_numero;
    IF v_estado = 'anulada' THEN
        SET v_msg = CONCAT('Factura ', p_numero, ' esta anulada: no se puede modificar');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    SET v_minimo = COALESCE(NULLIF(p_minimo_detalle, 0), 1);

    IF p_productos IS NULL OR JSON_LENGTH(p_productos) < v_minimo THEN
        SET v_msg = CONCAT('La factura requiere minimo ', v_minimo, ' producto(s).');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    -- Eliminar detalle anterior (el trigger restaura stock y recalcula total)
    DELETE FROM productosporfactura WHERE fknumfactura = p_numero;

    -- Insertar nuevos productos (el trigger calcula subtotal, descuenta stock, actualiza total)
    SET v_count = JSON_LENGTH(p_productos);

    WHILE v_index < v_count DO
        SET v_codproducto = JSON_UNQUOTE(JSON_EXTRACT(p_productos, CONCAT('$[', v_index, '].codigo')));
        SET v_cantidad = JSON_EXTRACT(p_productos, CONCAT('$[', v_index, '].cantidad'));

        INSERT INTO productosporfactura (fknumfactura, fkcodproducto, cantidad, subtotal)
        VALUES (p_numero, v_codproducto, v_cantidad, 0);

        SET v_index = v_index + 1;
    END WHILE;

    -- Actualizar cliente y vendedor de la factura
    UPDATE factura
    SET fkidcliente = p_fkidcliente,
        fkidvendedor = p_fkidvendedor
    WHERE numero = p_numero;

    -- Retornar resultado como JSON
    SELECT CONCAT(
        '{"numero":', f.numero,
        ',"fecha":"', DATE_FORMAT(f.fecha, '%Y-%m-%dT%H:%i:%s'), '"',
        ',"total":', f.total,
        ',"estado":"', f.estado, '"',
        ',"fkidcliente":', f.fkidcliente,
        ',"fkidvendedor":', f.fkidvendedor, '}'
    ) INTO v_factura_json
    FROM factura f WHERE f.numero = p_numero;

    SELECT CONCAT('[', COALESCE(GROUP_CONCAT(
        JSON_OBJECT(
            'codigo_producto', pf.fkcodproducto,
            'nombre_producto', pr.nombre,
            'cantidad', pf.cantidad,
            'valorunitario', pr.valorunitario,
            'subtotal', pf.subtotal
        )
    ), ''), ']')
    INTO v_productos_json
    FROM productosporfactura pf
    JOIN producto pr ON pr.codigo = pf.fkcodproducto
    WHERE pf.fknumfactura = p_numero;

    SET p_resultado = CONCAT('{"factura":', v_factura_json, ',"productos":', COALESCE(v_productos_json, '[]'), '}');
END$$

-- ------------------------------------------------------------
-- 5. SP BORRAR FACTURA Y PRODUCTOSPORFACTURA
-- ON DELETE CASCADE elimina productosporfactura automáticamente.
-- El trigger restaura stock al borrar cada producto de la factura.
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "sp_borrar_factura_y_productosporfactura",
--     "p_numero": 1, "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE sp_borrar_factura_y_productosporfactura(
    IN p_numero INT,
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_total DECIMAL(18,2);
    DECLARE v_cantidad_productos INT;
    DECLARE v_msg VARCHAR(500);

    IF NOT EXISTS (SELECT 1 FROM factura WHERE numero = p_numero) THEN
        SET v_msg = CONCAT('Factura ', p_numero, ' no existe');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    -- Guardar info antes de borrar para el JSON de respuesta
    SELECT COUNT(*) INTO v_cantidad_productos
    FROM productosporfactura WHERE fknumfactura = p_numero;

    SELECT total INTO v_total FROM factura WHERE numero = p_numero;

    -- Borrar factura (ON DELETE CASCADE borra productosporfactura,
    -- y el trigger restaura stock por cada producto eliminado)
    DELETE FROM factura WHERE numero = p_numero;

    -- Retornar resultado como JSON
    SET p_resultado = JSON_OBJECT(
        'mensaje', 'Factura eliminada exitosamente',
        'numero_eliminado', p_numero,
        'total_eliminado', v_total,
        'productos_eliminados', v_cantidad_productos
    );
END$$

-- ------------------------------------------------------------
-- 6. SP ANULAR FACTURA (borrado lógico)
-- Cambia el estado de la factura a 'anulada' y restaura el stock
-- de todos los productos. NO elimina la factura de la BD.
-- El borrado físico (DELETE) solo lo puede hacer el admin via
-- sp_borrar_factura_y_productosporfactura.
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "sp_anular_factura",
--     "p_numero": 1, "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE sp_anular_factura(
    IN p_numero INT,
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_total DECIMAL(18,2);
    DECLARE v_cantidad_productos INT;
    DECLARE v_estado VARCHAR(10);
    DECLARE v_msg VARCHAR(500);

    -- Validar que la factura existe
    IF NOT EXISTS (SELECT 1 FROM factura WHERE numero = p_numero) THEN
        SET v_msg = CONCAT('Factura ', p_numero, ' no existe');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    -- Validar que no esté ya anulada
    SELECT estado INTO v_estado FROM factura WHERE numero = p_numero;
    IF v_estado = 'anulada' THEN
        SET v_msg = CONCAT('Factura ', p_numero, ' ya está anulada');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    -- Restaurar stock de todos los productos de la factura
    UPDATE producto p
    JOIN productosporfactura pf ON p.codigo = pf.fkcodproducto
    SET p.stock = p.stock + pf.cantidad
    WHERE pf.fknumfactura = p_numero;

    -- Guardar info para la respuesta
    SELECT total INTO v_total FROM factura WHERE numero = p_numero;
    SELECT COUNT(*) INTO v_cantidad_productos FROM productosporfactura WHERE fknumfactura = p_numero;

    -- Cambiar estado a 'anulada'
    UPDATE factura SET estado = 'anulada' WHERE numero = p_numero;

    -- Retornar resultado como JSON
    SET p_resultado = JSON_OBJECT(
        'mensaje', 'Factura anulada exitosamente',
        'numero_anulado', p_numero,
        'total_anulado', v_total,
        'productos_afectados', v_cantidad_productos,
        'estado', 'anulada'
    );
END$$

DELIMITER ;

-- ============================================================
-- PROCEDIMIENTOS ALMACENADOS - USUARIOS CON ROLES
-- Nota: El cifrado lo hace la API C# con el parámetro camposEncriptar
-- ============================================================
DELIMITER $$

-- ------------------------------------------------------------
-- 6. SP CREAR USUARIO CON ROLES
-- Recibe: email, contraseña y JSON array de roles [{"fkidrol":1},...]
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "crear_usuario_con_roles",
--     "p_email": "user@correo.com", "p_contrasena": "pass123",
--     "p_roles_json": "[{\"fkidrol\":1},{\"fkidrol\":2}]",
--     "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE crear_usuario_con_roles(
    IN p_email VARCHAR(100),
    IN p_contrasena VARCHAR(200),
    IN p_roles_json JSON,
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_index INT DEFAULT 0;
    DECLARE v_count INT;
    DECLARE v_idrol INT;
    DECLARE v_roles_json TEXT;

    -- Insertar el usuario
    INSERT INTO usuario (email, contrasena) VALUES (p_email, p_contrasena);

    -- Insertar los roles del usuario
    SET v_count = JSON_LENGTH(p_roles_json);

    WHILE v_index < v_count DO
        SET v_idrol = JSON_EXTRACT(p_roles_json, CONCAT('$[', v_index, '].fkidrol'));
        INSERT INTO rol_usuario (fkemail, fkidrol) VALUES (p_email, v_idrol);
        SET v_index = v_index + 1;
    END WHILE;

    -- Retornar resultado como JSON
    SELECT CONCAT('[', COALESCE(GROUP_CONCAT(
        JSON_OBJECT('idrol', r.id, 'nombre', r.nombre)
    ), ''), ']')
    INTO v_roles_json
    FROM rol_usuario ru
    JOIN rol r ON r.id = ru.fkidrol
    WHERE ru.fkemail = p_email;

    SET p_resultado = CONCAT('{"email":"', p_email, '","roles":', COALESCE(v_roles_json, '[]'), '}');
END$$

-- ------------------------------------------------------------
-- 7. SP ACTUALIZAR USUARIO CON ROLES
-- Actualiza contraseña (si no está vacía) y reemplaza roles
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "actualizar_usuario_con_roles",
--     "p_email": "user@correo.com", "p_contrasena": "newpass",
--     "p_roles": "[{\"fkidrol\":1},{\"fkidrol\":3}]",
--     "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE actualizar_usuario_con_roles(
    IN p_email VARCHAR(100),
    IN p_contrasena VARCHAR(200),
    IN p_roles JSON,
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_index INT DEFAULT 0;
    DECLARE v_count INT;
    DECLARE v_idrol INT;
    DECLARE v_roles_json TEXT;

    -- Actualizar la contraseña solo si no está vacía
    IF p_contrasena IS NOT NULL AND p_contrasena != '' THEN
        UPDATE usuario SET contrasena = p_contrasena WHERE email = p_email;
    END IF;

    -- Eliminar los roles anteriores
    DELETE FROM rol_usuario WHERE fkemail = p_email;

    -- Insertar los nuevos roles
    SET v_count = JSON_LENGTH(p_roles);

    WHILE v_index < v_count DO
        SET v_idrol = JSON_EXTRACT(p_roles, CONCAT('$[', v_index, '].fkidrol'));
        INSERT INTO rol_usuario (fkemail, fkidrol) VALUES (p_email, v_idrol);
        SET v_index = v_index + 1;
    END WHILE;

    -- Retornar resultado como JSON
    SELECT CONCAT('[', COALESCE(GROUP_CONCAT(
        JSON_OBJECT('idrol', r.id, 'nombre', r.nombre)
    ), ''), ']')
    INTO v_roles_json
    FROM rol_usuario ru
    JOIN rol r ON r.id = ru.fkidrol
    WHERE ru.fkemail = p_email;

    SET p_resultado = CONCAT('{"email":"', p_email, '","roles":', COALESCE(v_roles_json, '[]'), '}');
END$$

-- ------------------------------------------------------------
-- 8. SP ELIMINAR USUARIO CON ROLES
-- Elimina el usuario y sus roles
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "eliminar_usuario_con_roles",
--     "p_email": "user@correo.com", "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE eliminar_usuario_con_roles(
    IN p_email VARCHAR(100),
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_msg VARCHAR(500);

    IF NOT EXISTS (SELECT 1 FROM usuario WHERE email = p_email) THEN
        SET v_msg = CONCAT('Usuario ', p_email, ' no existe');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    -- Eliminar roles del usuario primero (FK sin CASCADE)
    DELETE FROM rol_usuario WHERE fkemail = p_email;
    DELETE FROM usuario WHERE email = p_email;

    SET p_resultado = JSON_OBJECT(
        'mensaje', 'Usuario eliminado exitosamente',
        'email_eliminado', p_email
    );
END$$

-- ------------------------------------------------------------
-- 9. SP ACTUALIZAR ROLES DE USUARIO
-- Solo reemplaza los roles sin tocar la contraseña
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "actualizar_roles_usuario",
--     "p_email": "user@correo.com",
--     "p_roles_json": "[{\"fkidrol\":1},{\"fkidrol\":2}]",
--     "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE actualizar_roles_usuario(
    IN p_email VARCHAR(100),
    IN p_roles_json JSON,
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_index INT DEFAULT 0;
    DECLARE v_count INT;
    DECLARE v_idrol INT;
    DECLARE v_roles_json_result TEXT;
    DECLARE v_msg VARCHAR(500);

    IF NOT EXISTS (SELECT 1 FROM usuario WHERE email = p_email) THEN
        SET v_msg = CONCAT('Usuario ', p_email, ' no existe');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    -- Eliminar los roles anteriores
    DELETE FROM rol_usuario WHERE fkemail = p_email;

    -- Insertar los nuevos roles
    SET v_count = JSON_LENGTH(p_roles_json);

    WHILE v_index < v_count DO
        SET v_idrol = JSON_EXTRACT(p_roles_json, CONCAT('$[', v_index, '].fkidrol'));
        INSERT INTO rol_usuario (fkemail, fkidrol) VALUES (p_email, v_idrol);
        SET v_index = v_index + 1;
    END WHILE;

    -- Retornar resultado como JSON
    SELECT CONCAT('[', COALESCE(GROUP_CONCAT(
        JSON_OBJECT('idrol', r.id, 'nombre', r.nombre)
    ), ''), ']')
    INTO v_roles_json_result
    FROM rol_usuario ru
    JOIN rol r ON r.id = ru.fkidrol
    WHERE ru.fkemail = p_email;

    SET p_resultado = CONCAT('{"email":"', p_email, '","roles":', COALESCE(v_roles_json_result, '[]'), '}');
END$$

-- ------------------------------------------------------------
-- 10. SP CONSULTAR USUARIO CON ROLES
-- Retorna JSON con email y array de roles
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "consultar_usuario_con_roles",
--     "p_email": "admin@correo.com", "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE consultar_usuario_con_roles(
    IN p_email VARCHAR(100),
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_roles_json TEXT;
    DECLARE v_msg VARCHAR(500);

    IF NOT EXISTS (SELECT 1 FROM usuario WHERE email = p_email) THEN
        SET v_msg = CONCAT('Usuario ', p_email, ' no existe');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    SELECT CONCAT('[', COALESCE(GROUP_CONCAT(
        JSON_OBJECT('idrol', r.id, 'nombre', r.nombre)
    ), ''), ']')
    INTO v_roles_json
    FROM rol_usuario ru
    JOIN rol r ON r.id = ru.fkidrol
    WHERE ru.fkemail = p_email;

    SET p_resultado = CONCAT('{"email":"', p_email, '","roles":', COALESCE(v_roles_json, '[]'), '}');
END$$

-- ------------------------------------------------------------
-- 11. SP LISTAR USUARIOS CON ROLES
-- Retorna JSON array con todos los usuarios y sus roles
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "listar_usuarios_con_roles", "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE listar_usuarios_con_roles(
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_resultado TEXT;

    SELECT CONCAT('[', COALESCE(GROUP_CONCAT(
        CONCAT(
            '{"email":"', sub.email, '"',
            ',"roles":', COALESCE(sub.roles_json, '[]'),
            '}'
        )
    ), ''), ']')
    INTO v_resultado
    FROM (
        SELECT u.email,
            (SELECT CONCAT('[', COALESCE(GROUP_CONCAT(
                JSON_OBJECT('idrol', r.id, 'nombre', r.nombre)
            ), ''), ']')
            FROM rol_usuario ru
            JOIN rol r ON r.id = ru.fkidrol
            WHERE ru.fkemail = u.email
            ) AS roles_json
        FROM usuario u
        ORDER BY u.email
    ) AS sub;

    SET p_resultado = COALESCE(v_resultado, '[]');
END$$

DELIMITER ;

-- ============================================================
-- PROCEDIMIENTOS ALMACENADOS - PERMISOS (RBAC)
-- ============================================================
DELIMITER $$

-- ------------------------------------------------------------
-- 12. SP VERIFICAR ACCESO A RUTA
-- Verifica si un usuario tiene permiso para acceder a una ruta
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "verificar_acceso_ruta",
--     "p_email": "admin@correo.com", "p_fkidruta": 2,
--     "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE verificar_acceso_ruta(
    IN p_email VARCHAR(100),
    IN p_fkidruta INT,
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_tiene_acceso BOOLEAN DEFAULT FALSE;

    SELECT EXISTS(
        SELECT 1
        FROM usuario u
        INNER JOIN rol_usuario ur ON u.email = ur.fkemail
        INNER JOIN rutarol rr ON ur.fkidrol = rr.fkidrol
        WHERE u.email = p_email AND rr.fkidruta = p_fkidruta
    ) INTO v_tiene_acceso;

    SET p_resultado = JSON_OBJECT(
        'tiene_acceso', v_tiene_acceso,
        'email', p_email,
        'fkidruta', p_fkidruta
    );
END$$

-- ------------------------------------------------------------
-- 13. SP LISTAR RUTAROL
-- Lista todos los permisos ruta-rol con nombres
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "listar_rutarol", "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE listar_rutarol(
    OUT p_resultado JSON
)
BEGIN
    DECLARE v_resultado TEXT;

    SELECT CONCAT('[', COALESCE(GROUP_CONCAT(
        JSON_OBJECT(
            'fkidruta', rr.fkidruta,
            'ruta', rt.ruta,
            'fkidrol', rr.fkidrol,
            'rol', r.nombre
        )
        ORDER BY rt.ruta, r.nombre
    ), ''), ']')
    INTO v_resultado
    FROM rutarol rr
    JOIN ruta rt ON rt.id = rr.fkidruta
    JOIN rol r ON r.id = rr.fkidrol;

    SET p_resultado = COALESCE(v_resultado, '[]');
END$$

-- ------------------------------------------------------------
-- 14. SP CREAR RUTAROL
-- Asigna un rol a una ruta por IDs
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "crear_rutarol",
--     "p_fkidruta": 8, "p_fkidrol": 3,
--     "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE crear_rutarol(
    IN p_fkidruta INT,
    IN p_fkidrol INT,
    OUT p_resultado JSON
)
proc_body: BEGIN
    -- Verificar si la ruta existe
    IF NOT EXISTS (SELECT 1 FROM ruta WHERE id = p_fkidruta) THEN
        SET p_resultado = JSON_OBJECT('success', false, 'message', 'La ruta especificada no existe');
        LEAVE proc_body;
    END IF;

    -- Verificar si el rol existe
    IF NOT EXISTS (SELECT 1 FROM rol WHERE id = p_fkidrol) THEN
        SET p_resultado = JSON_OBJECT('success', false, 'message', 'El rol especificado no existe');
        LEAVE proc_body;
    END IF;

    -- Verificar si el permiso ya existe
    IF EXISTS (SELECT 1 FROM rutarol WHERE fkidruta = p_fkidruta AND fkidrol = p_fkidrol) THEN
        SET p_resultado = JSON_OBJECT('success', false, 'message', 'El permiso ya existe');
        LEAVE proc_body;
    END IF;

    INSERT INTO rutarol (fkidruta, fkidrol) VALUES (p_fkidruta, p_fkidrol);
    SET p_resultado = JSON_OBJECT('success', true, 'message', 'Permiso creado exitosamente');
END$$

-- ------------------------------------------------------------
-- 15. SP ELIMINAR RUTAROL
-- Quita un permiso ruta-rol por IDs
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "eliminar_rutarol",
--     "p_fkidruta": 8, "p_fkidrol": 3,
--     "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE eliminar_rutarol(
    IN p_fkidruta INT,
    IN p_fkidrol INT,
    OUT p_resultado JSON
)
proc_body: BEGIN
    -- Verificar si el permiso existe
    IF NOT EXISTS (SELECT 1 FROM rutarol WHERE fkidruta = p_fkidruta AND fkidrol = p_fkidrol) THEN
        SET p_resultado = JSON_OBJECT('success', false, 'message', 'El permiso no existe');
        LEAVE proc_body;
    END IF;

    DELETE FROM rutarol WHERE fkidruta = p_fkidruta AND fkidrol = p_fkidrol;
    SET p_resultado = JSON_OBJECT('success', true, 'message', 'Permiso eliminado exitosamente');
END$$

DELIMITER ;

-- ============================================================
-- DATOS (identicos a SQL Server y PostgreSQL)
-- ============================================================

-- Empresas
INSERT INTO empresa (codigo, nombre) VALUES
('E001', 'Comercial Los Andes S.A.'),
('E002', 'Distribuciones El Centro S.A.'),
('E999', 'Empresa Test');

-- Personas
INSERT INTO persona (codigo, nombre, email, telefono) VALUES
('P001', 'Ana Torres', 'ana.torres@correo.com', '3011111111'),
('P002', 'Carlos Pérez', 'carlos.perez@correo.com', '3022222222'),
('P003', 'María Gómez', 'maria.gomez@correo.com', '3033333333'),
('P004', 'Juan Díaz', 'juan.diaz@correo.com', '3044444444'),
('P005', 'Laura Rojas', 'laura.rojas@correo.com', '3055555555'),
('P006', 'Pedro Castillo', 'pedro.castillo@correo.com', '3066666666');

-- Productos (stocks ya ajustados post-facturas, igual que SQL Server/PostgreSQL)
INSERT INTO producto (codigo, nombre, stock, valorunitario) VALUES
('PR001', 'Laptop Lenovo IdeaPad', 17, 2500000),
('PR002', 'Monitor Samsung 24"', 27, 800000),
('PR003', 'Teclado Logitech K380', 42, 150000),
('PR004', 'Mouse HP', 55, 90000),
('PR005', 'Impresora Epson EcoTank1', 14, 1100000),
('PR006', 'Auriculares Sony WH-CH510', 23, 240000),
('PR007', 'Tablet Samsung Tab A9', 15, 950000),
('PR008', 'Disco Duro Seagate 1TB', 32, 280000);

-- Roles (con IDs explícitos)
INSERT INTO rol (id, nombre) VALUES
(1, 'Administrador'),
(2, 'Vendedor'),
(3, 'Cajero'),
(4, 'Contador'),
(5, 'Cliente');

-- Rutas
INSERT INTO ruta (ruta, descripcion) VALUES
('/home', 'Página principal - Dashboard'),
('/usuario', 'Gestión de usuarios'),
('/factura', 'Gestión de facturas'),
('/cliente', 'Gestión de clientes'),
('/vendedor', 'Gestión de vendedores'),
('/persona', 'Gestión de personas'),
('/empresa', 'Gestión de empresas'),
('/producto', 'Gestión de productos'),
('/rol', 'Gestión de roles'),
('/permiso', 'Gestión de permisos (asignación rol-ruta)'),
('/permiso/crear', 'Crear permiso (POST)'),
('/permiso/eliminar', 'Eliminar permiso (POST)'),
('/ruta', 'Gestión de rutas del sistema'),
('/ruta/crear', 'Crear ruta (POST)'),
('/ruta/eliminar', 'Eliminar ruta (POST)');

-- Usuarios (con hashes BCrypt identicos a SQL Server/PostgreSQL)
INSERT INTO usuario (email, contrasena) VALUES
('admin@correo.com', '$2a$12$3UgI.Eof.FhzsYUWESI9n.qFaqkV2JPhvW3L/1GTKowNJnGaD8F.G'),
('vendedor1@correo.com', '$2a$12$Dgog4VaHqMzhliPVJy1BcOMd6.izEGNeRDtZ.O7SPmBLc6UVthVTG'),
('jefe@correo.com', 'jefe123'),
('cliente1@correo.com', 'cli123'),
('test_encript@correo.com', '$2a$11$Ci0J2yBltDgQHfjadgkl0OtbcF5pUf97vTq/4Xr0KEU/86l8ybjBe'),
('nuevo@correo.com', '$2a$11$cmtGBxllwc7MCzpnKVSWuumiOgCaG6PaKWcN1z9N0bjjnkobbFDzO'),
('carlos.castro@usbmed.edu.co', '$2a$10$YYl6bHCflCnk8suUrms3ie.rnpLvfD9nHJtehZwhcSkINelGwt6iC'),
('carloscastro5033@correo.itm.edu.co', '$2a$10$YYl6bHCflCnk8suUrms3ie.rnpLvfD9nHJtehZwhcSkINelGwt6iC');

-- Clientes (con IDs explícitos, incluyendo id=5 saltando id=4)
INSERT INTO cliente (id, credito, fkcodpersona, fkcodempresa) VALUES
(1, 520000, 'P001', 'E001'),
(2, 250000, 'P003', 'E002'),
(3, 400000, 'P005', 'E001'),
(5, 700000, 'P006', 'E001');

-- Actualizar AUTO_INCREMENT de cliente
ALTER TABLE cliente AUTO_INCREMENT = 6;

-- Vendedores (con IDs explícitos)
INSERT INTO vendedor (id, carnet, direccion, fkcodpersona) VALUES
(1, 1001, 'Calle 10 #5-33', 'P002'),
(2, 1002, 'Carrera 15 #7-20', 'P004'),
(3, 1003, 'Avenida 30 #18-09', 'P006');

-- Actualizar AUTO_INCREMENT de vendedor
ALTER TABLE vendedor AUTO_INCREMENT = 4;

-- ============================================================
-- DATOS DE FACTURAS (con triggers deshabilitados para carga semilla)
-- Los stocks ya fueron insertados post-ajuste arriba, asi que
-- deshabilitamos triggers para no descontar de nuevo.
-- MySQL no tiene DISABLE TRIGGER, asi que los eliminamos y recreamos.
-- ============================================================

-- Eliminar triggers temporalmente
DROP TRIGGER IF EXISTS trg_prodfact_before_insert;
DROP TRIGGER IF EXISTS trg_prodfact_after_insert;
DROP TRIGGER IF EXISTS trg_prodfact_before_update;
DROP TRIGGER IF EXISTS trg_prodfact_after_update;
DROP TRIGGER IF EXISTS trg_prodfact_before_delete;
DROP TRIGGER IF EXISTS trg_prodfact_after_delete;

-- Facturas (con IDs explícitos)
INSERT INTO factura (numero, fecha, total, fkidcliente, fkidvendedor) VALUES
(1, '2025-12-03 12:57:19', 5000000, 1, 1),
(2, '2025-12-03 12:57:19', 1250000, 2, 2),
(3, '2025-12-03 12:57:19', 2030000, 3, 3),
(4, '2025-12-03 13:04:59', 950000, 1, 1),
(5, '2025-12-03 13:05:17', 2740000, 2, 2),
(6, '2025-12-03 13:05:35', 4850000, 3, 3);

-- Actualizar AUTO_INCREMENT de factura
ALTER TABLE factura AUTO_INCREMENT = 7;

-- Productos por factura (subtotales directos, sin triggers)
INSERT INTO productosporfactura (fknumfactura, fkcodproducto, cantidad, subtotal) VALUES
(1, 'PR001', 2, 5000000),
(2, 'PR002', 1, 800000),
(2, 'PR003', 3, 450000),
(3, 'PR004', 5, 450000),
(3, 'PR005', 1, 1100000),
(3, 'PR006', 2, 480000),
(4, 'PR007', 1, 950000),
(5, 'PR007', 2, 1900000),
(5, 'PR008', 3, 840000),
(6, 'PR001', 1, 2500000),
(6, 'PR002', 2, 1600000),
(6, 'PR003', 5, 750000);

-- Recrear triggers
DELIMITER $$

CREATE TRIGGER trg_prodfact_before_insert
BEFORE INSERT ON productosporfactura
FOR EACH ROW
BEGIN
    DECLARE v_precio DECIMAL(18,2);
    DECLARE v_stock INT;
    DECLARE v_msg VARCHAR(500);

    SELECT valorunitario, stock INTO v_precio, v_stock
    FROM producto WHERE codigo = NEW.fkcodproducto;

    IF v_stock < NEW.cantidad THEN
        SET v_msg = CONCAT('Stock insuficiente para producto ', NEW.fkcodproducto,
            '. Stock disponible: ', v_stock, ', cantidad solicitada: ', NEW.cantidad);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    SET NEW.subtotal = NEW.cantidad * v_precio;
    UPDATE producto SET stock = stock - NEW.cantidad WHERE codigo = NEW.fkcodproducto;
END$$

CREATE TRIGGER trg_prodfact_after_insert
AFTER INSERT ON productosporfactura
FOR EACH ROW
BEGIN
    UPDATE factura
    SET total = (SELECT COALESCE(SUM(subtotal), 0) FROM productosporfactura WHERE fknumfactura = NEW.fknumfactura)
    WHERE numero = NEW.fknumfactura;
END$$

CREATE TRIGGER trg_prodfact_before_update
BEFORE UPDATE ON productosporfactura
FOR EACH ROW
BEGIN
    DECLARE v_precio DECIMAL(18,2);
    DECLARE v_stock INT;
    DECLARE v_msg VARCHAR(500);

    SELECT valorunitario INTO v_precio FROM producto WHERE codigo = NEW.fkcodproducto;
    SELECT stock INTO v_stock FROM producto WHERE codigo = NEW.fkcodproducto;

    IF v_stock + OLD.cantidad < NEW.cantidad THEN
        SET v_msg = CONCAT('Stock insuficiente para producto ', NEW.fkcodproducto,
            '. Stock disponible: ', v_stock + OLD.cantidad, ', cantidad solicitada: ', NEW.cantidad);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    SET NEW.subtotal = NEW.cantidad * v_precio;
    UPDATE producto
    SET stock = stock + OLD.cantidad - NEW.cantidad
    WHERE codigo = NEW.fkcodproducto;
END$$

CREATE TRIGGER trg_prodfact_after_update
AFTER UPDATE ON productosporfactura
FOR EACH ROW
BEGIN
    UPDATE factura
    SET total = (SELECT COALESCE(SUM(subtotal), 0) FROM productosporfactura WHERE fknumfactura = NEW.fknumfactura)
    WHERE numero = NEW.fknumfactura;
END$$

CREATE TRIGGER trg_prodfact_before_delete
BEFORE DELETE ON productosporfactura
FOR EACH ROW
BEGIN
    UPDATE producto
    SET stock = stock + OLD.cantidad
    WHERE codigo = OLD.fkcodproducto;
END$$

CREATE TRIGGER trg_prodfact_after_delete
AFTER DELETE ON productosporfactura
FOR EACH ROW
BEGIN
    UPDATE factura
    SET total = (SELECT COALESCE(SUM(subtotal), 0) FROM productosporfactura WHERE fknumfactura = OLD.fknumfactura)
    WHERE numero = OLD.fknumfactura;
END$$

DELIMITER ;

-- Roles por usuario
INSERT INTO rol_usuario (fkemail, fkidrol) VALUES
('admin@correo.com', 1),
('vendedor1@correo.com', 2),
('vendedor1@correo.com', 3),
('jefe@correo.com', 1),
('jefe@correo.com', 3),
('jefe@correo.com', 4),
('cliente1@correo.com', 5),
('test_encript@correo.com', 1),
('nuevo@correo.com', 1),
('nuevo@correo.com', 2),
('nuevo@correo.com', 3),
('carlos.castro@usbmed.edu.co', 1),
('carlos.castro@usbmed.edu.co', 2),
('carlos.castro@usbmed.edu.co', 3),
('carlos.castro@usbmed.edu.co', 4),
('carlos.castro@usbmed.edu.co', 5),
('carloscastro5033@correo.itm.edu.co', 1),
('carloscastro5033@correo.itm.edu.co', 2),
('carloscastro5033@correo.itm.edu.co', 3),
('carloscastro5033@correo.itm.edu.co', 4),
('carloscastro5033@correo.itm.edu.co', 5);

-- Rutas por rol
-- Rutas: 1=/home,2=/usuarios,3=/facturas,4=/clientes,5=/vendedores,6=/personas,7=/empresas,8=/productos,9=/roles,10=/permisos,11=/permisos/crear,12=/permisos/eliminar,13=/rutas,14=/rutas/crear,15=/rutas/eliminar
-- Roles: 1=Administrador,2=Vendedor,3=Cajero,4=Contador,5=Cliente
INSERT INTO rutarol (fkidruta, fkidrol) VALUES
(1, 1), (2, 1), (3, 1), (4, 1), (5, 1), (6, 1), (7, 1), (8, 1), (9, 1), (10, 1), (11, 1), (12, 1), (13, 1), (14, 1), (15, 1),
(1, 2), (3, 2), (4, 2),
(1, 3), (3, 3),
(1, 4), (4, 4), (8, 4),
(1, 5), (8, 5);
