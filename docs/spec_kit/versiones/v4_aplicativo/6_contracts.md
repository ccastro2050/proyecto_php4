# Contratos — Versión 4: los seis de siempre, cinco nuevos y diez consultas

> **Versión 4** · En PHP puro no hay Swagger automático: **este documento ES
> el contrato publicado** (y el endpoint `/` lo enlaza).
>
> ## Lea esto antes de seguir
>
> **Este documento es, palabra por palabra, el de la versión 3** — que a su
> vez era el de la versión 2. Los endpoints, los códigos de estado, los
> formatos y las pantallas no se han movido en tres versiones.
>
> Y no es pereza: **es el resultado**. El contrato de una API describe lo que
> el sistema hace, no cómo lo hace. Si hubiera cambiado al agregar un motor,
> el motor se habría estado filtrando hasta la frontera HTTP.
>
> Lo único que cambió respecto de la v3 es que el campo `motor` de `GET /`
> ahora puede decir un valor más.
>
> | Parte | Base | Secciones |
> |---|---|---|
> | **La API** (lo que devuelve) | `http://localhost:8090` | §0 a §8 |
> | **El front** (lo que se ve) | `http://localhost:8088` | §9 y §10 |
>
> Las dos partes están en el mismo documento a propósito: la §10 es la tabla
> de traducción entre una y otra, y separarlas haría que se desincronizaran.

---

## 0. Convenciones

### 0.1 Las tres vías de envío, y los cinco verbos

| Vía | Dónde viaja | Ejemplo | Se usa para |
|---|---|---|---|
| Parámetro de **ruta** | en la URL, parte del camino | `/api/cliente/3` | Identificar UNA ficha |
| **Query string** | en la URL, después de `?` | `/api/persona?limite=3` | Opciones de la consulta |
| **Body** JSON | en el cuerpo de la petición | `{"nombre": "…"}` | Los datos de la ficha |

| Verbo | Semántica | Dónde se usa en la v2 |
|---|---|---|
| GET | Leer (nunca modifica) | listar · obtener · diagnóstico |
| POST | Crear | crear una ficha · **anular una factura** |
| PUT | **Reemplazar completo** | las 5 fichas · la factura entera |
| PATCH | **Actualizar parcial** | las 5 fichas — **la factura NO** |
| DELETE | Eliminar | todos |

### 0.2 La envoltura de los listados

```json
{ "tabla": "cliente", "limite": 1000, "total": 4, "datos": [ … ] }
```

Y **204 sin cuerpo** cuando la tabla está vacía. Un 204 no es un error: es la
respuesta correcta a «no hay filas».

### 0.3 El sobre de los errores

```json
{ "estado": 404, "mensaje": "Cliente no encontrado.", "detalle": "…" }
{ "estado": 422, "mensaje": "Datos inválidos.", "errores": [ "…", "…" ] }
```

| Origen | HTTP |
|---|---|
| Body inválido según la validación del controlador | **422** con `errores: [ … ]` |
| Regla de negocio (`limite ≤ 0`, PATCH con body vacío, id no numérico) | 400 |
| La ficha pedida no existe | 404 |
| **La base rechaza por integridad** (llave repetida, referencia rota, dependencias) | **409** ← nuevo en la v2 |
| El verbo no aplica a esa ruta | 405 |
| Error del motor o falla real | 500 |

**El 409 es lo nuevo, y la diferencia con el 500 es la que importa:** un 500
dice «se nos dañó algo»; un 409 dice «su petición está bien escrita, pero no
cabe en los datos que hay». En la v1 estos casos daban 500, y estaba bien
decidido para esa versión — el porqué del cambio está en
[4_research.md](4_research.md) D1.

### 0.4 Los campos de cada ficha, en el nombre EXACTO del JSON

Esta tabla es la que hace falta para reconstruir el sistema sin leer el
código: dice cómo se llama cada campo **en el JSON**, que no siempre es obvio.

| Recurso | Llave | La escribe | Campos del body |
|---|---|---|---|
| `producto` | `codigo` | el cliente | `nombre` texto(100) · `stock` entero ≥ 0 · `valorunitario` número ≥ 0 |
| `empresa` | `codigo` | el cliente | `nombre` texto(100) |
| `persona` | `codigo` | el cliente | `nombre` texto(100) · `email` texto(100) · `telefono` texto(20) |
| `cliente` | `id` | **la base** | `credito` número · `fkcodpersona` texto(10) · `fkcodempresa` texto(10) *(opcional, acepta `null`)* |
| `vendedor` | `id` | **la base** | `carnet` entero · `direccion` texto(100) · `fkcodpersona` texto(10) |
| `factura` | `numero` | **la base** | `fkidcliente` entero · `fkidvendedor` entero · `detalle` lista de `{codigo, cantidad}` |

