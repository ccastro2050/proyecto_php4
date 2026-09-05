# Tareas — Versión 2: seis recursos, integridad y maestro-detalle

> **Versión 2** · El orden de construcción, partiendo del estado que dejó la
> v1. Cada fase termina en algo **verificable**. Requisitos:
> [2_spec.md](2_spec.md) · técnica: [3_plan.md](3_plan.md) · contratos:
> [6_contracts.md](6_contracts.md) · validación final:
> [7_quickstart.md](7_quickstart.md).
>
> **El orden no es casual.** Se construye de abajo hacia arriba en el mapa de
> dependencias ([5_data_model.md](5_data_model.md) §2): primero las tablas de
> las que otras dependen, después las que dependen de alguien, y de últimas
> las facturas. Al revés no se puede probar nada: un cliente necesita una
> persona que exista.

---

## Fase 0 — Partir de la v1 y cambiar los puertos
- [ ] Partir del código de la v1 funcionando (o del repositorio
      `proyecto_php1`), que ya trae `producto` de punta a punta.
- [ ] Cambiar los puertos a los de esta versión: front **8024**, API **8026**,
      phpMyAdmin **8102**, MariaDB **13327** — en `docker-compose.yml`, en los
      dos `Dockerfile` (`EXPOSE` y `CMD`) y en el valor por defecto de
      `URL_API` en `front_php/cliente_api.php`.

**Verificar:** `docker compose up -d --build` y `http://localhost:8024/productos`
muestra los 8 productos. **Si esto no pasa, no siga**: lo que sigue se
construye encima.

## Fase 1 — La pieza que hace posible todo lo demás: traducir el «no» de la base
- [ ] `excepciones/ConflictoDeIntegridadExcepcion.php`.
- [ ] `repositorios/errores_de_integridad.php` con
      `traducirErrorDeIntegridad()`: reconoce **1062** (llave duplicada),
      **1452** (apunta a algo que no existe) y **1451** (otros dependen de
      esto), y deja pasar lo que no reconoce ([3_plan.md](3_plan.md) §4.1).

> Se hace PRIMERO, aunque todavía no haya quién la use, porque es de lo que
> depende que los cinco recursos siguientes salgan iguales. Si se deja para
> el final, cada repositorio inventa su propia forma de reportar el conflicto.

**Verificar:** `php -l` pasa. La prueba de verdad llega en la fase 3.

## Fase 2 — Las dos tablas que no dependen de nadie: `empresa` y `persona`
- [ ] Modelos `Empresa.php` y `Persona.php` (propiedades privadas, getters,
      setters menos la llave, `toArray()`).
- [ ] Interfaces `IRepositorio*` e `IServicio*`.
- [ ] `Repositorio*MariaDB` — igual que el de producto, **más el try/catch que
      llama a `traducirErrorDeIntegridad`** en las tres escrituras.
- [ ] `Servicio*` y `Controlador*`.
- [ ] Registrarlos en `ensamblador.php` (una función por recurso) y en el
      enrutador `index.php` (un bloque por recurso).

**Verificar:** `GET /api/empresa` devuelve las 3, y **crear una empresa con un
código que ya existe responde 409** con su mensaje en español — no 500.

## Fase 3 — Las dos con llave foránea y llave generada: `cliente` y `vendedor`
- [ ] Modelos con la llave como **`?int`**: una ficha sin guardar no tiene id,
      y el tipo lo dice ([3_plan.md](3_plan.md) §4.2).
- [ ] Repositorios cuyo `crear()` devuelve **`(int) $conexion->lastInsertId()`**.
- [ ] Servicios cuyo `crear()` devuelve ese id hacia arriba.
- [ ] Controladores que **no validan la llave al crear** (no viene) y que **la
      devuelven en la respuesta**.
- [ ] El `id` del body se ignora con la lista blanca (§4.3).

**Verificar (criterios 4 y 5):**
- `POST /api/cliente` sin `id` responde con el `id` que generó la base, y
  `GET /api/cliente/{ese id}` lo trae;
- `GET /api/cliente/abc` responde **400**, no 404;
- crear un cliente con `fkcodpersona` inexistente responde **409**;
- eliminar una persona que ya es cliente responde **409**.

> Éste es el punto donde la fase 1 se paga sola: los cuatro comportamientos
> salen sin escribir un solo `if` sobre llaves foráneas.

