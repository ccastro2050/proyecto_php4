# Plan de la versión 1 — una rebanada vertical en PHP puro

> **Qué es este documento.** Qué entró en la v1, **qué se decidió antes de
> programar** y por qué esas decisiones mandan sobre las tres versiones que
> siguen.
>
> El requisito que manda está en
> [`2_spec.md`](../spec_kit/versiones/v1_producto_mariadb/2_spec.md). Esto es
> el relato: lo que la spec no cuenta.
>
> Versión 1.0 · 22 de octubre de 2026.

---

## 0. Lo que queda al terminar la v1

**Un recurso —`producto`— funcionando de punta a punta**: base de datos, API
con sus cinco verbos, y **una interfaz gráfica que lo usa**. Todo levantado
con un solo comando.

| | |
|---|---|
| Tablas en juego | 1 de 12 — `producto` |
| Endpoints | 6: lista, uno, crear, reemplazar, actualizar, eliminar |
| Pantallas | 2: el listado y el formulario |
| Motores | 1 — MariaDB |

---

## 1. La decisión que define la versión: **una rebanada vertical, no una capa**

Había dos formas de empezar, y la diferencia se paga tres versiones después:

| | Qué sería | Qué pasa |
|---|---|---|
| **Por capas** | Primero todas las tablas, luego todos los repositorios, luego la API, al final el front | **Al final del semestre nada funciona todavía.** Y el día que el front aparece, descubre que el contrato no le sirve |
| **Por rebanada** ✅ | **Un** recurso, pero completo: base de datos → repositorio → servicio → controlador → pantalla | Al terminar la v1 hay algo **que se usa**. Lo demás es repetir un molde que ya se probó |

> **De ahí sale la regla de todas las versiones:** *una versión no está
> cerrada si la API responde y la interfaz no.* No es una exigencia estética:
> es que una API sin pantalla no demostró que su contrato sirva para algo.

---

## 2. La segunda decisión: **PHP puro, sin framework ni Composer**

| | |
|---|---|
| **Qué se descartó** | Laravel, Symfony, Slim — y hasta un enrutador de terceros |
| **Qué cuesta** | El enrutador, la validación, el cliente HTTP y después el JWT se escriben a mano |
| **Qué compra** | Que **todo lo que pasa se pueda leer**. No hay magia que explicar: hay archivos |

> **Y el costo se nota, y por eso está escrito:** el 422 que FastAPI o .NET
> dan gratis, aquí se escribe en cada controlador. A cambio, los mensajes
> salen en castellano y diciendo qué se esperaba.

---

## 3. Las tres capas, desde el primer día

Lo que se decidió aquí y no se volvió a discutir:

```
index.php ──▶ controlador ──▶ IServicio ──▶ IRepositorio ──▶ PDO
```

| Capa | Qué sabe | Qué NO sabe |
|---|---|---|
| Controlador | HTTP: códigos, sobres, validación de forma | qué es una consulta |
| Servicio | Las reglas del negocio | qué es un código de estado |
| Repositorio | SQL y PDO | qué es HTTP |

**Con interfaces desde la v1, aunque solo hubiera una implementación.** Eso
parece exagerado el primer día y es exactamente lo que hace posible la v3:
entra PostgreSQL y no se toca ni un controlador.

---

## 4. El orden en que se construyó

| # | Paso | Por qué en ese orden |
|---|---|---|
| 1 | El `docker-compose.yml` y el script de la base de datos | Sin base de datos no hay nada que probar |
| 2 | El modelo `Producto` y `IRepositorioProducto` | El contrato antes que la implementación |
| 3 | `RepositorioProductoMariaDB` | Con `prepare` y marcadores desde el primer `SELECT` |
| 4 | `ServicioProducto` y su interfaz | Donde viven las reglas |
| 5 | `ControladorProducto` y el enrutador | Los cinco verbos, con sus códigos |
| 6 | El front: `cliente_api.php` y las dos vistas | Que consume la API **por HTTP**, no la base de datos |

---

## 5. Los tropiezos que esta versión tiene preparados

| | Qué pasa | Cómo se nota |
|---|---|---|
| **El 204 sin cuerpo** | La tabla vacía responde 204, que **no trae JSON**. `json_decode('')` devuelve `null` | `foreach` sobre `null` revienta la página — y es el caso del primer día |
| **El sobre de la respuesta** | La API devuelve `{tabla, limite, total, datos}`, no una lista pelada | La pantalla sale **vacía y sin ningún error** |
| **El `(int) ''` que vale 0** | Un campo numérico vacío llega como cadena vacía, y `(int) ''` es `0` | Se guarda un 0 que nadie escribió |
| **`declare(strict_types=1)` mal puesto** | Tiene que ser **la primera** instrucción del archivo | Error fatal, y el mensaje no dice que el problema es el orden |
| **El servidor embebido y los estáticos** | `php -S` con un enrutador lo ejecuta **para todas** las peticiones, también para el CSS | La hoja de estilos cae en el 404 y la pantalla sale sin un solo estilo. Se resuelve devolviendo `false` desde el enrutador |

---

## 6. Cómo se verificó

| Criterio | Cómo |
|---|---|
| Los cinco verbos responden lo que dice el contrato | La colección de Postman de la v1 |
| El front **no toca** la base de datos | No hay un solo `new PDO` en `front_php/` |
| Un comando levanta todo | `docker compose up -d` |
| El 404 y el 422 responden en castellano | Pedir un producto que no existe; crear uno sin campos |

---

## 7. Lo que queda para la v2

Llaves foráneas, maestro-detalle y el 409. **En la v1 no hay ni una FK** —y
eso es deliberado: una llave repetida responde **500**, porque la defiende la
base de datos y no la API. Convertirlo en 409 sería lógica de negocio que
esta versión no pide.

> El 409 llega en la v2, cuando aparecen las foráneas y los rechazos se
> vuelven cotidianos. Ver [`PLAN_V2.md`](PLAN_V2.md).
