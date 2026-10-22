# Plan de la versión 4 — el tercer motor y el aplicativo

> **Qué es este documento.** Qué agrega la v4, en qué orden se construyó y
> **los seis tropiezos que aparecieron midiendo** — cuatro de los cuales no
> fallaban: respondían algo razonable y equivocado.
>
> El requisito que manda está en
> [`2_spec.md`](../spec_kit/versiones/v4_aplicativo/2_spec.md), y los
> tropiezos están contados con detalle en
> [`4_research.md`](../spec_kit/versiones/v4_aplicativo/4_research.md).
>
> Versión 1.0 · 22 de octubre de 2026.

---

## 0. Lo que queda al terminar la v4

| | v3 | v4 |
|---|---|---|
| Motores | 2 | **3** — con SQL Server |
| Recursos | 6 | **11** |
| Operaciones | ~40 | **81**, más las diez consultas |
| Control de acceso | las tablas, sin usar | **encendido**: token, 401 y 403 |
| Consultas de reporte | 0 | **10**, de cuatro o más tablas |
| Pantallas | 7 | **17**, con tablero y administración |

---

## 1. Las tres cosas que entraron, y en ese orden

**Primero los recursos, después la puerta, al final las consultas.** No es
casual: cerrar la API antes de tener los recursos obliga a probar cada uno con
token desde el primer día, y entonces los fallos de las dos cosas se
confunden — un 403 inesperado puede ser un permiso mal dado o un repositorio
mal escrito, y separarlos cuesta horas.

| | Qué entró | Por qué en esta versión |
|---|---|---|
| **A** | Los cinco recursos del acceso: `rol`, `ruta`, `usuario` y los dos puentes | Las tablas estaban desde la v1 y no las usaba nadie. Una tabla que existe y no se usa es una promesa sin cumplir |
| **B** | El token, la guardia, el 401 y el 403 | Sin esto, el CRUD de usuarios es una lista de correos sin consecuencia |
| **C** | `usuario-con-roles`, las diez consultas y el tablero | Crear un usuario con dos roles eran tres peticiones; y nada respondía «qué se vende y cuánto deja» |

---

## 2. Las decisiones que definen la versión

### El permiso NO va en el token

Cabría —el token es un JSON firmado— y sería más rápido. Está descartado a
propósito: **si el permiso viajara dentro, quitarle un rol a alguien no
tendría efecto hasta que su token venciera.** Se paga una consulta por
petición para que la tabla sea la verdad y no una copia vieja.

Y se comprueba en dos peticiones: quítele el rol de Vendedor a `vendedor1`
**con su token ya emitido** y `/api/cliente` pasa de 200 a **403**.

### La guardia, una sola vez

En .NET la guardia es un atributo sobre la clase y protege también el método
que alguien escriba mañana. En PHP puro no hay quien haga eso, así que se hizo
al revés: **una lista blanca de dos rutas y una sola llamada arriba de todo**.
Para dejar un endpoint abierto por accidente habría que agregarlo a la lista
blanca — y eso se ve en el `diff`.

### Las consultas NO llevan procedimientos

Los procedimientos de este proyecto están donde hay **una regla que
proteger** —la facturación, el usuario con sus roles: transacciones—, no
donde solo hay un `SELECT`. Una consulta de reporte cambia cada vez que
alguien quiere verla de otra forma, y tenerla en un procedimiento obliga a
tocar la base de datos para cambiar un `ORDER BY`.

---

## 3. Los seis tropiezos, y por qué cuatro no fallaban

| | Qué pasó | ¿Fallaba? |
|---|---|---|
| **1** | `(bool) "0"` es **verdadero** en PHP, y MariaDB devolvía el permiso como texto: **todo el mundo tenía acceso a todo** | **No.** Respondía 200 |
| **2** | MariaDB 11.5 cambió la colación por defecto: comparar `u.email = p_email` pasó a ser el error **1267** | Sí, y fuerte |
| **3** | Un `CREATE PROCEDURE` cortado **se crea sin quejarse**; falla al llamarlo, con un mensaje que no tiene que ver con la causa | A destiempo |
| **4** | Un reemplazo de texto tomó la **primera** coincidencia, que estaba dentro de un procedimiento y no en la siembra | Sí, pero con el mensaje equivocado: *409 ya existe* |
| **5** | **Los ocho procedimientos de MariaDB no tenían transacción**: un PATCH con un rol inexistente respondía 409 **y dejaba al administrador sin roles** | **No.** El 409 era correcto |
| **6** | La explicación del `CAST` de T-SQL que llevaba cuatro repositorios copiándose **era falsa** | **No.** El CAST estaba puesto y funcionaba |

> **El 5 es el más serio y el 6 el más instructivo.** De los tres motores,
> solo MariaDB perdía datos —SQL Server ya traía su `ROLLBACK` y en PostgreSQL
> una función es atómica sola—, y el camino feliz se veía idéntico en los
> tres. El 6 enseña otra cosa: **lo que se cree de un motor y lo que el motor
> responde son dos cosas distintas**, y solo una de las dos se puede medir.

---

## 4. Cómo se verificó

| Criterio | Cómo |
|---|---|
| Las diez consultas valen en los **tres** motores | `php pruebas/prueba_consultas_tres_motores.php`, que compara **columnas y valores**, no códigos de respuesta |
| El 401 y el 403 | Sin token, y con el token de `vendedor1` contra `/api/usuario` |
| El permiso no está en el token | 200 → 403 con el mismo token, tras quitar el rol |
| La operación de dos tablas es atómica | PATCH con un rol inexistente: 409 **y el usuario conserva sus roles** |
| El front no ofrece lo que la API rechaza | Con `vendedor1`: tres tarjetas y sin «Administrar» |
| Los 11 recursos y las 10 consultas | 118 peticiones de Postman, corridas dos veces seguidas |

---

## 5. Lo que esta versión NO hizo, y está anotado

| | Por qué |
|---|---|
| El front **no aplica el manual de marca** | Criterio de la v4 **en rojo**. Ver [`MANUAL_DE_MARCA.md`](MANUAL_DE_MARCA.md) §6 |
| Un producto inexistente en una factura responde **500**, no 409 | El disparador se queja antes que la llave foránea. Es igual en los cinco repositorios del semestre |
| No hay lista negra de tokens, ni bloqueo por intentos, ni auditoría | Cada uno con su razón en [`PENDIENTES.md`](PENDIENTES.md) §3 |
| El front no renueva el token | Cuando vence, la siguiente petición responde 401 y manda a `/entrar`. Honesto, y suficiente para un proyecto de aula |

---

## 6. Y aquí cierra la ruta

**No hay v5.** Lo que un v5 tendría —otro motor, u otro front— ya está
contado: los motores son el tema de la v3 y la v4, y el front va **dentro de
cada versión** desde la v1, no al final como premio.

> Una ruta de versiones también se diseña sabiendo dónde parar.
