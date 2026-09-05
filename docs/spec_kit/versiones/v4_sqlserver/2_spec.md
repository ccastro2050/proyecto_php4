# Especificación — Versión 4: el tercer motor y el compose completo

> **Versión 4** del desarrollo incremental ([mapa de versiones](../0_mapa_versiones.md)).
> Rige la constitución del proyecto: [../../1_constitution.md](../../1_constitution.md).
> Parte del estado que dejó la v3: seis recursos contra dos motores.
>
> **Es la última versión de la ruta.**
>
> | Documento de esta versión | Contenido |
> |---|---|
> | **2_spec.md** (este) | QUÉ construir en v4 y sus criterios de aceptación |
> | [3_plan.md](3_plan.md) | CÓMO: el tercer motor, y lo que resultó ser distinto |
> | [4_research.md](4_research.md) | Decisiones y alternativas *(lectura opcional)* |
> | [5_data_model.md](5_data_model.md) | La misma base, en tres dialectos |
> | [6_contracts.md](6_contracts.md) | Los contratos — que siguen sin cambiar |
> | [7_quickstart.md](7_quickstart.md) | Arranque, cambio de motor y smoke test |
> | [8_tasks.md](8_tasks.md) | Orden de construcción por fases verificables |

---

## 1. Propósito de la v4

La v3 demostró que el sistema estaba **abierto a un segundo motor**. Ésta
demuestra algo distinto y menos obvio: que **sigue abierto**.

No es lo mismo. Muchas arquitecturas aguantan la primera extensión —a veces
por casualidad— y se deforman en la segunda: aparece un `if` de más, una
interfaz que hay que retocar, una excepción que no cabía. Si eso pasara aquí,
querría decir que la v3 resolvió su caso en vez de resolver el problema.

Y hay una razón concreta para que **este** motor sea la prueba de fuego:

> Entre MariaDB y PostgreSQL, el SQL de las consultas de este sistema resultó
> ser **idéntico**. Se copiaron tal cual. Eso dejaba una duda razonable: ¿de
> verdad hacían falta clases separadas por motor, o era ceremonia?
>
> **Con SQL Server el SQL sí cambia.** No existe `LIMIT`. La llave generada se
> lee con una cláusula en medio del `INSERT`. Y su driver ni siquiera viene
> con PHP. Si algo del dialecto se había colado hacia arriba sin que se
> notara, aquí se ve.

Además, esta versión completa el `docker-compose.yml` al que apuntaba la ruta
desde la v1: seis servicios, tres motores, un solo comando.

## 2. Alcance

**Incluye:**

- **SQL Server 2022** como tercer motor, con la misma base `bdfacturas` en
  T-SQL (`db/sqlserver/bdfacturas.sql`).
- **Un contenedor que inicializa esa base**, porque la imagen de SQL Server
  —a diferencia de las de MariaDB y PostgreSQL— no ejecuta sola los scripts
  que encuentre.
- **El driver de PHP para SQL Server** en la misma imagen de la API, que lo
  publica Microsoft aparte y necesita un ODBC del sistema debajo.
- **Seis repositorios nuevos** (`Repositorio*SqlServer`) y **su traductor de
  errores**, cumpliendo las mismas interfaces.
- **La fábrica extendida** a tres motores.
- **La prueba de los tres motores.**

**No incluye (y es deliberado):**

- Ningún recurso, endpoint ni pantalla nueva. Igual que la v3, esta versión
  no agrega funcionalidad.
- Las seis tablas de control de acceso (`rol`, `ruta`, `usuario`…), que
  siguen existiendo en las tres bases sin que el código las nombre.
- Sincronizar datos entre los tres motores: **son tres bases
  independientes**.
- Un cuarto motor. La ruta termina aquí.

## 3. Requisitos funcionales

### RF1 — El motor se escoge entre TRES, por configuración
`MOTOR` acepta `mariadb` (por defecto), `postgres` y `sqlserver`. Ningún
archivo se edita para cambiar.

Un valor desconocido sigue arrancando en MariaDB, como en la v3.

### RF2 — Los seis recursos se comportan igual contra los tres motores
Todo lo que la v2 prometía y la v3 confirmó, ahora por triplicado.

