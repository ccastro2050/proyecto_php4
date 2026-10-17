# Reglas de negocio — Facturación (`bdfacturas`), en PHP

> **Qué es este archivo.** Lo que es **verdad siempre** en este dominio, sin
> importar qué endpoint se llame. No son requisitos: un requisito es algo que
> el sistema **hace**; una regla es algo que **no puede dejar de ser cierto**.
>
> **Cada regla dice quién la defiende**, y esa columna es la mitad del valor
> del documento: una regla que nadie defiende es un deseo.
>
> **Cada fila está medida** contra la API en el puerto 8090, contra MariaDB.
> Donde el motor cambia la respuesta, se dice.
>
> Versión 1.0 · 17 de octubre de 2026.

---

## 1. Cómo leer la tabla

| Columna | Qué dice |
|---|---|
| **Quién la defiende** | El mecanismo concreto. Si dice «nadie», la regla **no está defendida**, y eso también se escribe |
| **Si se viola** | El código que recibe quien lo intenta, medido |

Cuatro mecanismos, y conviene distinguirlos porque fallan distinto:

- **La base de datos** — una restricción (`PRIMARY KEY`, `FOREIGN KEY`,
  `NOT NULL`, `DEFAULT`). No se puede evadir, ni desde phpMyAdmin.
- **Un disparador** — SQL que rechaza con `SIGNAL SQLSTATE '45000'`. Tampoco
  se evade, y además explica el motivo con el dato exacto.
- **Un procedimiento almacenado** — el que recibe la factura entera y decide.
- **El controlador o el servicio** — código PHP. Solo protege a quien pase
  por la API.

> **El orden importa.** Una regla defendida solo por PHP se rompe con una
> línea de SQL en phpMyAdmin. Las que de verdad no pueden fallar están abajo
> del todo, en la base de datos.

---

## 2. La factura

| | Regla | Quién la defiende | Si se viola |
|---|---|---|---|
| **RN-01** | Toda factura tiene **un cliente y un vendedor**, y los dos existen | `fk_factura_cliente` y `fk_factura_vendedor` | 409 — referencia rota |
| **RN-02** | Una factura tiene **al menos un renglón**. No existe una factura vacía | el controlador, **y además** `sp_insertar_factura_y_productosporfactura` con su `SIGNAL` | **422** · *«El detalle debe traer al menos un producto.»* |
| **RN-03** | El **número** lo pone la base de datos y no se repite | `numero INT AUTO_INCREMENT` + `pk_factura` | — |
| **RN-04** | La **fecha** es la del momento de emitir, y no la manda el cliente | `fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP` | lo que llegue se ignora |
| **RN-05** | Una factura está **activa** o **anulada**. No hay un tercer estado | `estado VARCHAR(10) NOT NULL DEFAULT 'activa'`, y solo `sp_anular_factura` lo cambia | — |
| **RN-06** | El mismo producto **no aparece dos veces** en una factura | `pk_productosporfactura (fknumfactura, fkcodproducto)` | 409 — llave duplicada |

> **RN-02 está defendida DOS veces, y es a propósito.** El controlador
> responde 422 antes de viajar a la base de datos —más barato y con mejor
> mensaje—, y el procedimiento la rechaza igual si alguien llama al
> procedimiento directamente. **La validación del front no reemplaza la de la
> API, y la de la API no reemplaza la de la base de datos**: cada una protege
> contra un camino distinto.

---

## 3. El dinero

| | Regla | Quién la defiende | Si se viola |
|---|---|---|---|
| **RN-07** | El **subtotal** de un renglón es `cantidad × valor unitario` **del momento de emitir**, y queda congelado | `trg_prodfact_before_insert` lo calcula y lo guarda | no se puede: lo que mande el cliente se ignora |
| **RN-08** | El **total** de la factura es la suma de sus subtotales | `trg_prodfact_after_insert`, `_after_update` y `_after_delete` lo recalculan | no se puede |
| **RN-09** | Ni el subtotal ni el total viajan en la petición: **si llegan, se ignoran** | el controlador, que arma el cuerpo con los campos que acepta | — |

**RN-09, medido:** se mandó `{"total": 999, "detalle": [{"codigo":"PR002","cantidad":1,"subtotal":1}]}`
y la factura quedó con **total 800.000** —el precio real de PR002— y su
subtotal correcto. Ni el 999 ni el 1 llegaron a la tabla.

> **RN-07 es una decisión de negocio, no una optimización.** Si el subtotal se
> recalculara con el precio de hoy, subir el precio de un producto
> **reescribiría las ventas del año pasado**. Por eso se guarda aunque sea un
> dato derivado.
>
> **Y RN-08 explica el «total estimado» del front.** Lo que la pantalla
> muestra antes de emitir es un cálculo para que la persona sepa cuánto va. El
> total que queda guardado lo pone el disparador, y es el que manda. Dos
> fuentes, y gana la de la base de datos.

---

## 4. El inventario

| | Regla | Quién la defiende | Si se viola |
|---|---|---|---|
| **RN-10** | No se vende lo que no hay: el stock **nunca queda negativo** | `trg_prodfact_before_insert` y `_before_update`, con `SIGNAL SQLSTATE '45000'` | **409** · *«Stock insuficiente para producto PR003. Stock disponible: 39, cantidad solicitada: 9999»* |
| **RN-11** | Emitir una factura **baja** el stock de cada producto | los mismos disparadores | — |
| **RN-12** | **Anular** una factura **devuelve** el stock | `sp_anular_factura` | — |
| **RN-13** | Borrar una factura también devuelve el stock | `trg_prodfact_before_delete` y `_after_delete` | — |

