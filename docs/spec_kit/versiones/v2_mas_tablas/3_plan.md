# Plan técnico — Versión 2: seis recursos, integridad y maestro-detalle

> **Versión 2** · CÓMO construir lo especificado en [2_spec.md](2_spec.md).
> El porqué de cada decisión: [4_research.md](4_research.md) · contratos
> exactos: [6_contracts.md](6_contracts.md) · orden: [8_tasks.md](8_tasks.md).

---

## 1. Stack

El mismo de la v1, sin una dependencia nueva. Lo que cambia no es la
tecnología: es cuántas piezas hay y cómo se relacionan.

| Pieza | Elección | Qué cambia respecto de la v1 |
|---|---|---|
| Lenguaje | **PHP 8.3**, `declare(strict_types=1)` | nada |
| Framework / paquetes | **NINGUNO** | nada |
| Acceso a datos | **PDO** con prepared statements | **+ llamadas a procedimientos almacenados** para las facturas |
| Front | PHP puro, plantillas, Bootstrap local | seis secciones en vez de una |
| Motor | **MariaDB**, y solo MariaDB | nada (el segundo llega en v3) |

## 2. Estructura de carpetas

```
(raíz del proyecto)
├── docker-compose.yml                # mariadb + api-facturas + front-php + phpmyadmin
├── db/
│   └── init.sql                      # la BD completa, PROVISTA (con dos correcciones: D6)
│
├── api_facturas/                     # puerto 8026
│   ├── Dockerfile
│   ├── index.php                     # front controller: seis bloques de rutas
│   ├── modelos/
│   │   ├── Producto.php              # de la v1
│   │   ├── Empresa.php  Persona.php
│   │   ├── Cliente.php  Vendedor.php
│   │   ├── Factura.php               # el ENCABEZADO
│   │   └── LineaFactura.php          # un renglón del detalle
│   ├── controladores/                # uno por recurso (6)
│   ├── servicios/
│   │   ├── IServicio*.php  Servicio*.php     # 6 pares
│   │   └── ensamblador.php           # 6 funciones, una por recurso
│   ├── repositorios/
│   │   ├── IRepositorio*.php  Repositorio*MariaDB.php   # 6 pares
│   │   └── errores_de_integridad.php  ← LO NUEVO: traduce el veredicto del motor
│   ├── excepciones/
│   │   ├── NoEncontradoExcepcion.php
│   │   └── ConflictoDeIntegridadExcepcion.php  ← LO NUEVO
│   └── pruebas/
│       └── prueba_capas.php          # los servicios con repositorios falsos
│
├── front_php/                        # puerto 8024
│   ├── index.php                     # seis bloques de pantallas
│   ├── cliente_api.php               # una función por operación (más de 30)
│   ├── vistas/
│   │   ├── plantilla.php  inicio.php  no_encontrada.php
│   │   ├── {recurso}_lista.php  {recurso}_formulario.php   # 5 pares
│   │   ├── facturas_lista.php  facturas_formulario.php
│   │   └── facturas_detalle.php      ← una pantalla que ningún otro recurso tiene
│   └── publico/                      # Bootstrap guardado aquí, no traído de un CDN
│
└── pruebas_humo/
    └── humo_front.py                 # el recorrido desde la pantalla
```

**Cuente los archivos y fíjese en el patrón:** cinco recursos tienen
exactamente las mismas piezas con distinto nombre. La factura tiene las
mismas carpetas y **contenidos distintos** — dos modelos en vez de uno, una
vista de más, y ninguna operación de «actualizar parcial». La estructura
aguanta la diferencia sin deformarse: eso es lo que se le pide a una
arquitectura.

## 3. Arquitectura en capas (el flujo no cambió)

