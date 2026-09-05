# Especificación — Versión 3: el mismo sistema contra dos motores

> **Versión 3** del desarrollo incremental ([mapa de versiones](../0_mapa_versiones.md)).
> Rige la constitución del proyecto: [../../1_constitution.md](../../1_constitution.md).
> Parte del estado que dejó la v2: seis recursos funcionando contra MariaDB.
>
> | Documento de esta versión | Contenido |
> |---|---|
> | **2_spec.md** (este) | QUÉ construir en v3 y sus criterios de aceptación |
> | [3_plan.md](3_plan.md) | CÓMO: la fábrica y lo que cambia por motor |
> | [4_research.md](4_research.md) | Decisiones y alternativas *(lectura opcional)* |
> | [5_data_model.md](5_data_model.md) | La misma base, en dos dialectos |
> | [6_contracts.md](6_contracts.md) | Los contratos — que NO cambian, y por qué eso es el punto |
> | [7_quickstart.md](7_quickstart.md) | Arranque, cambio de motor y smoke test |
> | [8_tasks.md](8_tasks.md) | Orden de construcción por fases verificables |

---

## 1. Propósito de la v3

**Esta versión no agrega ni una funcionalidad.** No hay recursos nuevos, ni
pantallas nuevas, ni un solo endpoint más. Lo que agrega es un segundo motor
de base de datos — y con él, la única forma honesta de comprobar si las dos
versiones anteriores quedaron bien cortadas.

Es distinto de todo lo anterior, y conviene entender por qué:

- La v1 y la v2 **prometían** que el motor estaba encerrado en la capa de
  datos. Era una promesa razonable, escrita en documentos, imposible de
  verificar: con un solo motor, cualquier arquitectura parece independiente
  del motor.
- La v3 **cobra la promesa**. Si al agregar PostgreSQL hay que tocar un
  controlador, un servicio o una pantalla, es que el motor se había filtrado
  hacia arriba y nadie se había dado cuenta.

Por eso el criterio de aceptación más importante de esta versión no dice qué
hay que construir: dice **qué NO se puede haber tocado**.

> **El nombre técnico de esto es el principio abierto/cerrado** (la O de
> SOLID): un sistema debe estar **abierto** a que se le agreguen cosas y
> **cerrado** a que agregárselas obligue a reescribir lo que ya funcionaba.
> Hasta hoy era una frase de un documento. Hoy se ejecuta.

## 2. Alcance

**Incluye:**

- **PostgreSQL 16** como segundo motor, con la misma base `bdfacturas` en su
  dialecto (`db/postgres/init.sql`), levantada por el mismo compose.
- **Seis repositorios nuevos** (`Repositorio*Postgres`), que cumplen las
  **mismas interfaces** que los de MariaDB.
- **Un traductor de errores de integridad por motor**, y las frases del
  usuario compartidas entre los dos.
- **La fábrica**: `ensamblador.php` deja de armar una sola combinación y pasa
  a escoger según la variable de entorno `MOTOR`.
- **El motor activo publicado** en `GET /` y mostrado en el pie de las
  pantallas — no para el usuario, sino para poder comprobar la promesa.
- **La prueba de los dos motores**: el mismo guion de humo, corrido dos veces.

**No incluye (y es deliberado):**

- Ningún recurso, endpoint ni pantalla nueva.
- SQL Server, que llega en la v4.
- Migrar datos de un motor a otro, o mantenerlos sincronizados. **Son dos
  bases independientes**, cada una con los mismos datos de ejemplo; lo que se
  escriba en una no aparece en la otra. Esta versión demuestra que el
  *programa* funciona contra las dos, no que las dos sean la misma.
- Cambiar de motor sin reiniciar la API. La variable se lee al arrancar.

## 3. Requisitos funcionales

### RF1 — El motor se escoge por configuración, nunca por código
La variable de entorno `MOTOR` decide: `mariadb` (por defecto) o `postgres`.
Ningún archivo del sistema se edita para cambiar de motor.

Un valor desconocido (`MOTOR=oracle`) **no falla**: arranca en MariaDB. El
motor por defecto es una decisión de configuración, no una trampa para el que
se equivoque escribiendo.

### RF2 — Los seis recursos se comportan igual contra los dos motores
Todo lo que la v2 prometía —los listados, las seis operaciones, el 204, el
405, el maestro-detalle, anular— responde lo mismo con `MOTOR=mariadb` y con
`MOTOR=postgres`.

### RF3 — Los tres conflictos de integridad dicen el MISMO texto
Un 409 por llave duplicada, por referencia rota o por dependencias debe
entregar **la misma frase en español** con los dos motores.

Esto no sale gratis: los dos motores reportan esos problemas de forma
distinta —MariaDB con tres códigos numéricos, PostgreSQL con dos SQLSTATE, y
uno de ellos sirve para dos casos opuestos—. Que el usuario lea lo mismo es
un requisito, no una casualidad.

### RF4 — El diagnóstico dice qué motor está activo
`GET /` devuelve, además de lo de la v2, un campo `motor` con `"mariadb"` o
`"postgres"`.

