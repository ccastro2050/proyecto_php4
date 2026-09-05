# Los principios ACID

> Documento conceptual del curso. Usted ya vio bases de datos — este documento
> repasa ACID con los ojos de este proyecto: por qué una API de facturación
> DEPENDE de estas cuatro garantías y dónde se ven en cada versión.

---

## 1. Qué es ACID y de dónde viene

ACID es el acrónimo de las **cuatro garantías que un motor transaccional le da
a cada transacción**: *Atomicity, Consistency, Isolation, Durability*. El
término fue acuñado por Theo Härder y Andreas Reuter (1983) formalizando el
trabajo de Jim Gray sobre transacciones. Una **transacción** es una unidad de
trabajo: una o más operaciones que deben tratarse como un todo.

```sql
BEGIN;                                        -- empieza la transacción
INSERT INTO factura (…) VALUES (…);
INSERT INTO productosporfactura (…) VALUES (…);
UPDATE producto SET stock = stock - 2 WHERE codigo = 'PR003';
COMMIT;                                       -- todo queda — o ROLLBACK y nada queda
```

## 2. Las cuatro garantías, con los ejemplos de este proyecto

### A — Atomicidad
> La transacción se ejecuta COMPLETA o NO se ejecuta. No hay mitades.

**Ejemplo del proyecto (v2+):** una factura son dos escrituras (cabecera +
renglones) más el descuento de stock. Si el tercer renglón falla porque no hay
stock, **todo** se revierte: no puede quedar una factura sin renglones ni stock
descontado sin factura. El dinero no acepta "casi".

### C — Consistencia
> La BD pasa de un estado válido a otro estado válido: las reglas declaradas
> (PK, FK, CHECK, triggers) se cumplen SIEMPRE.

**Ejemplo desde la v1:** las **llaves foráneas**. Intente borrar el producto
`PR001` (aparece en facturas): el motor lo rechaza, porque dejaría renglones
de factura apuntando a un producto fantasma. La BD no acepta pasar a un
estado inválido — aunque la API o el SQL se lo pidan.

### I — Aislamiento (*Isolation*)
> Transacciones concurrentes no se ven las mitades a medio hacer: el resultado
> es como si hubieran ocurrido una después de otra.

**Ejemplo del proyecto (v2+):** dos usuarios facturan el mismo producto al
mismo tiempo con stock = 1. Sin aislamiento, ambos leen "hay 1" y ambos
venden: stock = -1. Con aislamiento, el motor serializa el acceso y el segundo
falla limpiamente. (Los niveles de aislamiento — read committed, repeatable
read, serializable — gradúan cuánto pagas de rendimiento por cuánta protección.)

### D — Durabilidad
> Después del COMMIT, el dato sobrevive a caídas del servidor: está en disco
> (write-ahead log), no solo en memoria.

**Ejemplo del proyecto (desde la v1):** apague el contenedor de MariaDB
(`docker compose down`) y vuélvalo a encender: los productos creados siguen
ahí — el volumen conserva los datos y el registro de transacciones de InnoDB
garantiza que lo confirmado no se pierde.

## 3. Justificación: por qué le importa a ESTE proyecto

Un sistema de **facturación** es el ejemplo canónico de sistema transaccional:
maneja dinero, inventario y relaciones (cliente-factura-producto) donde una
inconsistencia no es un bug estético sino plata perdida. Por eso:

1. La lógica crítica (totales, stock) vivirá **en la BD** (trigger, v2): el
   motor la ejecuta DENTRO de la transacción, con las cuatro garantías.
2. La API **nunca** hace "dos peticiones y ojalá lleguen ambas": lo que debe
   ser atómico se hace en una transacción del motor.
3. Los tres motores del proyecto (PostgreSQL, MariaDB/InnoDB, SQL Server) son
   ACID — por eso las versiones v3/v4 pueden intercambiarlos sin cambiar las
   garantías.

## 4. El contraste: ACID vs BASE

Los sistemas distribuidos de gran escala (redes sociales, IoT) a veces
sacrifican estas garantías por disponibilidad: **BASE** (*Basically Available,
Soft state, Eventually consistent*) — "los datos cuadrarán… eventualmente".
Es una elección válida para un feed de fotos e inaceptable para una factura:
**el inventario no puede cuadrar "eventualmente"**. Saber CUÁNDO exigir ACID y
cuándo relajarlo es criterio de arquitecto, no dogma.

## 5. Verlo con sus propias manos (ejercicio, v1)

```sql
-- En un cliente SQL contra la BD de la v1 (HeidiSQL/DBeaver a localhost:13326):
BEGIN;
UPDATE producto SET stock = 0 WHERE codigo = 'PR001';
SELECT stock FROM producto WHERE codigo = 'PR001';   -- 0 (dentro de la transacción)
ROLLBACK;
SELECT stock FROM producto WHERE codigo = 'PR001';   -- 17 otra vez: atomicidad

DELETE FROM producto WHERE codigo = 'PR001';
-- ERROR: viola la FK de productosporfactura → consistencia:
-- el motor no acepta dejar renglones de factura huérfanos
```

## 6. Referencias

1. Härder, T. & Reuter, A. — *Principles of Transaction-Oriented Database
   Recovery* (ACM Computing Surveys, 1983): el artículo que acuñó "ACID".
2. MariaDB — transacciones (START TRANSACTION, COMMIT, ROLLBACK):
   <https://mariadb.com/kb/en/start-transaction/>
3. MariaDB — niveles de aislamiento (SET TRANSACTION):
   <https://mariadb.com/kb/en/set-transaction/>
4. Kleppmann, M. — *Designing Data-Intensive Applications* (O'Reilly, 2017),
   cap. 7: la mejor discusión moderna de ACID, aislamiento y sus trampas.
5. En este repositorio: las FK de `bdfacturas` en el
   [modelo de datos de la v1](spec_kit/versiones/v1_producto_mariadb/5_data_model.md);
   el trigger de totales/stock llegará con la v2.
