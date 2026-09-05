-- ============================================================
-- Script de creación de base de datos: bdfacturas_postgres_local
-- Compatible con PostgreSQL 10+
-- Incluye: tablas, restricciones, secuencias y datos de ejemplo
-- ============================================================

-- Crear la base de datos (ejecutar aparte si es necesario):
-- CREATE DATABASE bdfacturas_postgres_local;

-- ============================================================
-- TABLAS INDEPENDIENTES (sin foreign keys)
-- ============================================================

CREATE TABLE empresa (
    codigo VARCHAR(10) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    CONSTRAINT pk_empresa PRIMARY KEY (codigo)
);

CREATE TABLE persona (
    codigo VARCHAR(10) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    CONSTRAINT pk_persona PRIMARY KEY (codigo)
);

CREATE TABLE producto (
    codigo VARCHAR(10) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    stock INTEGER NOT NULL,
    valorunitario NUMERIC NOT NULL,
    CONSTRAINT pk_producto PRIMARY KEY (codigo)
);

CREATE TABLE rol (
    id SERIAL NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    CONSTRAINT pk_rol PRIMARY KEY (id)
);

CREATE TABLE ruta (
    id SERIAL NOT NULL,
    ruta VARCHAR(100) NOT NULL,
    descripcion VARCHAR(200) NOT NULL,
    CONSTRAINT pk_ruta PRIMARY KEY (id),
    CONSTRAINT uq_ruta UNIQUE (ruta)
);

CREATE TABLE usuario (
    email VARCHAR(100) NOT NULL,
    contrasena VARCHAR(200) NOT NULL,
    CONSTRAINT pk_usuario PRIMARY KEY (email)
);

-- ============================================================
-- TABLAS DEPENDIENTES (con foreign keys)
-- ============================================================

CREATE TABLE cliente (
    id SERIAL NOT NULL,
    credito NUMERIC NOT NULL DEFAULT 0,
    fkcodpersona VARCHAR(10) NOT NULL,
    fkcodempresa VARCHAR(10),
    CONSTRAINT pk_cliente PRIMARY KEY (id),
    CONSTRAINT fk_cliente_persona FOREIGN KEY (fkcodpersona) REFERENCES persona(codigo),
    CONSTRAINT fk_cliente_empresa FOREIGN KEY (fkcodempresa) REFERENCES empresa(codigo)
);

CREATE TABLE vendedor (
    id SERIAL NOT NULL,
    carnet INTEGER NOT NULL,
    direccion VARCHAR(100) NOT NULL,
    fkcodpersona VARCHAR(10) NOT NULL,
    CONSTRAINT pk_vendedor PRIMARY KEY (id),
    CONSTRAINT fk_vendedor_persona FOREIGN KEY (fkcodpersona) REFERENCES persona(codigo)
);

CREATE TABLE factura (
    numero SERIAL NOT NULL,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total NUMERIC NOT NULL DEFAULT 0,
    estado VARCHAR(10) NOT NULL DEFAULT 'activa',
    fkidcliente INTEGER NOT NULL,
    fkidvendedor INTEGER NOT NULL,
    CONSTRAINT pk_factura PRIMARY KEY (numero),
    CONSTRAINT fk_factura_cliente FOREIGN KEY (fkidcliente) REFERENCES cliente(id),
    CONSTRAINT fk_factura_vendedor FOREIGN KEY (fkidvendedor) REFERENCES vendedor(id)
);

CREATE TABLE productosporfactura (
    fknumfactura INTEGER NOT NULL,
    fkcodproducto VARCHAR(10) NOT NULL,
    cantidad INTEGER NOT NULL,
    subtotal NUMERIC NOT NULL DEFAULT 0,
    CONSTRAINT pk_productosporfactura PRIMARY KEY (fknumfactura, fkcodproducto),
    CONSTRAINT fk_prodfact_factura FOREIGN KEY (fknumfactura) REFERENCES factura(numero) ON DELETE CASCADE,
    CONSTRAINT fk_prodfact_producto FOREIGN KEY (fkcodproducto) REFERENCES producto(codigo)
);

CREATE TABLE rol_usuario (
    fkemail VARCHAR(100) NOT NULL,
    fkidrol INTEGER NOT NULL,
    CONSTRAINT pk_rol_usuario PRIMARY KEY (fkemail, fkidrol),
    CONSTRAINT fk_rolusuario_usuario FOREIGN KEY (fkemail) REFERENCES usuario(email),
    CONSTRAINT fk_rolusuario_rol FOREIGN KEY (fkidrol) REFERENCES rol(id)
);