**Lo que NINGÚN body lleva, aunque exista en la tabla:** `total` y `subtotal`
de las facturas (los calcula un trigger), `estado` (lo cambia la operación de
anular) y `fecha` (la pone la base). Si llegan, se ignoran.

---

## 1. `/api/producto` — Productos

El catálogo del que se surten las facturas.

```
GET /api/producto[?limite=N]
→ 200 { "tabla": "producto", "limite": 1000, "total": N, "datos": [ … ] }
→ 204 si la tabla está vacía
→ 400 si limite <= 0

GET /api/producto/3
→ 200 { "codigo": …, "nombre": …, "stock": …, "valorunitario": … }
→ 404 si no existe

POST /api/producto
body {
  "codigo": "3",
  "nombre": "Nombre de ejemplo",
  "stock": 1005,
  "valorunitario": 250000
}
→ 200 { "estado": 200, "mensaje": "Producto creado exitosamente." }
→ 422 con la lista de errores si el body no cumple

PUT /api/producto/3      body con TODOS los campos obligatorios
→ 200 { "estado": 200, "mensaje": "…", "filasAfectadas": 1 }
→ 422 si falta un obligatorio · 404 si la llave no existe

PATCH /api/producto/3    body con SOLO los campos a cambiar
→ 200 · 400 si el body viene vacío · 404 si no existe

DELETE /api/producto/3
→ 200 { "estado": 200, "mensaje": "…", "filasEliminadas": 1 }
→ 404 si no existe
```

- **409** si la llave ya existe.
- **409** al ELIMINAR, si otras fichas dependen de esto producto.

## 2. `/api/empresa` — Empresas

Las compañías a las que puede pertenecer un cliente.

```
GET /api/empresa[?limite=N]
→ 200 { "tabla": "empresa", "limite": 1000, "total": N, "datos": [ … ] }
→ 204 si la tabla está vacía
→ 400 si limite <= 0

GET /api/empresa/E001
→ 200 { "codigo": …, "nombre": … }
→ 404 si no existe

POST /api/empresa
body {
  "codigo": "E001",
  "nombre": "Nombre de ejemplo"
}
→ 200 { "estado": 200, "mensaje": "Empresa creada exitosamente." }
→ 422 con la lista de errores si el body no cumple

PUT /api/empresa/E001      body con TODOS los campos obligatorios
→ 200 { "estado": 200, "mensaje": "…", "filasAfectadas": 1 }
→ 422 si falta un obligatorio · 404 si la llave no existe

PATCH /api/empresa/E001    body con SOLO los campos a cambiar
→ 200 · 400 si el body viene vacío · 404 si no existe

DELETE /api/empresa/E001
→ 200 { "estado": 200, "mensaje": "…", "filasEliminadas": 1 }
→ 404 si no existe
```

- **409** si la llave ya existe.
- **409** al ELIMINAR, si otras fichas dependen de esta empresa.

## 3. `/api/persona` — Personas

Los datos de contacto de alguien. Una persona no es todavía ni cliente ni vendedor: es el dato de quién es.

```
GET /api/persona[?limite=N]
→ 200 { "tabla": "persona", "limite": 1000, "total": N, "datos": [ … ] }
→ 204 si la tabla está vacía
→ 400 si limite <= 0

GET /api/persona/P001
→ 200 { "codigo": …, "nombre": …, "email": …, "telefono": … }
→ 404 si no existe

POST /api/persona
body {
  "codigo": "P001",
  "nombre": "Nombre de ejemplo",
  "email": "quien@correo.com",
  "telefono": "3001234567"
}
→ 200 { "estado": 200, "mensaje": "Persona creada exitosamente." }
→ 422 con la lista de errores si el body no cumple

PUT /api/persona/P001      body con TODOS los campos obligatorios
→ 200 { "estado": 200, "mensaje": "…", "filasAfectadas": 1 }
→ 422 si falta un obligatorio · 404 si la llave no existe

PATCH /api/persona/P001    body con SOLO los campos a cambiar
→ 200 · 400 si el body viene vacío · 404 si no existe

DELETE /api/persona/P001
→ 200 { "estado": 200, "mensaje": "…", "filasEliminadas": 1 }
→ 404 si no existe
```

- **409** si la llave ya existe.
- **409** al ELIMINAR, si otras fichas dependen de esta persona.

## 4. `/api/cliente` — Clientes

Una persona que compra. Puede estar asociada a una empresa, o comprar a título propio.

