# Especificación — Versión 2: seis recursos, integridad y maestro-detalle

> **Versión 2** del desarrollo incremental ([mapa de versiones](../0_mapa_versiones.md)).
> Rige la constitución del proyecto: [../../1_constitution.md](../../1_constitution.md).
> Parte del estado que dejó la v1: la API y el front de `producto`, funcionando.
> La API y el front siguen construyéndose **a la vez** (Artículo 1.1).
>
> | Documento de esta versión | Contenido |
> |---|---|
> | **2_spec.md** (este) | QUÉ construir en v2 y sus criterios de aceptación |
> | [3_plan.md](3_plan.md) | CÓMO: estructura, capas y las piezas nuevas |
> | [4_research.md](4_research.md) | Decisiones y alternativas *(lectura opcional)* |
> | [5_data_model.md](5_data_model.md) | Las seis tablas, sus llaves foráneas y la lógica que vive en la base |
> | [6_contracts.md](6_contracts.md) | Los endpoints y las pantallas, con formatos exactos |
> | [7_quickstart.md](7_quickstart.md) | Arranque y smoke test |
> | [8_tasks.md](8_tasks.md) | Orden de construcción por fases verificables |

---

## 1. Propósito de la v2

La v1 dejó el esqueleto probado con **una tabla que no dependía de nadie**.
Esta versión mete al sistema en el problema que empieza cuando las tablas se
relacionan entre sí:

1. **Que una ficha apunte a otra** — un cliente es una persona, una factura
   tiene un cliente y un vendedor. ¿Qué pasa cuando se apunta a algo que no
   existe? ¿Y cuando se quiere borrar algo de lo que otros dependen?
2. **Que la llave la ponga la base** — hasta ahora el código lo escribía
   quien creaba la ficha. Un `id` autogenerado cambia lo que el cliente manda
   y lo que la API tiene que devolver.
3. **Que una operación sean varias escrituras** — crear una factura es
   insertar el encabezado, insertar cada renglón, recalcular el total y
   descontar el stock. Si se cae a la mitad, no puede quedar media factura.
4. **Que parte del negocio ya esté escrito en la base** — los triggers y los
   procedimientos existen desde la v1, esperando. Aquí se usan.

Y hay un descubrimiento que la v1 no podía tener: **no todo recurso es un
CRUD**. Cinco de los seis se comportan como fichas. La factura no: se anula,
que no es corregirla ni borrarla.

## 2. Alcance

**Incluye:**

- Cinco recursos nuevos en la API y en el front:
  **empresa**, **persona**, **cliente**, **vendedor** y **factura** — más
  `producto`, que viene de la v1 y no se toca.
- **La traducción de los errores de integridad del motor** a un mensaje en
  español y a un código **409**, en un solo archivo
  (`repositorios/errores_de_integridad.php`).
- **Llaves generadas por la base** en `cliente`, `vendedor` y `factura`: el
  cliente no las manda al crear, y la API le devuelve la que quedó.
- **Maestro-detalle**: la factura y sus renglones viajan juntos, en una sola
  petición y en una sola respuesta.
- **El uso de los procedimientos almacenados** que ya trae la base para las
  seis operaciones de factura.
- **La operación de anular**, con su propia ruta.
- Las pantallas de los cinco recursos nuevos, incluida la de **ver una
  factura con su detalle**.

**No incluye (y es deliberado):**

- Las otras seis tablas de la base (`rol`, `ruta`, `usuario`, `rol_usuario`,
  `rutarol`, y lo que cuelga de ellas). Existen, y el código de la v2 no
  puede nombrarlas.
- Autenticación y control de acceso — que es justamente para lo que sirven
  esas tablas. No es alcance de esta versión.
- Otros motores y la fábrica de repositorios (v3, v4).
- JavaScript de aplicación: las pantallas siguen siendo formularios
  corrientes. Los únicos usos de JS son el `confirm()` antes de borrar y el
  guion de Bootstrap que cierra los avisos; quítelos y todo sigue andando.

## 3. Requisitos funcionales

### 3.A — La API (`api_facturas`, puerto 8026)

#### RF1 a RF7 — Producto
Sin cambios respecto de la v1: los siete endpoints siguen respondiendo igual.
Que sigan pasando sus pruebas es parte de los criterios de esta versión.

#### RF8 — Las cuatro fichas nuevas
`empresa`, `persona`, `cliente` y `vendedor` ofrecen las mismas seis
operaciones que `producto`, cada una en **su propia ruta**: `/api/empresa`,
`/api/persona`, `/api/cliente`, `/api/vendedor`.

