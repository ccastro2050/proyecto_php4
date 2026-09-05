# Contratos — Versión 1: los 7 endpoints y las 5 pantallas

> **Versión 1** · En PHP puro no hay Swagger automático: **este documento ES
> el contrato publicado** (y el endpoint `/` lo enlaza).
>
> | Parte | Base | Secciones |
> |---|---|---|
> | **La API** (lo que devuelve) | `http://localhost:8022` | §0 a §8 |
> | **El front** (lo que se ve) | `http://localhost:8020` | §9 y §10 |
>
> Las dos partes están en el mismo documento a propósito: la §9 es la
> **tabla de traducción** entre una y otra, y separarlas haría que se
> desincronizaran.

---

## 0. Convenciones (y el HTTP que la v1 enseña)

La v1 usa **los cinco verbos** y las **tres vías de envío de datos**:

| Vía | Dónde viaja | Ejemplo | Se usa para |
|---|---|---|---|
| Parámetro de **ruta** | en la URL, parte del camino | `/api/producto/PR001` | Identificar UN recurso |
| **Query string** | en la URL, después de `?` | `/api/producto?limite=3` | Opciones de la consulta |
| **Body** JSON | en el cuerpo de la petición | `{"nombre": "…", "stock": 5}` | Los datos del recurso (POST/PUT/PATCH) |

| Verbo | Semántica | Endpoint en v1 |
|---|---|---|
| GET | Leer (nunca modifica) | listar · obtener por código · diagnóstico |
| POST | Crear un recurso nuevo | crear producto |
| PUT | **Reemplazar completo** (todos los campos) | reemplazar producto |
| PATCH | **Actualizar parcial** (solo los enviados) | actualizar campos sueltos |
| DELETE | Eliminar | eliminar producto |

- Todas las respuestas son JSON (`Content-Type: application/json`).
- Errores SIEMPRE con esta envoltura:

```json
{ "estado": 404, "mensaje": "Producto no encontrado.", "detalle": "..." }
```

| Origen | HTTP |
|---|---|
| Body inválido según la **validación del controlador** | **422** con `errores: [ "...", ... ]` |
| Regla de negocio (`limite ≤ 0`, PATCH con body vacío) | 400 |
| Código inexistente (`NoEncontradoExcepcion`) | 404 |
| Error del motor (PK duplicada, BD caída — `PDOException`) | 500 con el mensaje en `detalle` |

## 1. `GET /api/producto` — Listar (query string)

Query param opcional: `limite` (entero > 0, default 1000).

```
GET /api/producto
→ 200 { "tabla": "producto", "limite": 1000, "total": 8,
        "datos": [ { "codigo": "PR001", "nombre": "Laptop Lenovo IdeaPad",
                     "stock": 17, "valorunitario": 2500000 }, … ] }

GET /api/producto?limite=3
→ 200 con exactamente 3 productos (total: 3)
→ 400 si limite ≤ 0
→ 204 (cuerpo vacío) si no hay productos
```

## 2. `GET /api/producto/{codigo}` — Obtener uno (parámetro de ruta)

```
GET /api/producto/PR001
→ 200 { "codigo": "PR001", "nombre": "Laptop Lenovo IdeaPad",
        "stock": 17, "valorunitario": 2500000 }
→ 404 { "estado": 404, "mensaje": "Producto no encontrado.",
        "detalle": "No existe un producto con codigo = PR999" }
```

## 3. `POST /api/producto` — Crear (body completo)

Body (todos obligatorios; los valida el controlador):

```
POST /api/producto
body { "codigo": "PR009", "nombre": "Webcam Logitech", "stock": 5, "valorunitario": 120000 }
→ 200 { "estado": 200, "mensaje": "Producto creado exitosamente." }
→ 422 { "estado": 422, "mensaje": "Datos inválidos.",
        "errores": [ "El campo stock debe ser un entero mayor o igual a 0." ] }
→ 500 si el código ya existe (error del motor en detalle)
```

## 4. `PUT /api/producto/{codigo}` — Reemplazar completo

Body: `nombre`, `stock`, `valorunitario` — **todos obligatorios** (el código
va en la ruta y no cambia). Valida `validarReemplazo`.

```
PUT /api/producto/PR009
body { "nombre": "Webcam Logitech C920", "stock": 10, "valorunitario": 150000 }
→ 200 { "estado": 200, "mensaje": "Producto reemplazado exitosamente.",
        "filasAfectadas": 1 }
→ 422 si falta CUALQUIER campo — PUT reemplaza el recurso entero,
      no existe "dejar el que estaba"
→ 404 si el código no existe
```

## 5. `PATCH /api/producto/{codigo}` — Actualizar parcial

Body: los mismos campos pero **todos opcionales** — solo se modifican los
enviados (cada uno validado si llega, con `validarParcial`).

```
PATCH /api/producto/PR009      body { "stock": 7 }
→ 200 { "estado": 200, "mensaje": "Producto actualizado exitosamente.",
        "filasAfectadas": 1 }
→ 400 si el body viene vacío (no hay nada que actualizar)
→ 422 si algún campo enviado viola la validación (stock < 0)
→ 404 si el código no existe
```