> **RN-10 trae el número exacto en el mensaje**, y eso no es cosmético:
> «stock insuficiente» obliga a ir a mirar cuánto hay; «hay 39 y pidió 9999»
> deja corregir sin salir de la pantalla.

---

## 5. Anular y borrar

| | Regla | Quién la defiende | Si se viola |
|---|---|---|---|
| **RN-14** | Una factura anulada **se queda**, con su número y su fecha | `sp_anular_factura` hace `UPDATE`, no `DELETE` | — |
| **RN-15** | Una factura **no se anula dos veces** | `sp_anular_factura` | **409** · *«Factura 21 ya está anulada»* |
| **RN-16** | Anular una que no existe no es conflicto: es que no está | el mismo | **404** · *«Factura 9999 no existe»* |

**Y aquí este repositorio se aparta del de Python, así que vale decirlo
claro:** en php4 el **`DELETE /api/factura/{numero}` está activo** y responde
200. Borra la factura, su detalle —por `ON DELETE CASCADE` en
`fk_prodfact_factura`— y devuelve el stock.

| | php4 | El gemelo en Python |
|---|---|---|
| `DELETE /api/factura/{n}` | **activo**, 200 | escrito y **comentado** |
| Razón | el CRUD completo es criterio de la v1, y los cinco verbos se demuestran en todos los recursos | la numeración consecutiva no debe tener huecos |

> **Las dos posturas son defendibles y conviene conocerlas.** Un hueco en la
> numeración es una pregunta que alguien tendrá que responder en una
> auditoría — por eso existe **anular**, que deja el rastro: la factura
> existió y se dejó sin efecto. Borrar está disponible aquí porque esta ruta
> enseña los cinco verbos en cada recurso; **en un sistema de facturación de
> verdad, el `DELETE` se apaga.** Que esté encendido es una decisión del
> proyecto de aula, no una recomendación.

---

## 6. El control de acceso

| | Regla | Quién la defiende | Si se viola |
|---|---|---|---|
| **RN-17** | La contraseña **nunca se guarda en claro** | el repositorio, con `password_hash(..., PASSWORD_BCRYPT, ['cost' => 12])` antes del `INSERT` | — |
| **RN-18** | La contraseña **nunca sale** en una respuesta | los `SELECT` del repositorio, que no piden esa columna | — |
| **RN-19** | Un usuario puede tener **varios roles** | `pk_rol_usuario (fkemail, fkidrol)` | 409 al repetir la pareja |
| **RN-20** | Un rol alcanza **varias rutas**, y una ruta la alcanzan varios roles | `pk_rutarol (fkidruta, fkidrol)` | 409 al repetir |
| **RN-21** | El **permiso se consulta en cada petición**, no se lee del token | `verificar_acceso_ruta`, llamada por `exigirPermiso()` | — |
| **RN-22** | Lo que no está declarado **no pasa** | la lista blanca de `index.php`: lo que no es ruta abierta exige sesión | 401 |

> **RN-21 es la que hace el sistema administrable**, y se puede comprobar en
> dos peticiones: quítele el rol de Vendedor a `vendedor1` con su token ya
> emitido, y la misma petición a `/api/cliente` pasa de **200 a 403** sin que
> el token cambie ni venza.
>
> **RN-18 tiene una consecuencia visible:** `GET /api/usuario` devuelve
> `{"email": "..."}` y nada más. No es que el hash se oculte al serializar —
> es que el `SELECT` no lo trae.

---

## 7. Lo que NINGUNA regla defiende, y hay que decirlo

Un documento de reglas que solo lista lo protegido engaña por omisión. Las
cuatro están **medidas**: se intentaron y la API respondió 200.

| Lo que NO se valida | Qué pasa | Medido |
|---|---|---|
| Que el **crédito** del cliente alcance | Se factura por encima del cupo sin un aviso | cliente 2, cupo 250.000, factura de 5.000.000 → **200** |
| Que el correo de `persona` **tenga forma de correo** | Se acepta `aaa` | `POST /api/persona` con `"email": "aaa"` → **200** |
| Que el **valor unitario** sea mayor que cero | Un producto puede valer 0 | `"valorunitario": 0` → **200** |
| Que la **cantidad** de un renglón sea razonable | Un millón de unidades se acepta si hay stock | el único freno es RN-10 |

> **No están validadas a propósito**, y es material del proyecto de aula: cada
> una es un criterio de aceptación que un equipo puede escribir, defender y
> probar. Lo que no se vale es creer que ya están.
>
> Y fíjese en dónde iría cada una: el **formato** del correo es del
> controlador (422); el **crédito** es del servicio (400 o 409, porque
> necesita consultar la base de datos); el **valor unitario mayor que cero**
> puede ser del controlador o un `CHECK` de la base de datos — y la segunda
> opción es la que no se puede evadir.

---

## 8. Dónde comprobar cada regla

Ninguna fila de este documento es una opinión:

| Qué | Dónde |
|---|---|
| Las restricciones y los `DEFAULT` | `db/mariadb/init.sql` · `db/postgres/init.sql` · `db/sqlserver/bdfacturas.sql` |
| Los seis disparadores del detalle | el mismo archivo: `trg_prodfact_before_insert`, `_after_insert`, `_before_update`, `_after_update`, `_before_delete`, `_after_delete` |
| Los `SIGNAL` y sus mensajes | el mismo archivo — el del stock y el del mínimo de renglones |
| Cómo se traduce cada rechazo a HTTP | [`POLITICA_DE_ERRORES.md`](POLITICA_DE_ERRORES.md) |
| Qué significa cada término | [`GLOSARIO.md`](GLOSARIO.md) |
| Lo que quedó pendiente | [`PENDIENTES.md`](PENDIENTES.md) |
