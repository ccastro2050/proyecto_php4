# Especificación — Versión 4: el tercer motor, el control de acceso y el aplicativo

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


---

# ANEXO A LA v4 — LO QUE SE AGREGÓ DESPUÉS DEL TERCER MOTOR

Este anexo no corrige nada de lo anterior: lo que dice el documento sobre
SQL Server y el compose de tres motores sigue valiendo palabra por palabra.
Lo que hace es **decir el resto**, porque la v4 acabó trayendo tres cosas más
que el documento no mencionaba, y un documento que describe menos de lo que
el código hace es peor que no tenerlo: el que lo lee cree que ya sabe.

Las tres, en el orden en que se construyeron:

| | Qué entró | Por qué en esta versión |
|---|---|---|
| **A** | El **control de acceso**: entrar con contraseña, token, 401 y 403 | Las seis tablas estaban en la base de datos desde la v1 y no las usaba nadie. Una tabla que no se usa es una promesa sin cumplir |
| **B** | **`/api/usuario-con-roles`**: el usuario y sus roles en una operación | Crear un usuario con dos roles eran tres peticiones, y si fallaba la segunda quedaba a medias |
| **C** | Las **diez consultas** y el **tablero** | El CRUD responde «qué hay en esta tabla». Nada respondía «qué se vende y cuánto deja» |

Y con eso esta v4 queda **equivalente a las otras cuatro de la ruta** —.NET
con Blazor, .NET con Flask, Python con Flask— en lo que hacen. Lo que cambia
entre las cinco es el stack, no el alcance.

## A. El control de acceso

### A.1 Requisitos funcionales

| Id | Requisito |
|---|---|
| RF-A1 | `POST /api/sesion` recibe correo y contraseña y devuelve un **token** firmado, el correo, los roles y la fecha de vencimiento |
| RF-A2 | Toda la API exige la cabecera `Authorization: Bearer <token>`, **salvo dos rutas**: `GET /` (el diagnóstico) y `POST /api/sesion` (la puerta) |
| RF-A3 | Sin token o con token inválido: **401**. Con token válido y sin permiso para esa ruta: **403**. Son dos respuestas distintas porque son dos problemas distintos |
| RF-A4 | El permiso se consulta **en cada petición** contra la base de datos (`verificar_acceso_ruta`), no se lee del token |
| RF-A5 | `GET /api/permisos/mios` devuelve las rutas que alcanza quien pregunta, para que el front arme su menú |
| RF-A6 | Las contraseñas se guardan con **bcrypt costo 12**. La API nunca las devuelve: `GET /api/usuario` trae el correo y nada más |
| RF-A7 | Los cinco recursos del acceso —`rol`, `ruta`, `usuario`, `rutarol`, `rol-usuario`— tienen su CRUD, **con una excepción declarada**: las dos tablas puente no admiten PUT ni PATCH |
| RF-A8 | El front: pantalla de entrar, guardia que manda a `/entrar`, menú armado con los permisos, y pantalla para cada uno de los cinco recursos |

### A.2 Las dos decisiones que hay que poder defender

**EL PERMISO NO VA EN EL TOKEN.** Cabría —el token es un JSON firmado— y
sería más rápido: una petición menos a la base de datos. Y está descartado a
propósito: si el permiso viajara dentro, quitarle un rol a alguien no tendría
efecto hasta que su token venciera. Con la consulta en cada petición, el
cambio se nota en **la petición siguiente**. Se paga una consulta por
petición para que la tabla `rutarol` sea la verdad y no una copia vieja.

**EL TOKEN ESTÁ FIRMADO, NO CIFRADO.** Cualquiera puede leer su contenido
—son dos bloques de base64 y el navegador los decodifica—. Lo que nadie
puede es **cambiarlo**: la firma se rompe. Por eso dentro no va ni un dato
secreto, solo el correo, los roles y las dos fechas.

### A.3 Criterios de aceptación

