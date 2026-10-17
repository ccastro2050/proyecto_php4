# Diseño de base de datos — `bdfacturas`, en tres motores

> **Qué es este archivo.** Las once tablas, por qué están así, y **en qué se
> parecen y en qué no** los tres scripts: MariaDB, PostgreSQL y SQL Server.
>
> El mismo modelo, tres dialectos. Lo que cambia entre ellos se queda abajo —
> en el script y en el repositorio— y las capas de arriba no se enteran.
>
> **Leído de los scripts**, no de la memoria:
> `db/mariadb/init.sql` · `db/postgres/init.sql` · `db/sqlserver/bdfacturas.sql`
>
> Versión 1.0 · 17 de octubre de 2026.

---

## 1. Las once tablas, agrupadas por para qué sirven

| Grupo | Tablas | De qué habla |
|---|---|---|
| **El catálogo** | `producto` · `empresa` · `persona` | Lo que se vende y quiénes existen |
| **Los papeles** | `cliente` · `vendedor` | La misma persona, vista desde dos lados |
| **La venta** | `factura` · `productosporfactura` | El encabezado y sus renglones — **maestro-detalle** |
| **El acceso** | `usuario` · `rol` · `ruta` · `rol_usuario` · `rutarol` | Quién entra y a dónde llega |

**Las cinco del acceso están desde la v1** y ningún código las nombraba hasta
la v4. Una tabla que existe y no se usa es una promesa sin cumplir; la v4 la
cumplió.

---

## 2. Las llaves, y por qué cada una es como es

| Tabla | Llave primaria | Por qué |
|---|---|---|
| `empresa`, `persona`, `producto` | `codigo VARCHAR(10)` | **La llave la pone el negocio**: `PR001`, `P001`, `E001`. Son códigos que la gente dice en voz alta |
| `usuario` | `email VARCHAR(100)` | El correo **es** la identidad. Un `id` al lado sería un segundo nombre para lo mismo |
| `rol`, `ruta`, `cliente`, `vendedor` | `id` autogenerado | No hay un código natural: un rol se llama «Cajero» y mañana se puede llamar distinto |
| `factura` | `numero` autogenerado | La numeración es consecutiva y la pone la base de datos |
| `productosporfactura` | **(`fknumfactura`, `fkcodproducto`)** | Compuesta: el mismo producto no puede estar dos veces en la misma factura |
| `rol_usuario` | **(`fkemail`, `fkidrol`)** | Compuesta: la pareja no se repite |
| `rutarol` | **(`fkidruta`, `fkidrol`)** | Compuesta, por lo mismo |

> **Las tres llaves compuestas son la decisión de diseño que más consecuencias
> tiene.** Un `id` autonumérico en `rol_usuario` dejaría pasar la misma pareja
> dos veces —Juan con el rol 2 en dos filas distintas, las dos válidas—. Con la
> llave compuesta lo impide **el motor**, no el código, y por eso dar un
> permiso repetido responde 409 sin que ninguna línea de PHP lo compruebe.
>
> Y de ahí sale otra: esos dos recursos **no tienen PUT ni PATCH**. La fila
> *es* su llave; no hay nada que editar.

Y una restricción más que conviene ver:

```sql
CONSTRAINT uq_ruta UNIQUE (ruta)
```

El **nombre** de la interfaz es único. Dos filas `/factura` harían que el
control de acceso dependiera de cuál encontrara primero.

---

## 3. El maestro-detalle, que es el corazón de la v2

```
factura (numero, fecha, total, estado, fkidcliente, fkidvendedor)
   │
   └── productosporfactura (fknumfactura, fkcodproducto, cantidad, subtotal)
           ON DELETE CASCADE
```

| Columna | Quién la escribe |
|---|---|
| `numero` | la base de datos (`AUTO_INCREMENT`) |
| `fecha` | la base de datos (`DEFAULT CURRENT_TIMESTAMP`) |
| `total` | **el disparador**, sumando los subtotales |
| `estado` | `DEFAULT 'activa'`; solo `sp_anular_factura` lo cambia |
| `subtotal` | **el disparador**, `cantidad × valorunitario` del momento |
| `cantidad`, `fkcodproducto`, `fkidcliente`, `fkidvendedor` | el cliente de la API |

> **La mitad de las columnas no las escribe quien llama a la API**, y esa es
> la lección de la tabla: el dato que se puede calcular mal se calcula en un
> solo sitio, y ese sitio es el más difícil de evadir.

**`ON DELETE CASCADE` está solo en el detalle**, no en la factura: borrar una
factura se lleva sus renglones, pero borrar un producto **no** borra renglones
—`fk_prodfact_producto` no tiene cascada—, porque eso reescribiría ventas ya
hechas. Dos llaves foráneas en la misma tabla, con dos comportamientos
distintos y por razones distintas.

---

## 4. Los seis disparadores del detalle

| Disparador | Cuándo | Qué hace |
|---|---|---|
| `trg_prodfact_before_insert` | antes de insertar un renglón | calcula el `subtotal` y **rechaza si no hay stock** |
| `trg_prodfact_after_insert` | después | descuenta el stock y recalcula el `total` |
| `trg_prodfact_before_update` | antes de cambiar un renglón | recalcula el subtotal y vuelve a comprobar el stock |
| `trg_prodfact_after_update` | después | ajusta el stock por la diferencia y recalcula el total |
| `trg_prodfact_before_delete` | antes de borrar | guarda lo que hay que devolver |
| `trg_prodfact_after_delete` | después | **devuelve el stock** y recalcula el total |