**`id` es numérico.** `/api/cliente/abc` responde **400** —no 404—: no es que no se haya encontrado, es que eso no es un identificador.

```
GET /api/cliente[?limite=N]
→ 200 { "tabla": "cliente", "limite": 1000, "total": N, "datos": [ … ] }
→ 204 si la tabla está vacía
→ 400 si limite <= 0

GET /api/cliente/3
→ 200 { "id": …, "credito": …, "fkcodpersona": …, "fkcodempresa": … }
→ 404 si no existe

POST /api/cliente
body {
  "credito": 250000,
  "fkcodpersona": "P001",
  "fkcodempresa": "E001"
}
→ 200 { "estado": 200, "mensaje": "Cliente creado exitosamente.",
        "id": 7 }   ← **la llave que generó la base**
→ 422 con la lista de errores si el body no cumple

**El `id` NO va en el body**: lo genera la base. Si llega, la lista blanca lo bota — no es un error del cliente, es un campo que esta API no acepta de nadie.

PUT /api/cliente/3      body con TODOS los campos obligatorios
→ 200 { "estado": 200, "mensaje": "…", "filasAfectadas": 1 }
→ 422 si falta un obligatorio · 404 si la llave no existe

PATCH /api/cliente/3    body con SOLO los campos a cambiar
→ 200 · 400 si el body viene vacío · 404 si no existe

DELETE /api/cliente/3
→ 200 { "estado": 200, "mensaje": "…", "filasEliminadas": 1 }
→ 404 si no existe
```

- **409** si la llave ya existe.
- **409** si `fkcodpersona` apunta a una persona que no existe.
- **409** si `fkcodempresa` apunta a una empresa que no existe.
- **409** al ELIMINAR, si hay facturas que la usan.

## 5. `/api/vendedor` — Vendedores

Quien factura. También es una persona, vista desde otro papel.

**`id` es numérico.** `/api/vendedor/abc` responde **400** —no 404—: no es que no se haya encontrado, es que eso no es un identificador.

```
GET /api/vendedor[?limite=N]
→ 200 { "tabla": "vendedor", "limite": 1000, "total": N, "datos": [ … ] }
→ 204 si la tabla está vacía
→ 400 si limite <= 0

GET /api/vendedor/3
→ 200 { "id": …, "carnet": …, "direccion": …, "fkcodpersona": … }
→ 404 si no existe

POST /api/vendedor
body {
  "carnet": 1005,
  "direccion": "Calle 10 #5-33",
  "fkcodpersona": "P001"
}
→ 200 { "estado": 200, "mensaje": "Vendedor creado exitosamente.",
        "id": 7 }   ← **la llave que generó la base**
→ 422 con la lista de errores si el body no cumple

**El `id` NO va en el body**: lo genera la base. Si llega, la lista blanca lo bota — no es un error del cliente, es un campo que esta API no acepta de nadie.

PUT /api/vendedor/3      body con TODOS los campos obligatorios
→ 200 { "estado": 200, "mensaje": "…", "filasAfectadas": 1 }
→ 422 si falta un obligatorio · 404 si la llave no existe

PATCH /api/vendedor/3    body con SOLO los campos a cambiar
→ 200 · 400 si el body viene vacío · 404 si no existe

DELETE /api/vendedor/3
→ 200 { "estado": 200, "mensaje": "…", "filasEliminadas": 1 }
→ 404 si no existe
```

- **409** si la llave ya existe.
- **409** si `fkcodpersona` apunta a una persona que no existe.
- **409** al ELIMINAR, si hay facturas que la usan.

## 6. `/api/factura` — Facturas

**Éste es el recurso que no es un CRUD.** Compare esta sección con las cinco
de arriba antes de seguir: no tiene PATCH, tiene una operación de más, y sus
respuestas devuelven la factura entera en vez de un conteo de filas.

```
GET /api/factura
→ 200 { "tabla": "factura", "limite": null, "total": N, "datos": [ … ] }
→ 204 si no hay ninguna
```

**`limite` viene en `null` a propósito.** Este recurso **no acepta
`?limite`**: el procedimiento almacenado que lo resuelve no lo recibe.
Aceptar el parámetro y no usarlo habría sido peor que decir que no existe.

```
GET /api/factura/1
→ 200 {
      "numero": 1,
      "fecha": "2025-12-03T12:57:19",
      "total": 5000000,
      "estado": "activa",
      "fkidcliente": 1,   "nombreCliente":  "Ana Torres",
      "fkidvendedor": 1,  "nombreVendedor": "Carlos Pérez",
      "detalle": [
        { "codigoProducto": "PR001", "nombreProducto": "Laptop Lenovo IdeaPad",
          "cantidad": 2, "valorunitario": 2500000, "subtotal": 5000000 }
      ]
    }
→ 404 si ese número no existe
```