**Es el único sitio de la API donde se menciona el motor.** Ninguna otra
respuesta lo nombra ni cambia de forma según cuál sea.

### RF5 — La pantalla lo muestra, en el pie y en letra pequeña
El front muestra el motor activo en el pie de página, y **no hace nada más
con ese dato**: no cambia una consulta, no oculta un botón, no toma ninguna
decisión.

El sitio es la mitad del mensaje: al usuario no le importa, y puede usar el
sistema entero sin mirarlo nunca. Está para que se pueda comprobar la promesa
de la versión sin ir a leer el compose.

### RF6 — Un solo comando sigue levantando todo
`docker compose up -d --build` deja funcionando **las dos bases**, la API (en
MariaDB) y el front. Cambiar de motor es una variable y un reinicio de la
API, no un compose distinto.

## 4. Requisitos no funcionales

- **RNF1 — Las interfaces no cambian.** `IRepositorioProducto`,
  `IRepositorioFactura` y las demás son las mismas de la v2. Si una interfaz
  tuvo que cambiar para que PostgreSQL entrara, estaba describiendo a MariaDB
  y no al negocio.
- **RNF2 — El dialecto vive SOLO en las clases con nombre de motor.** Ni un
  `SELECT`, ni un nombre de procedimiento, ni un código de error fuera de
  `Repositorio*MariaDB`, `Repositorio*Postgres` y sus dos traductores.
- **RNF3 — Las frases del usuario son las mismas.** Los mensajes de conflicto
  se escriben una vez, en un archivo compartido; los traductores por motor
  solo deciden **cuál** de ellas corresponde.
- **RNF4 — Una sola imagen de la API.** El mismo contenedor sirve para los dos
  motores: trae los dos drivers de PDO y escoge al arrancar. Dos imágenes
  distintas habrían escondido que es el mismo programa.
- **RNF5 — Todo lo demás de la v2 sigue vigente**: capas estrictas, PHP puro,
  prepared statements, el front sin acceso a la base, y nada de jerga en la
  pantalla.

## 5. Criterios de aceptación

1. **`docker compose up -d --build` — un solo comando —** deja corriendo
   MariaDB, PostgreSQL, la API y el front. `GET /` responde `"motor":
   "mariadb"`.
2. Con `MOTOR=postgres` y un reinicio de la API, `GET /` responde
   `"motor": "postgres"` — **sin haber editado ningún archivo**.
3. Los seis recursos listan sus datos de ejemplo con los dos motores, con la
   misma envoltura y los mismos totales.
4. El ciclo completo de una ficha (crear, PUT, PATCH, DELETE) funciona igual
   con los dos motores, incluido el `id` que devuelve el `POST`.
5. **Los tres conflictos de integridad responden 409 con el MISMO texto** con
   los dos motores. Compare las respuestas palabra por palabra.
6. El maestro-detalle funciona igual con los dos: crear una factura de dos
   renglones calcula el mismo total, mueve el stock lo mismo, y el `PATCH`
   sigue respondiendo 405.
7. Anular funciona igual con los dos, y los dos rechazan con 409 el segundo
   intento y la modificación de una factura anulada.
8. `PUT /api/factura/999` responde **404** con los dos motores. (Los dos
   procedimientos avisan de forma distinta; que el resultado sea el mismo es
   trabajo de sus repositorios.)
9. Prueba de capas: los servicios siguen funcionando con repositorios falsos,
   sin ninguna base corriendo — y **ningún repositorio falso tuvo que
   cambiar de forma**, porque las interfaces no se movieron. (El archivo sí
   cambió, pero solo por el cambio de nombre del criterio 13.b.)
10. Todas las pantallas responden y se ven con sus estilos, con los dos
    motores, y el pie muestra el nombre del que está activo.
11. El nombre del motor aparece **exactamente una vez** en cada pantalla, y
    es en el pie. En ninguna otra parte del texto que el usuario ve.
12. **La prueba de los dos motores termina en verde:**
    `python pruebas_humo/humo_los_dos_motores.py` corre el guion de humo
    completo contra cada uno y los dos dan el mismo resultado.