- Listar (`GET`, con `?limite`), obtener (`GET /{llave}`), crear (`POST`),
  reemplazar (`PUT`), actualizar parcial (`PATCH`) y eliminar (`DELETE`).
- Envoltura de listado `{tabla, limite, total, datos}` y **204** cuando la
  tabla está vacía, igual que en la v1.

#### RF9 — Las llaves que genera la base
En `cliente` y `vendedor` la llave es un `id` numérico que asigna la base:

- el `POST` **no lo lleva** en el body, y si llega se ignora;
- la respuesta del `POST` **sí lo devuelve**, porque quien creó la ficha no
  tiene otra forma de saber con qué llave quedó;
- un identificador que no sea un entero mayor que cero es **400**, no 404: no
  es que no se haya encontrado, es que eso no es un identificador.

#### RF10 — La integridad la defiende la base, y la API la explica
Cuando el motor rechaza una escritura por una regla de integridad, la API
responde **409** con el motivo en español. Los tres casos:

| Lo que se intentó | Lo que responde |
|---|---|
| Crear una ficha con una llave que ya existe | 409 «Ya existe … con esa llave.» |
| Apuntar a un código que no existe (`fkcodpersona`, `fkidcliente`…) | 409 «Alguno de los códigos a los que apunta … no existe.» |
| Eliminar una ficha de la que otras dependen | 409 «No se puede eliminar … porque hay otras fichas que dependen de ella.» |

La API **no comprueba antes**: intenta, y traduce el veredicto. El porqué
está en [4_research.md](4_research.md) D2.

#### RF11 — La factura se crea con su detalle, de una vez
`POST /api/factura` recibe el encabezado y la lista de renglones en el mismo
body, y devuelve **la factura completa**: su número, su total calculado y su
detalle.

- El body no lleva `total` ni `subtotal`: los calcula la base. Si llegan, se
  ignoran.
- Una factura sin renglones se rechaza con **422**.
- Cada renglón se valida por separado, y el error dice **cuál** renglón falla.

#### RF12 — La factura se reemplaza entera, no por partes
`PUT /api/factura/{numero}` reemplaza cliente, vendedor y **todo** el detalle.
**No existe `PATCH` de facturas**: cambiar un renglón cambia el total y el
stock, así que no hay «cambiar solo un pedacito». Un `PATCH` responde **405**,
y ese 405 sale del enrutador sin que nadie lo escriba.

#### RF13 — Anular no es eliminar
`POST /api/factura/{numero}/anular` deja la factura en estado `anulada` y
**devuelve el stock** a los productos. La fila no se borra: conserva su
número, su fecha y su detalle.

- Anular una factura ya anulada es **409**.
- Modificar una factura anulada es **409**.
- `DELETE /api/factura/{numero}` sigue existiendo y sí la borra.

#### RF14 — Diagnóstico
`GET /` → JSON con mensaje, versión (`"v2"`), **la lista de los seis
recursos** y la ruta de los contratos.

### 3.B — El front (`front_php`, puerto 8024)

> Aquí no hay verbos ni códigos de estado: **el usuario no sabe qué es un
> 409**. La traducción entre las dos cosas está en
> [6_contracts.md](6_contracts.md) §10.

#### RF15 — Seis secciones, cada una con su dirección
`/facturas`, `/productos`, `/clientes`, `/vendedores`, `/personas`,
`/empresas`, y el menú lleva a las seis desde cualquier pantalla. Ninguna
dirección lleva el nombre de la tabla como parámetro.

#### RF16 — Las fichas se manejan igual que en la v1
Listado, agregar, editar con **los dos botones** («Guardar la ficha completa»
/ «Guardar solo lo que cambié») y eliminar con confirmación. Donde la llave la
genera la base, el formulario **no la muestra como campo**: se ve como dato,
porque no se puede escribir ni cambiar.

#### RF17 — Las llaves foráneas se escogen, no se escriben
Los campos que apuntan a otra tabla se ofrecen en un **desplegable** con los
nombres, no como una casilla donde haya que teclear un código que hay que ir a
buscar a otra pantalla.

El desplegable **no valida nada**, y la pantalla no debe hacer creer que sí:
la lista pudo cargarse hace un minuto y esa ficha pudo borrarse entretanto.

#### RF18 — Los rechazos de la base se ven en español
Cuando la API responde 409, la pantalla muestra el motivo tal como viene —
«No se puede eliminar la persona porque hay otras fichas que dependen de
ella» — dentro de la aplicación, sin números de estado y sin jerga.