**`nombreCliente` y `nombreVendedor` no son columnas de `factura`**: la base
los trae con un JOIN hasta `persona`. Viajan en la respuesta porque una
pantalla que dijera «cliente 2» no le sirve a nadie.

```
POST /api/factura
body {
  "fkidcliente": 1,
  "fkidvendedor": 2,
  "detalle": [
    { "codigo": "PR001", "cantidad": 2 },
    { "codigo": "PR003", "cantidad": 1 }
  ]
}
→ 200 { "estado": 200, "mensaje": "Factura creada exitosamente.",
        "factura": { …la factura COMPLETA, igual que el GET… } }
→ 422 si el detalle viene vacío, o si un renglón está mal:
      "errores": [ "El renglón 2: la cantidad debe ser un entero mayor que cero." ]
→ 409 si el cliente, el vendedor o un producto no existen
→ 409 si no hay stock suficiente para algún renglón
```

**Se devuelve la factura completa, no solo su número.** Quien la creó
necesita ver el total y los subtotales que calculó el trigger — datos que no
mandó y no podía saber.

**El error de un renglón dice CUÁL renglón**, numerado desde 1. Un mensaje
que dijera «el detalle es inválido» no sirve para corregir nada.

```
PUT /api/factura/1        body igual al del POST
→ 200 { …, "factura": { … } }   reemplaza cliente, vendedor y TODO el detalle
→ 404 si el número no existe
→ 409 si la factura está anulada

PATCH /api/factura/1
→ 405 Método no permitido
```

**El 405 sale del enrutador sin que el número esté escrito en ninguna
parte**: simplemente no hay un `elseif` para PATCH en ese bloque. Y no es un
olvido — cambiar un renglón cambia el total y el stock, así que el detalle se
reemplaza entero o no se toca.

```
POST /api/factura/1/anular
→ 200 { "estado": 200, "mensaje": "Factura anulada exitosamente.",
        "resultado": { "numero_anulado": "1", "total_anulado": "5000000.00",
                       "productos_afectados": "1", "estado": "anulada" } }
→ 404 si el número no existe
→ 409 si ya estaba anulada
```

**Anular tiene su propia ruta, y no es un `PATCH` de `estado`.** Anular no es
escribir un campo: cierra la factura **y devuelve el stock**. Ofrecerlo como
un campo editable haría creer que se puede poner y quitar a voluntad.

Y la factura **no se borra**: conserva su número, su fecha y su detalle. Un
negocio no borra facturas; las anula, y queda el rastro.

```
DELETE /api/factura/1
→ 200 { "estado": 200, "mensaje": "Factura eliminada exitosamente.",
        "resultado": { "numero_eliminado": "1", … } }
→ 404 si no existe
```

Esto sí borra de verdad, la factura y su detalle (la llave foránea del detalle
tiene `ON DELETE CASCADE`). Existe porque en un curso hace falta poder
limpiar; en un sistema real esta operación probablemente no estaría.

## 7. `GET /` — Diagnóstico (LO ÚNICO QUE CAMBIÓ)

```
→ 200 { "mensaje": "API Facturas funcionando", "version": "v4",
        "motor": "mariadb",          ← "mariadb", "postgres" o "sqlserver"
        "recursos": ["/api/producto", "/api/empresa", "/api/persona",
                     "/api/cliente", "/api/vendedor", "/api/factura"],
        "contratos": "docs/spec_kit/versiones/v4_aplicativo/6_contracts.md" }
```

**Es el único sitio de toda la API donde se menciona el motor.** Ninguna otra
respuesta lo nombra ni cambia de forma según cuál sea — compruébelo pidiendo
cualquier recurso con los tres y comparando las respuestas.

Está publicado porque la versión promete que todo funciona igual contra los
dos motores, y una promesa así hay que poder comprobarla sin ir a leer el
archivo de configuración.

## 8. Los 405 que salen gratis

Ninguna ruta declara qué verbos NO admite. El 405 aparece solo, porque el
enrutador tiene un `else` al final de cada bloque:

| Petición | Respuesta | Por qué |
|---|---|---|
| `PUT /api/cliente` (la colección) | 405 | Reemplazar «todos los clientes» no significa nada |
| `PATCH /api/factura/1` | 405 | La factura no se actualiza por partes |
| `GET /api/factura/1/anular` | 405 | Anular cambia datos: no puede ser un GET |

Esto es lo contrario de escribir el número 405 en cada sitio donde podría
hacer falta. La lista de verbos que un recurso admite **es** la lista de sus
`elseif`.

---

