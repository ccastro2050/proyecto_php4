# Modelo de datos — Versión 2: seis tablas, sus relaciones y la lógica que vive adentro

> **Versión 2** · La base sigue siendo la misma que en la v1: se crea COMPLETA
> desde el inicio con `db/init.sql`. Lo que cambia es **cuánto de ella usa el
> código**: de una tabla a seis, y por primera vez usando sus llaves foráneas,
> sus triggers y sus procedimientos.
>
> Este documento es el que hay que tener al lado mientras se construye:
> explica no solo qué columnas hay, sino **quién defiende qué**.

---

## 1. El script de la BD (artefacto de esta versión)

`db/init.sql` viene **provisto**: 12 tablas, restricciones, datos de ejemplo,
triggers y procedimientos en dialecto MariaDB. **Se copia tal cual.**

La v2 le hizo **dos correcciones**, y están explicadas con su motivo en
[4_research.md](4_research.md) D6: el procedimiento de listar no devolvía el
`estado` de la factura, y el de actualizar dejaba modificar una factura ya
anulada. Fuera de eso, el script es el mismo de la v1.

## 2. El mapa de las seis tablas que usa la v2

```
   empresa          persona                producto
      │                │  │                    │
      │                │  └──────────┐         │
      └────────┐       │             │         │
               ▼       ▼             ▼         │
             cliente             vendedor      │
                │                    │         │
                └────────┐  ┌────────┘         │
                         ▼  ▼                  │
                       factura ◄───────────────┤
                          │                    │
                          ▼                    │
                 productosporfactura ──────────┘
```

Léalo de arriba abajo: **arriba están las tablas de las que otras dependen**,
abajo las que dependen de alguien. Esa forma decide dos cosas prácticas:

- **Para crear**, se va de arriba abajo: primero la persona, después el
  cliente, después la factura. Al revés, la base rechaza.
- **Para borrar**, al revés: primero la factura, después el cliente, y solo
  entonces la persona. Al revés, la base rechaza.

Los dos rechazos son los que la API traduce a **409**.

## 3. Las tablas, una por una

### 3.1 `empresa` — no depende de nadie

| Columna | Tipo | Restricción | Descripción |
|---|---|---|---|
| `codigo` | VARCHAR(10) | **PK** | Lo escribe quien crea la ficha (E001…) |
| `nombre` | VARCHAR(100) | NOT NULL | El nombre de la compañía |

Datos de ejemplo: **3 empresas** (E001 Comercial Los Andes S.A. …).

### 3.2 `persona` — no depende de nadie, y de ella dependen dos

| Columna | Tipo | Restricción | Descripción |
|---|---|---|---|
| `codigo` | VARCHAR(10) | **PK** | Lo escribe quien crea la ficha (P001…) |
| `nombre` | VARCHAR(100) | NOT NULL | |
| `email` | VARCHAR(100) | NOT NULL | |
| `telefono` | VARCHAR(20) | NOT NULL | |

Datos de ejemplo: **6 personas** (P001 Ana Torres …).

**Es la tabla donde mejor se ve el 409 de borrado:** P001 ya es cliente, así
que eliminarla falla. Y está bien que falle — si se borrara, quedaría un
cliente apuntando a alguien que no existe.

### 3.3 `producto` — la de la v1, sin cambios

| Columna | Tipo | Restricción | Descripción |
|---|---|---|---|
| `codigo` | VARCHAR(10) | **PK** | PR001… |
| `nombre` | VARCHAR(100) | NOT NULL | |
| `stock` | INTEGER | NOT NULL | **Lo mueve un trigger al facturar** |
| `valorunitario` | NUMERIC(18,2) | NOT NULL | |

Datos de ejemplo: **8 productos**.

> **`stock` es la primera columna del proyecto que el código NO escribe.**
> La API la lee y la muestra, pero quien la cambia al facturar es un trigger.
> Un `PATCH` de producto sí puede corregir el stock a mano —eso es un ajuste
> de inventario, y es legítimo—, pero la API nunca lo descuenta por vender.

### 3.4 `cliente` — la llave la pone la base

| Columna | Tipo | Restricción | Descripción |
|---|---|---|---|
| `id` | INT | **PK, AUTO_INCREMENT** | **Lo genera la base** |
| `credito` | NUMERIC(18,2) | NOT NULL, DEFAULT 0 | Cupo de crédito |
| `fkcodpersona` | VARCHAR(10) | **FK → persona**, NOT NULL | Quién es |
| `fkcodempresa` | VARCHAR(10) | **FK → empresa**, NULL | Opcional |

Datos de ejemplo: **4 clientes**.

**`fkcodempresa` acepta NULL, y eso es una regla de negocio escrita en la
base**: un cliente puede comprar a título propio. Por eso el controlador lo
trata distinto — que llegue vacío no es un error, y el front lo manda como
`null`, no como `""`.

### 3.5 `vendedor` — igual, con una sola llave foránea

| Columna | Tipo | Restricción | Descripción |
|---|---|---|---|
| `id` | INT | **PK, AUTO_INCREMENT** | **Lo genera la base** |
| `carnet` | INT | NOT NULL | |
| `direccion` | VARCHAR(100) | NOT NULL | |
| `fkcodpersona` | VARCHAR(10) | **FK → persona**, NOT NULL | |

Datos de ejemplo: **3 vendedores**.

### 3.6 `factura` + `productosporfactura` — maestro y detalle

**El maestro:**

