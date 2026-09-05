# Especificación — Versión 1 del proyecto: producto de punta a punta (front + API + MariaDB)

> **Versión 1** del desarrollo incremental ([mapa de versiones](../0_mapa_versiones.md)).
> Rige la constitución del proyecto: [../../1_constitution.md](../../1_constitution.md).
> En v1 el sistema completo ES esto: **una tabla, un motor — y su pantalla.**
> La API y el front se construyen **a la vez** (Artículo 1.1 de la
> constitución): la versión no está cerrada si la API responde y la pantalla
> no. (La BD `bdfacturas` sí se crea COMPLETA desde el inicio — es
> infraestructura dada, ver [5_data_model.md](5_data_model.md); lo que crece
> por versiones es el código.)
>
> | Documento de esta versión | Contenido |
> |---|---|
> | **2_spec.md** (este) | QUÉ construir en v1 y sus criterios de aceptación |
> | [3_plan.md](3_plan.md) | CÓMO: stack, estructura y diseño de las capas |
> | [4_research.md](4_research.md) | Decisiones y alternativas *(lectura opcional)* |
> | [5_data_model.md](5_data_model.md) | La BD completa (dada) y la tabla `producto` |
> | [6_contracts.md](6_contracts.md) | Los 7 endpoints y las 5 pantallas, con formatos exactos |
> | [7_quickstart.md](7_quickstart.md) | Arranque y smoke test |
> | [8_tasks.md](8_tasks.md) | Orden de construcción por fases verificables |

---

## 1. Propósito de la v1

Construir la **primera rebanada vertical (corte vertical)** del sistema de
facturación en **PHP puro**: el CRUD completo de **una sola entidad
(`producto`)** contra **un solo motor (MariaDB)** — con la **arquitectura en
capas completa desde el primer día** (controlador → servicio → repositorio,
comunicados por **interfaces nativas de PHP**) **y con la pantalla desde la
que se usa**.

La rebanada llega hasta arriba a propósito. Una que se detuviera en la API
dejaría sin comprobar justo lo que la constitución promete: que el front y la
API son **dos procesos separados**, y que el de la pantalla no puede llegar a
la base de datos ni queriendo.