CREATE TABLE rutarol (
    fkidruta INT NOT NULL,
    fkidrol INT NOT NULL,
    CONSTRAINT pk_rutarol PRIMARY KEY (fkidruta, fkidrol),
    CONSTRAINT fk_rutarol_ruta FOREIGN KEY (fkidruta) REFERENCES ruta(id) ON DELETE CASCADE,
    CONSTRAINT fk_rutarol_rol FOREIGN KEY (fkidrol) REFERENCES rol(id) ON DELETE CASCADE
);

-- ============================================================
-- DATOS
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

-- Productos
INSERT INTO producto (codigo, nombre, stock, valorunitario) VALUES
('PR001', 'Laptop Lenovo IdeaPad', 17, 2500000),
('PR002', 'Monitor Samsung 24"', 27, 800000),
('PR003', 'Teclado Logitech K380', 42, 150000),
('PR004', 'Mouse HP', 55, 90000),
('PR005', 'Impresora Epson EcoTank1', 14, 1100000),
('PR006', 'Auriculares Sony WH-CH510', 23, 240000),
('PR007', 'Tablet Samsung Tab A9', 15, 950000),
('PR008', 'Disco Duro Seagate 1TB', 32, 280000);

-- Roles
INSERT INTO rol (id, nombre) VALUES
(1, 'Administrador'),
(2, 'Vendedor'),
(3, 'Cajero'),
(4, 'Contador'),
(5, 'Cliente');

-- Actualizar secuencia de rol
SELECT setval('rol_id_seq', (SELECT MAX(id) FROM rol));

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

-- Usuarios
INSERT INTO usuario (email, contrasena) VALUES
('admin@correo.com', '$2a$12$3UgI.Eof.FhzsYUWESI9n.qFaqkV2JPhvW3L/1GTKowNJnGaD8F.G'),
('vendedor1@correo.com', '$2a$12$Dgog4VaHqMzhliPVJy1BcOMd6.izEGNeRDtZ.O7SPmBLc6UVthVTG'),
('jefe@correo.com', 'jefe123'),
('cliente1@correo.com', 'cli123'),
('test_encript@correo.com', '$2a$11$Ci0J2yBltDgQHfjadgkl0OtbcF5pUf97vTq/4Xr0KEU/86l8ybjBe'),
('nuevo@correo.com', '$2a$11$cmtGBxllwc7MCzpnKVSWuumiOgCaG6PaKWcN1z9N0bjjnkobbFDzO'),
('carlos.castro@usbmed.edu.co', '$2a$10$YYl6bHCflCnk8suUrms3ie.rnpLvfD9nHJtehZwhcSkINelGwt6iC'),
('carloscastro5033@correo.itm.edu.co', '$2a$10$YYl6bHCflCnk8suUrms3ie.rnpLvfD9nHJtehZwhcSkINelGwt6iC');

-- Clientes
INSERT INTO cliente (id, credito, fkcodpersona, fkcodempresa) VALUES
(1, 520000, 'P001', 'E001'),
(2, 250000, 'P003', 'E002'),
(3, 400000, 'P005', 'E001'),
(5, 700000, 'P006', 'E001');

-- Actualizar secuencia de cliente
SELECT setval('cliente_id_seq', (SELECT MAX(id) FROM cliente));

-- Vendedores
INSERT INTO vendedor (id, carnet, direccion, fkcodpersona) VALUES
(1, 1001, 'Calle 10 #5-33', 'P002'),
(2, 1002, 'Carrera 15 #7-20', 'P004'),
(3, 1003, 'Avenida 30 #18-09', 'P006');

-- Actualizar secuencia de vendedor
SELECT setval('vendedor_id_seq', (SELECT MAX(id) FROM vendedor));

-- Facturas
INSERT INTO factura (numero, fecha, total, fkidcliente, fkidvendedor) VALUES
(1, '2025-12-03 12:57:19.27592', 5000000, 1, 1),
(2, '2025-12-03 12:57:19.27592', 1250000, 2, 2),
(3, '2025-12-03 12:57:19.27592', 2030000, 3, 3),
(4, '2025-12-03 13:04:59.028613', 950000, 1, 1),
(5, '2025-12-03 13:05:17.874385', 2740000, 2, 2),
(6, '2025-12-03 13:05:35.02846', 4850000, 3, 3);

-- Actualizar secuencia de factura
SELECT setval('factura_numero_seq', (SELECT MAX(numero) FROM factura));

