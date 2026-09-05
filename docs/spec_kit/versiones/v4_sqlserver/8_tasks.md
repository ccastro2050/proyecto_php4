# Tareas — Versión 4: el tercer motor

> **Versión 4** · El orden de construcción, partiendo del estado que dejó la
> v3. Requisitos: [2_spec.md](2_spec.md) · técnica: [3_plan.md](3_plan.md) ·
> validación: [7_quickstart.md](7_quickstart.md).
>
> **Estas fases son casi las mismas de la v3, con otro motor.** Que la lista
> se pueda reutilizar es, en sí mismo, parte del resultado: la v3 no resolvió
> su caso, dejó un procedimiento.

---

## Fase 0 — Partir de la v3 y cambiar los puertos
- [ ] Partir del código de la v3 funcionando (repositorio `proyecto_php3`).
- [ ] Cambiar los puertos: front **8088**, API **8090**, phpMyAdmin **8104**,
      MariaDB **13329**, PostgreSQL **15465** — en `docker-compose.yml`, los
      dos `Dockerfile`, el `URL_API` por defecto, el `ensamblador.php` y los
      guiones de prueba.

**Verificar:** `docker compose up -d --build` y todo funciona igual que la v3,
contra los dos motores. **Si esto no pasa, no siga.**

## Fase 1 — La tercera base
- [ ] Copiar el script **provisto** a `db/sqlserver/bdfacturas.sql` y su
      `init.sh`.
- [ ] Agregarle **la guarda que impide modificar una factura anulada**, la
      misma que la v2 le puso a MariaDB y la v3 a PostgreSQL. En T-SQL se
      escribe con `THROW`.
- [ ] Agregar al compose los servicios `sqlserver` (con `ACCEPT_EULA`, la
      clave de política y un healthcheck con `sqlcmd`) y `sqlserver-init`
      (con `restart: "no"` y `depends_on … service_healthy`).

**Verificar:** `docker compose up -d sqlserver sqlserver-init` y
`docker compose logs sqlserver-init` termina con
«SQL Server inicializado correctamente». Repítalo: la segunda vez no debe
hacer nada.

> **Ésta es la primera diferencia de la versión, y aparece antes de escribir
> una línea de PHP.** Las imágenes de MariaDB y PostgreSQL ejecutan solas los
> `.sql` que encuentren; la de SQL Server no tiene ese mecanismo.

## Fase 2 — El driver, en la misma imagen
- [ ] `api_facturas/Dockerfile`: agregar el repositorio de paquetes de
      Microsoft, instalar `msodbcsql18` con `ACCEPT_EULA=Y`, y compilar
      `pdo_sqlsrv` con PECL.

**Verificar:**
`docker compose exec api-facturas php -r "var_dump(PDO::getAvailableDrivers());"`
lista `mysql`, `pgsql` **y `sqlsrv`**.

> Son diez líneas para lo que en los otros dos motores fue una. No se
> esconde: los tres motores no se instalan igual, y eso es información.

## Fase 3 — El traductor de errores de SQL Server
- [ ] `repositorios/errores_de_integridad_sqlserver.php`: `2627`/`2601` para
      llave duplicada y `547` para las llaves foráneas — **mirando el mensaje**
      para saber en qué sentido falló, porque SQL Server usa un solo código
      para los dos.
- [ ] **No tocar `conflictos.php`.** Las frases del usuario son las mismas.

**Verificar:** `php -l` pasa, y las tres frases siguen viniendo del archivo
compartido.

## Fase 4 — Los seis repositorios de SQL Server
- [ ] Los cinco de ficha, cumpliendo **las mismas interfaces**, que **no se
      tocan**. Lo que cambia respecto de los otros motores:
      `OFFSET/FETCH` en vez de `LIMIT`, `OUTPUT INSERTED` para la llave
      generada, y **sin** `ATTR_EMULATE_PREPARES` (el driver no lo admite).
- [ ] `RepositorioFacturaSqlServer`, con el bloque de T-SQL que declara la
      variable de salida y la pide con un `SELECT` — **el `bindParam` no
      funciona** con `NVARCHAR(MAX)` ([3_plan.md](3_plan.md) §4.3). Y con
      `SET NOCOUNT ON`, sin el cual el `fetch()` devuelve cualquier cosa.
- [ ] Recortar los **siete** decimales de la fecha, para que el contrato
      entregue el mismo formato con los tres motores.

**Verificar:** `php -l` pasa en los seis y ninguna interfaz cambió.

> **Si alguna interfaz tuvo que cambiar, pare.** La v3 estaba describiendo a
> dos motores y no al negocio, y eso se arregla allá.

## Fase 5 — La fábrica, con una rama más
- [ ] `ensamblador.php`: `motorActivo()` acepta `sqlserver`,
      `datosDeConexion()` devuelve **sus credenciales propias** (`sa` y su
      clave), y las seis funciones pasan de ternario a `match`.
- [ ] El compose: el tercer DSN y el tercer `depends_on`.

**Verificar (criterios 1 y 2):** con `$env:MOTOR = "sqlserver"` y
`--force-recreate`, `GET /` responde `"motor": "sqlserver"` — sin haber
editado ningún archivo.

## Fase 6 — El nombre bonito en la pantalla
- [ ] `front_php/vistas/plantilla.php`: la tabla de nombres, para que el pie
      diga **«SQL Server»** y no `sqlserver`.

**Verificar (criterios 10 y 11):** el pie dice el nombre bonito, y ese nombre
aparece **una sola vez** en cada pantalla.

## Fase 7 — La prueba de los tres
- [ ] `humo_front.py`: un nombre más en la tabla de etiquetas. Nada más.
- [ ] `pruebas_humo/humo_los_tres_motores.py`: el de la v3 con un motor más
      en la lista.

**Verificar (criterio 12):** termina con los tres en verde.

> Que estos dos archivos casi no cambien es el resultado de la versión. Si
> hubiera habido que reescribir las pruebas para acomodar el tercer motor,
> querría decir que el motor se está filtrando hasta la pantalla.

## Fase 8 — El criterio 13 y el cierre
- [ ] Correr el `diff` contra la v3 y compararlo con el criterio 13.b.
- [ ] Correr `docker compose down -v && docker compose up -d --build` y
      comprobar que el sistema arranca **desde cero** de una sola vez.
- [ ] Correr el smoke test completo con los tres motores: los 13 criterios.
- [ ] Commit y tag `v4`.

**La v4 está TERMINADA, y con ella la ruta de versiones.** No hay v5 — el
porqué está en [4_research.md](4_research.md) D8.
