# Tareas — Versión 3: el segundo motor

> **Versión 3** · El orden de construcción, partiendo del estado que dejó la
> v2. Cada fase termina en algo **verificable**. Requisitos:
> [2_spec.md](2_spec.md) · técnica: [3_plan.md](3_plan.md) · validación:
> [7_quickstart.md](7_quickstart.md).
>
> **Esta versión tiene menos fases que las anteriores, y son más cortas.** Es
> la señal de que las dos versiones previas quedaron bien cortadas: si esta
> lista fuera larga, sería porque hay que ir a arreglar cosas por todas
> partes — y eso ya sería el diagnóstico.

---

## Fase 0 — Partir de la v2 y cambiar los puertos
- [ ] Partir del código de la v2 funcionando (repositorio `proyecto_php2`).
- [ ] Cambiar los puertos a los de esta versión: front **8084**, API **8086**,
      phpMyAdmin **8103**, MariaDB **13328** — en `docker-compose.yml`, en los
      dos `Dockerfile` (`EXPOSE` y `CMD`), en el `URL_API` por defecto de
      `front_php/cliente_api.php` y en `pruebas_humo/humo_front.py`.

**Verificar:** `docker compose up -d --build` y todo funciona igual que la v2.
**Si esto no pasa, no siga.**

## Fase 1 — La segunda base
- [ ] Mover `db/init.sql` → `db/mariadb/init.sql` (y el de phpMyAdmin con él).
      Actualizar las rutas en el compose.
- [ ] Copiar el script **provisto** de PostgreSQL a `db/postgres/init.sql`.
- [ ] Agregarle **la guarda que impide modificar una factura anulada**, la
      misma que la v2 le puso al de MariaDB. (El otro defecto de la v2 —el
      `estado` faltante en el listado— este script no lo tiene.)
- [ ] Agregar el servicio `postgres` al compose: imagen `postgres:16-alpine`,
      volumen `pgdata`, el script montado en
      `/docker-entrypoint-initdb.d/`, puerto 15464 y healthcheck con
      `pg_isready`.

**Verificar:** `docker compose up -d postgres` y, con un cliente SQL o con
`docker compose exec postgres psql -U paradigmas -d bdfacturas_postgres_local`,
las 12 tablas existen y `SELECT count(*) FROM producto` da **8**.

## Fase 2 — El driver, en la misma imagen
- [ ] `api_facturas/Dockerfile`: instalar `libpq-dev` y compilar **`pdo_mysql`
      y `pdo_pgsql`** en el mismo `RUN`.

**Verificar:**
`docker compose exec api-facturas php -r "var_dump(PDO::getAvailableDrivers());"`
debe listar `mysql` **y** `pgsql`.

> Una sola imagen para los dos motores, no dos. El punto de la versión es que
> es el mismo programa; dos imágenes lo esconderían.

## Fase 3 — Los errores de integridad se parten en tres
- [ ] `repositorios/conflictos.php`: las tres frases del usuario
      (`conflictoLlaveDuplicada`, `conflictoReferenciaRota`,
      `conflictoTieneDependientes`), **una sola vez**.
- [ ] `repositorios/errores_de_integridad_mariadb.php`: lo que hacía el
      archivo de la v2, pero llamando a esas frases.
- [ ] `repositorios/errores_de_integridad_postgres.php`: los SQLSTATE
      `23505` y `23503` — y, para el segundo, mirar el mensaje, porque
      **PostgreSQL usa el mismo código para los dos sentidos de una llave
      foránea** ([3_plan.md](3_plan.md) §4.3).
- [ ] Actualizar los seis repositorios de MariaDB para que llamen a
      `traducirErrorMariaDB`.

**Verificar:** nada cambió todavía para el usuario — los tres 409 de la v2
siguen respondiendo el mismo texto contra MariaDB.

> Se hace **antes** de escribir los repositorios nuevos, y no después, por la
> misma razón que en la v2: es de lo que depende que los seis salgan iguales.