**El contraste didáctico:** el body `{ "stock": 7 }` en PATCH es 200; ese mismo
body en PUT es 422 (le faltan `nombre` y `valorunitario`).

## 6. `DELETE /api/producto/{codigo}` — Eliminar

```
DELETE /api/producto/PR009
→ 200 { "estado": 200, "mensaje": "Producto eliminado exitosamente.",
        "filasEliminadas": 1 }
→ 404 si el código no existe
```

## 7. `GET /` — Diagnóstico

```
→ 200 { "mensaje": "API Facturas funcionando", "version": "v1",
        "contratos": "docs/spec_kit/versiones/v1_producto_mariadb/6_contracts.md" }
```

## 8. Estabilidad de este contrato

Estos 7 endpoints **no cambian en las versiones siguientes**: v2 agrega
entidades nuevas (rutas nuevas con este mismo patrón), v3/v4 cambian el motor
por configuración — si algún cambio futuro rompiera este contrato, es una
decisión mayor que debe quedar registrada en la spec de esa versión.

Y hay una consecuencia que ahora se puede comprobar: **v3 y v4 no deberían
tocar ni una línea del front.** Cambiar de motor sin que la pantalla se entere
es la promesa fuerte del Artículo 5, y hasta ahora era imposible de verificar
porque no había pantalla.

---

# El front (`http://localhost:8020`)

## 9. Las cinco pantallas, y la traducción

Aquí no hay verbos ni códigos de estado: **el usuario no sabe qué es un 422**.
Esta tabla es el contrato de la pantalla — y, leída de izquierda a derecha, es
también la lista de lo que el front traduce.

| Dirección | Qué ve la persona | Qué le pide a la API |
|---|---|---|
| `GET /` | El inicio, con el menú | *(nada)* |
| `GET /productos` | La tabla con Código, Nombre, Stock y Valor unitario | `GET /api/producto?limite=1000` |
| `GET /productos/nuevo` | El formulario vacío | *(nada)* |
| `POST /productos/nuevo` | Vuelve al listado con «Se agregó…», o el formulario con lo escrito y los motivos | `POST /api/producto` |
| `GET /productos/{codigo}/editar` | El formulario con la ficha; el **código de solo lectura** | `GET /api/producto/{codigo}` |
| `POST /productos/{codigo}/editar` con **«Guardar la ficha completa»** | Vuelve al listado con «Se guardó…» | `PUT /api/producto/{codigo}` con los **tres** campos |
| `POST /productos/{codigo}/editar` con **«Guardar solo lo que cambié»** | Igual | `PATCH /api/producto/{codigo}` con **solo lo diligenciado** |
| `POST /productos/{codigo}/eliminar` | Confirma, y vuelve al listado con «Se eliminó…» | `DELETE /api/producto/{codigo}` |
| `GET /publico/…` | Las hojas de estilo y el guion de los avisos | *(nada: los sirve el propio front desde el disco)* |
| cualquier otra | «Esa página no existe» **con el marco de la aplicación** | *(nada)* |

**Fíjese en las dos filas del medio.** Son la misma dirección y el mismo
formulario; lo único distinto es el botón que se oprimió — y de ahí sale un
PUT o un PATCH. La diferencia entre RF4 y RF5 no está en una regla de
negocio: está en **qué se envía**.

**Y en la última:** un `POST` para eliminar, no un enlace. Un enlace que borra
lo puede disparar el navegador solo al precargar la página.

## 10. Cómo se traduce lo que responde la API

Un solo archivo del front (`cliente_api.php`) conoce esta tabla. Ninguna
vista la conoce, y por eso el día que la API cambie el sobre se cambia en un
sitio.

| Lo que responde la API | Lo que hace el front | Lo que ve la persona |
|---|---|---|
| `200` con `{tabla, limite, total, datos}` | Se queda con `datos` | La tabla llena |
| **`204`** (tabla vacía) | `ok = true` con la lista **vacía** | «Todavía no hay productos» y el botón de agregar |
| `200` de una escritura | `ok = true` | El aviso verde en el listado |
| `400` / `404` / `500` con `{estado, mensaje, detalle}` | Junta `mensaje` y `detalle` | El aviso rojo, en español |
| `422` con `{estado, mensaje, errores:[…]}` | Se queda con la lista `errores` | Los motivos, uno por línea, junto al formulario |
| **Nada** (la API no responde) | `null`, que no es lo mismo que un error | «El servicio no está disponible» — y la pantalla sigue en pie |

**Las dos filas en negrita son las que se equivocan casi siempre:**

- Un **204 no es un error**: es la API diciendo que no hay filas. Confundirlo
  con un fallo es el error más común al consumir una API.
- **No responder no es responder mal.** Un 404 es la API funcionando y
  diciendo que ese producto no existe; que no llegue nada es que no hay con
  quién hablar. De esa distinción sale el aviso del criterio 10, y por eso
  `llamar_api()` devuelve `null` en ese caso y no un código inventado.
