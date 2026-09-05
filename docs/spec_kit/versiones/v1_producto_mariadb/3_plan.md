# Plan técnico — Versión 1: producto de punta a punta (front + API + MariaDB)

> **Versión 1** · CÓMO construir lo especificado en [2_spec.md](2_spec.md).
> El porqué de cada decisión: [4_research.md](4_research.md) · contratos
> exactos: [6_contracts.md](6_contracts.md) · orden: [8_tasks.md](8_tasks.md).

---

## 1. Stack

| Pieza | Elección | Por qué |
|---|---|---|
| Lenguaje | **PHP 8.3** (tipos estrictos: `declare(strict_types=1)`) | El lenguaje del curso; los tipos escalares y de retorno son parte del contenido |
| Framework | **NINGUNO** (PHP puro) | Constitución, Artículo 2: lo que se ve es PHP, no magia |
| Gestor de paquetes | **NINGUNO** (sin Composer, sin `vendor/`) | Cero dependencias = cero fricción y cero cajas negras |
| Enrutamiento | Front controller `index.php` (comparaciones simples de ruta con `if`) | Un router legible completo ES la lección |
| Acceso a datos | **PDO** (extensión `pdo_mysql`) con prepared statements | SQL visible y parametrizado; PDO es el estándar del lenguaje |
| Servidor (desarrollo) | **PHP built-in server** (`php -S 0.0.0.0:8022 index.php`) | Todo pasa por el front controller sin configurar Apache/nginx; suficiente y transparente para el curso |
| Contenedor de la API | `php:8.3-cli` + `docker-php-ext-install pdo_mysql` | Imagen oficial; la extensión de MySQL/MariaDB se compila en el build |
| **Front** | **PHP puro también**, con su propio front controller y plantillas `.php` | Un solo lenguaje en todo el proyecto: lo que se aprende del lado de la API sirve del lado de la pantalla |
| **El front habla con la API** | **cURL** (extensión `curl`, viene en la imagen) | Es la forma canónica de PHP de hacer una petición HTTP saliente, y deja el verbo y el body a la vista |
| Contenedor del front | `php:8.3-cli` **sin `pdo_mysql`** | La ausencia no es un olvido: **es la comprobación**. Ese proceso no puede llegar a MariaDB ni queriendo |
| Estilos | **Bootstrap 5.3**, guardado en `front_php/publico/` | Una pantalla que se ve terminada es parte del trabajo, y escribir CSS no es contenido de este curso. Va **local, no por CDN**: el salón se queda sin internet y la pantalla se sigue viendo igual |

## 2. Estructura de carpetas

```
(raíz del proyecto)
├── docker-compose.yml                # UN comando: mariadb + api-facturas + front-php
├── db/
│   └── init.sql                      # la BD completa, PROVISTA (se copia, no se genera)
├── front_php/                        # LA PANTALLA (puerto 8020)
│   ├── Dockerfile                    # php:8.3-cli — SIN pdo_mysql, a propósito
│   ├── index.php                     # front controller del front: ruta → pantalla
│   ├── cliente_api.php               # la ÚNICA pieza que habla HTTP con la API
│   ├── vistas/
│   │   ├── plantilla.php             # el marco: cabecera, menú, avisos, pie
│   │   ├── inicio.php
│   │   ├── productos_lista.php
│   │   ├── productos_formulario.php  # sirve para agregar Y para editar
│   │   └── no_encontrada.php
│   └── publico/
│       ├── bootstrap.min.css         # guardado aquí, NO traído de un CDN
│       ├── bootstrap.bundle.min.js   # solo para cerrar los avisos
│       └── estilos.css               # los pocos retoques propios
└── api_facturas/
    ├── Dockerfile                    # php:8.3-cli + pdo_mysql (el compose lo construye)
    ├── index.php                     # front controller: recibe TODO y enruta
    ├── modelos/
    │   └── Producto.php              # el modelo: entidad clásica (propiedades privadas,
    │                                 #   getters/setters y toArray para el JSON)
    ├── controladores/
    │   └── ControladorProducto.php   # HTTP: lee request, VALIDA el body (422), llama
    │                                 #   servicio y arma la respuesta
    ├── servicios/
    │   ├── IServicioProducto.php     # interface del servicio
    │   ├── ServicioProducto.php      # reglas de negocio; recibe IRepositorioProducto
    │   └── ensamblador.php           # crearServicioProducto() — proto-fábrica (ver §4.3)
    ├── repositorios/
    │   ├── IRepositorioProducto.php  # interface: 5 métodos de datos
    │   └── RepositorioProductoMariaDB.php
    ├── excepciones/
    │   └── NoEncontradoExcepcion.php # la excepción de negocio que el controlador traduce a 404
    └── pruebas/
        └── prueba_capas.php          # criterio 6: el servicio con un repositorio falso, sin BD
```

