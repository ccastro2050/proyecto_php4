# Glosario — Facturación (`bdfacturas`), en PHP

> **Qué es este documento.** Qué significa cada palabra **en este proyecto**.
> No es un diccionario general: cuando un término aquí se usa distinto a como
> suele usarse, lo dice.
>
> Las palabras que más confunden están primero.
>
> Versión 1.0 · 21 de octubre de 2026.

---

## 1. Las cinco que más se confunden

### «ruta», que significa dos cosas

| | Qué es | Ejemplo |
|---|---|---|
| **`ruta` (la tabla)** | Una **interfaz del sistema a la que se da permiso** | `/factura`, `/usuario`, `/home` |
| **la ruta (la dirección)** | La dirección de una petición o de una pantalla | `/api/factura/3`, `/facturas` |

> **El singular es el permiso; el plural, la pantalla.** `/factura` es lo que
> se le da a un rol; `/facturas` es la pantalla del front. Confundirlos deja
> el menú vacío y cuesta un rato encontrarlo, porque nada falla: simplemente
> `verificar_acceso_ruta` no encuentra el nombre y **nadie pasa**.

### «maestro» y «detalle»

| | Qué es | En este dominio |
|---|---|---|
| **Maestro** | La tabla que **existe por sí sola** | `factura` |
| **Detalle** | La que **no existe sin su maestro** | `productosporfactura` |

La prueba: ¿tiene sentido una fila de ésta sin la otra? Un renglón de factura
sin factura no significa nada.

### «anular» y «borrar»

| | Qué hace | Qué queda |
|---|---|---|
| **Anular** | `UPDATE factura SET estado = 'anulada'` y devuelve el stock | La factura, con su número y su fecha |
| **Borrar** | `DELETE`, que se lleva el detalle en cascada y devuelve el stock | Un hueco en la numeración |

En este repositorio **las dos están disponibles**. En un sistema de
facturación real el borrado se apaga: un hueco en la numeración es una
pregunta que alguien tendrá que responder en una auditoría.

### «401» y «403»

| | Qué dice | Qué hace la persona |
|---|---|---|
| **401** | «No sé quién es usted» | Identificarse |
| **403** | «Sé quién es, y no le toca» | Pedir el permiso |

### «la base de datos», no «la base»

En este curso se dice **la base de datos**, completo. «La base» a secas es
ambiguo: puede ser la base de una clase, la base de un cálculo o la carpeta
base del proyecto.

---

## 2. Del dominio

| Término | Qué es aquí |
|---|---|
| **Factura** | El encabezado: número, fecha, total, estado, cliente y vendedor |
| **Renglón** | Una fila de `productosporfactura`: un producto, su cantidad y su subtotal |
| **Subtotal** | `cantidad × valor unitario` **del momento en que se emitió**, congelado. Lo calcula un disparador |
| **Total** | La suma de los subtotales. Lo recalcula un disparador; **lo que mande el cliente se ignora** |
| **Stock** | Las unidades disponibles de un producto. Baja al facturar y vuelve al anular o borrar |
| **Crédito** | El cupo de un cliente. **Hoy es informativo**: nadie valida que la compra quepa |
| **Cliente** y **Vendedor** | Dos papeles de una misma `persona`. Por eso el nombre no está en esas tablas: está en `persona` |
| **Empresa** | Opcional en un cliente: `fkcodempresa` puede ser `null` para quien compra a nombre propio |

---

## 3. Del control de acceso

| Término | Qué es aquí |
|---|---|
| **Usuario** | Una cuenta. Su llave es el **correo**; guarda el hash de la contraseña |
| **Rol** | Un papel: Administrador, Vendedor, Cajero, Contador, Cliente |
| **`rol_usuario`** | Tabla **puente**: qué roles tiene cada usuario |
| **`rutarol`** | Tabla **puente**: qué rutas alcanza cada rol |
| **Permiso** | Que exista la fila `(ruta, rol)` y que el usuario tenga ese rol. **No viaja en el token** |
| **Token (JWT)** | Un JSON **firmado, no cifrado**: cualquiera lo lee, nadie lo cambia sin romper la firma. Dura una hora |
| **Fallar cerrado** | Que lo no declarado no pase: una ruta sin permiso declarado exige al menos sesión |
| **Lista blanca** | Las dos rutas que se sirven sin token: `GET /` y `POST /api/sesion` |

---

## 4. De la arquitectura

| Término | Qué es aquí |
|---|---|
| **Controlador** | Capa de HTTP: valida la forma, arma el sobre, decide el código |
| **Servicio** | Capa de negocio: las reglas. No sabe qué es un código HTTP |
| **Repositorio** | Capa de datos: el SQL y PDO. **Tres por recurso**, uno por motor |
| **Interfaz** | El contrato entre dos capas (`IRepositorioProducto`). Lo que permite cambiar la de abajo sin tocar la de arriba |
| **Ensamblador** | `servicios/ensamblador.php`: el único archivo que nombra clases concretas, con sus 17 `match` |
| **Modelo** | La ficha del dominio como objeto de PHP |
| **Sobre** | La forma fija de la respuesta: `{tabla, limite, total, datos}` en el CRUD; `{consulta, total, datos}` en las consultas |
| **Guardia** | `exigirSesion()` y `exigirPermiso()`: responden y cortan |

---

## 5. De PHP, para quien viene de otro lenguaje

| Término | Qué significa aquí |
|---|---|
| **PDO** | La biblioteca con la que PHP habla con cualquier motor. Una sola interfaz, tres controladores distintos |
| **Sentencia preparada** | `prepare` + marcadores `?`: el dato viaja **aparte** de la sentencia y el motor no lo interpreta como SQL. Es la defensa contra la inyección |
| **`declare(strict_types=1)`** | Que PHP no convierta tipos a escondidas. Tiene que ser **la primera** instrucción del archivo |
| **`match`** | Como un `switch`, pero compara con `===` y devuelve un valor |
| **`??` y `?:`** | `??` da el valor de la derecha si lo de la izquierda es nulo; `?:` si es «vacío» —y `"0"` es vacío para `?:`, que es por donde entran los errores |
| **Superglobales** | `$_GET`, `$_POST`, `$_SERVER`, `$_SESSION`: arreglos que PHP llena solo |
| **`password_hash` / `password_verify`** | El hash de contraseñas que trae el lenguaje. `password_verify` devuelve `false` ante un hash malformado, **sin lanzar nada** |
| **`hash_equals`** | Compara en tiempo constante. Con `===`, el tiempo delata cuántos bytes se acertaron |

---

## 6. Del curso

| Término | Qué significa |
|---|---|
| **Requisito** | Lo que el sistema **hace**. El término del curso es «requisito», no «requerimiento» |
| **Regla de negocio** | Lo que **no puede dejar de ser cierto** |
| **Elicitación** | Sacarle los requisitos a quien tiene el problema, preguntando |
| **Spec kit** | Los nueve documentos de cada versión, escritos **antes** del código |
| **Criterio de aceptación** | La condición que decide si una versión está terminada. Si no se puede comprobar, no es criterio |
| **Regresión** | Volver a correr los criterios de las versiones anteriores |
| **Interpretabilidad** | Poder explicar en voz alta el código que uno entregó |
| **Versión acumulativa** | Cada versión **contiene** a la anterior |