#### RF19 — La factura se arma en una pantalla
Una sola pantalla con el encabezado y el detalle. Al guardar, se va a la
pantalla de **ver la factura**, que muestra los renglones, los subtotales y
el total que calculó la base.

El detalle se ofrece en **cinco renglones fijos** y los vacíos se ignoran: un
formulario que crezca solo necesita JavaScript, y esta versión no lo usa. Es
una limitación de la pantalla, no de la API, y se declara aquí en vez de
esconderse.

#### RF20 — Anular se ve distinto de eliminar
En el listado de facturas, «Anular» solo aparece en las facturas activas —una
acción que no se puede hacer no se muestra apagada: no se muestra— y su
confirmación dice qué va a pasar con el stock. La factura anulada se ve
marcada y no ofrece editarse.

#### RF21 — La pantalla sobrevive a la API caída
Igual que en la v1: con la API apagada las pantallas siguen respondiendo, con
su menú y su aviso, y **sin una sola fila**.

## 4. Requisitos no funcionales

- **RNF1 — Capas estrictas:** el controlador no toca SQL; el servicio no
  conoce HTTP ni el motor; el repositorio no conoce HTTP.
- **RNF2 — PHP puro:** sin framework, sin Composer, sin `vendor/`.
- **RNF3 — SQL SIEMPRE en prepared statements de PDO.**
- **RNF4 — Errores uniformes:** el mismo sobre de la v1, más el 409.
- **RNF5 — El dialecto de MariaDB vive SOLO en las clases `*MariaDB`.** Ni un
  `SELECT`, ni un nombre de procedimiento, ni un número de error del motor
  fuera de ellas. Es lo que la v3 va a poner a prueba.
- **RNF6 — El front no toca la base ni el código de la API.**
- **RNF7 — El front no le habla al usuario en jerga:** ninguna pantalla
  muestra verbos HTTP, números de estado, rutas de la API, nombres de
  columna (`fkcodpersona`) ni nombres de motores.
- **RNF8 — Sin anticipación:** ni fábrica multi-motor ni selección de motor
  en v2.

## 5. Criterios de aceptación

1. **`docker compose up -d --build` — un solo comando —** deja corriendo la
   BD, la API y el front; `GET /` lista los seis recursos y
   `http://localhost:8024` abre el inicio con sus seis secciones.
2. Los siete endpoints de `producto` **siguen respondiendo como en la v1**,
   incluido el contraste PUT (422) contra PATCH (200) con el mismo body.
3. Los cuatro recursos de ficha nuevos listan sus datos de ejemplo con la
   envoltura `{tabla, limite, total, datos}`, y `?limite=2` devuelve 2.
4. `POST /api/cliente` **sin `id` en el body** crea la ficha y **devuelve el
   `id`** que generó la base; `GET /api/cliente/{ese id}` la trae.
   `GET /api/cliente/abc` responde 400.
5. Los tres rechazos de integridad responden **409** con su motivo en
   español, y ninguno responde 500:
   - crear una empresa con un código que ya existe;
   - crear un cliente con `fkcodpersona` que no existe;
   - eliminar una persona que ya es cliente.
6. `POST /api/factura` con dos renglones devuelve la factura con su número,
   **el total calculado por la base** (la suma de cantidad × valor unitario) y
   sus dos renglones; y el stock de los dos productos bajó exactamente lo
   facturado.
7. `PATCH /api/factura/{numero}` responde **405**. `POST /api/factura` sin
   renglones responde 422 diciendo cuál es el problema.
8. `POST /api/factura/{numero}/anular` deja la factura existiendo con estado
   `anulada`, **el stock vuelve**, y un segundo intento responde 409. Un `PUT`
   sobre una factura anulada también responde 409.
9. Prueba de capas: los servicios se pueden probar con repositorios **falsos**
   en memoria, sin MariaDB corriendo.
10. Todas las pantallas responden por su dirección propia y **se ven con sus
    estilos** (`/publico/bootstrap.min.css` y `/publico/estilos.css` responden
    200 con tipo `text/css`); una dirección inventada da 404 con el marco de
    la aplicación.
11. El recorrido completo **desde la pantalla**: agregar una ficha, los dos
    botones de guardar, y los dos rechazos de integridad explicados en
    español, sin números de estado.
12. Desde la pantalla: crear una factura de dos renglones, verla con su
    detalle y su total, anularla, y comprobar que sigue ahí y que el stock
    volvió.