-- Productos por factura
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
-- Rutas por rol (fkidruta, fkidrol)
-- Rutas: 1=/home,2=/usuarios,3=/facturas,4=/clientes,5=/vendedores,6=/personas,7=/empresas,8=/productos,9=/roles,10=/permisos,11=/permisos/crear,12=/permisos/eliminar,13=/rutas,14=/rutas/crear,15=/rutas/eliminar
-- Roles: 1=Administrador,2=Vendedor,3=Cajero,4=Contador,5=Cliente
INSERT INTO rutarol (fkidruta, fkidrol) VALUES
(1, 1), (2, 1), (3, 1), (4, 1), (5, 1), (6, 1), (7, 1), (8, 1), (9, 1), (10, 1), (11, 1), (12, 1), (13, 1), (14, 1), (15, 1),
(1, 2), (3, 2), (4, 2),
(1, 3), (3, 3),
(1, 4), (4, 4), (8, 4),
(1, 5), (8, 5);

-- ============================================================
-- TRIGGER: Actualizar totales de factura y stock de producto
-- Se ejecuta automáticamente al INSERT, UPDATE o DELETE en
-- productosporfactura. Calcula subtotal, ajusta stock y
-- recalcula el total de la factura.
-- ============================================================

CREATE OR REPLACE FUNCTION actualizar_totales_y_stock()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        -- Validar stock suficiente
        IF (SELECT stock FROM producto WHERE codigo = NEW.fkcodproducto) < NEW.cantidad THEN
            RAISE EXCEPTION 'Stock insuficiente para producto %. Stock disponible: %, cantidad solicitada: %',
                NEW.fkcodproducto,
                (SELECT stock FROM producto WHERE codigo = NEW.fkcodproducto),
                NEW.cantidad;
        END IF;
        NEW.subtotal := NEW.cantidad * (SELECT valorunitario FROM producto WHERE codigo = NEW.fkcodproducto);
        UPDATE producto SET stock = stock - NEW.cantidad WHERE codigo = NEW.fkcodproducto;
        UPDATE factura SET total = (SELECT COALESCE(SUM(subtotal),0) FROM productosporfactura WHERE fknumfactura = NEW.fknumfactura) + NEW.subtotal WHERE numero = NEW.fknumfactura;
        RETURN NEW;
    END IF;
    IF TG_OP = 'UPDATE' THEN
        -- Validar stock suficiente (considerando la devolucion del stock anterior)
        IF (SELECT stock FROM producto WHERE codigo = NEW.fkcodproducto) + OLD.cantidad < NEW.cantidad THEN
            RAISE EXCEPTION 'Stock insuficiente para producto %. Stock disponible: %, cantidad solicitada: %',
                NEW.fkcodproducto,
                (SELECT stock FROM producto WHERE codigo = NEW.fkcodproducto) + OLD.cantidad,
                NEW.cantidad;
        END IF;
        NEW.subtotal := NEW.cantidad * (SELECT valorunitario FROM producto WHERE codigo = NEW.fkcodproducto);
        UPDATE producto SET stock = stock + OLD.cantidad - NEW.cantidad WHERE codigo = NEW.fkcodproducto;
        UPDATE factura SET total = (SELECT COALESCE(SUM(subtotal),0) FROM productosporfactura WHERE fknumfactura = NEW.fknumfactura AND fkcodproducto != NEW.fkcodproducto) + NEW.subtotal WHERE numero = NEW.fknumfactura;
        RETURN NEW;
    END IF;
    IF TG_OP = 'DELETE' THEN
        UPDATE producto SET stock = stock + OLD.cantidad WHERE codigo = OLD.fkcodproducto;
        UPDATE factura SET total = (SELECT COALESCE(SUM(subtotal),0) FROM productosporfactura WHERE fknumfactura = OLD.fknumfactura AND fkcodproducto != OLD.fkcodproducto) WHERE numero = OLD.fknumfactura;
        RETURN OLD;
    END IF;
    RETURN NULL;
END;
$$;

CREATE TRIGGER trg_actualizar_totales_y_stock
    BEFORE INSERT OR UPDATE OR DELETE ON productosporfactura
    FOR EACH ROW
    EXECUTE FUNCTION actualizar_totales_y_stock();

-- ============================================================
-- PROCEDIMIENTOS ALMACENADOS - FACTURAS Y PRODUCTOS POR FACTURA
-- Requiere PostgreSQL 11+ (CREATE PROCEDURE)
-- La API detecta automáticamente PROCEDURE y usa CALL
-- Los resultados se retornan via parámetro INOUT tipo JSON
-- ============================================================