## Fase 4 — Los seis repositorios de PostgreSQL
- [ ] `RepositorioProductoPostgres`, `...Empresa...`, `...Persona...`,
      `...Cliente...`, `...Vendedor...`, cumpliendo **las mismas interfaces**,
      que **no se tocan**.
- [ ] `RepositorioFacturaPostgres`, que es el que más se separa del suyo de
      MariaDB: el `CALL` devuelve una fila en vez de necesitar una variable de
      sesión, el rechazo llega como `P0001`, y el mensaje trae un bloque
      `CONTEXT:` que hay que recortar.
- [ ] Emparejar lo que llega distinto: la fecha con microsegundos, el
      `json_agg` que devuelve `NULL` en vez de lista vacía.

**Verificar:** todavía no se puede probar por HTTP —falta la fábrica—, pero
`php -l` pasa en los seis y ninguna interfaz cambió.

> **Si alguna interfaz tuvo que cambiar para que PostgreSQL entrara, pare.**
> Esa interfaz estaba describiendo a MariaDB y no al negocio, y el sitio para
> arreglarlo es la v2, no aquí.

## Fase 5 — La fábrica
- [ ] `servicios/ensamblador.php`: `motorActivo()`, `datosDeConexion()` que
      escoge el DSN, y las seis funciones que ahora eligen la clase.
- [ ] El compose: la variable `MOTOR: ${MOTOR:-mariadb}` y los dos DSN.

**Verificar (criterios 1 y 2):** `GET /` dice `"motor": "mariadb"`; con
`$env:MOTOR = "postgres"` y `--force-recreate`, dice `"motor": "postgres"` —
**sin haber editado ningún archivo**.

> **Ésta es la fase de la versión.** Todo lo anterior fue preparar; aquí es
> donde el principio abierto/cerrado se ejecuta.

## Fase 6 — Publicar el motor y mostrarlo
- [ ] `api_facturas/index.php`: **una línea** — el campo `motor` en el
      diagnóstico.
- [ ] `front_php/cliente_api.php`: la función `diagnostico()`.
- [ ] `front_php/index.php`: pasarle el motor a la plantilla.
- [ ] `front_php/vistas/plantilla.php`: la etiqueta en el pie.

**Verificar (criterios 10 y 11):** el pie dice el motor, y ese nombre aparece
**una sola vez** en cada pantalla.

> Al hacer esto, la prueba de humo va a **fallar**: la regla de la v2 decía
> que ninguna pantalla puede nombrar un motor. Es un choque real entre una
> regla vieja y una necesidad nueva, y se resuelve en la fase siguiente
> **haciendo la regla más precisa, no quitándola**.

## Fase 7 — Las pruebas
- [ ] `humo_front.py`: quitar los nombres de motor de la lista de jerga y
      agregar la sección **4.b**, que exige que aparezcan **exactamente una
      vez y en el pie**. La comprobación queda más estricta que antes, no más
      floja.
- [ ] `humo_front.py`: la sección **10**, que verifica que la API diga el
      motor **y que sea el que se pidió**. Sin ella, las dos corridas podrían
      estar dando contra la misma base sin que nadie lo notara.
- [ ] `pruebas_humo/humo_los_dos_motores.py`: reinicia la API contra cada
      motor y corre el guion completo contra los dos.

**Verificar (criterio 12):** `python pruebas_humo/humo_los_dos_motores.py`
termina con los dos en verde.

## Fase 8 — El criterio 13 y el cierre
- [ ] Correr el `diff` contra la v2 y **comparar la lista con la del criterio
      13.b**. Si aparece un controlador o una vista, la versión no está
      terminada.
- [ ] Si algún archivo cambió por una razón que el criterio no contempla,
      **escribirla ahí** — no borrarla del criterio. Un criterio redactado
      para dar verde no sirve de nada.
- [ ] Correr el smoke test completo de [7_quickstart.md](7_quickstart.md) con
      los dos motores: los 13 criterios.
- [ ] Commit y tag `v3`.

**La v3 está TERMINADA.** Solo ahora se escribe la spec de la v4
([mapa de versiones](../0_mapa_versiones.md)).
