# Investigación y decisiones — Versión 2

> **Versión 2** · **Lectura opcional** (el porqué de las decisiones del plan,
> con las alternativas que se evaluaron y descartaron). Complementa a
> [3_plan.md](3_plan.md); el orden de trabajo está en [8_tasks.md](8_tasks.md).

---

## D1 — El rechazo del motor sale como 409, no como 500

**Alternativas descartadas:** dejarlo como 500 (que es lo que hace la v1), o
convertirlo en 400.
**Decisión:** **409 Conflicto**, con el motivo en español.
**Por qué:**

En la v1 se decidió 500 a propósito, y estaba bien decidido: la integridad no
era tema de esa versión, la única regla era la llave primaria, y traducirla
habría sido lógica que la spec no pedía.

En la v2 la integridad **es** el tema, así que la balanza cambia. Y los tres
códigos dicen cosas distintas:

| | Qué le comunica al que llama |
|---|---|
| **500** | «Se nos dañó algo». Es mentira: el sistema funcionó perfectamente |
| **400** | «Su petición está mal escrita». También es mentira: estaba bien escrita |
| **409** | «Su petición está bien, pero no cabe en los datos que hay» |

El 409 es el único que dice la verdad, y la diferencia se nota donde importa:
en la pantalla. Con 500 el usuario lee «error interno» y llama a soporte; con
409 lee «no se puede eliminar la persona porque hay otras fichas que dependen
de ella» y sabe qué hacer.

**Precio asumido:** hay que reconocer los códigos del motor, y eso ata un
archivo a MariaDB. Se paga aislándolo (`errores_de_integridad.php`), que es
justo lo que la v3 va a poner a prueba.

## D2 — No se pregunta antes: se intenta y se traduce el veredicto

**Alternativa descartada:** que el servicio comprobara primero («¿existe la
persona PE001?») y solo entonces insertara.
**Decisión:** intentar la escritura y traducir el «no» del motor.
**Por qué, con las dos razones que lo deciden:**

1. **La comprobación previa no evita el problema, lo hace más raro.** Entre
   el `SELECT` que comprueba y el `INSERT` que escribe, otro usuario puede
   borrar esa persona. La ventana es de milisegundos, y por eso el error
   aparece una vez cada mil — que es exactamente lo que lo vuelve difícil de
   encontrar. Un error que falla siempre se arregla; uno que falla a veces se
   convierte en leyenda.
2. **Duplica una regla que ya está escrita.** La llave foránea vive en la
   base. Comprobarla también en PHP significa mantener el mismo negocio en
   dos idiomas, y el día que cambie uno solo, el sistema miente.

**Precio asumido:** el mensaje de error lo redacta el traductor a partir del
código del motor, no del caso concreto, así que es un poco más general de lo
que podría ser («alguno de los códigos a los que apunta no existe», sin decir
cuál). Se prefirió eso a la carrera del punto 1.

## D3 — La factura no tiene la misma interfaz que las demás

**Alternativa descartada, y era la cómoda:** darle a `IRepositorioFactura` los
mismos cinco métodos que a los demás (`obtenerTodos`, `obtenerPorClave`,
`crear`, `actualizar`, `eliminar`) y meter «anular» adentro de `actualizar`.
**Decisión:** una interfaz distinta, con `obtenerTodas`, `obtenerPorNumero`,
`crear`, `reemplazar`, `anular` y `eliminar`.
**Por qué:** copiar el CRUD habría sido más rápido y habría **descrito mal el
negocio**. Una factura no se «actualiza»: se reemplaza entera, porque cambiar
un renglón cambia el total y el stock. Y anular no es ni corregir ni borrar —
la factura se queda, con su número y su fecha, y el stock vuelve.

Cuando la forma de la interfaz no coincide con lo que la cosa hace, el que
programa termina metiendo `if`s para tapar la diferencia. La interfaz dice lo
que la factura **hace**, no lo que las demás entidades hacen.

**Lo que esto demuestra, y es la razón de que esté escrito aquí:** si esta
API fuera genérica —una ruta `/api/{tabla}` con las mismas seis operaciones
para todo—, esta decisión **no habría sido posible**. Habría habido PATCH de
facturas porque las demás lo tienen, y no habría habido `/anular` porque las
demás no lo necesitan.

## D4 — Las llaves autogeneradas se devuelven, no se adivinan

**Alternativa descartada:** que el `POST` respondiera solo `{estado, mensaje}`
como en la v1, y que el cliente buscara después su ficha.
**Decisión:** el `POST` devuelve el `id` que generó la base.
**Por qué:** ese id **no existía antes de la petición**, y el cliente no tiene
ninguna forma de deducirlo. Sin devolverlo, la única salida sería listar y
adivinar cuál es el nuevo — que además falla si dos personas crean fichas a la
vez.

En el modelo, la llave quedó como `?int`: una ficha recién construida y
todavía sin guardar **no tiene** id, y el tipo lo dice. Poner un `0` habría
sido inventarse un valor que ya significa otra cosa.