Y, fuera de los dos programas, el guion que los prueba juntos:

```
└── pruebas_humo/
    └── humo_front.py                 # criterios 7 a 10: el recorrido desde la PANTALLA
```

## 3. Arquitectura en capas (flujo de una petición)

```
NAVEGADOR
     → front_php/index.php      (front controller del FRONT: ruta → pantalla)
     → cliente_api.php          (la única pieza que habla HTTP hacia afuera)
     ↓  HTTP + JSON  — aquí termina un proceso y empieza otro
     → api_facturas/index.php   (front controller de la API: método + ruta → controlador)
     → ControladorProducto  (lee query/body, valida con el modelo → 422,
                             traduce excepciones a códigos HTTP)
     → IServicioProducto    (interfaz — reglas de negocio)
     → IRepositorioProducto (interfaz — el servicio no sabe qué motor hay detrás)
     → RepositorioProductoMariaDB (PDO + prepared statements)
     → MariaDB
```

**Regla de dependencias:** controlador → servicio → interfaz de repositorio.
Solo `ensamblador.php` conoce las clases concretas.

**La flecha que no existe:** del front a MariaDB no hay ninguna, y esa
ausencia es la que se comprueba en el criterio 10. Fíjese además en dónde
está la línea de puntos: el front no llama a una función de la API, le manda
una **petición HTTP**. Son dos programas que solo comparten el JSON.

## 4. Decisiones de diseño clave

### 4.1 Interfaces nativas de PHP desde v1
```php
interface IRepositorioProducto
{
    public function obtenerTodos(int $limite): array;      // lista de objetos Producto
    public function obtenerPorCodigo(string $codigo): ?Producto;  // el modelo, o null
    public function crear(Producto $producto): bool;       // recibe el modelo
    public function actualizar(string $codigo, array $datos): int;  // la usan PUT y PATCH
    public function eliminar(string $codigo): int;
}
```
Las lecturas devuelven **objetos del modelo** (`Producto`); `actualizar` va
con array porque un PATCH puede traer solo algunos campos.
El servicio recibe **la interfaz** por constructor. Esto es lo que compra la
v3: un segundo motor será otra clase con `implements IRepositorioProducto`.

### 4.2 La validación del body vive en el controlador (la frontera HTTP)
PHP puro no trae validación integrada: se construye — y construirla enseña
más que recibirla gratis de un framework. El **controlador** trae los
métodos privados que revisan el body y devuelven la lista de errores
(vacía = válido):

- `validarCodigo(array $datos): array`     → el código: texto de 1 a 10 caracteres
- `validarCampos($datos, obligatorios: true)`  → POST/PUT: los 3 campos deben venir
- `validarCampos($datos, obligatorios: false)` → PATCH: valida SOLO los enviados
- `filtrarColumnas(array $datos): array`   → lista blanca: bota campos desconocidos

El **modelo** (`modelos/Producto.php`) es la clase entidad clásica: las 4
propiedades privadas, sus getters/setters y `toArray()`. El repositorio lo
construye al leer de la BD y así el dato viaja como objeto, no como array
anónimo.

Reglas: `codigo` 1–10 caracteres · `nombre` no vacío · `stock` entero ≥ 0 ·
`valorunitario` numérico ≥ 0. Si hay errores, el controlador responde
**422** con `{estado, mensaje, errores:[…]}` y nada llega al servicio ni a
la BD. (El body vacío en PATCH es 400 y lo decide el **servicio**: no es un
problema de forma sino de regla de negocio.)

### 4.3 `ensamblador.php`: la proto-fábrica honesta
```php
function crearServicioProducto(): IServicioProducto
{
    $repositorio = new RepositorioProductoMariaDB(
        getenv('DB_DSN'), getenv('DB_USUARIO'), getenv('DB_CLAVE')
    );
    return new ServicioProducto($repositorio);
}
```
Sin arrays de motores ni selección: v1 tiene UN motor y el código lo dice.
Cuando v3 agregue PostgreSQL, **solo este archivo** se convierte en la fábrica
real — controladores y servicios no se tocan (ese es el examen de la v3).

