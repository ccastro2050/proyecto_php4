# Plan técnico — Versión 4: el tercer motor

> **Versión 4** · CÓMO construir lo especificado en [2_spec.md](2_spec.md).
> El porqué de cada decisión: [4_research.md](4_research.md) · orden:
> [8_tasks.md](8_tasks.md).

---

## 1. Stack

| Pieza | v3 | v4 |
|---|---|---|
| Motores | MariaDB 11 + PostgreSQL 16 | **+ SQL Server 2022** |
| Drivers de PDO | `pdo_mysql`, `pdo_pgsql` | **+ `pdo_sqlsrv`** |
| Imagen de la API | una, con dos drivers | **una, con tres** |
| Servicios del compose | 4 | **6** |

**El driver del tercer motor no viene con PHP, y ésa es la primera
diferencia.** `pdo_mysql` y `pdo_pgsql` son extensiones del propio lenguaje:
se compilan con un comando. El de SQL Server lo publica Microsoft aparte, hay
que traerlo de su repositorio, y necesita un controlador ODBC del sistema
debajo. Son diez líneas de `Dockerfile` en vez de una.

Se escogió el driver oficial y no `pdo_dblib` —que se instala con un
comando— porque `pdo_dblib` tiene limitaciones conocidas con parámetros y
tipos, y el oficial es el que se usaría de verdad. El precio se paga una sola
vez, en el build.

## 2. Estructura de carpetas

Solo lo que cambió respecto de la v3.

```
├── db/
│   ├── mariadb/init.sql
│   ├── postgres/init.sql
│   └── sqlserver/
│       ├── bdfacturas.sql        ← la misma base, en T-SQL
│       └── init.sh               ← el que la crea (ver §5)
│
├── api_facturas/
│   ├── Dockerfile                ← + el driver de Microsoft
│   ├── repositorios/
│   │   ├── Repositorio*MariaDB.php     (6)
│   │   ├── Repositorio*Postgres.php    (6)
│   │   ├── Repositorio*SqlServer.php   ← LOS SEIS NUEVOS
│   │   ├── conflictos.php              ← sin cambios: las frases son las mismas
│   │   ├── errores_de_integridad_mariadb.php
│   │   ├── errores_de_integridad_postgres.php
│   │   └── errores_de_integridad_sqlserver.php   ← NUEVO
│   └── servicios/
│       └── ensamblador.php       ← una rama más
│
└── pruebas_humo/
    └── humo_los_tres_motores.py  ← el de la v3, con un motor más en la lista
```

**Dieciocho repositorios** (seis recursos × tres motores) y **tres**
traductores de errores. La duplicación es aparente: §4.2 muestra que los tres
hacen lo mismo y ninguno se parece.

## 3. Arquitectura: la misma, con una rama más

```
     → IRepositorio{Recurso}      ← NADA por encima de aquí se enteró
                    ↓
            ensamblador.php       ← la única rama que se agregó
         ┌──────────┼──────────┐
         ▼          ▼          ▼
    *MariaDB   *Postgres   *SqlServer
         ▼          ▼          ▼
     MariaDB   PostgreSQL   SQL Server
```

Que este dibujo sea el de la v3 con una rama más **es el resultado de la
versión**. No hubo que reorganizar nada.

## 4. Decisiones de diseño clave

### 4.1 `ensamblador.php`: de ternario a `match`

Con dos motores, un ternario alcanzaba. Con tres, un `match` dice mejor lo que
pasa — es una tabla de correspondencia, no una cadena de preguntas:

```php
$repositorio = match (motorActivo()) {
    'postgres'  => new RepositorioClientePostgres($dsn, $usuario, $clave),
    'sqlserver' => new RepositorioClienteSqlServer($dsn, $usuario, $clave),
    default     => new RepositorioClienteMariaDB($dsn, $usuario, $clave),
};
```

**Dieciocho `new` escritos a mano.** La tentación de armar el nombre de la
clase con texto nunca fue tan grande: dieciocho líneas contra cuatro. Y sigue
estando mal, solo que ahora los números explican mejor por qué: son
**dieciocho combinaciones**, nadie va a probarlas todas a mano, y con las
clases escritas PHP verifica cada una al llamar su función. Con nombres
armados en texto, la que nadie probó falla en producción — y con tres motores
la probabilidad de que exista una así se triplicó.

