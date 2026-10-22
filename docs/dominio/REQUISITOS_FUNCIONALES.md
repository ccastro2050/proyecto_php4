# Requisitos funcionales — Facturación (`bdfacturas`), en PHP

> **Qué es este documento.** Todo lo que el sistema **hace**, con la versión en
> que entró y **la petición que lo comprueba**. Un requisito que no se puede
> comprobar es una intención.
>
> La diferencia con [`REGLAS_DE_NEGOCIO.md`](REGLAS_DE_NEGOCIO.md): un
> requisito es algo que el sistema **hace**; una regla es algo que **no puede
> dejar de ser cierto**.
>
> **Medido contra la API en el puerto 8090**, contra MariaDB.
>
> Versión 1.0 · 21 de octubre de 2026.

---

## 1. El tamaño del contrato, en números

| | |
|---|---|
| Rutas declaradas | **36** |
| Operaciones (ruta + verbo) | **81**, más las **10** consultas = **91** |
| Recursos con CRUD completo | **11** |
| Consultas de solo lectura | **10** |
| Rutas abiertas, sin token | **2** — `GET /` y `POST /api/sesion` |

---

## 2. Lo que entró en cada versión

| Versión | Qué agrega | Se comprueba |
|---|---|---|
| **v1** | El CRUD de `producto` de punta a punta: API contra MariaDB **y su front** | `GET /api/producto` |
| **v2** | Cinco recursos más —`empresa`, `persona`, `cliente`, `vendedor`, `factura`— con llaves foráneas, maestro-detalle y el 409 | `POST /api/factura` con su detalle |
| **v3** | El segundo motor: **PostgreSQL**, escogido por configuración. Ni una funcionalidad nueva | `GET /` devuelve `motor` |
| **v4** | El tercer motor (**SQL Server**), el **control de acceso**, `usuario-con-roles` y las **diez consultas** con su tablero | las tres de abajo |

---

## 3. Los once recursos y sus verbos

Los seis del CRUD clásico aceptan los cinco verbos. Medido, verbo por verbo:

| Recurso | GET lista | GET uno | POST | PUT | PATCH | DELETE |
|---|---|---|---|---|---|---|
| `producto` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `empresa` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `persona` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `cliente` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `vendedor` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `rol` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `ruta` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `usuario` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `usuario-con-roles` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `factura` | ✅ | ✅ | ✅ | ✅ | **405** | ✅ |
| `rutarol` | ✅ | — | ✅ | **405** | **405** | ✅ |
| `rol-usuario` | ✅ | — | ✅ | **405** | **405** | ✅ |

**Los tres 405 no son huecos: son el contrato diciendo que ese verbo no
aplica**, y cada uno tiene su razón escrita en el código:

| Dónde | Por qué |
|---|---|
| `PATCH /api/factura/{n}` | Una factura no se corrige campo por campo: cambiar un renglón cambia el total y el stock, así que el detalle **se reemplaza entero** (PUT) o no se toca |
| `PUT` y `PATCH` de los dos puentes | Una fila de `rutarol` o de `rol_usuario` **es** su llave: una pareja de llaves foráneas sin datos propios. «Editarla» sería quitar un permiso y dar otro, que son dos operaciones |

En el código esos verbos **están escritos y comentados**, no borrados: así la
ausencia es visible y razonada, no un olvido.

---

## 4. Lo que la factura hace y los demás no

| Requisito | Petición | Qué responde |
|---|---|---|
| Crear encabezado y detalle **en una sola petición** | `POST /api/factura` con `{"fkidcliente", "fkidvendedor", "detalle": [...]}` | 200 con la factura, su número, su fecha y su total **calculados por la base de datos** |
| Reemplazar el detalle entero | `PUT /api/factura/{n}` | 200 — y el stock se ajusta por la diferencia |
| **Anular**, que no es borrar | `POST /api/factura/{n}/anular` | 200; repetirlo da **409**; sobre una que no existe, **404** |
| Ver una factura con sus renglones | `GET /api/factura/{n}` | el encabezado, el nombre del cliente y del vendedor, y el detalle con sus subtotales |

> La ruta de anular es **`POST /api/factura/{n}/anular`** y solo POST: con GET
> responde 405. Un GET que cambia el estado de algo lo puede disparar
> cualquier cosa que cargue una dirección.

---

## 5. El control de acceso (v4)