-- ------------------------------------------------------------
-- 1. SP INSERTAR FACTURA Y PRODUCTOSPORFACTURA
-- Recibe: id cliente, id vendedor, y un JSON array de productos
-- Retorna: JSON con la factura creada y sus productos
-- Nota: El trigger actualizar_totales_y_stock() se encarga de
--       calcular subtotal, descontar stock y actualizar total factura.
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "sp_insertar_factura_y_productosporfactura",
--     "p_fkidcliente": 1, "p_fkidvendedor": 1,
--     "p_productos": "[{\"codigo\":\"PR001\",\"cantidad\":2},{\"codigo\":\"PR003\",\"cantidad\":3}]",
--     "p_resultado": null }
-- ------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_insertar_factura_y_productosporfactura(
    IN p_fkidcliente INTEGER,
    IN p_fkidvendedor INTEGER,
    IN p_productos JSON,
    IN p_minimo_detalle INTEGER DEFAULT 1,
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_numero INTEGER;
    v_item JSON;
    v_codigo VARCHAR;
    v_cantidad INTEGER;
BEGIN
    -- Validar minimo de productos
    -- NULLIF(p_minimo_detalle, 0): la API envia 0 cuando no se pasa el parametro, NULLIF lo convierte a NULL
    -- COALESCE(..., 1): si es NULL usa 1 como default
    IF p_productos IS NULL OR json_array_length(p_productos) < COALESCE(NULLIF(p_minimo_detalle, 0), 1) THEN
        RAISE EXCEPTION 'La factura requiere minimo % producto(s).', COALESCE(NULLIF(p_minimo_detalle, 0), 1);
    END IF;

    -- Crear la factura con total 0 (el trigger actualiza el total)
    INSERT INTO factura (fkidcliente, fkidvendedor, total)
    VALUES (p_fkidcliente, p_fkidvendedor, 0)
    RETURNING factura.numero INTO v_numero;

    -- Recorrer cada producto del JSON e insertar detalle
    -- El trigger calcula subtotal, descuenta stock y actualiza total
    FOR v_item IN SELECT * FROM json_array_elements(p_productos)
    LOOP
        v_codigo := v_item->>'codigo';
        v_cantidad := (v_item->>'cantidad')::INTEGER;

        INSERT INTO productosporfactura (fknumfactura, fkcodproducto, cantidad, subtotal)
        VALUES (v_numero, v_codigo, v_cantidad, 0);
    END LOOP;

    -- Retornar resultado como JSON
    SELECT json_build_object(
        'factura', (
            SELECT row_to_json(fac) FROM (
                SELECT f.numero, f.fecha, f.total, f.estado, f.fkidcliente, f.fkidvendedor
                FROM factura f WHERE f.numero = v_numero
            ) fac
        ),
        'productos', (
            SELECT json_agg(row_to_json(det)) FROM (
                SELECT pf.fkcodproducto AS codigo_producto, pr.nombre AS nombre_producto,
                       pf.cantidad, pr.valorunitario, pf.subtotal
                FROM productosporfactura pf
                JOIN producto pr ON pr.codigo = pf.fkcodproducto
                WHERE pf.fknumfactura = v_numero
            ) det
        )
    ) INTO p_resultado;
END;
$$;

-- ------------------------------------------------------------
-- 2. SP CONSULTAR FACTURA Y PRODUCTOSPORFACTURA
-- Consulta una factura por número con detalle de productos,
-- nombre del cliente y nombre del vendedor
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "sp_consultar_factura_y_productosporfactura",
--     "p_numero": 1, "p_resultado": "" }
-- ------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_consultar_factura_y_productosporfactura(
    IN p_numero INTEGER,
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM factura WHERE factura.numero = p_numero) THEN
        RAISE EXCEPTION 'Factura % no existe', p_numero;
    END IF;

    SELECT json_build_object(
        'factura', json_build_object(
            'numero', f.numero,
            'fecha', f.fecha,
            'total', f.total,
            'estado', f.estado,
            'fkidcliente', f.fkidcliente,
            'nombre_cliente', pc.nombre,
            'fkidvendedor', f.fkidvendedor,
            'nombre_vendedor', pv.nombre
        ),
        'productos', (
            SELECT json_agg(json_build_object(
                'codigo_producto', pr.codigo,
                'nombre_producto', pr.nombre,
                'cantidad', pf.cantidad,
                'valorunitario', pr.valorunitario,
                'subtotal', pf.subtotal
            ))
            FROM productosporfactura pf
            JOIN producto pr ON pr.codigo = pf.fkcodproducto
            WHERE pf.fknumfactura = f.numero
        )
    ) INTO p_resultado
    FROM factura f
    JOIN cliente c ON c.id = f.fkidcliente
    JOIN persona pc ON pc.codigo = c.fkcodpersona
    JOIN vendedor v ON v.id = f.fkidvendedor
    JOIN persona pv ON pv.codigo = v.fkcodpersona
    WHERE f.numero = p_numero;
END;
$$;