| Columna | Tipo | Restricción | Quién lo escribe |
|---|---|---|---|
| `numero` | INT | **PK, AUTO_INCREMENT** | **la base** |
| `fecha` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | **la base** |
| `total` | NUMERIC(18,2) | DEFAULT 0 | **un TRIGGER** |
| `estado` | VARCHAR(10) | DEFAULT 'activa' | **un procedimiento** (al anular) |
| `fkidcliente` | INT | **FK → cliente**, NOT NULL | la API |
| `fkidvendedor` | INT | **FK → vendedor**, NOT NULL | la API |

**El detalle:**

| Columna | Tipo | Restricción | Quién lo escribe |
|---|---|---|---|
| `fknumfactura` | INT | **PK (parte), FK → factura ON DELETE CASCADE** | la API |
| `fkcodproducto` | VARCHAR(10) | **PK (parte), FK → producto** | la API |
| `cantidad` | INT | NOT NULL | la API |
| `subtotal` | NUMERIC(18,2) | DEFAULT 0 | **un TRIGGER** |

Datos de ejemplo: **6 facturas** con sus renglones.

**Lea otra vez la columna «quién lo escribe».** De las seis columnas de la
factura, la API escribe **dos**. Ésa es la lección de esta versión, y por eso
el modelo `Factura` no tiene setters: lo que el código no escribe, el objeto
no debería dejar cambiar.

Y fíjese en la llave primaria del detalle: es **compuesta**
(`fknumfactura` + `fkcodproducto`). O sea que un producto no puede aparecer
dos veces en la misma factura — si se necesitan diez unidades, van en la
`cantidad`, no en dos renglones. Esa regla no está en ninguna línea de PHP.

## 4. La lógica que vive en la base

### 4.1 Los triggers de `productosporfactura`

Seis triggers (antes y después de insertar, actualizar y borrar) que hacen
**tres cosas**:

1. calculan el `subtotal` del renglón (cantidad × valor unitario del producto
   en ese momento);
2. recalculan el `total` de la factura;
3. mueven el `stock` del producto: lo descuentan al insertar, lo devuelven al
   borrar, y ajustan la diferencia al actualizar.

Y además **se niegan** cuando no hay stock suficiente. Ese «no» llega a la API
como un rechazo del procedimiento y sale como 409.

> **Por qué la API no hace ninguna de las tres.** Si PHP calculara el total y
> el trigger también, habría dos cuentas del mismo número; el día que
> discrepen —y discrepan— nadie sabe cuál creer. Y si PHP descontara el
> stock, dos facturas simultáneas podrían vender la misma unidad, porque
> entre leer el stock y escribirlo hay una ventana. Adentro de la base no la
> hay.

### 4.2 Los seis procedimientos de facturación

| Procedimiento | Qué hace | Lo llama |
|---|---|---|
| `sp_insertar_factura_y_productosporfactura` | Crea el encabezado y todos los renglones. Exige mínimo 1 | `crear()` |
| `sp_consultar_factura_y_productosporfactura` | Devuelve la factura con su detalle y los nombres | `obtenerPorNumero()` |
| `sp_listar_facturas_y_productosporfactura` | Todas, cada una con su detalle | `obtenerTodas()` |
| `sp_actualizar_factura_y_productosporfactura` | Reemplaza cliente, vendedor y detalle completo | `reemplazar()` |
| `sp_anular_factura` | Marca `anulada` y devuelve el stock | `anular()` |
| `sp_borrar_factura_y_productosporfactura` | Borra factura y detalle | `eliminar()` |

Los seis devuelven su resultado como **JSON en un parámetro `OUT`**, y los
seis rechazan con `SIGNAL SQLSTATE '45000'` cuando algo no cumple. Cómo se
leen desde PHP está en [3_plan.md](3_plan.md) §4.4.

### 4.3 Las otras seis tablas: existen y la v2 no las toca

`rol`, `ruta`, `usuario`, `rol_usuario`, `rutarol` y sus procedimientos de
control de acceso están en la base desde la v1. **El código de la v2 no puede
nombrarlas.** Que estén a la vista no es invitación a usarlas.

## 5. Los datos de ejemplo de los que dependen las pruebas

El smoke test de [7_quickstart.md](7_quickstart.md) cuenta con esto, así que
si alguien cambia los datos hay que actualizarlo:

| Tabla | Cuántos | Un ejemplo que se usa en las pruebas |
|---|---|---|
| `empresa` | 3 | `E001` — crear otra E001 debe dar 409 |
| `persona` | 6 | `P001` Ana Torres — **ya es cliente**: borrarla debe dar 409 |
| `producto` | 8 | `PR001` Laptop, stock 17, 2.500.000 |
| `cliente` | 4 | `id = 1`, persona P001 |
| `vendedor` | 3 | `id = 1`, carné 1001 |
| `factura` | 6 | la número 1, activa, con un renglón de PR001 |

## 6. Montar la BD

```powershell
docker compose up -d mariadb      # desde la raíz del proyecto
```

Y el botón de pánico, que en esta versión se usa más que en la v1 porque las
pruebas mueven stock y crean facturas:

```powershell
docker compose down -v && docker compose up -d --build
```

`down -v` borra el volumen, así que `init.sql` vuelve a ejecutarse y la base
queda como recién nacida. Es la forma de volver al punto de partida cuando
una prueba dejó el inventario en un estado raro.

## 7. Qué toca el código en cada versión (la BD no cambia)

| Versión | Tablas que usa el código (API **y** pantalla) |
|---|---|
| v1 | `producto` — nada más |
| **v2** | **+ empresa, persona, cliente, vendedor, factura, productosporfactura** |
| v3–v4 | las mismas, contra más motores |

Las seis restantes (`rol`, `ruta`, `usuario`, `rol_usuario`, `rutarol` y lo
que cuelga de ellas) siguen esperando: son control de acceso, y eso no es
alcance de esta ruta de versiones.
