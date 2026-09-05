# Contratos — Versión 2: los endpoints y las pantallas

> **Versión 2** · En PHP puro no hay Swagger automático: **este documento ES
> el contrato publicado** (y el endpoint `/` lo enlaza).
>
> | Parte | Base | Secciones |
> |---|---|---|
> | **La API** (lo que devuelve) | `http://localhost:8026` | §0 a §8 |
> | **El front** (lo que se ve) | `http://localhost:8024` | §9 y §10 |
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

## 7. `GET /` — Diagnóstico

```
→ 200 { "mensaje": "API Facturas funcionando", "version": "v2",
        "recursos": ["/api/producto", "/api/empresa", "/api/persona",
                     "/api/cliente", "/api/vendedor", "/api/factura"],
        "contratos": "docs/spec_kit/versiones/v2_mas_tablas/6_contracts.md" }
```

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

## 9. Las pantallas del front (`http://localhost:8024`)

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

## 11. Estabilidad de este contrato

Los endpoints de `producto` **no cambiaron** respecto de la v1, y eso es parte
de los criterios de aceptación: una versión nueva no rompe lo que la anterior
prometió.

Y hay una consecuencia que la v3 va a comprobar: **cambiar de motor no debería
tocar ni una línea de este documento.** Si la v3 tiene que cambiar un contrato
para que PostgreSQL funcione, es que el motor se había filtrado hacia arriba.