El rechazo del stock trae el dato exacto:

```
Stock insuficiente para producto PR003. Stock disponible: 39, cantidad solicitada: 9999
```

> **MariaDB no acepta expresiones dentro de `SIGNAL ... SET MESSAGE_TEXT`**,
> así que el mensaje se arma antes en una variable (`SET v_msg = CONCAT(...)`)
> y se lanza después. Es una limitación del dialecto que obliga a escribir dos
> líneas donde SQL Server escribe una con `THROW`. Está en el script, con su
> comentario.

---

## 5. Lo que cambia entre los tres motores

Esta es la tabla que importa cuando algo «funciona en uno y no en el otro»:

| | MariaDB | PostgreSQL | SQL Server |
|---|---|---|---|
| Llave autogenerada | `INT AUTO_INCREMENT` | `SERIAL` | `INT IDENTITY(1,1)` |
| Recuperar el id recién creado | `lastInsertId()` | `RETURNING id` | `OUTPUT INSERTED.id` |
| Limitar filas | `LIMIT n` | `LIMIT n` | `OFFSET 0 ROWS FETCH NEXT n ROWS ONLY` |
| Rechazar desde SQL | `SIGNAL SQLSTATE '45000'` | `RAISE EXCEPTION` | `THROW 50001, ..., 1` |
| Parámetro de salida de un procedimiento | variable de sesión (`@salida` + `SELECT`) | la fila que devuelve el `CALL` | lote con `DECLARE` / `EXEC` / `SELECT` |
| Pegar textos de varias filas | `GROUP_CONCAT(... SEPARATOR ', ')` | `STRING_AGG(DISTINCT x, ', ')` | `STRING_AGG(...)` **sin `DISTINCT`** → subconsulta |
| Llave duplicada | error **1062** | **23505** | **2627** |
| Referencia rota | **1452** | **23503** | **547** |
| Atomicidad de un procedimiento | **hay que escribirla** | una función es atómica sola | `BEGIN TRY` + `ROLLBACK` |

> **La última fila costó un fallo con pérdida de datos**, y está contado en
> `docs/spec_kit/versiones/v4_aplicativo/4_research.md` (tropiezo 5): en
> MariaDB cada sentencia se confirma sola, así que un procedimiento que
> escribe en dos tablas podía quedarse a medias. Hoy los ocho que lo necesitan
> llevan `START TRANSACTION`, `COMMIT` y un `EXIT HANDLER` con `ROLLBACK` y
> `RESIGNAL`.

Y dos diferencias más que aparecieron midiendo, no leyendo:

- **La colación de un parámetro.** MariaDB 11.5 cambió la colación por defecto
  del servidor, y comparar `u.email = p_email` pasó a ser el **error 1267**
  —«Illegal mix of collations»— aunque el script no había cambiado. Los seis
  parámetros de correo llevan su colación declarada para que el script valga
  en 10.x y en 11.x.
- **El booleano que llega como texto.** `verificar_acceso_ruta` devuelve 0 o 1,
  y MariaDB lo entregaba como cadena. En PHP `(bool) "0"` es **verdadero**, así
  que con MariaDB todo el mundo tenía acceso a todo. Los tres repositorios
  convierten explícitamente.

---

## 6. Los datos sembrados

El script deja la base de datos lista para probar sin escribir una fila:

| Tabla | Filas sembradas |
|---|---|
| `producto` | 8 |
| `persona` | 6 · `empresa` 3 |
| `cliente` | 4 · `vendedor` 3 |
| `factura` | 6, con su detalle |
| `usuario` | 8, con contraseña **bcrypt costo 12** |
| `rol` | 5 · `ruta` 15 |
| `rol_usuario` | 21 · `rutarol` 25 |

Y un detalle del sembrado que es una lección en sí: **las contraseñas en claro
están escritas en la documentación**, porque de un hash no se vuelve a la
clave —eso es lo que lo hace un hash— y sin la clave no se comprueba un solo
criterio del control de acceso. El detalle está en
[`DATOS_DE_PRUEBA.md`](DATOS_DE_PRUEBA.md).

---

## 7. Dónde está cada cosa

| Qué | Dónde |
|---|---|
| El esquema de cada motor | `db/mariadb/init.sql` · `db/postgres/init.sql` · `db/sqlserver/bdfacturas.sql` |
| Qué reglas defiende la base de datos | [`REGLAS_DE_NEGOCIO.md`](REGLAS_DE_NEGOCIO.md) |
| Cómo se traduce cada rechazo a HTTP | [`POLITICA_DE_ERRORES.md`](POLITICA_DE_ERRORES.md) |
| Las diferencias de dialecto, con su tropiezo | `docs/spec_kit/versiones/v4_aplicativo/5_data_model.md` |
| Cómo mirar la base de datos sin salir del editor | `docs/TUTORIAL_VSCODE_SQLTOOLS.md` y `docs/TUTORIAL_PHPMYADMIN.md` |