### RF3 — Los tres conflictos de integridad dicen el MISMO texto
Con los tres motores, palabra por palabra. Y ahora hay un dato que la v3 no
podía ver: **dos de los tres motores usan un solo código para los dos
sentidos de una llave foránea** (PostgreSQL y SQL Server). MariaDB, que los
separa, resultó ser la excepción — lo cual era imposible de saber con dos.

### RF4 — El diagnóstico dice cuál de los tres está activo
`GET /` devuelve `motor` con `"mariadb"`, `"postgres"` o `"sqlserver"`.

### RF5 — La pantalla lo muestra con su nombre bonito
El pie dice **«SQL Server»**, no `sqlserver`. Al usuario el valor de una
variable de entorno no le dice nada.

### RF6 — Un solo comando levanta los seis servicios
`docker compose up -d --build` deja funcionando las **tres** bases, el
inicializador de SQL Server, la API y el front.

**Y tiene que funcionar dos veces seguidas.** El inicializador comprueba si la
base ya existe antes de crearla: volver a levantar el sistema no puede
romperlo ni duplicar datos.

## 4. Requisitos no funcionales

- **RNF1 — Las interfaces no cambian.** Otra vez. Si una tuvo que cambiar
  para que SQL Server entrara, la v3 estaba describiendo a dos motores y no
  al negocio.
- **RNF2 — El dialecto vive SOLO en las clases con apellido de motor**, ahora
  tres familias.
- **RNF3 — Las frases del usuario son las mismas**, escritas una vez en
  `conflictos.php`.
- **RNF4 — Una sola imagen de la API**, con los tres drivers.
- **RNF5 — El sistema arranca desde cero con un comando**, incluida la
  creación de la base de SQL Server, sin pasos manuales.

## 5. Criterios de aceptación

1. **`docker compose up -d --build` desde cero** deja los seis servicios
   funcionando: las tres bases, `sqlserver-init` terminado correctamente, la
   API y el front. `GET /` responde `"motor": "mariadb"`.
2. Con `MOTOR=sqlserver` y un reinicio de la API, `GET /` responde
   `"motor": "sqlserver"` — sin editar ningún archivo.
3. Los seis recursos listan sus datos de ejemplo con **los tres** motores, con
   la misma envoltura y los mismos totales. `?limite=3` devuelve 3 en los
   tres (en SQL Server eso no es un `LIMIT`: es `OFFSET/FETCH`).
4. El ciclo completo de una ficha funciona igual con los tres, incluido el
   `id` que devuelve el `POST` (que cada motor genera de una manera distinta).
5. **Los tres conflictos de integridad responden 409 con el MISMO texto** con
   los tres motores.
6. El maestro-detalle funciona igual con los tres: mismo total calculado,
   mismo stock movido, `PATCH` en 405.
7. Anular funciona igual con los tres, con sus dos 409.
8. `PUT /api/factura/999` responde **404** con los tres. (Los tres
   procedimientos avisan de forma distinta —`SIGNAL`, `RAISE`, `THROW`— y el
   mensaje llega envuelto de tres maneras.)
9. Prueba de capas: los servicios siguen funcionando con repositorios falsos.
   **Este archivo no cambió.**
10. Todas las pantallas responden y se ven con sus estilos, con los tres
    motores, y el pie muestra el nombre bonito del que está activo.
11. El nombre del motor aparece **exactamente una vez** en cada pantalla, y
    es en el pie.
12. **La prueba de los tres motores termina en verde:**
    `python pruebas_humo/humo_los_tres_motores.py`.
13. **EL CRITERIO DE LA VERSIÓN.** Compare con la v3:

    ```powershell
    diff -rq --exclude=.git --exclude=docs ..\proyecto_php3 .
    ```

    **13.a — Ningún controlador, ningún servicio y ninguna interfaz
    cambiaron.** Ni uno. Esta vez es más estricto que en la v3: allá cambió un
    servicio por un cambio de nombre heredado, y aquí no había nada pendiente
    que arreglar.

    **13.b — Los únicos cambios permitidos, y su motivo:**

    | Archivo | Por qué cambió |
    |---|---|
    | `servicios/ensamblador.php` | Una rama más en la fábrica. **Es el archivo de esta versión.** |
    | `api_facturas/Dockerfile` | El driver de SQL Server, que no viene con PHP |
    | `api_facturas/index.php` | **Una línea**: el número de versión |
    | `docker-compose.yml` | Los dos servicios de SQL Server |
    | `front_php/vistas/plantilla.php` | El nombre bonito del tercer motor en el pie |
    | `front_php/Dockerfile`, `front_php/cliente_api.php` | Los puertos de esta versión |
    | `pruebas_humo/humo_front.py` | Un nombre más en la tabla de etiquetas |

    **13.c — Lo que se agregó**, que es donde vive todo el trabajo: seis
    `Repositorio*SqlServer`, su traductor de errores, `db/sqlserver/` y el
    guion de los tres motores.