> **¿Qué es una "rebanada vertical"?** En lugar de construir el sistema por
> capas horizontales ("primero TODOS los repositorios, luego TODOS los
> servicios…" — donde nada funciona hasta el final), se construye un corte
> que **atraviesa todas las capas de arriba a abajo** para UNA funcionalidad.
> Como una rebanada de pastel: un solo corte, pero con todas las capas.
>
> ```
> ┌─────────────────────────── el sistema completo ───────────────────────────┐
> │  PANTALLA    │ producto █ │ persona    │ factura    │ ...las demás (v2)   │
> │  CONTROLADOR │ producto █ │ persona    │ factura    │ ...                 │
> │  SERVICIO    │ producto █ │ persona    │ factura    │ ...                 │
> │  REPOSITORIO │ producto █ │ persona    │ factura    │ ...                 │
> │  BD          │ producto █ │ persona    │ factura    │ ...                 │
> └──────────────┴─────▲──────┴────────────┴────────────┴─────────────────────┘
>                      └── la v1 ES esta rebanada: del navegador a la tabla
> ```
>
> La fila de arriba es la que suele faltar. Sin ella el corte no llega hasta
> el usuario, y lo que se valida es media arquitectura.
>
> Ventaja: algo funciona **desde la v1** y la arquitectura queda validada —
> si las capas encajan para `producto`, las siguientes rebanadas (v2) caen en
> surcos ya hechos.

La v1 es pequeña a propósito: su valor no está en la funcionalidad sino en
dejar el **esqueleto arquitectónico correcto** —el de la API **y el del
front**— sobre el que las versiones siguientes agregan tablas (v2) y motores
(v3, v4) **sin reescribir lo construido**.

## 2. Alcance

**Incluye:**
- CRUD de `producto`: listar, obtener por código, crear, reemplazar,
  actualizar parcialmente, eliminar.
- **Modelo clásico**: la clase `Producto` con las 4 propiedades **privadas**,
  getters y setters (encapsulamiento) y `toArray()` para el JSON — el dato
  viaja como objeto, no como array anónimo.
- **Validación en el controlador** (la frontera HTTP), con un método por
  verbo: PHP puro no trae validación integrada — se construye a mano, y eso
  es contenido del curso.
- Capas con interfaces: `IRepositorioProducto` (interface PHP) implementada
  por `RepositorioProductoMariaDB`; el servicio depende de la interfaz.
- Configuración por variables de entorno (DSN de PDO, usuario, clave).
- **Un solo comando** (Artículo 4 de la constitución): `docker-compose.yml`
  mínimo — MariaDB + la API + **el front**, cada uno con su `Dockerfile` — de
  modo que `docker compose up -d --build` deja todo funcionando.
- Endpoint `/` de diagnóstico.
- **El front `front_php`** (puerto 8020), con su propio front controller y sus
  pantallas de `producto`: inicio, listado, agregar y editar. Habla con la API
  por HTTP (cURL) y **no abre ninguna conexión a la base**.

**No incluye (y es deliberado — ver [mapa de versiones](../0_mapa_versiones.md)):**
- Endpoints **ni pantallas** para otras entidades (v2) — las otras 11 tablas
  EXISTEN en la BD, pero el código de la v1 solo puede nombrar `producto`.
- Lógica de aplicación en JavaScript: las pantallas son HTML servido por
  PHP y formularios corrientes. Hay dos usos de JS y ninguno hace falta para
  que el sistema funcione: el `confirm()` antes de eliminar y el guion de
  Bootstrap que cierra los avisos con la «x». Quítelos y todo sigue andando.
- Un framework de front (React, Vue, Blazor): quien llama a la API es **el
  proceso del front**, no el navegador. Esa es la arquitectura que se está
  enseñando.
- Autenticación de usuarios en el front: cualquiera que abra el puerto 8020
  ve las pantallas. Es un entorno de curso, y el Artículo 9 lo dice.
- Otros motores y la fábrica de repositorios (v3, v4).
- Frameworks, Composer y librerías externas (constitución, Artículo 2).
- Autenticación y uso del trigger/SPs de facturación (los aprovechará la v2).

## 3. Requisitos funcionales

### 3.A — La API (`api_facturas`, puerto 8022)

> La v1 usa **los cinco verbos HTTP** (GET, POST, PUT, PATCH, DELETE) y las
> **tres vías de envío de datos**: parámetro de ruta (`/{codigo}`), query
> string (`?limite=N`) y body JSON. Es parte del objetivo didáctico.

#### RF1 — Listar productos (GET + query string)
`GET /api/producto` → 200 con envoltura `{tabla, limite, total, datos:[…]}`.
- Query param opcional `limite` (entero > 0, por defecto 1000).
- Tabla vacía → **204** sin cuerpo.

#### RF2 — Obtener por código (GET + parámetro de ruta)
`GET /api/producto/{codigo}` → 200 con el producto; inexistente → 404.

#### RF3 — Crear producto (POST + body)
`POST /api/producto` con body JSON validado por la **validación del controlador**
(`codigo` 1–10 caracteres, `nombre` no vacío, `stock` entero ≥ 0,
`valorunitario` numérico ≥ 0 — todos obligatorios).
Éxito → 200 `{estado, mensaje}`; body inválido → **422 con la lista de
errores**; código duplicado → 500 con el error del motor en `detalle`.

#### RF4 — Reemplazar producto (PUT + body completo)
`PUT /api/producto/{codigo}` con body de **todos los campos obligatorios**
(`nombre`, `stock`, `valorunitario`): PUT reemplaza el recurso completo —
omitir un campo es 422, no "dejarlo como estaba".
Devuelve `filasAfectadas`; código inexistente → 404.

#### RF5 — Actualizar parcialmente (PATCH + body parcial)
`PATCH /api/producto/{codigo}` con body de **campos opcionales**: solo se
modifican los enviados (cada uno validado si llega). Es el contraste
didáctico con PUT. Devuelve `filasAfectadas`; inexistente → 404;
body vacío → 400.

#### RF6 — Eliminar producto (DELETE)
`DELETE /api/producto/{codigo}`. Devuelve `filasEliminadas`;
inexistente → 404.

#### RF7 — Diagnóstico
`GET /` → JSON con mensaje, versión (`"v1"`) y la ruta de los contratos.

### 3.B — El front (`front_php`, puerto 8020)

> Aquí no hay verbos ni códigos de estado: **el usuario no sabe qué es un
> 422**. Los requisitos del front se escriben en lo que la persona ve y hace.
> La traducción entre las dos cosas es justamente el trabajo del front, y
> está en [6_contracts.md](6_contracts.md) §9.

#### RF8 — Cada pantalla tiene su propia dirección, y se ve terminada
`/` (inicio) · `/productos` (listado) · `/productos/nuevo` (agregar) ·
`/productos/{codigo}/editar` (editar). Ninguna dirección lleva el nombre de
la tabla como parámetro: se pueden guardar como marcador y poner en un menú.
Una dirección que no existe responde 404 **con el marco de la aplicación**,
no con una página en blanco del servidor.

Y las pantallas llegan **con sus estilos aplicados**: la hoja de Bootstrap y
la del proyecto se sirven desde el propio front, no desde internet, así que
un salón sin red ve exactamente lo mismo.

#### RF9 — Ver el catálogo
`/productos` muestra una tabla con las columnas **Código, Nombre, Stock y
Valor unitario**, con los datos que devolvió la API. Si no hay ninguno, la
pantalla lo dice con palabras y ofrece agregar el primero — **una tabla vacía
no es un error**.

#### RF10 — Agregar
`/productos/nuevo` presenta el formulario con los cuatro campos. Al guardar,
la pantalla vuelve al listado con un aviso de éxito. Si la API rechaza los
datos, **el formulario vuelve con lo que la persona había escrito** y los
motivos en español: perder lo digitado por un error de validación la castiga
dos veces.

#### RF11 — Editar, con dos botones que no hacen lo mismo
`/productos/{codigo}/editar` trae la ficha cargada y el **código de solo
lectura** —es la identidad de la fila—, con dos botones:

- **«Guardar la ficha completa»** manda los tres campos aunque alguno esté en
  blanco, así que un nombre vacío se rechaza;
- **«Guardar solo lo que cambié»** manda **únicamente lo diligenciado**, así
  que el mismo formulario a medio llenar sí se guarda y lo demás queda como
  estaba.

Es el contraste PUT/PATCH de RF4 y RF5, pero visto desde donde se usa: la
diferencia no la decide ninguna regla de negocio, la decide **qué se envía**.

#### RF12 — Eliminar, con confirmación
Cada fila del listado ofrece eliminar, se pregunta antes, y la petición viaja
por POST y no por un enlace: un enlace que borra lo puede disparar el
navegador solo al precargar la página. Al terminar, aviso y listado
actualizado.

#### RF13 — La pantalla sobrevive a la API caída
Con la API apagada, las pantallas **siguen respondiendo**, con su menú y su
marco intactos, y muestran dentro de la aplicación un aviso de que el
servicio no está disponible. No muestran ni una fila: el front no tiene por
dónde llegar a la base de datos. Cuando la API vuelve, los datos vuelven sin
tocar nada.

## 4. Requisitos no funcionales

- **RNF1 — Capas estrictas:** el controlador no toca SQL; el servicio no
  conoce HTTP ni el motor; el repositorio no conoce HTTP. Contratos con
  `interface` de PHP.
- **RNF2 — PHP puro:** sin framework, sin Composer, sin `vendor/` (Artículo 2).
- **RNF3 — SQL SIEMPRE en prepared statements de PDO**; nada de concatenar
  valores.
- **RNF4 — Errores uniformes:** `{estado, mensaje, detalle}` (y `errores:[…]`
  en el 422); InvalidArgumentException→400 · NoEncontradoExcepcion→404 ·
  PDOException y demás→500.
- **RNF5 — Sin anticipación:** ni fábrica multi-motor ni selección de motor
  en v1 (los introduce la v3 cuando exista el segundo motor).
- **RNF6 — El front no toca la base ni el código de la API:** en `front_php`
  no hay `new PDO(...)` ni un `require` de nada que viva en `api_facturas`.
  Lo único que comparten es el JSON. El compose lo hace cumplir: el servicio
  del front no recibe credenciales de la base ni depende de ella.
- **RNF7 — El front no le habla al usuario en jerga:** ninguna pantalla
  muestra verbos HTTP, números de estado, rutas de la API ni nombres de
  motores. Los errores llegan traducidos en un solo sitio
  (`cliente_api.php`), no en cada vista.

## 5. Criterios de aceptación

1. **`docker compose up -d --build` — un solo comando —** deja corriendo la
   BD (creada con el `db/init.sql` provisto: 12 tablas), la API y el front;
   `GET /` responde el JSON de diagnóstico y `http://localhost:8020` abre la
   pantalla de inicio. Guardar un `.php` y refrescar el navegador muestra el
   cambio (sin reiniciar nada: PHP reinterpreta).
2. `GET /api/producto` devuelve los 8 productos de ejemplo con
   `{tabla:"producto", total:8, datos:[…]}`, y `GET /api/producto?limite=3`
   devuelve exactamente 3.
3. `GET /api/producto/PR001` devuelve la Laptop Lenovo; `/api/producto/PR999`
   responde 404 con mensaje claro.
4. Ciclo completo con los 5 verbos: `POST` crea `PR009` → `PUT` lo reemplaza
   completo → `PATCH` le cambia solo el stock → `GET` lo confirma → `DELETE`
   lo elimina, y un segundo `DELETE` responde 404. Además, un `PUT` sin el
   campo `nombre` responde 422 (reemplazo completo) mientras el mismo body en
   `PATCH` responde 200 (parcial) — la diferencia entre ambos verbos.
5. `POST` con `stock: -5` o sin `nombre` → **422 con la lista de errores**
   (lo rechaza la validación del controlador, nunca llega a la BD); `POST` con código
   duplicado → 500 con el error del motor.
6. Prueba de capas (la evidencia de que la arquitectura quedó bien): el
   servicio se puede probar con un repositorio **falso** en memoria que
   implemente `IRepositorioProducto`, sin MariaDB corriendo
   (`php pruebas/prueba_capas.php` o script equivalente).
7. `http://localhost:8020/productos` muestra los 8 productos con sus cuatro
   columnas, y cada pantalla responde por su dirección propia (`/`,
   `/productos`, `/productos/nuevo`, `/productos/{codigo}/editar`); una
   dirección inventada da 404 con el marco de la aplicación. Y las pantallas
   **se ven con sus estilos**: `/publico/bootstrap.min.css` y
   `/publico/estilos.css` responden 200 **con tipo de contenido `text/css`**.
   El criterio dice el tipo y no solo el código porque un 200 que devuelve
   HTML disfrazado de CSS deja la pantalla igual de fea.
8. El recorrido completo **desde la pantalla**: agregar un producto → verlo
   en el listado → «Guardar la ficha completa» → dejar el nombre en blanco y
   volver a intentarlo (se rechaza, con el motivo en español) → «Guardar solo
   lo que cambié» con únicamente el stock lleno (guarda, **y el nombre no se
   borra**) → eliminar, con su confirmación, y desaparece del listado.
9. Ninguna de las pantallas muestra `PUT`, `PATCH`, `DELETE`, `/api/`, `PDO`,
   `SELECT` ni `MariaDB` en el texto que el usuario ve.
10. **La prueba de los dos procesos:** con `docker compose stop api-facturas`
    —la base de datos **encendida**—, `/productos` sigue respondiendo, con su
    menú y su aviso, y **sin una sola fila**. Al encender la API otra vez,
    los datos vuelven sin tocar nada.

> El criterio 10 es el que no se puede fingir. Los datos siguen ahí, en
> MariaDB, a un puerto de distancia: si la pantalla los mostrara, sería
> porque el front llegó a la base por su cuenta. No los muestra.

## 6. Glosario mínimo

| Término | Significado |
|---|---|
| **Documento (del kit)** | Texto que DESCRIBE qué/cómo construir; se lee y de él sale el código |
| **Artefacto (provisto)** | Archivo que se entrega LISTO y se usa tal cual, sin generarlo ni modificarlo — en v1: `db/init.sql` (la BD completa) |
| **Criterio de aceptación** | Prueba verificable que decide si la versión está terminada (no es opinión) |
| **Rebanada vertical** | Un recorte del sistema que atraviesa TODAS las capas (HTTP→negocio→datos) aunque cubra una sola entidad |
| **Front controller** | El único `.php` que recibe TODAS las peticiones (`index.php`) y las enruta al controlador que corresponde. **Lo tienen los dos**: la API y el front, cada uno el suyo |
| **Front** (la capa) | El programa que pinta las pantallas. No confundirlo con «front controller»: son dos usos distintos de la misma palabra, y en este proyecto conviven |

## 7. Definición de TERMINADA

Los 10 criterios pasan → commit + tag `v1` → recién entonces se escribe la spec
de la v2 ([mapa](../0_mapa_versiones.md)).

## 8. Clarificaciones

> **Qué es esta sección:** el registro de las ambigüedades detectadas ANTES
> de planear, con la respuesta que se acordó y su razón. Es **la compuerta
> 1** del método (ver [SDD_SPECKIT](../../../SDD_SPECKIT.md)): mientras
> quede un `[NECESITA ACLARACIÓN: …]` en los requisitos de arriba, esta
> versión no pasa a la planeación.
>
> Las entradas de abajo se reconstruyeron **al cerrar la versión**, a
> partir de las decisiones que sus propios contratos ya dejaban fijadas.
> De aquí en adelante esta sección se llena **en vivo**, antes del
> `3_plan.md` — que es como debe ser.

| # | La pregunta | La respuesta acordada, con su razón | Dónde quedó |
|---|---|---|---|
| C1 | El listado sin filas, ¿es un error o un resultado? | Un resultado: **204 sin cuerpo**. Vacío no es error. | RF de listar · contrato del `GET` |
| C2 | `?limite=0` o negativo, ¿422 o 400? | **400**: la FORMA del dato es correcta (sí es un entero); lo que se rompe es una regla de negocio. El 422 se reserva para el body mal formado. | Contrato del `GET` · convenciones |
| C3 | Crear con una llave que ya existe, ¿409 o 500? | **500**, con el error del motor en `detalle`: la llave la defiende la BD, no la API. Convertirlo en 409 sería lógica de negocio que esta versión no pide. | Convenciones de error · contrato del `POST` |
| C4 | `PATCH` con el body vacío, ¿200 sin hacer nada, o error? | **400**: pedir una actualización sin decir qué actualizar es una regla de negocio rota. | Contrato del `PATCH` |

| C5 | El front y la API son los dos PHP: ¿el front puede usar la clase `Producto` de la API con un `require_once`? | **No.** Funcionaría, y por eso hay que decirlo explícito: dejarían de ser dos procesos, y renombrar un método adentro de la API rompería la pantalla sin que nadie tocara el contrato. El front trabaja con **arrays**, que es lo que el JSON trae. | Constitución Art. 3 · RNF6 · `cliente_api.php` |
| C6 | Cuando la API no responde, ¿el front muestra una página de error o su pantalla? | **Su pantalla**, con el aviso adentro y sin datos. Una página de error del servidor no demuestra nada; una pantalla en pie con la tabla vacía demuestra que son dos procesos. | RF13 · criterio 10 |
| C8 | ¿Los estilos se traen de un CDN? | **No: se guardan en `front_php/publico/`.** Un CDN es una línea más corta, pero el día que el salón se quede sin internet la pantalla se ve como en 1995 — y eso pasa en clase, no en teoría. Dos archivos en el repositorio lo resuelven para siempre. | `vistas/plantilla.php` · 4_research D13 |
| C7 | El formulario manda texto y la API pide números. ¿Quién convierte? | **El front convierte la FORMA** (`"12"` → `12`) porque un formulario HTML solo produce texto, y mandarlo entre comillas haría que la API lo rechazara **siendo correcto**. Lo que el front NO hace es juzgar el VALOR: si alguien escribió «doce», eso viaja como «doce» y la API dice que no sirve. | `front_php/index.php` · 6_contracts §9 |

**Cómo se escribe una entrada nueva:** la pregunta tal como se hizo (no
"revisar el borrado", sino "¿físico o lógico?"), la respuesta **con su
razón**, y el documento donde quedó plasmada. Si la respuesta cambia un
requisito, se corrige el requisito allá arriba: esta sección lo registra,
no lo reemplaza.
