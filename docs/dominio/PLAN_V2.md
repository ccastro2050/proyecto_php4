# Plan de la versión 2 — llaves foráneas, maestro-detalle y el 409

> **Qué es este documento.** Qué agrega la v2, qué se decidió antes de
> programarla, y **los tropiezos que tiene preparados** — varios de los cuales
> fallan en silencio, que es lo que los hace caros.
>
> El requisito que manda está en
> [`2_spec.md`](../spec_kit/versiones/v2_mas_tablas/2_spec.md). Esto es el
> relato.
>
> Versión 1.0 · 22 de octubre de 2026.

---

## 0. Lo que queda al terminar la v2

**Seis recursos** —`producto`, `empresa`, `persona`, `cliente`, `vendedor` y
`factura`—, con sus llaves foráneas, su maestro-detalle y sus pantallas.

| | v1 | v2 |
|---|---|---|
| Tablas en juego | 1 | **7** |
| Llaves foráneas | 0 | las de cliente, vendedor y factura |
| Procedimientos almacenados | 0 | los de la facturación |
| Disparadores | 0 | los del detalle: subtotal, stock y total |
| Códigos nuevos | — | **409** |

---

## 1. La decisión que define la versión: **¿422 o 409?**

Llega un `POST /api/cliente` con `"fkcodpersona": "P999"`, que no existe.
¿Qué responde?

| | Qué diría | Por qué **no** |
|---|---|---|
| **422** | «el body no tiene la forma» | Pero **sí la tiene**: es un texto de hasta 10 caracteres, como pide el contrato |
| **409** ✅ | «choca con lo que ya hay» | Exacto: la forma está bien y el dato **apunta a algo que no existe** |

> **Y eso obliga a una decisión de arquitectura**, que es lo que de verdad
> enseña esta versión: para responder 409 hay que **traducir el error del
> motor**. MariaDB dice `1452`; alguien tiene que convertir ese número en una
> frase en castellano — y ese alguien es **el repositorio**, no el controlador.
>
> Si el controlador conociera el `1452`, cambiar de motor en la v3 obligaría a
> tocar el controlador. La traducción vive abajo precisamente para que arriba
> nadie se entere.

---

## 2. La segunda decisión: **la factura no escribe SQL de tablas**

El encabezado y sus renglones entran **en un solo envío**, y los guarda **un
procedimiento almacenado**.

| | Qué sería | Qué pasa |
|---|---|---|
| Un `INSERT` por renglón desde PHP | El repositorio recorre la lista | Si el tercero falla, quedan **media factura** y el stock descontado de dos productos |
| **Un `CALL` con el detalle en JSON** ✅ | El procedimiento lo abre y lo recorre **dentro de una transacción** | O entran todos los renglones, o no entra ninguno |

Y de ahí sale la otra mitad: **el subtotal y el total los calculan los
disparadores**, no PHP. Lo que el cliente mande en esos campos **se ignora**.

> **Por qué congelar el subtotal aunque sea un dato derivado.** Si se
> recalculara con el precio de hoy, subir el precio de un producto
> **reescribiría las ventas del año pasado**. No es una optimización: es el
> requisito.

---

## 3. El orden en que se construyó

| # | Paso | Verificar |
|---|---|---|
| 1 | Las seis tablas con sus `FOREIGN KEY` | Que el motor rechace una referencia rota |
| 2 | El traductor de errores de MariaDB | Que 1062, 1452 y 1451 den **tres mensajes distintos** |
| 3 | Los cinco recursos sencillos | Sus cinco verbos, con 409 donde toca |
| 4 | Los disparadores del detalle | Que el total cambie **sin que la API lo mande** |
| 5 | El procedimiento de la factura | Que una factura sin renglones se rechace |
| 6 | Las pantallas, con desplegables | Que **ninguna llave foránea se digite** |

---

## 4. Los tropiezos de esta versión

| | Qué pasa | Cómo se nota |
|---|---|---|
| **El `DELIMITER` olvidado** | El script se parte en el primer `;` del cuerpo del procedimiento. **Las tablas sí se crean** | La API responde *PROCEDURE … does not exist* — y uno busca el error en el PHP |
| **El SQLSTATE que no distingue** | `getCode()` de PDO devuelve `'23000'` para **todas** las violaciones de integridad | Todos los rechazos dicen lo mismo. Hay que leer `errorInfo[1]`, que trae el número del motor |
| **El cursor sin cerrar** | Un `CALL` que devolvió filas deja resultados pendientes en la conexión | La **siguiente** consulta falla con el error 2014 — lejos de donde está la causa |
| **El disparador de un solo evento** | En MariaDB cada disparador atiende **un** evento: `INSERT`, `UPDATE` y `DELETE` son tres | Editar o borrar un renglón deja el total mal |
| **El desplegable con el nombre y la clave** | Tiene que **mostrar el nombre** y **mandar la clave** | Si manda el nombre, el 409 habla de un código que nadie escribió |

---

## 5. Cómo se verificó

| Criterio | Cómo |
|---|---|
| Una referencia rota responde **409 en castellano** | `POST /api/cliente` con una persona inexistente |
| Una factura sin renglones se rechaza | `POST /api/factura` con `"detalle": []` → **422** |
| El total lo pone la base de datos | Mandar `"total": 999` y ver que la factura queda con el total real |
| El stock baja al facturar y vuelve al anular | Mirar `producto.stock` antes y después |
| Ninguna clave foránea se digita | Abrir los formularios: todos los campos de FK son desplegables llenos |

---

## 6. Lo que queda para la v3

**El segundo motor.** La v2 cierra con todo funcionando contra MariaDB, y la
pregunta queda abierta: si las capas están bien cortadas, agregar PostgreSQL
no debería tocar ni un controlador ni una pantalla.

> Ésa es exactamente la apuesta que la v3 cobra. Ver [`PLAN_V3.md`](PLAN_V3.md).