```
NAVEGADOR
     → front_php/index.php      (ruta → pantalla)
     → cliente_api.php          (la única pieza que habla HTTP hacia afuera)
     ↓  HTTP + JSON  — aquí termina un proceso y empieza otro
     → api_facturas/index.php   (método + ruta → controlador)
     → Controlador{Recurso}     (valida la forma del body → 422)
     → IServicio{Recurso}       (interfaz — reglas de negocio)
     → IRepositorio{Recurso}    (interfaz — el servicio no sabe qué motor hay)
     → Repositorio{Recurso}MariaDB   (PDO; en factura, procedimientos)
     → MariaDB                  (llaves foráneas, triggers, procedimientos)
```

Lo que la v2 agrega es una flecha **de vuelta**: la base ya no solo guarda,
también **dice que no**. Ese «no» sube por el repositorio, que lo traduce, y
sale por el controlador como un 409.

## 4. Decisiones de diseño clave

### 4.1 `errores_de_integridad.php`: dónde se traduce el «no» del motor

Un archivo con una función, y vive en `repositorios/` por una razón que vale
la pena entender:

```php
function traducirErrorDeIntegridad(PDOException $error, string $ficha): never
{
    $codigo = $error->errorInfo[1] ?? 0;
    if ($codigo === 1062) { /* llave duplicada        */ }
    if ($codigo === 1452) { /* apunta a algo que no existe */ }
    if ($codigo === 1451) { /* otros dependen de esto */ }
    throw $error;   // no lo reconozco: que se vuelva un 500
}
```

**Los números 1062, 1452 y 1451 son de MariaDB.** PostgreSQL usa otros y SQL
Server otros. El repositorio es la única capa que tiene derecho a conocerlos,
porque es la única que sabe qué motor hay detrás. Si esta traducción viviera
en el servicio, cambiar de motor obligaría a tocar el negocio — que es
exactamente lo que la arquitectura por capas viene a evitar.

Hacia arriba no sale un número: sale una `ConflictoDeIntegridadExcepcion` con
el problema dicho en español.

> **Detalle que se aprendió probando:** `$e->getCode()` de PDO devuelve el
> SQLSTATE genérico —`'23000'` para *todas* las violaciones de integridad—, y
> con eso no se distingue «llave repetida» de «apunta a algo que no existe».
> El número que sirve está en `$e->errorInfo[1]`.

### 4.2 Las llaves que genera la base

Tres cambios encadenados, y conviene verlos juntos:

| Pieza | Con llave del cliente (`producto`) | Con llave de la base (`cliente`) |
|---|---|---|
| Modelo | `private string $codigo;` | `private ?int $id;` — **puede ser null**: una ficha sin guardar no tiene id |
| Repositorio `crear` | devuelve `bool` | devuelve `int`: **`lastInsertId()`** |
| Servicio `crear` | `void` | `int` — pasa el id hacia arriba |
| Controlador | valida la llave del body | **no la valida**: no viene. Y la devuelve en la respuesta |

El `?int` no es un tecnicismo: es el tipo diciendo la verdad. Poner un `0`
como «todavía no tiene id» habría sido inventarse un valor que significa
otra cosa.

### 4.3 La lista blanca, ahora con más trabajo

`filtrarColumnas()` ya existía en la v1. En la v2 hace algo que se nota: si
alguien manda `{"id": 99, "credito": 100}` en un `POST /api/cliente`, el `id`
**se cae ahí** y nunca llega al SQL. No es 422 —no es un error del cliente,
es un campo que esta API no acepta de nadie— y tampoco es un `id` que la base
vaya a respetar.

Lo mismo con `total` y `subtotal` en las facturas: los calcula un trigger, y
si vinieran en el body se ignoran.

### 4.4 El repositorio de facturas llama procedimientos, no escribe SQL

Los cinco repositorios de ficha arman su `SELECT` y su `INSERT`. El de
factura no: la base ya trae las seis operaciones escritas como procedimientos
almacenados, y este repositorio los llama.

**Cómo se lee un parámetro de salida con PDO.** Los procedimientos devuelven
su resultado en un `OUT` con un JSON adentro. PDO no lee los `OUT` de MariaDB
directamente; el camino normal es pasarle una variable de sesión del servidor
y después consultarla:

```php
$sql = "CALL $nombre(?, ?, @resultado)";
$sentencia->execute($parametros);
$sentencia->closeCursor();
$json = $conexion->query('SELECT @resultado')->fetchColumn();
```

Tres detalles que costaron una corrida cada uno, y por eso están escritos:

1. **`closeCursor()` no es opcional:** MariaDB no deja lanzar otra consulta
   mientras la anterior siga abierta, y la siguiente es justamente el
   `SELECT @resultado`.
2. **El caso de CERO parámetros de entrada hay que tratarlo aparte.**
   `sp_listar_facturas_y_productosporfactura` no recibe nada, y armar la lista
   de marcadores sin mirar dejaba un `CALL sp(, @resultado)` con una coma
   suelta que el motor rechaza por sintaxis.
3. **Los procedimientos rechazan con `SIGNAL SQLSTATE '45000'`**, y ahí llega
   el motivo del negocio: «requiere mínimo 1 producto», «ya está anulada»,
   «no existe». Todos con el mismo SQLSTATE, así que lo único que los
   distingue es el texto — ver §4.5.

### 4.5 Distinguir «no existe» de «no se deja»: una fragilidad declarada

Los rechazos de los procedimientos llegan todos con `SQLSTATE '45000'`, y no
significan lo mismo para quien llama:

- «Factura 999 no existe» → debe ser **404**;
- «Factura 7 esta anulada» → debe ser **409**;
- «La factura requiere minimo 1 producto» → **409**.

El único dato que los separa es el **texto del mensaje**, y mirar texto es
frágil: si mañana alguien reescribe el procedimiento con otras palabras, esto
deja de funcionar.

Se acepta, y se dice en voz alta en vez de disimularlo. El arreglo limpio
—que cada rechazo trajera su propio código— exigiría cambiar los seis
procedimientos y está fuera del alcance de esta versión. Queda **en un solo
método** (`interpretarRechazo`) justamente para que el día que se arregle, se
arregle ahí.

### 4.6 El controlador de facturas valida dos niveles

El body de una factura no es una ficha plana: trae una lista adentro.

```json
{ "fkidcliente": 1, "fkidvendedor": 2,
  "detalle": [ {"codigo": "PR001", "cantidad": 2},
               {"codigo": "PR003", "cantidad": 1} ] }
```

Validar eso es validar que `detalle` sea una lista con algo adentro **y** que
cada renglón traiga su código y su cantidad con el tipo correcto. Un mensaje
que dijera «el detalle es inválido» no sirve: hay que decir **cuál** renglón
y **por qué**, y por eso los errores salen numerados desde 1 (no desde 0: el
renglón 0 no existe para quien está mirando la pantalla).

### 4.7 `ensamblador.php`: seis funciones, y por qué no un `switch`

La tentación es una sola función `crearServicio(string $recurso)` con un
`switch` adentro, o peor, armar el nombre de la clase con texto
(`"Repositorio{$recurso}MariaDB"`).

Se descartó por lo mismo que la API es específica (Artículo 10):

- con seis funciones, PHP verifica los tipos y el error sale al llamarlas;
- con nombres armados en texto, el error sale **en producción**, cuando
  alguien pida el recurso que nadie probó;
- y quien lea el archivo ve el inventario completo sin ejecutar nada.

Cuando la v3 traiga el segundo motor, **es este archivo —y solo éste— el que
se convierte en una fábrica de verdad**.

### 4.8 El front: seis bloques y una excepción

`front_php/index.php` repite el mismo bloque de seis rutas para cinco
recursos. Es largo, y la alternativa era un enrutador con una tabla de
configuración: cuarenta líneas en vez de trescientas.

La prueba de que la repetición era el camino correcto está a la vista en el
mismo archivo: **el bloque de facturas no se parece a los demás.** No tiene
«guardar solo lo que cambié», tiene una pantalla de ver el detalle y tiene
«anular». Una tabla de configuración habría tenido que crecer con excepciones
hasta volverse ilegible, o habría obligado a que las facturas se comportaran
como lo que no son.