## Fase 4 — La factura: maestro-detalle con procedimientos almacenados
- [ ] Modelos `Factura.php` (el encabezado, **sin setters** — ver §4.2 del
      plan) y `LineaFactura.php` (un renglón).
- [ ] `IRepositorioFactura`: **no son los cinco métodos de siempre**
      (`obtenerTodas`, `obtenerPorNumero`, `crear`, `reemplazar`, `anular`,
      `eliminar`) — [4_research.md](4_research.md) D3.
- [ ] `RepositorioFacturaMariaDB` con `llamarProcedimiento()`:
      el `CALL … @resultado`, el `closeCursor()`, el
      `SELECT @resultado`, el caso de **cero parámetros de entrada**, y
      `interpretarRechazo()` para separar el 404 del 409
      ([3_plan.md](3_plan.md) §4.4 y §4.5).
- [ ] `ServicioFactura` — el más corto del proyecto, y está bien (D9).
- [ ] `ControladorFactura` con la validación de **dos niveles**: que el
      detalle sea una lista con algo adentro, y cada renglón por separado
      diciendo **cuál** falla.
- [ ] El bloque del enrutador: **sin PATCH**, y con `/anular` **antes** de la
      ruta de `{numero}` (si no, el número quedaría `"3/anular"`).

**Verificar (criterios 6, 7 y 8):** el ciclo completo de
[7_quickstart.md](7_quickstart.md) §2, pasos 6 a 8 — crear con dos renglones y
comprobar el total y el stock, el 405 del PATCH, y anular con su 409 al
repetir.

## Fase 5 — Las dos correcciones al script de la base
- [ ] Agregar el `estado` al `SELECT` de
      `sp_listar_facturas_y_productosporfactura`.
- [ ] Agregar al `sp_actualizar_factura_y_productosporfactura` la guarda que
      impide modificar una factura anulada (la misma que ya tiene
      `sp_anular_factura`).
- [ ] `docker compose down -v && docker compose up -d --build` para que el
      script vuelva a ejecutarse.

> Las dos están explicadas con su motivo en [4_research.md](4_research.md) D6.
> Van en su propia fase, y no escondidas dentro de otra, porque **tocar un
> artefacto dado es una decisión** y tiene que verse.

**Verificar:** el listado de facturas trae `estado`, y un `PUT` sobre una
factura anulada responde 409.

## Fase 6 — El front: las cinco secciones nuevas
- [ ] `cliente_api.php`: una función por operación, con el nombre del recurso
      adentro. **Las facturas no llevan `actualizar_factura`** (no hay PATCH)
      **y sí llevan `anular_factura`**.
- [ ] `index.php`: un bloque de rutas por recurso, y el de facturas distinto.
      Los ayudantes `catalogos_de_cliente()`, `catalogos_de_vendedor()` y
      `catalogos_de_factura()` para los desplegables.
- [ ] Las vistas: `{recurso}_lista.php` y `{recurso}_formulario.php` para los
      cinco de ficha.
- [ ] `facturas_lista.php`, `facturas_formulario.php` (cinco renglones fijos,
      **un solo botón**) y **`facturas_detalle.php`**, la pantalla que ningún
      otro recurso tiene.
- [ ] El menú de `plantilla.php` con las seis entradas, y el `inicio.php` con
      sus tarjetas.

**Verificar (criterios 10 a 13):** las nueve pantallas responden, se ven con
sus estilos, y el recorrido a mano de [7_quickstart.md](7_quickstart.md) §3.2
—incluido el intento de borrar a Ana Torres— funciona desde el navegador.

## Fase 7 — Las pruebas
- [ ] Ampliar `pruebas/prueba_capas.php` para que cubra los servicios nuevos
      con repositorios falsos en memoria (criterio 9).
- [ ] `pruebas_humo/humo_front.py`: las nueve pantallas, los estáticos, la
      jerga, el ciclo de una ficha, **los dos rechazos de integridad**,
      **la factura de dos renglones con su total y su stock**, **anular**, y
      la prueba de apagar la API (criterio 14).

**Verificar:** `python pruebas_humo/humo_front.py` termina en verde.

## Fase 8 — Cierre de la versión
- [ ] Correr el smoke test completo de [7_quickstart.md](7_quickstart.md) —
      los **14** criterios de aceptación de [2_spec.md](2_spec.md) §5.
- [ ] Commit y tag `v2`.

**La v2 está TERMINADA.** Solo ahora se escribe la spec de la v3
([mapa de versiones](../0_mapa_versiones.md)).