> **Compare la lista 13.b de esta versión con la de la v3.** Es más corta, y
> eso no es porque se haya hecho menos: es porque la v3 dejó el sitio
> preparado. Ésa es la diferencia entre una arquitectura que aguantó una
> extensión y una que aguanta las que vengan.

## 6. Glosario mínimo

| Término | Significado |
|---|---|
| **T-SQL** | El dialecto de SQL de SQL Server. Se parece al estándar hasta que deja de parecerse |
| **`OFFSET/FETCH`** | El equivalente estándar de `LIMIT`. Exige un `ORDER BY` |
| **`OUTPUT INSERTED`** | La forma de SQL Server de devolver la llave que acaba de generar |
| **ODBC** | La capa por la que PHP habla con SQL Server. Se instala aparte del lenguaje |
| **PECL** | El gestor de extensiones de PHP. Con él se compila el driver de SQL Server |
| **Contenedor de un solo uso** | Uno que hace su encargo y termina, como `sqlserver-init`. No es un servicio |

## 7. Definición de TERMINADA

Los 13 criterios pasan → commit + tag `v4`.

**Y con eso la ruta de versiones se cierra.** No hay v5.

## 8. Clarificaciones

| # | La pregunta | La respuesta acordada, con su razón | Dónde quedó |
|---|---|---|---|
| C1 | El driver de SQL Server no viene con PHP. ¿Se usa `pdo_dblib`, que sí es más fácil de instalar? | **No: el de Microsoft (`pdo_sqlsrv`).** `pdo_dblib` se instala con un comando, y a cambio tiene limitaciones conocidas con parámetros y tipos. El driver oficial es el que se usaría en un sistema real, y las diez líneas del Dockerfile son un precio de una sola vez. | 3_plan §1 · 4_research D1 |
| C2 | ¿Por qué SQL Server necesita un contenedor aparte para crear la base? | Porque su imagen **no tiene** el mecanismo de `/docker-entrypoint-initdb.d/` que las otras dos sí traen. Es la primera diferencia de la versión, y aparece antes de escribir una línea de PHP: no todos los motores se dejan levantar igual. | 3_plan §5 · C6 |
| C3 | El parámetro de salida de los procedimientos no se deja enlazar con PDO. ¿Se cambian los procedimientos? | **No: se cambia cómo se llaman.** Enlazar un `NVARCHAR(MAX)` de salida falla con `Invalid precision value` —a un MAX no se le puede dar longitud fija—. La salida es declarar la variable en el propio bloque de T-SQL y pedirla con un `SELECT`. Tocar los seis procedimientos habría sido cambiar la base para acomodar una limitación del driver. | 3_plan §4.3 · 4_research D2 |
| C4 | La clave de SQL Server no cumple la convención del proyecto (`paradigmas123`). | **Se acepta la excepción**: SQL Server exige mayúscula, número y símbolo, y su cuenta administrativa se llama `sa`. Forzar la convención habría significado no poder arrancar. Está declarado en la constitución. | Artículo 8 · compose |
| C5 | ¿`TrustServerCertificate=yes` no es un riesgo? | **En este entorno no, y en producción sí** — por eso está comentado en el compose. SQL Server se genera un certificado propio; aceptarlo sin verificar está bien en un curso y estaría mal en un sistema real. Decirlo es parte de enseñarlo. | compose · 4_research D3 |
| C6 | Si `sqlserver-init` corre otra vez, ¿duplica los datos? | **No: comprueba primero si la base existe.** Un inicializador que solo funcione la primera vez es una trampa esperando a que alguien vuelva a levantar el sistema. | RF6 |
| C7 | ¿Se puede quitar MariaDB ahora que hay tres? | **No, y ninguno de los tres.** El punto de la ruta es que los tres funcionan a la vez con el mismo código. Quitar uno sería quitar la demostración. | Alcance |