### 4.9 Los desplegables de las llaves foráneas

Los formularios de cliente, vendedor y factura ofrecen las opciones en un
`<select>` en vez de pedir que se teclee un código. Cuesta tres peticiones
más a la API al abrir la pantalla, y las vale.

**Lo que el desplegable NO hace es validar.** La lista pudo cargarse hace un
minuto y esa ficha pudo borrarse entretanto; y cualquiera puede mandar un
`POST` sin pasar por la pantalla. Quien defiende la integridad sigue siendo
la base de datos — el desplegable solo hace el formulario cómodo.

## 5. Docker: los mismos cuatro servicios

```yaml
services:
  mariadb:             # mariadb:11 + db/init.sql · 13327 al host · healthcheck
  api-facturas:        # build ./api_facturas · 8026 · DB_DSN → mariadb:3306
  front-php:           # build ./front_php · 8024 · URL_API → http://api-facturas:8026
                       #   sin DB_DSN, sin credenciales, sin depends_on de mariadb
  phpmyadmin:          # 8102
volumes:
  mariadbdata:
```

**Los puertos son distintos a los de la v1 a propósito**, y no por capricho:
así se pueden tener las dos versiones encendidas al mismo tiempo y compararlas
lado a lado. El registro de todos los puertos del curso está en
`2026_2/PUERTOS.md`.

## 6. Convenciones

Las de la constitución: todo en español, comentario de apertura por archivo,
clases PascalCase (prefijo `I` en interfaces), métodos y variables camelCase,
un archivo por clase, `declare(strict_types=1)` en todo archivo PHP.

## 7. Chequeo de constitución

> **La compuerta 2** del método (ver [SDD_SPECKIT](../../../SDD_SPECKIT.md)):
> antes de pasar a `8_tasks.md` se revisa la
> [constitución](../../1_constitution.md) **artículo por artículo**.

| Artículo | Cómo lo cumple esta versión |
|---|---|
| **1** — Propósito didáctico | Todo en español y comentado; explícito y repetitivo antes que compacto — ver §4.7 y §4.8, donde la repetición se escogió a conciencia. |
| **1.1** — Una versión incluye su front | Cumple: los cinco recursos nuevos entregan sus pantallas en esta misma versión, incluida la de ver una factura. |
| **2** — PHP puro | Sin framework y sin Composer. Bootstrap es un archivo copiado, no una dependencia instalada. |
| **3** — Tres capas estrictas | Cumple, y el compose lo hace cumplir: el front no recibe credenciales de la base ni depende de ella, y su imagen no trae `pdo_mysql`. |
| **4** — Un solo comando | `docker compose up -d --build` deja los cuatro servicios funcionando. |
| **5** — Independencia del motor | El acceso a datos pasa por interfaces y el dialecto vive solo en las clases `*MariaDB` (RNF5). Con un solo motor la independencia sigue siendo **meta**; la v3 la comprueba. |
| **6** — Persistencia | Volúmenes; `down -v` devuelve la BD a su estado original. |
| **7** — Recarga natural | PHP reinterpreta cada petición. |
| **8** — Convenciones fijas | Puertos de esta versión, sobre de respuesta y catálogo de errores tal como los fija el artículo. |
| **9** — Seguridad académica | Credenciales didácticas; todo lo que el front pinta pasa por `htmlspecialchars`. |
| **10** — API específica | Seis recursos, seis rutas escritas. Y la prueba de que hacía falta: la factura no admite PATCH y sí admite `/anular` — ninguna ruta genérica habría podido decir eso. |
| **11** — La base también es parte del sistema | Es el artículo que esta versión estrena: la API no repite las llaves foráneas, no recalcula lo que el trigger calcula, y traduce el veredicto del motor a español (§4.1). |

**Complejidad justificada:** la desviación consciente de esta versión es la de
§4.5 —depender del texto de un mensaje para distinguir un 404 de un 409—, con
su alternativa descartada y su razón. Está aislada en un método para que se
pueda arreglar en un solo sitio.