### 4.4 SQL del repositorio (PDO, siempre preparado)
```sql
SELECT codigo, nombre, stock, valorunitario FROM producto ORDER BY codigo LIMIT :limite
SELECT … WHERE codigo = :codigo
INSERT INTO producto (codigo, nombre, stock, valorunitario) VALUES (:codigo, :nombre, :stock, :valorunitario)
UPDATE producto SET … WHERE codigo = :codigo      -- los campos que lleguen (PUT: los 3; PATCH: los enviados)
DELETE FROM producto WHERE codigo = :codigo
```
- Conexión PDO **perezosa** (se abre en el primer uso y se reutiliza) con
  `PDO::ERRMODE_EXCEPTION` y **`PDO::ATTR_EMULATE_PREPARES => false`**
  (prepared statements REALES del servidor).
- **Detalle MariaDB #1:** el `:limite` del `LIMIT` debe enlazarse como entero —
  `bindValue(':limite', $limite, PDO::PARAM_INT)` — o el motor rechaza la
  consulta.
- **Detalle MariaDB #2 (trampa clásica):** por defecto, `rowCount()` de un
  UPDATE cuenta filas **cambiadas**, no **encontradas** — un PATCH que escribe
  el mismo valor reportaría 0 filas y parecería un 404. Se corrige en la
  conexión con **`PDO::MYSQL_ATTR_FOUND_ROWS => true`**.
- El SET del UPDATE se arma solo con columnas que vienen del modelo
  (lista blanca), nunca con claves del cliente.
- El driver mysql devuelve `DECIMAL` como string → el repositorio castea:
  `stock → (int)`, `valorunitario → (float)` al serializar.

### 4.5 Traducción de excepciones a HTTP (en el controlador)
| Excepción | HTTP |
|---|---|
| (body con errores de forma — no es excepción) | 422 |
| `InvalidArgumentException` (regla de negocio: límite ≤ 0, body vacío) | 400 |
| `NoEncontradoExcepcion` (código inexistente) | 404 |
| `PDOException` y cualquier otra | 500 (mensaje del motor en `detalle`) |

### 4.6 El front controller (`index.php`)
1. `declare(strict_types=1)` + `require` de las clases (sin autoloader: la
   lista de requires ES el inventario del proyecto).
2. `header('Content-Type: application/json; charset=utf-8')`.
3. Lee `$_SERVER['REQUEST_METHOD']` y el path de `REQUEST_URI`.
4. Enruta con comparaciones simples: `$ruta === '/'` (diagnóstico) ·
   `$ruta === '/api/producto'` (colección) ·
   `str_starts_with($ruta, '/api/producto/')` + `substr` para sacar el
   código — y 404 para todo lo demás. Dentro de cada ruta, `if/elseif`
   por verbo (405 si el verbo no aplica).
5. El body JSON se lee UNA vez: `json_decode(file_get_contents('php://input'), true)`.

### 4.7 El diseño del front (`front_php`)

Tres piezas, y cada una tiene UN trabajo. Es la misma idea de capas de la
API, sin ceremonia:

| Archivo | Su único trabajo | Lo que NO hace |
|---|---|---|
| `cliente_api.php` | Hablar HTTP con la API y devolver siempre `['ok', 'datos', 'errores']` | No pinta HTML, no conoce sesiones |
| `index.php` | Mirar la ruta, llamar al cliente y elegir la vista | No arma SQL, no arma JSON a mano |
| `vistas/*.php` | Pintar | No llaman a la API |

**`cliente_api.php` es el repositorio del front.** Igual que el repositorio es
la única pieza de la API que sabe que detrás hay MariaDB, éste es la única
que sabe que los datos vienen por HTTP. Y es el **único sitio del front que
conoce el formato del error de la API**: si mañana el sobre cambia, se cambia
aquí y en ninguna vista.

Devuelve siempre la misma forma, para que una vista pregunte «¿salió bien?» y
no «¿fue 200 o 204?»:

```php
['ok' => bool, 'datos' => mixed, 'errores' => string[]]
```

**Una función por operación, con el nombre del recurso adentro**
(`listar_productos`, `crear_producto`, …) — no una `listar($tabla)` genérica.
Es el Artículo 10 de la constitución aplicado del lado del front: cuando la
v2 traiga más tablas habrá más funciones, no un parámetro más.