| Requisito | Petición | Qué responde |
|---|---|---|
| Entrar | `POST /api/sesion` | 200 con el token, el correo, los roles y la fecha de vencimiento |
| Saber quién soy | `GET /api/sesion` | el correo y los roles **sacados del token**, no de la URL |
| Salir | `DELETE /api/sesion` | 200. Es cortesía: un JWT firmado no se apaga desde el servidor |
| Renovar | `POST /api/sesion/renovar` | un token nuevo para quien ya tiene uno válido |
| Saber qué alcanzo | `GET /api/permisos/mios` | la lista de rutas, **para que el front arme su menú** |
| Comprobar una contraseña sin entregar token | `POST /api/usuario/verificar-contrasena` | 200 si coincide. Exige el permiso `/usuario` |

Y las tres condiciones que definen el comportamiento, medidas:

1. **Sin token** → 401, con el detalle diciendo si falta la cabecera, si el
   token venció o si la firma no es válida.
2. **Con token y sin permiso** → 403, diciendo qué usuario y qué ruta.
3. **El permiso se consulta en cada petición**: quitarle un rol a alguien le
   cierra la ruta **en la petición siguiente**, sin que su token cambie.

---

## 6. Las diez consultas (v4)

Todas `GET`, todas bajo `/api/consultas/`, todas con el permiso `/home`, y
**todas cruzan cuatro o más tablas** — que es lo que las hace distintas del
CRUD:

| # | Ruta | Qué responde |
|---|---|---|
| 1 | `ventas-por-producto` | qué se vende y cuánto deja |
| 2 | `ventas-por-cliente` | quién compra y cuánto |
| 3 | `ventas-por-vendedor` | quién vende y cuánto |
| 4 | `ventas-por-empresa` | cuánto factura cada empresa |
| 5 | `ticket-por-vendedor` | cuánto vale en promedio una factura suya |
| 6 | `productos-sin-vender` | lo que nunca se vendió |
| 7 | `anulaciones-por-cliente` | quién anula y por cuánto |
| 8 | `alcance-de-usuarios` | a cuántas interfaces llega cada quien |
| 9 | `interfaces-sin-usuarios` | rutas a las que no llega nadie |
| 10 | `credito-contra-consumo` | crédito, consumido y disponible |

Más `GET /api/consultas`, que devuelve el índice de las diez para que el front
no las tenga escritas a mano.

**Cero filas no es un error:** tres de las diez preguntan por lo que *no*
existe, y con los datos sembrados pueden responder `total: 0`.

---

## 7. El front: once pantallas

| Pantalla | Qué hace |
|---|---|
| `/entrar` · `/salir` | La única sin sesión · salir es **POST**, no un enlace |
| `/` | El inicio, con las tarjetas que esa persona alcanza |
| `/facturas` · `/facturas/nueva` · `/facturas/{n}` | Listado, emisión con su detalle renglón por renglón, y el detalle guardado |
| `/productos` `/clientes` `/vendedores` `/personas` `/empresas` | Lista y formulario, con los cinco verbos |
| `/roles` `/rutas` `/usuarios` | Lo mismo, para el control de acceso |
| `/permisos` · `/usuarios-roles` | Los dos puentes: **ver, dar y quitar** |
| `/usuarios-con-roles` | El usuario y sus roles, con casillas |
| `/tablero` · `/tablero/{consulta}` | Las diez consultas |

Y tres requisitos del front que son del contrato, no del gusto:

1. **Sin sesión, todo redirige a `/entrar`** — y eso no protege los datos: los
   protege la API.
2. **El menú se arma con los permisos** que responde la API. Esconder un
   enlace no es control de acceso: es no ofrecer lo que va a responder 403.
3. **La contraseña no vuelve a la pantalla**, ni al repintar un formulario con
   error ni en un campo oculto.

---

## 8. El mismo sistema contra tres motores

| Requisito | Cómo se comprueba |
|---|---|
| El motor se escoge **por configuración**, no tocando código | `MOTOR` en el compose: `mariadb`, `postgres` o `sqlserver` |
| La API responde **lo mismo** en los tres | `php pruebas/prueba_consultas_tres_motores.php` con los tres valores |
| El front **no sabe** qué motor hay debajo | solo lo muestra en el pie, y no decide nada con ese dato |

---

## 9. Dónde está cada cosa

| Qué | Dónde |
|---|---|
| El contrato endpoint por endpoint | `docs/spec_kit/versiones/v4_aplicativo/6_contracts.md` |
| Qué código responde a cada fallo | [`POLITICA_DE_ERRORES.md`](POLITICA_DE_ERRORES.md) |
| Lo que no puede dejar de ser cierto | [`REGLAS_DE_NEGOCIO.md`](REGLAS_DE_NEGOCIO.md) |
| Las peticiones listas para correr | `postman/coleccion_v4.postman_collection.json` — 118, probadas |
| Lo que falta | [`PENDIENTES.md`](PENDIENTES.md) |