## 9. Las pantallas del front (`http://localhost:8088`)

Aquí no hay verbos ni códigos de estado: **el usuario no sabe qué es un 409**.

| Dirección | Qué ve la persona | Qué le pide a la API |
|---|---|---|
| `GET /` | El inicio, con las seis secciones | *(nada)* |
| `GET /{recurso}` | La tabla del recurso | `GET /api/{recurso}?limite=1000` |
| `GET /{recurso}/nuevo` | El formulario vacío | los catálogos, si tiene llaves foráneas |
| `POST /{recurso}/nuevo` | Vuelve al listado con «Se agregó», o el formulario con lo escrito y los motivos | `POST /api/{recurso}` |
| `GET /{recurso}/{llave}/editar` | El formulario con la ficha | `GET /api/{recurso}/{llave}` |
| `POST …/editar` con **«Guardar la ficha completa»** | Vuelve al listado | `PUT` con **todos** los campos |
| `POST …/editar` con **«Guardar solo lo que cambié»** | Igual | `PATCH` con **solo lo diligenciado** |
| `POST /{recurso}/{llave}/eliminar` | Confirma y vuelve al listado | `DELETE /api/{recurso}/{llave}` |
| `GET /publico/…` | Las hojas de estilo | *(las sirve el propio front desde el disco)* |
| cualquier otra | «Esa página no existe», con el marco puesto | *(nada)* |

donde `{recurso}` es `productos`, `empresas`, `personas`, `clientes` o
`vendedores`.

### 9.1 Las facturas tienen sus propias pantallas

| Dirección | Qué ve la persona | Qué le pide a la API |
|---|---|---|
| `GET /facturas` | El listado, con estado y número de renglones | `GET /api/factura` |
| `GET /facturas/nueva` | Encabezado y **cinco renglones fijos** | los tres catálogos |
| `POST /facturas/nueva` | Va a la pantalla de la factura creada | `POST /api/factura` |
| **`GET /facturas/{n}`** | **La factura con su detalle y su total** — una pantalla que ningún otro recurso tiene | `GET /api/factura/{n}` |
| `GET /facturas/{n}/editar` | El mismo formulario, cargado | `GET /api/factura/{n}` |
| `POST /facturas/{n}/editar` | **UN solo botón** | `PUT /api/factura/{n}` |
| `POST /facturas/{n}/anular` | Confirma diciendo qué pasa con el stock | `POST /api/factura/{n}/anular` |
| `POST /facturas/{n}/eliminar` | Confirma advirtiendo que esto sí borra | `DELETE /api/factura/{n}` |

**Compare las dos tablas.** Las facturas no tienen «Guardar solo lo que
cambié» y sí tienen dos pantallas y una acción que las demás no. Si el front
tuviera una sola pantalla genérica parametrizada por recurso, no habría
podido expresar ninguna de las dos diferencias.

## 10. Cómo traduce el front lo que responde la API

Un solo archivo del front (`cliente_api.php`) conoce esta tabla. Ninguna
vista la conoce, y por eso el día que la API cambie el sobre se cambia en un
sitio.

| Lo que responde la API | Lo que hace el front | Lo que ve la persona |
|---|---|---|
| `200` con `{tabla, limite, total, datos}` | Se queda con `datos` | La tabla llena |
| **`204`** (tabla vacía) | `ok = true` con la lista **vacía** | «Todavía no hay …» y el botón de agregar |
| `200` de una escritura | `ok = true` | El aviso verde en el listado |
| `400` / `404` / `500` con `{estado, mensaje, detalle}` | Junta `mensaje` y `detalle` | El aviso rojo, en español |
| **`409`** con su `detalle` | **Lo mismo que un 400** | «No se puede eliminar la persona porque hay otras fichas que dependen de ella» |
| `422` con `{estado, mensaje, errores:[…]}` | Se queda con la lista `errores` | Los motivos, uno por línea |
| **Nada** (la API no responde) | `null`, que no es lo mismo que un error | «El servicio no está disponible» — y la pantalla sigue en pie |

**Fíjese en la fila del 409: el front NO la distingue de un 400.** Y es
deliberado. Para la persona que está usando la pantalla, lo que importa es el
texto —«hay otras fichas que dependen de ella»—, no el número. El número le
importa a quien programa contra la API, y está en la §0.3.

## 11. Estabilidad de este contrato: tres versiones sin moverse

La v2 dejó escrito que cambiar de motor no debería tocar ni una línea de este
documento. La v3 lo cumplió. **La v4 lo cumplió otra vez, y con un motor que
se parece mucho menos.**