-- ------------------------------------------------------------
-- 3. SP LISTAR FACTURAS Y PRODUCTOSPORFACTURA
-- Lista todas las facturas con sus productos, cliente y vendedor
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "sp_listar_facturas_y_productosporfactura",
--     "p_resultado": "" }
-- ------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_listar_facturas_y_productosporfactura(
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
BEGIN
    SELECT json_agg(factura_completa ORDER BY numero) INTO p_resultado
    FROM (
        SELECT f.numero, json_build_object(
            'numero', f.numero,
            'fecha', f.fecha,
            'total', f.total,
            'estado', f.estado,
            'fkidcliente', f.fkidcliente,
            'nombre_cliente', pc.nombre,
            'fkidvendedor', f.fkidvendedor,
            'nombre_vendedor', pv.nombre,
            'productos', (
                SELECT json_agg(json_build_object(
                    'codigo_producto', pr.codigo,
                    'nombre_producto', pr.nombre,
                    'cantidad', pf.cantidad,
                    'valorunitario', pr.valorunitario,
                    'subtotal', pf.subtotal
                ))
                FROM productosporfactura pf
                JOIN producto pr ON pr.codigo = pf.fkcodproducto
                WHERE pf.fknumfactura = f.numero
            )
        ) AS factura_completa
        FROM factura f
        JOIN cliente c ON c.id = f.fkidcliente
        JOIN persona pc ON pc.codigo = c.fkcodpersona
        JOIN vendedor v ON v.id = f.fkidvendedor
        JOIN persona pv ON pv.codigo = v.fkcodpersona
    ) sub;
END;
$$;

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
CREATE OR REPLACE PROCEDURE sp_actualizar_factura_y_productosporfactura(
    IN p_numero INTEGER,
    IN p_fkidcliente INTEGER,
    IN p_fkidvendedor INTEGER,
    IN p_productos JSON,
    IN p_minimo_detalle INTEGER DEFAULT 1,
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_item JSON;
    v_codigo VARCHAR;
    v_cantidad INTEGER;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM factura WHERE factura.numero = p_numero) THEN
        RAISE EXCEPTION 'Factura % no existe', p_numero;
    END IF;

    -- Guarda agregada en la v2 (aqui, al portarla a PostgreSQL en la v3).
    -- Sin ella se podia REEMPLAZAR el detalle de una factura ya anulada: la
    -- factura seguia marcada 'anulada' pero el stock se volvia a mover.
    -- Una factura anulada es historia: no se edita.
    IF (SELECT estado FROM factura WHERE factura.numero = p_numero) = 'anulada' THEN
        RAISE EXCEPTION 'Factura % esta anulada: no se puede modificar', p_numero;
    END IF;

    -- Validar minimo de productos
    -- NULLIF(p_minimo_detalle, 0): la API envia 0 cuando no se pasa el parametro, NULLIF lo convierte a NULL
    -- COALESCE(..., 1): si es NULL usa 1 como default
    IF p_productos IS NULL OR json_array_length(p_productos) < COALESCE(NULLIF(p_minimo_detalle, 0), 1) THEN
        RAISE EXCEPTION 'La factura requiere minimo % producto(s).', COALESCE(NULLIF(p_minimo_detalle, 0), 1);
    END IF;

    -- Eliminar detalle anterior (el trigger restaura stock y recalcula total)
    DELETE FROM productosporfactura WHERE fknumfactura = p_numero;

    -- Insertar nuevos productos (el trigger calcula subtotal, descuenta stock, actualiza total)
    FOR v_item IN SELECT * FROM json_array_elements(p_productos)
    LOOP
        v_codigo := v_item->>'codigo';
        v_cantidad := (v_item->>'cantidad')::INTEGER;

        INSERT INTO productosporfactura (fknumfactura, fkcodproducto, cantidad, subtotal)
        VALUES (p_numero, v_codigo, v_cantidad, 0);
    END LOOP;

    -- Actualizar cliente y vendedor de la factura
    UPDATE factura
    SET fkidcliente = p_fkidcliente,
        fkidvendedor = p_fkidvendedor
    WHERE factura.numero = p_numero;

    -- Retornar resultado como JSON
    SELECT json_build_object(
        'factura', (
            SELECT row_to_json(fac) FROM (
                SELECT f.numero, f.fecha, f.total, f.estado, f.fkidcliente, f.fkidvendedor
                FROM factura f WHERE f.numero = p_numero
            ) fac
        ),
        'productos', (
            SELECT json_agg(row_to_json(det)) FROM (
                SELECT pf.fkcodproducto AS codigo_producto, pr.nombre AS nombre_producto,
                       pf.cantidad, pr.valorunitario, pf.subtotal
                FROM productosporfactura pf
                JOIN producto pr ON pr.codigo = pf.fkcodproducto
                WHERE pf.fknumfactura = p_numero
            ) det
        )
    ) INTO p_resultado;
END;
$$;

-- ------------------------------------------------------------
-- 5. SP BORRAR FACTURA Y PRODUCTOSPORFACTURA
-- ON DELETE CASCADE elimina productosporfactura automáticamente.
-- El trigger restaura stock al borrar cada producto de la factura.
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "sp_borrar_factura_y_productosporfactura",
--     "p_numero": 1, "p_resultado": null }
-- ------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_borrar_factura_y_productosporfactura(
    IN p_numero INTEGER,
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_total NUMERIC;
    v_cantidad_productos BIGINT;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM factura WHERE factura.numero = p_numero) THEN
        RAISE EXCEPTION 'Factura % no existe', p_numero;
    END IF;

    -- Guardar info antes de borrar para el JSON de respuesta
    SELECT COUNT(*) INTO v_cantidad_productos
    FROM productosporfactura WHERE fknumfactura = p_numero;

    SELECT f.total INTO v_total FROM factura f WHERE f.numero = p_numero;

    -- Borrar factura (ON DELETE CASCADE borra productosporfactura,
    -- y el trigger restaura stock por cada producto eliminado)
    DELETE FROM factura WHERE factura.numero = p_numero;

    -- Retornar resultado como JSON
    p_resultado := json_build_object(
        'mensaje', 'Factura eliminada exitosamente',
        'numero_eliminado', p_numero,
        'total_eliminado', v_total,
        'productos_eliminados', v_cantidad_productos
    );
