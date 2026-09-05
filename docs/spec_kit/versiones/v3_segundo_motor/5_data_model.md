# Modelo de datos — Versión 3: la misma base, en dos dialectos

> **Versión 3** · Las tablas, las llaves foráneas, los triggers y los
> procedimientos son **exactamente los mismos** que en la v2. Lo que cambia es
> que ahora existen **dos veces**: una en MariaDB y otra en PostgreSQL.
>
> Por eso este documento se lee igual que el de la v2 —las seis tablas y
> quién escribe cada columna no se movieron— y le agrega al final la sección
> que sí es nueva: **en qué se parecen y en qué no las dos bases**.

---

## 1. Los DOS scripts de la BD (artefactos de esta versión)

Ahora son dos, y por eso la carpeta `db/` ganó subcarpetas:

| Archivo | Motor | Líneas |
|---|---|---|
| `db/mariadb/init.sql` | MariaDB 11 | ~1.300 |
| `db/postgres/init.sql` | PostgreSQL 16 | ~1.060 |

**Los dos vienen provistos y se copian tal cual.** Crean las mismas 12 tablas,
con los mismos datos de ejemplo, los mismos triggers y los mismos seis
procedimientos de facturación — cada uno en su dialecto.

**Mover `db/init.sql` a `db/mariadb/init.sql` no es cosmética.** Mientras hubo
un motor, «la base» no necesitaba apellido. Con dos, una carpeta `db/` con un
solo `init.sql` adentro dejaría la duda de a cuál pertenece.

Al script de PostgreSQL hubo que hacerle **una** de las dos correcciones que la
v2 le hizo al de MariaDB: la guarda que impide modificar una factura ya
anulada, que tampoco tenía. La otra —el `estado` faltante en el listado— no
hizo falta: el de PostgreSQL ya lo devolvía.

> **Que uno de los dos scripts tuviera el defecto y el otro no es, en sí
> mismo, la lección.** Fueron escritos por separado a partir del mismo diseño,
> y por separado divergieron. Cuando la misma regla vive en dos sitios, tarde
> o temprano dice dos cosas distintas — y aquí lo dijo.

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
docker compose up -d mariadb postgres     # desde la raíz del proyecto
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
| v2 | + empresa, persona, cliente, vendedor, factura, productosporfactura |
| **v3** | **las mismas seis, contra DOS motores** |
| v4 | las mismas, contra tres |

Las seis restantes (`rol`, `ruta`, `usuario`, `rol_usuario`, `rutarol` y lo
que cuelga de ellas) siguen esperando: son control de acceso, y eso no es
alcance de esta ruta de versiones.


## 8. En qué se parecen y en qué NO las dos bases

### 8.1 Lo que es idéntico

- Las 12 tablas, con los mismos nombres de tabla y de columna.
- Las llaves primarias, las foráneas y sus reglas de borrado.
- Los datos de ejemplo: 3 empresas, 6 personas, 8 productos, 4 clientes,
  3 vendedores y 6 facturas, con los mismos códigos.
- Los seis procedimientos de facturación, con los mismos nombres y los mismos
  parámetros de entrada.
- Los triggers: calculan subtotales y total, y mueven el stock.
- **Y el SQL de las consultas corrientes.** Los `SELECT`, `INSERT`, `UPDATE` y
  `DELETE` de los repositorios se copiaron tal cual entre los dos motores.

### 8.2 Lo que cambia, y obligó a escribir código distinto

| | MariaDB | PostgreSQL |
|---|---|---|
| Nombre de la base | `bdfacturas_mariadb_local` | `bdfacturas_postgres_local` |
| Llave autogenerada | `AUTO_INCREMENT` + `lastInsertId()` | `SERIAL` + `INSERT … RETURNING` |
| Parámetro de salida de un procedimiento | `OUT` + variable de sesión `@resultado` | `INOUT`, y llega como fila del `CALL` |
| Cómo se niega un procedimiento | `SIGNAL SQLSTATE '45000'` | `RAISE EXCEPTION` (`P0001`) |
| Código de llave duplicada | `1062` (en `errorInfo[1]`) | `23505` (en `getCode()`) |
| Códigos de llave foránea | `1452` al insertar, `1451` al borrar | **`23503` para los dos** |
| Un `INT` leído | llega como texto | llega como entero |
| La fecha de una factura | con segundos | **con microsegundos** |

La lista completa, con lo que cada diferencia obliga a hacer, está en
[3_plan.md](3_plan.md) §4.3.

> **Dos motores que hacen lo mismo no exponen la misma información de la
> misma manera.** Por eso el detalle del motor tiene que quedar encerrado en
> una clase por motor: no porque quede bonito, sino porque no hay forma de
> escribir un solo código que sirva para los dos sin mentir en alguno.

### 8.3 Lo que NO se comparte: los datos

Las dos bases son **independientes**. Arrancan con los mismos datos de
ejemplo, y a partir de ahí cada una lleva su vida: un cliente creado con
`MOTOR=mariadb` no aparece con `MOTOR=postgres`.

No es un defecto y está declarado en el alcance. Esta versión demuestra que
**el programa** funciona contra las dos, no que las dos sean la misma.