| Id | Criterio | Cómo se comprueba |
|---|---|---|
| CA-A1 | Sin token, cualquier ruta responde 401 | `Invoke-RestMethod http://localhost:8090/api/producto` sin cabecera |
| CA-A2 | Con el token de `vendedor1`, `/api/usuario` responde **403** y `/api/factura` responde 200 | Dos peticiones con el mismo token |
| CA-A3 | El 403 **no depende del front**: se obtiene escribiendo la dirección a mano o desde Postman | Entrar al front como `vendedor1` y pedir `/productos` |
| CA-A4 | Quitarle el rol a un usuario le cierra la ruta **sin cerrar su sesión** | Borrar la fila de `rol_usuario` y repetir la misma petición con el mismo token: 200 → 403 |
| CA-A5 | Las contraseñas sembradas son usables y están **escritas en la documentación** | Entrar con las tres cuentas de la pantalla de entrar |
| CA-A6 | Una ruta no declarada en la tabla `ruta` **no deja pasar a nadie** (fallar cerrado) | Pedir una ruta cuyo nombre no exista en la tabla |

## B. `usuario-con-roles`

| Id | Requisito |
|---|---|
| RF-B1 | `POST` crea el usuario **y** le da sus roles en una sola petición |
| RF-B2 | `PUT` reemplaza contraseña y roles; `PATCH` cambia solo lo que llegue |
| RF-B3 | `DELETE` borra el usuario y sus filas de `rol_usuario` |
| RF-B4 | `GET` (uno y lista) devuelve cada usuario con su lista de roles **en una consulta**, no una por usuario |
| RF-B5 | La operación es **atómica**: si un rol no existe, no queda ni el usuario creado ni los roles a medias |

El RF-B5 es el que importa, y es el que **falló al medirlo**: ver el anexo de
`4_research.md`. Los procedimientos de MariaDB no tenían transacción.

## C. Las diez consultas y el tablero

| Id | Requisito |
|---|---|
| RF-C1 | Diez consultas de solo lectura bajo `/api/consultas/...`, **todas GET** |
| RF-C2 | Cada una cruza **cuatro o más tablas**: una consulta de una sola la responde el CRUD |
| RF-C3 | El sobre es `{consulta, total, datos}` —no `{tabla, limite, total, datos}`—, porque ninguna sale de una sola tabla y un reporte no se pagina |
| RF-C4 | Las diez exigen el permiso `/home`, el de entrar al aplicativo |
| RF-C5 | `GET /api/consultas` devuelve el índice de las diez, para que el front no las tenga escritas a mano |
| RF-C6 | El front tiene un **tablero**: el índice y una pantalla por consulta |
| RF-C7 | Las diez dan el mismo resultado en los **tres motores**, con el SQL escrito como cada uno lo entiende |

### Criterios de aceptación

| Id | Criterio | Cómo se comprueba |
|---|---|---|
| CA-C1 | Las diez corren en los tres motores y devuelven las columnas del contrato | `php pruebas/prueba_consultas_tres_motores.php` con `MOTOR` en los tres valores |
| CA-C2 | Tres de las diez pueden devolver **cero filas** con los datos sembrados, y eso no es un error | La pantalla lo dice con esas palabras, y explica cómo hacer que respondan |
| CA-C3 | Un POST, PUT, PATCH o DELETE a una consulta responde **405** | Cualquiera de las diez con otro verbo |
| CA-C4 | El `ticket_promedio` coincide con `total / facturas` en los tres motores | La misma prueba lo calcula y lo compara |

## Definición de TERMINADA, al cierre de la v4

A lo que ya decía el documento se le suman cuatro líneas:

- la API está **cerrada**: dos rutas abiertas y declaradas, el resto exige token;
- los **once** recursos tienen su CRUD y su pantalla, y los dos puentes tienen
  declarado por escrito por qué les faltan dos verbos;
- las **diez consultas** pasan en los tres motores, medido con una prueba que
  se puede volver a correr;
- y el front **no muestra lo que va a rechazar**: el menú, las tarjetas del
  inicio y los botones se arman con los permisos que responde la API.