## D5 — El formulario de factura tiene cinco renglones fijos

**Alternativas descartadas:** un botón «agregar renglón» con JavaScript, o una
pantalla de dos pasos (primero el encabezado, después los renglones uno a uno).
**Decisión:** cinco casillas, y las vacías se ignoran al armar el cuerpo.
**Por qué:** un formulario que crezca solo necesita JavaScript, y el
Artículo 2 pide que lo que se vea sea PHP, no magia. La pantalla de dos pasos
resolvía eso, pero rompía lo que la versión viene a enseñar: **el encabezado y
el detalle se guardan juntos, en una sola operación.** Partirla en dos pasos
habría hecho posible una factura a medio crear, que es exactamente lo que el
procedimiento almacenado existe para impedir.

**Precio asumido, y está declarado en la spec (RF19), no escondido:** una
factura de seis productos no cabe en esta pantalla. Es una limitación **de la
pantalla**; la API acepta los renglones que sean.

## D6 — Se corrigieron dos cosas del script de la base, y aquí está cuáles

La base viene dada, y la regla del curso es que no se toca. Esta versión hizo
dos excepciones, y por eso quedan escritas — un artefacto que cambia sin que
nadie diga por qué es peor que uno que no cambia.

**1. El procedimiento de listar no devolvía el `estado`.**
`sp_listar_facturas_y_productosporfactura` traía número, fecha, total,
cliente, vendedor y productos, pero no el estado. Los otros cinco
procedimientos sí lo devolvían. Con eso, la pantalla del listado no podía
distinguir una factura anulada de una activa — y anular es media lección de
esta versión. Se agregó el campo.

**2. Se podía REEMPLAZAR una factura ya anulada.**
`sp_actualizar_factura_y_productosporfactura` comprobaba que la factura
existiera, pero no que estuviera activa. El resultado: la factura seguía
marcada «anulada» y sin embargo el stock se volvía a mover y el total
cambiaba. `sp_anular_factura` ya tenía esa misma guarda; a este se le había
quedado. Se agregó, con el mismo estilo del procedimiento vecino.

**Cómo se encontraron los dos:** probando el sistema, no leyendo el script. El
primero apareció al pintar la pantalla del listado (faltaba un dato que la
columna necesitaba); el segundo, al correr el ciclo completo y ver que el
stock cambiaba en una factura que ya estaba anulada.

## D7 — El detalle viaja anidado dentro del encabezado

**Alternativa descartada:** dos recursos separados —`/api/factura` y
`/api/factura/{n}/renglones`— como hacen algunas APIs.
**Decisión:** un solo recurso; el `GET` devuelve el encabezado con su
`detalle` adentro, y el `POST` los recibe juntos.
**Por qué:** son maestro-detalle, no dos cosas que se relacionan. Una factura
sin renglones no es una factura incompleta: es inválida, y la base lo impide.
Partirla en dos recursos habría hecho que el camino normal fueran dos
peticiones y que existiera un estado intermedio —una factura sin renglones—
que el negocio no admite.

Se nota en la pantalla: `facturas_detalle.php` hace **una** llamada y pinta
todo. Con dos recursos tendría que hacer dos y coserlas.

## D8 — Los formularios ofrecen desplegables para las llaves foráneas

**Alternativa descartada:** una casilla de texto donde se teclea el código.
**Decisión:** un `<select>` con los nombres.
**Por qué:** teclear un identificador que hay que ir a buscar a otra pantalla
es de las cosas que hacen odiar un sistema. Cuesta tres peticiones más al
abrir el formulario, y las vale.

**Y lo que NO significa, que es lo importante:** el desplegable no valida
nada. La lista pudo cargarse hace un minuto y esa ficha pudo borrarse
entretanto; y cualquiera puede mandar un `POST` sin pasar por la pantalla.
Quien defiende la integridad sigue siendo la base — lo de arriba solo hace el
formulario cómodo. Confundir «la pantalla no deja escoger eso» con «eso no
puede pasar» es uno de los errores más caros que se cometen escribiendo
software.

## D9 — El servicio de facturas es el más corto del proyecto, y está bien

**Alternativa descartada:** recalcular en PHP el total y los subtotales para
«no depender de la base».
**Decisión:** el servicio de facturas casi no hace nada — valida el número,
normaliza el detalle y delega.
**Por qué:** las reglas de la factura ya están escritas en la base (los
triggers calculan, los procedimientos exigen al menos un renglón y se niegan
a anular dos veces). Repetirlas en PHP produce dos cuentas del mismo número,
y el día que discrepen nadie sabe cuál creer.

Un servicio flaco no es un servicio mal hecho. Lo que sí hace, y por eso
existe, es lo que la base no puede saber: qué significa «no existe» para esta
API, y qué forma tiene el detalle que llega del cliente. En particular,
`normalizarDetalle()` se queda solo con `codigo` y `cantidad`: si el cliente
mandó además un `subtotal`, se ignora — el subtotal no lo decide quien
factura.