Entre MariaDB y PostgreSQL, hasta el SQL era el mismo. Con SQL Server no lo
es: no existe `LIMIT`, la llave generada se lee de otra forma, y el driver ni
siquiera viene con PHP. Y aun así, este documento no se movió.

Compruébelo usted mismo, que es más convincente que leerlo:

```powershell
docker compose up -d --build
curl http://localhost:8090/api/factura/1 > mariadb.json

$env:MOTOR = "postgres"; docker compose up -d --no-deps --force-recreate api-facturas
curl http://localhost:8090/api/factura/1 > postgres.json

$env:MOTOR = "sqlserver"; docker compose up -d --no-deps --force-recreate api-facturas
curl http://localhost:8090/api/factura/1 > sqlserver.json

# Los tres archivos deben ser idénticos
Compare-Object (Get-Content mariadb.json) (Get-Content postgres.json)
Compare-Object (Get-Content mariadb.json) (Get-Content sqlserver.json)
```

**Y con eso la ruta de versiones se cierra.** No hay v5: un cuarto motor no
enseñaría nada que la tercera columna no haya mostrado ya.


---

# ANEXO A LA v4 — LOS CONTRATOS QUE SE AGREGARON

Todo lo de arriba sigue en pie: los seis recursos de la v2 responden lo mismo
por tercera vez, y **esa estabilidad es el resultado que la versión persigue**.
Lo que cambia es que ahora hay que identificarse para llegar a ellos, y que
hay once recursos y diez consultas en vez de seis recursos.

Los códigos y los sobres de este anexo salieron de peticiones de verdad
contra la API en marcha, no de leer el código.

## 0. La regla nueva que afecta a TODO lo anterior

**Desde la v4 cada petición lleva la cabecera `Authorization: Bearer <token>`.**
Hay exactamente dos rutas abiertas, y cada una tiene su razón:

| Ruta abierta | Por qué |
|---|---|
| `GET /` | Es el diagnóstico. Un *healthcheck* que necesita credenciales no sirve de healthcheck: Docker lo usa para saber si el servicio está vivo |
| `POST /api/sesion` | Es la puerta. No puede exigir el token que ella misma entrega |

Cualquier otra ruta sin token responde **401** con el sobre de siempre. Con
token válido pero sin permiso para ese recurso, **403**. La diferencia
importa: 401 es «no sé quién es usted», 403 es «sé quién es y no le toca».

```
401  {"estado":401,"mensaje":"No hay una sesion valida.",
      "detalle":"Falta la cabecera Authorization: Bearer <token>."}

403  {"estado":403,"mensaje":"Su rol no tiene permiso para esta operacion.",
      "detalle":"El usuario vendedor1@correo.com no alcanza la ruta /usuario."}
```

## 1. `POST /api/sesion` — entrar

**Petición**

```json
{"email": "admin@correo.com", "contrasena": "admin123"}
```

**Respuesta 200**

```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJhZG1pbkBjb3JyZW8uY29tIiwi...",
  "email": "admin@correo.com",
  "roles": ["Administrador"],
  "expira": "2026-10-10T19:21:00+00:00"
}
```

**Errores**

| Código | Cuándo |
|---|---|
| 401 | El correo no existe **o** la contraseña no coincide. **Un solo mensaje para los dos casos**: decir «ese correo no existe» le confirma a quien prueba al azar que los demás sí existen |
| 422 | Falta el correo o la contraseña en el cuerpo |

El token es un **JWT HS256 firmado, no cifrado**: su contenido se lee con
cualquier decodificador de base64. Lo que nadie puede es cambiarlo sin
romper la firma. Por eso dentro no hay ningún dato secreto.

## 2. Los otros tres verbos de la sesión

| Verbo y ruta | Qué hace | Exige token |
|---|---|---|
| `GET /api/sesion` | Dice quién está dentro: `{"email": ..., "roles": [...]}`. El correo sale **del token**, no de la URL ni del cuerpo | Sí |
| `DELETE /api/sesion` | Salir. Es cortesía, y hay que saber por qué: **un JWT firmado no se puede apagar desde el servidor** sin una lista negra. Lo que de verdad cierra la sesión es que el cliente tire el token | Sí |
| `POST /api/sesion/renovar` | Devuelve un token nuevo a quien ya tiene uno válido | Sí |

## 3. `GET /api/permisos/mios` — las rutas de quien pregunta

```json
{"consulta": "mis rutas", "email": "vendedor1@correo.com",
 "roles": ["Cajero", "Vendedor"], "total": 3,
 "datos": ["/cliente", "/factura", "/home"]}
```

Existe **para que el front arme su menú** y no para decidir nada: el front
usa esta lista para no ofrecer lo que va a ser rechazado. La decisión de
dejar pasar la sigue tomando la API en cada petición.