**El 204 no es un error.** `listar_productos()` lo traduce a
`['ok' => true, 'datos' => []]`, y la pantalla muestra su recuadro de
«todavía no hay ninguno». Confundir «no hay filas» con «algo falló» es el
error más común al consumir una API, y aquí queda resuelto en un solo sitio.

**Y la distinción que importa:** `llamar_api()` devuelve `null` cuando **no
hubo respuesta** —API caída, tiempo agotado—, que es distinto de «respondió
con un error». Un 404 es la API funcionando y diciendo que ese producto no
existe; un `null` es que no hay con quién hablar. De esa diferencia sale el
aviso del criterio 10.

#### Los archivos estáticos, y una trampa que costó una pantalla fea

`php -S 0.0.0.0:8020 index.php` le da un **router** al servidor embebido, y
entonces el servidor **ejecuta el router para todas las peticiones** —
también para `/publico/estilos.css`. Como `index.php` no tiene una ruta que
se llame así, la hoja de estilos caía en el 404 del final: el navegador
recibía una página HTML donde esperaba CSS, y la pantalla salía sin un solo
estilo.

La solución es la que el propio PHP documenta: **devolver `false`** desde el
router cuando el archivo pedido existe en el disco. Eso significa «yo no me
encargo de ésta, entrégala tal cual».

```php
if (PHP_SAPI === 'cli-server') {
    $archivo = __DIR__ . $ruta;
    if ($ruta !== '/' && is_file($archivo)) {
        return false;
    }
}
```

Vale la pena que esto esté escrito y no solo arreglado, por lo que enseña:
**el fallo no lo detectó ninguna prueba**, porque todas comprobaban el texto
de las páginas y el texto estaba perfecto. Lo detectó una persona abriendo el
navegador. Desde entonces la prueba de humo verifica que cada archivo
estático responda **y con su tipo de contenido**.

#### Los avisos entre pantallas

Después de guardar se **redirige** al listado (patrón *post/redirect/get*:
así refrescar la página no vuelve a guardar). Pero una redirección pierde
todo lo que hubiera en memoria, así que el aviso viaja en la **sesión de
PHP**, se muestra una vez y se borra. Dos funciones, `redirigir_con()` y
`aviso_pendiente()`, y ya.

#### La conversión de texto a número

Un formulario HTML **solo produce texto**: el «12» que la persona escribió
llega como `"12"`. El contrato pide un número, y un número entre comillas no
es un número — la API lo rechazaría **siendo correcto**. Por eso el front
tiene `a_numero()`.

Parece validación del lado del cliente, y hay que ser preciso porque no lo
es: **ajusta la FORMA del dato, que es trabajo del front, y no juzga su
VALOR, que es trabajo de la API.** Si alguien escribió «doce», eso viaja como
«doce» y la API dice que no sirve.

#### Los dos botones del formulario

El mismo formulario, dos comportamientos, y la diferencia **no la decide
ningún `if` de negocio: la decide qué se envía.** Según el botón que se
oprimió, el front arma el cuerpo con los tres campos —vayan llenos o no, que
es un reemplazo— o solo con los diligenciados.

Por eso «Guardar la ficha completa» con el nombre en blanco se rechaza y
«Guardar solo lo que cambié» con el mismo formulario a medio llenar sí
guarda. Es RF4 contra RF5, visto desde donde se usa.

## 5. Docker: un solo comando desde v1

La constitución (Artículo 4) manda: `docker compose up -d --build` deja TODO
funcionando. En v1 eso son **tres servicios**:

```yaml
services:
  mariadb:             # mariadb:11 + db/init.sql (la BD completa)
    # volumen mariadbdata (persistencia) · puerto 13326 al host · healthcheck healthcheck.sh
  api-facturas:        # build: ./api_facturas (su Dockerfile)
    # código montado como volumen → guardar un .php = refrescar (PHP reinterpreta)
    # command: php -S 0.0.0.0:8022 index.php
    # DB_DSN apunta al host interno "mysql:host=mariadb;port=3306;..."
    # depends_on: mariadb con condition: service_healthy
  front-php:           # build: ./front_php
    # puerto 8020 · URL_API=http://api-facturas:8022  ← el NOMBRE DEL SERVICIO
    # depends_on: api-facturas   ...y NADA de mariadb
volumes:
  mariadbdata:
```