### 4.2 Los tres motores, lado a lado

Ésta es la tabla que cierra la ruta de versiones. La v3 tenía dos columnas; la
tercera es la que despeja las dudas.

| | MariaDB | PostgreSQL | SQL Server |
|---|---|---|---|
| Prefijo del DSN | `mysql:` | `pgsql:` | `sqlsrv:` |
| Usuario | `paradigmas` | `paradigmas` | **`sa`**, con clave de política |
| `ATTR_EMULATE_PREPARES` | se pone en `false` | se pone en `false` | **no lo admite**: falla si se pone |
| `rowCount()` de un UPDATE | necesita `MYSQL_ATTR_FOUND_ROWS` | funciona solo | funciona solo |
| Limitar filas | `LIMIT :limite` | `LIMIT :limite` | **`OFFSET 0 ROWS FETCH NEXT :limite ROWS ONLY`**, y exige `ORDER BY` |
| Llave autogenerada | `lastInsertId()`, en otra consulta | `INSERT … RETURNING id` | **`OUTPUT INSERTED.id`**, en medio del INSERT |
| Llave duplicada | `1062` en `errorInfo[1]` | `23505` en `getCode()` | `2627`/`2601` en `errorInfo[1]` |
| Referencia rota | `1452` | `23503` | `547` |
| Borrar con dependientes | `1451` | `23503` — **el mismo** | `547` — **el mismo** |
| Cómo se distinguen esos dos | por el número | por el texto | por el texto |
| Salida de un procedimiento | `OUT` + `@resultado` + otra consulta | `INOUT`, fila del `CALL` | **variable declarada en el SQL + `SELECT`** |
| Rechazo de un procedimiento | `SIGNAL` → `45000` | `RAISE` → `P0001` | `THROW` → `42000` + número ≥ 50000 |
| Ruido en ese mensaje | número del motor **delante** | bloque `CONTEXT:` **detrás** | firma del driver ODBC **delante** |
| `INT` al leerlo | texto | **entero** | texto |
| Decimales de la fecha | ninguno | seis | **siete** |

**Dos conclusiones que solo aparecen con la tercera columna:**

1. **MariaDB es la excepción, no la regla**, en lo de separar los dos sentidos
   de una llave foránea con códigos distintos. Con dos motores parecía que
   PostgreSQL era el raro; con tres se ve que es al revés.
2. **Y el SQL sí cambia.** Entre los dos primeros motores las consultas se
   copiaron tal cual, lo que dejaba la duda de si las clases separadas eran
   ceremonia. Con SQL Server no hay `LIMIT` y la llave generada se lee de otra
   forma. La duda queda resuelta.

### 4.3 El parámetro de salida que no se deja enlazar

El camino «normal» en SQL Server sería enlazar el parámetro de salida:

```php
$sentencia->bindParam(2, $salida, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 8000);
```

**No funciona** con estos procedimientos: el driver responde
`SQLSTATE[HY104]: Invalid precision value`, porque el parámetro está declarado
`NVARCHAR(MAX)` y a un MAX no se le puede dar una longitud fija.

La salida es escribir el bloque de T-SQL completo y pedir el valor con un
`SELECT`:

```sql
SET NOCOUNT ON;
DECLARE @salida NVARCHAR(MAX);
EXEC sp_lo_que_sea ?, ?, @salida OUTPUT;
SELECT @salida AS p_resultado;
```

Y **`SET NOCOUNT ON` no es adorno**: sin él, cada `INSERT` de adentro del
procedimiento manda un aviso de «N filas afectadas» que PDO entrega como
resultado intermedio, y el `fetch()` devuelve cualquier cosa menos el JSON.

> **Las dos cosas se descubrieron probando, no leyendo documentación**, y por
> eso están escritas en el código además de aquí. El que llegue con el mismo
> error va a saber en dos minutos lo que a nosotros nos costó media hora.
>
> Lo que **no** se hizo: cambiar los seis procedimientos para que el
> parámetro fuera `NVARCHAR(8000)`. Habría sido modificar la base para
> acomodar una limitación de un driver.

### 4.4 Lo que NO hubo que hacer

Vale la pena la lista, porque es corta y es el resultado:

- No se tocó ninguna interfaz.
- No se tocó ningún controlador, ningún servicio ni ningún modelo.
- No se tocó `conflictos.php`: las frases del usuario son las mismas.
- No se tocó la prueba de capas.
- No se reorganizó la fábrica: se le agregó una rama.

## 5. Docker: seis servicios y un contenedor de un solo uso

```yaml
services:
  mariadb:             # 13329 · db/mariadb/init.sql
  postgres:            # 15465 · db/postgres/init.sql
  sqlserver:           # 11474 · ACCEPT_EULA, clave de política, healthcheck con sqlcmd
  sqlserver-init:      # crea la base y termina. restart: "no"
  api-facturas:        # 8090 · MOTOR + los TRES DSN
  front-php:           # 8088 · sin credenciales de base, como siempre
  phpmyadmin:          # 8104 (solo mira MariaDB)
volumes:
  mariadbdata:  pgdata:  mssqldata:
```

**`sqlserver-init` no tiene pareja en los otros dos motores**, y ésa es la
primera diferencia de la versión — aparece antes de escribir una línea de PHP.
Las imágenes de MariaDB y PostgreSQL ejecutan solas cualquier `.sql` que
encuentren en `/docker-entrypoint-initdb.d/`. La de SQL Server **no tiene ese
mecanismo**, así que hace falta un contenedor que espere a que el servidor
responda, cree la base y corra el script.

`restart: "no"` porque termina y se queda quieto: no es un servicio, es un
encargo. Y el script **comprueba si la base ya existe** antes de crearla — un
inicializador que solo funcione la primera vez es una trampa esperando a que
alguien vuelva a levantar el sistema.

**Dos concesiones a SQL Server, declaradas:**

- Su clave (`Paradigmas123!`) no sigue la convención del proyecto, porque el
  motor exige mayúscula, número y símbolo. Y su usuario es `sa`, no
  `paradigmas`.
- `TrustServerCertificate=yes` acepta el certificado que el servidor se genera
  solo. **En producción no se pone**: desactiva la comprobación de con quién
  se está hablando. En un curso está bien, y decirlo es parte de enseñarlo.

## 6. Convenciones

Las de siempre, con el apellido de motor ya establecido en la v3. La tercera
familia se llama `*SqlServer` — en PascalCase, como todas las clases, aunque
el producto se escriba «SQL Server».

## 7. Chequeo de constitución

| Artículo | Cómo lo cumple esta versión |
|---|---|
| **1** — Propósito didáctico | Los tres repositorios de una misma entidad, abiertos lado a lado, enseñan más que cualquier explicación. |
| **1.1** — Una versión incluye su front | Cumple; el front cambió en una línea (el nombre del tercer motor en el pie) porque no había nada más que agregarle. |
| **2** — PHP puro | Sin framework ni Composer. El driver de SQL Server es una extensión de PDO, no una librería del proyecto. |
| **3** — Tres capas estrictas | Cumple, y el criterio 13 lo comprueba con un `diff` más limpio que el de la v3. |
| **4** — Un solo comando | `docker compose up -d --build` levanta los seis servicios, incluida la creación de la base de SQL Server. |
| **5** — Independencia del motor | **Completado.** Tres motores, escogidos por configuración, con el mismo código. |
| **6** — Persistencia | Tres volúmenes; `down -v` devuelve las tres bases a su estado original. |
| **7** — Recarga natural | PHP reinterpreta cada petición. |
| **8** — Convenciones fijas | Cumple, **con una excepción declarada**: el usuario y la clave de SQL Server (§5). |
| **9** — Seguridad académica | Credenciales didácticas, y el `TrustServerCertificate` con su advertencia de producción escrita al lado. |
| **10** — API específica | Las rutas no cambiaron. Y la fábrica siguió la regla: dieciocho clases escritas, ningún nombre armado con texto. |
| **11** — La base también es parte del sistema | Se cumple **tres veces**: los mismos triggers y procedimientos en los tres motores, y la API sin recalcular nada en ninguno. |

**Complejidad justificada:** dos desviaciones conscientes, las dos declaradas
y aisladas — depender del texto de un mensaje para distinguir casos (ahora en
dos de los tres motores), y las credenciales distintas de SQL Server.