## 4. `/api/rol` y `/api/ruta` — los cinco verbos

Los dos se comportan como los seis recursos de la v2, con la llave numérica
que genera la base de datos:

| Verbo | Ruta | Respuesta |
|---|---|---|
| GET | `/api/rol` | `{"tabla":"rol","limite":N,"total":N,"datos":[{"id":1,"nombre":"Administrador"}]}` |
| GET | `/api/rol/{id}` | la ficha, o 404 |
| POST | `/api/rol` | **201** con la ficha creada |
| PUT | `/api/rol/{id}` | 200 |
| PATCH | `/api/rol/{id}` | 200 |
| DELETE | `/api/rol/{id}` | 200, o **409** si hay filas que dependen |

`/api/ruta` es igual, con `{"id":1,"ruta":"/home","descripcion":"..."}`.

Y una advertencia de vocabulario que ha costado tiempo: **«ruta» aquí es una
interfaz del sistema a la que se da permiso** —`/factura`—, no la dirección
de una pantalla del front —`/facturas`—. El singular es el permiso; el
plural, la pantalla.

## 5. `/api/usuario` — y lo que NO devuelve

```json
{"tabla":"usuario","limite":2,"total":2,
 "datos":[{"email":"admin@correo.com"},{"email":"jefe@correo.com"}]}
```

**La columna de la contraseña existe en la tabla y no sale de ahí.** No es
que el front la esconda: la API no la devuelve, ni en la lista ni en la
ficha. Compruébelo en Postman.

| Verbo | Qué hace |
|---|---|
| POST | Crea la cuenta. La contraseña viaja **en claro** por el cuerpo y la API la hashea con bcrypt costo 12 antes de guardarla |
| PUT | Reemplaza la ficha: correo **y** contraseña |
| PATCH | Cambia solo lo que llegue —en la práctica, la contraseña— |
| DELETE | Borra la cuenta |

Hay además `POST /api/usuario/verificar-contrasena`, que dice si una
contraseña coincide **sin** entregar un token: sirve para pedir confirmación
antes de una operación delicada.

**¿Por qué no se hashea en el front?** Porque entonces el hash **sería** la
contraseña —quien lo copiara entraría— y la API no podría imponer su costo.
El hash se hace donde se guarda.

## 6. `/api/rutarol` y `/api/rol-usuario` — los dos puentes, con DOS verbos

Y aquí el contrato dice algo que hay que leer dos veces: **a estos dos
recursos les faltan el PUT y el PATCH a propósito.**

| Verbo | Ruta | Qué hace |
|---|---|---|
| GET | `/api/rutarol` | la lista, con los nombres resueltos: `{"fkidruta":4,"ruta":"/cliente","fkidrol":1,"rol":"Administrador"}` |
| GET | `/api/rutarol/ruta/{id}` | los roles que alcanzan esa ruta |
| GET | `/api/rutarol/rol/{id}` | las rutas que alcanza ese rol |
| POST | `/api/rutarol` | da el permiso. **409** si la pareja ya existe |
| DELETE | `/api/rutarol/{idRuta}/{idRol}` | lo quita. **La pareja entera es la llave**, y va en la dirección |

En el código los dos verbos que faltan **están escritos y comentados**, con
la razón al lado: una fila de estas tablas es una pareja de llaves foráneas
sin ningún dato propio, así que «editarla» no significa nada —cambiarle el
rol es quitar un permiso y dar otro, que son dos operaciones con dos
efectos—. Dejarlos comentados en vez de borrarlos es deliberado: así la
ausencia es visible y razonada, no un olvido.

`/api/rol-usuario` es igual, con `fkemail` en lugar de `fkidruta`:
`DELETE /api/rol-usuario/{email}/{idRol}`.

## 7. `/api/usuario-con-roles` — el recurso de la v4

El único que escribe en dos tablas con una sola petición.

| Verbo | Ruta | Cuerpo / respuesta |
|---|---|---|
| GET | `/api/usuario-con-roles` | `{"tabla":"usuario_con_roles","total":8,"datos":[{"email":"...","roles":[{"idrol":1,"nombre":"Administrador"}]}]}` |
| GET | `/api/usuario-con-roles/{email}` | la ficha con sus roles, o 404 |
| POST | `/api/usuario-con-roles` | `{"email":"...","contrasena":"...","roles":[2,3]}` → **201** |
| PUT | `/api/usuario-con-roles/{email}` | `{"contrasena":"...","roles":[3,4]}` → 200. Contraseña vacía = no se toca |
| PATCH | `/api/usuario-con-roles/{email}` | solo lo que llegue → 200 |
| DELETE | `/api/usuario-con-roles/{email}` | borra el usuario **y** sus roles → 200 |