**Dos detalles del servicio del front que valen por un párrafo de teoría:**

- `URL_API` usa `http://api-facturas:8022`, el **nombre del servicio**, no
  `localhost`. Dentro de un contenedor, `localhost` es el contenedor mismo —
  y ahí no hay ninguna API.
- El front **no recibe `DB_DSN`, ni usuario, ni clave**, y su `depends_on` no
  nombra a `mariadb`. Así el Artículo 3 deja de ser una regla que alguien
  puede olvidar y pasa a ser algo que el sistema **no permite**: aunque
  alguien escribiera un `new PDO(...)` en el front, no tendría ni con qué ni
  a dónde conectarse. Y su imagen ni siquiera instala `pdo_mysql`.

`api_facturas/Dockerfile`: `php:8.3-cli` → instalar `libpq-dev` y compilar
`pdo_mysql` → copiar el código → `CMD php -S 0.0.0.0:8022 index.php`.

`front_php/Dockerfile`: `php:8.3-cli` → copiar el código →
`CMD php -S 0.0.0.0:8020 index.php`. **Sin ninguna extensión de base de
datos**, que es justamente el punto.

**Durante la construcción fase a fase** también se puede correr local si se
tiene PHP 8.3 con pdo_mysql (`php -S localhost:8022 index.php` con las
variables de entorno hacia `localhost:13326`) — el compose es la forma
oficial de entrega.

## 6. Convenciones

Las de la constitución: todo en español, comentario de apertura por archivo,
clases PascalCase (prefijo `I` en interfaces), métodos y variables camelCase,
un archivo por clase, `declare(strict_types=1)` en todo archivo PHP.

## 7. Chequeo de constitución

> **La compuerta 2** del método (ver [SDD_SPECKIT](../../../SDD_SPECKIT.md)):
> antes de pasar a `8_tasks.md` se revisa la
> [constitución](../../1_constitution.md) **artículo por artículo**. Si algo
> no cumple, o se corrige el plan, o se enmienda la constitución. Nunca se
> deja pasar "por esta vez".

| Artículo | Cómo lo cumple esta versión |
|---|---|
| **1** — Propósito didáctico ante todo | Todo en español y comentado para principiantes; se prefiere lo explícito y legible sobre lo compacto. |
| **1.1** — Una versión incluye SU FRONT | Cumple: `front_php` se construye en esta misma versión, con las pantallas de `producto`. La versión no se cierra con la API sola. |
| **2** — PHP puro: sin framework y sin Composer | PHP puro: sin framework y sin Composer. Lo que esta versión agrega no introduce dependencias. |
| **3** — Arquitectura de 3 capas estricta | Las tres capas existen y están separadas (§3): el front solo habla HTTP, la API solo devuelve JSON. Y no se queda en la promesa: el compose no le da al front ni credenciales ni dependencia de la base, y su imagen no trae `pdo_mysql` (§5). |
| **4** — Un solo comando para arrancar | `docker compose up -d --build` deja funcionando lo que esta versión declara (§5 de este plan). |
| **5** — Independencia del motor de base de datos | El acceso a datos pasa por interfaces. Si esta versión trae un solo motor, la independencia todavía es **meta**, no estado. |
| **6** — Persistencia y reproducibilidad | Los datos viven en volúmenes; `docker compose down -v` devuelve la BD a su estado original. |
| **7** — Desarrollo con recarga natural | PHP reinterpreta cada petición: guardar el archivo y refrescar es el ciclo, sin reconstruir la imagen. |
| **8** — Convenciones fijas | Puertos, rutas, sobre de respuesta y catálogo de errores, tal como los fija el artículo. |
| **9** — Seguridad en su justa medida académica | Credenciales didácticas y sin secretos reales. Todo lo que el front pinta pasa por `htmlspecialchars`, que es lo mínimo que se le pide a una pantalla. |
| **10** — La API es específica, nunca genérica | Cumple de los dos lados: `/api/producto` con sus campos escritos, y en el front una función por operación (`listar_productos`, `crear_producto`, …), no una `listar($tabla)`. |

**Complejidad justificada:** si esta versión se desvía de algún artículo,
la desviación va aquí, con la alternativa más simple que se descartó y por
qué no sirvió. Sin desviaciones anotadas, se entiende que no las hay.