END;
$$;

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
CREATE OR REPLACE PROCEDURE sp_anular_factura(
    IN p_numero INTEGER,
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_total NUMERIC;
    v_cantidad_productos BIGINT;
    v_estado VARCHAR(10);
BEGIN
    -- Validar que la factura existe
    IF NOT EXISTS (SELECT 1 FROM factura WHERE factura.numero = p_numero) THEN
        RAISE EXCEPTION 'Factura % no existe', p_numero;
    END IF;

    -- Validar que no esté ya anulada
    SELECT estado INTO v_estado FROM factura WHERE factura.numero = p_numero;
    IF v_estado = 'anulada' THEN
        RAISE EXCEPTION 'Factura % ya está anulada', p_numero;
    END IF;

    -- Restaurar stock de todos los productos de la factura
    UPDATE producto p
    SET stock = p.stock + pf.cantidad
    FROM productosporfactura pf
    WHERE p.codigo = pf.fkcodproducto AND pf.fknumfactura = p_numero;

    -- Guardar info para la respuesta
    SELECT COUNT(*) INTO v_cantidad_productos
    FROM productosporfactura WHERE fknumfactura = p_numero;

    SELECT f.total INTO v_total FROM factura f WHERE f.numero = p_numero;

    -- Cambiar estado a 'anulada'
    UPDATE factura SET estado = 'anulada' WHERE factura.numero = p_numero;

    -- Retornar resultado como JSON
    p_resultado := json_build_object(
        'mensaje', 'Factura anulada exitosamente',
        'numero_anulado', p_numero,
        'total_anulado', v_total,
        'productos_afectados', v_cantidad_productos,
        'estado', 'anulada'
    );
END;
$$;

-- ============================================================
-- PROCEDIMIENTOS ALMACENADOS - USUARIOS CON ROLES
-- Nota: El cifrado lo hace la API C# con el parámetro camposEncriptar
-- ============================================================

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
CREATE OR REPLACE PROCEDURE crear_usuario_con_roles(
    IN p_email VARCHAR(100),
    IN p_contrasena VARCHAR(200),
    IN p_roles_json JSON,
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_item JSON;
    v_idrol INT;
BEGIN
    -- Insertar el usuario
    INSERT INTO usuario (email, contrasena)
    VALUES (p_email, p_contrasena);

    -- Insertar los roles del usuario
    FOR v_item IN SELECT * FROM json_array_elements(p_roles_json)
    LOOP
        v_idrol := (v_item->>'fkidrol')::INTEGER;
        INSERT INTO rol_usuario (fkemail, fkidrol) VALUES (p_email, v_idrol);
    END LOOP;

    -- Retornar resultado
    SELECT json_build_object(
        'email', p_email,
        'roles', (
            SELECT json_agg(json_build_object('idrol', r.id, 'nombre', r.nombre))
            FROM rol_usuario ru
            JOIN rol r ON r.id = ru.fkidrol
            WHERE ru.fkemail = p_email
        )
    ) INTO p_resultado;
END;
$$;

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
CREATE OR REPLACE PROCEDURE actualizar_usuario_con_roles(
    IN p_email VARCHAR(100),
    IN p_contrasena VARCHAR(200),
    IN p_roles JSON,
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_item JSON;
    v_idrol INT;
BEGIN
    -- Actualizar la contraseña solo si no está vacía
    IF p_contrasena IS NOT NULL AND p_contrasena != '' THEN
        UPDATE usuario SET contrasena = p_contrasena WHERE email = p_email;
    END IF;

    -- Eliminar los roles anteriores
    DELETE FROM rol_usuario WHERE fkemail = p_email;

    -- Insertar los nuevos roles
    FOR v_item IN SELECT * FROM json_array_elements(p_roles)
    LOOP
        v_idrol := (v_item->>'fkidrol')::INTEGER;
        INSERT INTO rol_usuario (fkemail, fkidrol) VALUES (p_email, v_idrol);
    END LOOP;

    -- Retornar resultado
    SELECT json_build_object(
        'email', p_email,
        'roles', (
            SELECT json_agg(json_build_object('idrol', r.id, 'nombre', r.nombre))
            FROM rol_usuario ru
            JOIN rol r ON r.id = ru.fkidrol
            WHERE ru.fkemail = p_email
        )
    ) INTO p_resultado;
END;
$$;

-- ------------------------------------------------------------
-- 8. SP ELIMINAR USUARIO CON ROLES
-- Elimina el usuario (ON DELETE CASCADE borra sus roles)
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "eliminar_usuario_con_roles",
--     "p_email": "user@correo.com", "p_resultado": null }
-- ------------------------------------------------------------
CREATE OR REPLACE PROCEDURE eliminar_usuario_con_roles(
    IN p_email VARCHAR(100),
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM usuario WHERE email = p_email) THEN
        RAISE EXCEPTION 'Usuario % no existe', p_email;
    END IF;

    -- Eliminar roles del usuario primero (FK sin CASCADE)
    DELETE FROM rol_usuario WHERE fkemail = p_email;
    DELETE FROM usuario WHERE email = p_email;

    p_resultado := json_build_object(
        'mensaje', 'Usuario eliminado exitosamente',
        'email_eliminado', p_email
    );
END;
$$;

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
CREATE OR REPLACE PROCEDURE actualizar_roles_usuario(
    IN p_email VARCHAR(100),
    IN p_roles_json JSON,
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_item JSON;
    v_idrol INT;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM usuario WHERE email = p_email) THEN
        RAISE EXCEPTION 'Usuario % no existe', p_email;
    END IF;

    -- Eliminar los roles anteriores
    DELETE FROM rol_usuario WHERE fkemail = p_email;

    -- Insertar los nuevos roles
    FOR v_item IN SELECT * FROM json_array_elements(p_roles_json)
    LOOP
        v_idrol := (v_item->>'fkidrol')::INTEGER;
        INSERT INTO rol_usuario (fkemail, fkidrol) VALUES (p_email, v_idrol);
    END LOOP;

    -- Retornar resultado
    SELECT json_build_object(
        'email', p_email,
        'roles', (
            SELECT json_agg(json_build_object('idrol', r.id, 'nombre', r.nombre))
            FROM rol_usuario ru
            JOIN rol r ON r.id = ru.fkidrol
            WHERE ru.fkemail = p_email
        )
    ) INTO p_resultado;
END;
$$;

-- ------------------------------------------------------------
-- 10. SP CONSULTAR USUARIO CON ROLES
-- Retorna JSON con email y array de roles
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "consultar_usuario_con_roles",
--     "p_email": "admin@correo.com", "p_resultado": null }
-- ------------------------------------------------------------
CREATE OR REPLACE PROCEDURE consultar_usuario_con_roles(
    IN p_email VARCHAR(100),
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM usuario WHERE email = p_email) THEN
        RAISE EXCEPTION 'Usuario % no existe', p_email;
    END IF;

    SELECT json_build_object(
        'email', u.email,
        'roles', COALESCE((
            SELECT json_agg(json_build_object('idrol', r.id, 'nombre', r.nombre))
            FROM rol_usuario ru
            JOIN rol r ON r.id = ru.fkidrol
            WHERE ru.fkemail = u.email
        ), '[]'::json)
    ) INTO p_resultado
    FROM usuario u
    WHERE u.email = p_email;
END;
$$;

-- ------------------------------------------------------------
-- 11. SP LISTAR USUARIOS CON ROLES
-- Retorna JSON array con todos los usuarios y sus roles
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "listar_usuarios_con_roles", "p_resultado": null }
-- ------------------------------------------------------------
CREATE OR REPLACE PROCEDURE listar_usuarios_con_roles(
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
BEGIN
    SELECT COALESCE(json_agg(sub), '[]'::json) INTO p_resultado
    FROM (
        SELECT json_build_object(
            'email', u.email,
            'roles', COALESCE((
                SELECT json_agg(json_build_object('idrol', r.id, 'nombre', r.nombre))
                FROM rol_usuario ru
                JOIN rol r ON r.id = ru.fkidrol
                WHERE ru.fkemail = u.email
            ), '[]'::json)
        ) AS sub
        FROM usuario u
        ORDER BY u.email
    ) t(sub);
END;
$$;

-- ============================================================
-- PROCEDIMIENTOS ALMACENADOS - PERMISOS (RBAC)
-- ============================================================

-- ------------------------------------------------------------
-- 12. SP VERIFICAR ACCESO A RUTA
-- Verifica si un usuario tiene permiso para acceder a una ruta
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "verificar_acceso_ruta",
--     "p_email": "admin@correo.com", "p_fkidruta": 2,
--     "p_resultado": null }
-- ------------------------------------------------------------
CREATE OR REPLACE PROCEDURE verificar_acceso_ruta(
    IN p_email VARCHAR(100),
    IN p_fkidruta INT,
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_tiene_acceso BOOLEAN;
BEGIN
    SELECT EXISTS(
        SELECT 1
        FROM usuario u
        INNER JOIN rol_usuario ur ON u.email = ur.fkemail
        INNER JOIN rutarol rr ON ur.fkidrol = rr.fkidrol
        WHERE u.email = p_email AND rr.fkidruta = p_fkidruta
    ) INTO v_tiene_acceso;

    p_resultado := json_build_object(
        'tiene_acceso', v_tiene_acceso,
        'email', p_email,
        'fkidruta', p_fkidruta
    );
END;
$$;

-- ------------------------------------------------------------
-- 13. SP LISTAR RUTAROL
-- Lista todos los permisos ruta-rol con nombres
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "listar_rutarol", "p_resultado": null }
-- ------------------------------------------------------------
CREATE OR REPLACE PROCEDURE listar_rutarol(
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
BEGIN
    SELECT COALESCE(json_agg(
        json_build_object(
            'fkidruta', rr.fkidruta,
            'ruta', rt.ruta,
            'fkidrol', rr.fkidrol,
            'rol', r.nombre
        )
        ORDER BY rt.ruta, r.nombre
    ), '[]'::json) INTO p_resultado
    FROM rutarol rr
    JOIN ruta rt ON rt.id = rr.fkidruta
    JOIN rol r ON r.id = rr.fkidrol;
END;
$$;

-- ------------------------------------------------------------
-- 14. SP CREAR RUTAROL
-- Asigna un rol a una ruta por IDs
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "crear_rutarol",
--     "p_fkidruta": 8, "p_fkidrol": 3,
--     "p_resultado": null }
-- ------------------------------------------------------------
CREATE OR REPLACE PROCEDURE crear_rutarol(
    IN p_fkidruta INT,
    IN p_fkidrol INT,
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
BEGIN
    -- Verificar si la ruta existe
    IF NOT EXISTS (SELECT 1 FROM ruta WHERE id = p_fkidruta) THEN
        p_resultado := json_build_object('success', false, 'message', 'La ruta especificada no existe');
        RETURN;
    END IF;

    -- Verificar si el rol existe
    IF NOT EXISTS (SELECT 1 FROM rol WHERE id = p_fkidrol) THEN
        p_resultado := json_build_object('success', false, 'message', 'El rol especificado no existe');
        RETURN;
    END IF;

    -- Verificar si el permiso ya existe
    IF EXISTS (SELECT 1 FROM rutarol WHERE fkidruta = p_fkidruta AND fkidrol = p_fkidrol) THEN
        p_resultado := json_build_object('success', false, 'message', 'El permiso ya existe');
        RETURN;
    END IF;

    INSERT INTO rutarol (fkidruta, fkidrol) VALUES (p_fkidruta, p_fkidrol);
    p_resultado := json_build_object('success', true, 'message', 'Permiso creado exitosamente');
END;
$$;

-- ------------------------------------------------------------
-- 15. SP ELIMINAR RUTAROL
-- Quita un permiso ruta-rol por IDs
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "eliminar_rutarol",
--     "p_fkidruta": 8, "p_fkidrol": 3,
--     "p_resultado": null }
-- ------------------------------------------------------------
CREATE OR REPLACE PROCEDURE eliminar_rutarol(
    IN p_fkidruta INT,
    IN p_fkidrol INT,
    INOUT p_resultado JSON DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
BEGIN
    -- Verificar si el permiso existe
    IF NOT EXISTS (SELECT 1 FROM rutarol WHERE fkidruta = p_fkidruta AND fkidrol = p_fkidrol) THEN
        p_resultado := json_build_object('success', false, 'message', 'El permiso no existe');
        RETURN;
    END IF;

    DELETE FROM rutarol WHERE fkidruta = p_fkidruta AND fkidrol = p_fkidrol;
    p_resultado := json_build_object('success', true, 'message', 'Permiso eliminado exitosamente');
END;
$$;