| Código | Cuándo | Medido |
|---|---|---|
| 201 | creado con sus roles | `{"estado":201,"mensaje":"Usuario creado exitosamente.","datos":{...}}` |
| 409 | el correo ya existe, **o** un rol de la lista no existe | las dos cosas chocan con la integridad |
| 422 | la contraseña no llega a 6 caracteres, o `roles` no es una lista |
| 404 | el correo no existe (GET y DELETE) |

**Y la garantía que hay que probar en la sustentación:** mande un rol que no
exista y mire la base de datos después. No queda el usuario creado sin roles,
ni el usuario sin los roles que tenía. El procedimiento lo deshace todo —y no
siempre fue así: ver el tropiezo 5 de `4_research.md`—.

## 8. `/api/consultas` — las diez

Todas **GET**, todas con el permiso `/home`, y el sobre es otro:

```json
{"consulta": "ventas-por-producto", "total": 8, "datos": [ ... ]}
```

`tabla` sería falso —ninguna sale de una sola— y `limite` no aplica: un
reporte se devuelve entero o no sirve para sumar.

| # | Ruta | Columnas que devuelve |
|---|---|---|
| 1 | `/api/consultas/ventas-por-producto` | `codigo, nombre, unidades, ingreso, facturas, clientes` |
| 2 | `/api/consultas/ventas-por-cliente` | `id, cliente, email, facturas, comprado, unidades` |
| 3 | `/api/consultas/ventas-por-vendedor` | `id, vendedor, carnet, facturas, vendido` |
| 4 | `/api/consultas/ventas-por-empresa` | `codigo, empresa, clientes, facturas, facturado` |
| 5 | `/api/consultas/ticket-por-vendedor` | `vendedor, facturas, total, ticket_promedio` |
| 6 | `/api/consultas/productos-sin-vender` | `codigo, nombre, stock, valorunitario` |
| 7 | `/api/consultas/anulaciones-por-cliente` | `cliente, anuladas, valor_anulado` |
| 8 | `/api/consultas/alcance-de-usuarios` | `email, roles, interfaces, sus_roles` |
| 9 | `/api/consultas/interfaces-sin-usuarios` | `id, ruta, descripcion, roles_con_acceso, usuarios_con_acceso` |
| 10 | `/api/consultas/credito-contra-consumo` | `cliente, empresa, credito, consumido, disponible` |

**Los nombres de esas columnas son parte del contrato**, y no los vigila
ningún tipo: si el SQL renombra una, el tablero cambia de encabezado y nadie
se enteraría. Por eso existe `pruebas/prueba_consultas_tres_motores.php`, que
los comprueba uno por uno en los tres motores.

Y `GET /api/consultas` devuelve el índice de las diez
—`{"nombre": ..., "titulo": ...}`— para que el front no las tenga escritas a
mano: dos listas que hay que acordarse de cambiar a la vez son una lista de
más.

**Cero filas no es un error.** Tres de las diez preguntan por lo que *no*
existe —productos que nunca se vendieron, facturas anuladas, rutas a las que
no llega nadie— y con los datos sembrados responden `total: 0` con toda la
razón.

Un POST, PUT, PATCH o DELETE a cualquiera de las diez responde **405**: una
consulta no tiene nada que crear ni que borrar.

## 9. Las pantallas nuevas del front (`http://localhost:8088`)

| Pantalla | Qué hace |
|---|---|
| `/entrar` | La única que se sirve sin sesión. Las tres cuentas del curso están impresas en ella |
| `/salir` | Solo **POST**: un GET que cierra la sesión lo dispara cualquier cosa que cargue una dirección |
| `/tablero` · `/tablero/{consulta}` | El índice de las diez y una pantalla por consulta |
| `/roles` · `/rutas` · `/usuarios` | Lista y formulario, el patrón de siempre |
| `/permisos` · `/usuarios-roles` | Los dos puentes: **ver, dar y quitar**. Sin editar, por lo que dice la sección 6 |
| `/usuarios-con-roles` | Lista con los roles en etiquetas, y un formulario con **casillas** |

Y tres reglas del front que son del contrato, no del gusto:

1. **sin sesión, todo redirige a `/entrar`** —y eso no protege los datos: los
   protege la API—;
2. el menú, las tarjetas del inicio y los botones se arman con
   `GET /api/permisos/mios`: **esconder un enlace no es control de acceso**,
   es no ofrecer lo que va a responder 403;
3. la contraseña **no vuelve nunca a la pantalla**, ni al repintar un
   formulario con error ni en un campo oculto.