13. Ninguna pantalla muestra `PUT`, `PATCH`, `DELETE`, `/api/`, `PDO`,
    `SELECT`, `MariaDB` ni nombres de columna como `fkcodpersona`.
14. **La prueba de los dos procesos:** con `docker compose stop api-facturas`
    —la base **encendida**—, las pantallas siguen respondiendo, con su menú y
    su aviso, y sin una sola fila.

## 6. Glosario mínimo

| Término | Significado |
|---|---|
| **Llave foránea** | Una columna que solo acepta valores que existen en otra tabla. La defiende la base, no el programa |
| **Integridad referencial** | Que no queden referencias rotas: ningún cliente apuntando a una persona que ya no está |
| **Maestro-detalle** | Dos tablas que no tienen sentido por separado: el encabezado y sus renglones |
| **Trigger** | Código que la base ejecuta sola cuando una tabla cambia. Aquí calcula totales y mueve stock |
| **Procedimiento almacenado** | Una operación escrita y guardada dentro de la base, que se llama por su nombre |
| **AUTO_INCREMENT** | Que la base ponga la llave, y no quien crea la ficha |
| **409 Conflicto** | «Su petición está bien escrita, pero no cabe en los datos que hay». Distinto de un 500, que es «se rompió algo» |

## 7. Definición de TERMINADA

Los 14 criterios pasan → commit + tag `v2` → recién entonces se escribe la
spec de la v3 ([mapa](../0_mapa_versiones.md)).

## 8. Clarificaciones

> **Qué es esta sección:** el registro de las ambigüedades detectadas ANTES
> de planear, con la respuesta que se acordó y su razón. Es **la compuerta
> 1** del método (ver [SDD_SPECKIT](../../../SDD_SPECKIT.md)): mientras
> quede un `[NECESITA ACLARACIÓN: …]` en los requisitos de arriba, esta
> versión no pasa a la planeación.

| # | La pregunta | La respuesta acordada, con su razón | Dónde quedó |
|---|---|---|---|
| C1 | Cuando la base rechaza por una llave foránea, ¿eso es un 500 como en la v1? | **No: 409.** En la v1 se decidió 500 porque la integridad no era tema de esa versión y traducirla habría sido lógica que nadie pidió. En la v2 la integridad **es** el tema, así que se traduce. Un 500 le dice al usuario «se dañó algo»; un 409 le dice «su petición no cabe en los datos que hay», que es la verdad. | RF10 · 4_research D1 |
| C2 | ¿La API comprueba que la persona exista antes de crear el cliente? | **No.** Se intenta y se traduce el veredicto. Comprobar antes no evita el problema —entre la comprobación y la escritura otro puede borrar esa persona— y obliga a mantener la misma regla en PHP y en la base. | RF10 · Artículo 11 |
| C3 | Un `POST` de cliente que traiga `id`, ¿es 422 o se ignora? | **Se ignora**, con la lista blanca. No es un error del cliente: es un campo que esta API no acepta de nadie. Rechazar la petición entera por un campo de más sería severo sin ganar nada. | RF9 · 3_plan §4.3 |
| C4 | ¿La factura tiene `PATCH`? | **No.** Cambiar un renglón cambia el total y el stock; el detalle se reemplaza entero. El 405 lo da el enrutador sin escribir el número en ninguna parte. | RF12 |
| C5 | Anular, ¿es un `PATCH` del campo `estado`? | **No: su propia ruta** (`POST /api/factura/{n}/anular`). Anular no es escribir un campo — devuelve stock y cierra la factura. Ofrecerlo como un campo editable haría creer que se puede poner y quitar. | RF13 · 6_contracts §7 |
| C6 | Si la factura queda anulada, ¿se puede editar? | **No: 409.** Una factura anulada es historia. Esta guarda **no estaba** en el procedimiento almacenado y se agregó; ver 4_research D6. | RF13 |
| C7 | El formulario de factura, ¿cuántos renglones ofrece? | **Cinco fijos**, y los vacíos se ignoran. Un formulario que crezca solo necesita JavaScript, que esta versión no usa. Es una limitación de la pantalla y se declara; la API acepta los renglones que sean. | RF19 · 4_research D5 |
| C8 | Cuando el detalle llega vacío, ¿quién lo rechaza: el controlador o el procedimiento? | **El controlador**, con 422, porque está antes. La regla del procedimiento no sobra: protege a la base de cualquier otro programa que le escriba sin pasar por esta API. Dos defensas a distinta altura. | RF11 · 7_quickstart |