13. **EL CRITERIO DE LA VERSIÓN.** Compare esta versión con la v2 archivo
    por archivo:

    ```powershell
    # (con las dos carpetas al lado, fuera del contenedor)
    diff -rq --exclude=.git ..\proyecto_php2 .
    ```

    **13.a — Ningún controlador y ninguna vista cambiaron.** Ni uno. Si
    alguno cambió, el motor estaba filtrado hasta ahí y se corrigió en el
    sitio equivocado: la versión **no está terminada** aunque todo funcione.

    **13.b — Los únicos cambios permitidos, y su motivo:**

    | Archivo | Por qué cambió |
    |---|---|
    | `servicios/ensamblador.php` | Pasó a ser la fábrica. **Es el archivo de esta versión.** |
    | Los seis `Repositorio*MariaDB` | Su traductor de errores cambió de nombre al partirse en dos (uno por motor) |
    | `api_facturas/index.php` | **Una línea**: publicar el motor en el diagnóstico |
    | `repositorios/IRepositorioProducto.php`, `servicios/ServicioProducto.php`, `pruebas/prueba_capas.php` | Un **cambio de nombre**: `obtenerPorCodigo` → `obtenerPorClave` (ver abajo) |
    | El front y su prueba de humo | Mostrar el motor en el pie, y correr contra los dos |
    | Los dos `Dockerfile` y el `docker-compose.yml` | El driver y el servicio del motor nuevo |

    **Sobre el cambio de nombre, porque es el único que incomoda.** Sí,
    cambió un servicio — y hay que decirlo en vez de esconderlo detrás de un
    criterio redactado para que dé verde.

    Lo que pasó: en la v1, la interfaz de `producto` llamaba
    `obtenerPorCodigo` a su método de lectura. En la v2, los cinco recursos
    nuevos estrenaron `obtenerPorClave`, porque dos de ellos tienen una llave
    numérica y llamarla «código» habría sido mentir. Quedaron seis interfaces
    con dos nombres para lo mismo, **y nadie lo notó durante toda la v2**.

    Se notó ahora, y por una razón que vale la lección: al escribir cada
    repositorio **por segunda vez**, la diferencia saltó a la vista. Un
    segundo motor no solo prueba la arquitectura — también obliga a releer
    lo escrito, y ahí es donde aparecen las inconsistencias que un solo
    camino nunca revela.

    El cambio es de nombre interno: **el contrato HTTP no se movió**, así que
    no rompe nada de lo que las versiones anteriores prometieron.

> El criterio 13 es raro y es el más importante. Los demás comprueban que el
> sistema funciona; ése comprueba que la arquitectura servía para algo. Y
> fíjese en que **se aprueba con un `diff`, no con una opinión**.

## 6. Glosario mínimo

| Término | Significado |
|---|---|
| **Fábrica** | La pieza que decide qué clase concreta construir. Aquí, `ensamblador.php` |
| **Abierto/cerrado** | Abierto a que le agreguen cosas, cerrado a que agregárselas obligue a reescribirlo |
| **Dialecto** | Las diferencias de SQL y de comportamiento entre motores. `RETURNING` existe en PostgreSQL y no en MariaDB |
| **SQLSTATE** | El código de error estándar de SQL. PostgreSQL lo usa bien; MariaDB pone `23000` para todo y el número útil va aparte |
| **DSN** | La cadena que le dice a PDO a qué base conectarse. Es lo único de la conexión que cambia entre motores |

## 7. Definición de TERMINADA

Los 13 criterios pasan → commit + tag `v3` → recién entonces se escribe la
spec de la v4 ([mapa](../0_mapa_versiones.md)).

## 8. Clarificaciones

| # | La pregunta | La respuesta acordada, con su razón | Dónde quedó |
|---|---|---|---|
| C1 | ¿Dos servicios de API, uno por motor, o uno solo que escoge? | **Uno solo.** El punto de la versión es que **es el mismo programa**; dos servicios habrían escondido justamente eso, y además habrían duplicado los puertos y la configuración. | RNF4 · 4_research D1 |
| C2 | Si `MOTOR` trae un valor desconocido, ¿la API falla al arrancar? | **No: arranca en MariaDB.** El motor por defecto es configuración, no una trampa. Un sistema que no arranca porque alguien escribió «postgresql» con L de más es un sistema hostil. | RF1 |
| C3 | Los dos motores reportan las llaves foráneas distinto. ¿El usuario debería ver esa diferencia? | **No.** Los tres mensajes de conflicto se escriben una sola vez en `conflictos.php` y los dos traductores los usan. El contrato promete un texto, no un código; un contrato que cambia según lo que haya detrás no es un contrato. | RF3 · RNF3 |
| C4 | ¿El front necesita saber qué motor hay? | **No, y no lo usa para nada.** Lo muestra en el pie y ahí termina. El día que el front pregunte «¿estoy contra PostgreSQL?» para hacer algo distinto, la separación de capas se rompió. | RF5 |
| C5 | Las dos bases, ¿se sincronizan? | **No: son independientes.** Cada una arranca con los mismos datos de ejemplo, y lo que se escriba en una no aparece en la otra. Esta versión demuestra que el programa funciona contra las dos, no que las dos sean la misma. | Alcance |
| C6 | El `SELECT` resultó idéntico en los dos motores. ¿Hacía falta una clase por motor? | **Sí.** Lo que cambia no es el SQL de estas consultas —esta base usa SQL estándar— sino **todo lo de alrededor**: cómo se lee una llave generada, cómo se llama un procedimiento, cómo se reconoce un error. Una sola clase con `if ($motor === …)` adentro habría acumulado esas diferencias hasta volverse ilegible, y habría hecho imposible el criterio 13. | 4_research D2 |
| C7 | ¿Se puede cambiar de motor sin reiniciar? | **No, y no se intentó.** Leer la variable en cada petición habría permitido que dos peticiones simultáneas usaran motores distintos, con la mitad de los datos en cada base. Se lee al arrancar. | Alcance |
