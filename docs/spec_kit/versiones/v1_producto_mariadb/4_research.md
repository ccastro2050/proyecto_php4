# Investigación y decisiones — Versión 1: producto de punta a punta

> **Versión 1** · **Lectura opcional** (el porqué de las decisiones del plan,
> con las alternativas que se evaluaron y descartaron). Complementa a
> [3_plan.md](3_plan.md); el orden de trabajo está en [8_tasks.md](8_tasks.md).

---

## D1 — PHP puro: sin framework y sin Composer

**Alternativas descartadas:** Slim 4 (micro-framework con routing PSR-7) y
Laravel (framework completo con ORM).
**Decisión:** PHP "vanilla" — front controller propio, PDO directo, cero
dependencias.
**Por qué:** el objetivo es aprender **PHP y arquitectura**, no un framework.
Laravel esconde exactamente lo que el curso quiere mostrar (el SQL, el
enrutamiento, la validación); Slim es razonable pero mete Composer, `vendor/`
y estándares PSR que compiten por la atención del estudiante. Escribir el
router de ~60 líneas y la validación a mano ES el contenido. **Precio
asumido:** características gratis que no tendremos (middleware, DI container,
docs automáticas) — ninguna es objetivo de la v1.

## D2 — Capas completas desde el día 1 (y no un MVP en un solo archivo)

**Alternativa descartada:** v1 = todo en `index.php` y refactorizar a capas
después.
**Decisión:** controlador → servicio → repositorio con interfaces desde v1.
**Por qué:** el valor de la v1 es el **esqueleto** sobre el que crecen las
demás versiones sin reescribir. El criterio de aceptación 6 (probar el
servicio con un repositorio falso, sin MariaDB) **solo es posible** si el
servicio depende de una `interface` — la prueba objetiva de que las capas
quedaron bien cortadas. Bonus de PHP: las interfaces son nativas del lenguaje
(`interface` / `implements`), más explícitas incluso que los Protocol de
otros lenguajes.

## D3 — Sin fábrica ni selección de motor: un ensamblador de una función

**Alternativa descartada:** escribir de una vez la fábrica multi-motor.
**Decisión:** `ensamblador.php` con una función que instancia la única
combinación existente (YAGNI con dirección).
**Por qué:** una fábrica con un solo producto es código muerto. La interfaz
`IRepositorioProducto` SÍ se escribe hoy — es la puerta por la que entrará
MariaDB — pero el mecanismo de selección llega cuando exista algo que
seleccionar (v3). El examen del principio abierto/cerrado será ese: en v3,
solo `ensamblador.php` cambia.

## D4 — La BD completa desde la v1 (la API solo toca `producto`)

**Alternativa descartada:** una BD mínima que crece con cada versión.
**Decisión:** `db/init.sql` crea `bdfacturas` COMPLETA (12 tablas, trigger,
SPs); la regla es que el código de v1 solo puede nombrar `producto`.
**Por qué:** los estudiantes ya vieron bases de datos — la BD es
**infraestructura dada**; lo que se construye por versiones es la API. Evita
migraciones entre versiones y deja el trigger de facturación esperando a la
v2. Costo asumido: 11 tablas a la vista que aún no se usan — por eso la regla
se declara explícita en la spec.

## D5 — Modelo básico + validación en el controlador

**Alternativas descartadas:** una clase validadora aparte (mete un concepto
extra a la estructura), meter la validación dentro del modelo o del
servicio, o no validar y dejar que la BD rechace.
**Decisión:** el modelo `Producto` es una clase **básica** (las 4
propiedades tipadas — el dato como objeto, nada más), y la validación del
body vive en el **controlador** como métodos privados: la frontera HTTP
revisa lo que llega de afuera → 422 con lista de errores ANTES de tocar el
servicio. La estructura queda en las 4 carpetas canónicas: controladores,
modelos, servicios, repositorios.
**Por qué:** en los frameworks con validación integrada la frontera viene
gratis; en PHP puro **construirla** enseña qué hace realmente una frontera
de entrada. Validar es trabajo de quien recibe la petición (el controlador),
y el modelo se mantiene simple para no confundir: modelo = el dato con
tipos. La validación por verbo materializa la semántica HTTP: el mismo body
`{"stock": 7}` falla en PUT (le faltan campos) y pasa en PATCH — la
diferencia queda escrita en código, no en comentarios.

## D6 — PDO con prepared statements (SQL visible)

**Alternativa descartada:** un ORM (Eloquent/Doctrine) o funciones `pg_*`.
**Decisión:** PDO + `prepare`/`execute` con parámetros nombrados.
**Por qué:** la constitución exige SQL visible y parametrizado. PDO es el
estándar del lenguaje, funciona con los tres motores de la ruta (v3/v4 solo
cambian el DSN y el dialecto) y sus prepared statements son la defensa
canónica contra inyección SQL. Detalle didáctico: el driver mysql entrega `DECIMAL`
como string — el repositorio castea al serializar, y ese matiz (cada driver
serializa distinto) es lección del curso.

## D7 — `php -S` (built-in server) en vez de Apache/nginx

**Alternativa descartada:** `php:8.3-apache` con mod_rewrite y `.htaccess`.
**Decisión:** el servidor embebido de PHP con `index.php` como router:
`php -S 0.0.0.0:8022 index.php`.
**Por qué:** con `php -S … index.php` TODAS las peticiones pasan por el front
controller sin configurar rewrite — cero archivos de configuración de
servidor, que no son contenido de la v1. Producción real usaría
nginx + PHP-FPM — fuera del alcance (documentado). El compose además monta el
código como volumen: guardar un `.php` = refrescar el navegador, porque PHP
reinterpreta cada petición (ni siquiera existe "reload").

## D8 — Docker compose desde la v1 (tres servicios)

**Alternativa descartada:** `docker run` a mano para la BD y PHP local como
única forma de correr.
**Decisión:** `docker-compose.yml` con `mariadb` + `api-facturas` +
`front-php` desde v1 — `docker compose up -d --build` deja todo funcionando.
**Por qué:** el Artículo 4 de la constitución ("un solo comando") es
permanente — y la constitución gana. El compose de v1 es mínimo y **crece por
versiones** (v3 suma PostgreSQL, v4 SQL Server…): la infraestructura también
se construye por incrementos.

## D9 — El front se hace en la v1, no en una versión final

**Alternativa descartada:** la que este proyecto tenía escrita hasta ahora —
API en v1 a v4 y **el front al final, en una v5**.
**Decisión:** cada versión incluye su front; la v1 entrega las pantallas de
`producto` (Artículo 1.1 de la constitución).
**Por qué, y esto es un cambio de criterio, no un detalle:**

- Los desajustes entre lo que la API devuelve y lo que la pantalla necesita
  aparecen **el día que alguien pinta una tabla**. Con el front al final, ese
  día llega con cuatro versiones de API encima: lo que en la v1 era corregir
  en diez minutos, allá es rehacer.
- Una API sin pantalla **se cree terminada sin estarlo**. Un `curl` que
  responde 200 no dice si el dato sirve; la pantalla obliga a decidir qué se
  muestra, cómo se dice el error en español y qué pasa cuando no hay filas.
- Y sobre todo: **la separación de capas del Artículo 3 no se puede
  demostrar sin front.** El criterio 10 —apagar la API con la base encendida
  y ver que la pantalla no muestra ni una fila— es la única prueba de que son
  dos procesos y no uno partido en dos carpetas.

**Precio asumido:** cada versión es más trabajo, y hay que aprender dos
oficios a la vez. A cambio, cada versión entrega algo que un humano puede
usar, no un endpoint que hay que creerle a Postman.

## D10 — El front también en PHP puro, con plantillas y sin JavaScript

**Alternativas descartadas:** una página con JavaScript que llame a la API
desde el navegador (SPA a mano), o un motor de plantillas como Twig.
**Decisión:** PHP renderiza el HTML **en el servidor**; el navegador recibe
páginas terminadas y manda formularios corrientes. Las plantillas son
archivos `.php` con `require`.
**Por qué:** con JavaScript, quien llama a la API es el **navegador**, y
entonces el criterio 10 cambia de significado —hay que hablar de CORS, de
promesas y de errores asíncronos antes de haber enseñado qué es una capa—.
Con render en el servidor, la petición a la API la hace **el proceso del
front**, que es exactamente el dibujo de la arquitectura. Twig, por su parte,
metería Composer, que el Artículo 2 prohíbe.
**Y un beneficio que no se esperaba:** como todo son formularios, la prueba
de humo puede **recorrer el sistema completo sin manos** — crear, guardar de
las dos maneras y eliminar. En los fronts de Blazor de otros cursos eso no se
puede: los clics viajan por una conexión persistente. La tecnología más
sencilla resultó ser la más fácil de probar.

## D11 — `cliente_api.php` trabaja con arrays, no con las clases de la API

**Alternativa descartada, y hay que nombrarla porque es tentadora:**
`require_once __DIR__ . '/../api_facturas/modelos/Producto.php';` para usar
en el front la clase `Producto` que ya existe. **Funcionaría.**
**Decisión:** el front trabaja con los arrays que `json_decode` devuelve.
**Por qué:** ese `require` los volvería un solo programa repartido en dos
carpetas. Renombrar un método adentro de la API rompería la pantalla **sin
que nadie tocara el contrato** — y el contrato es lo único que deberían
compartir. Además el front no podría existir en otro lenguaje ni en otra
máquina, que es la mitad del sentido de tener una API.
**Precio asumido:** se pierde el autocompletado y los tipos del lado del
front. Es un precio real, y es el correcto: en este proyecto la tentación es
mayor que en otros porque los dos están en PHP, y por eso la regla se escribe
en vez de darse por obvia.

## D13 — Bootstrap, y guardado en el repositorio en vez de traído de un CDN

**Alternativas descartadas:** escribir el CSS a mano (fue el primer intento) y
enlazar Bootstrap desde un CDN.
**Decisión:** Bootstrap 5.3, con sus dos archivos guardados en
`front_php/publico/`, y un `estilos.css` propio de veinte líneas para lo poco
que Bootstrap no trae.
**Por qué no a mano:** una pantalla que se ve terminada es parte del trabajo
—un sistema feo se lee como un sistema a medias—, y escribir una hoja de
estilos decente no es contenido de este curso. Con Bootstrap, tres clases en
una tabla dan un resultado profesional y el estudiante se concentra en lo que
sí se está enseñando: capas, contratos y la separación front/API.
**Por qué no del CDN:** una línea más corta, sí, y el día que el salón se
quede sin internet la pantalla se ve como en 1995. Eso pasa en clase, no en
teoría. Dos archivos en el repositorio lo resuelven para siempre, y de paso
el proyecto sigue cumpliendo el Artículo 2: **esto no es una dependencia
instalada por un gestor de paquetes**, es un archivo copiado — no hay
`composer.json` ni `vendor/`.
**Precio asumido:** 300 KB en el repositorio, y actualizar Bootstrap es
volver a copiar el archivo. Los dos son baratos.

## D12 — El aviso viaja en la sesión, y después de guardar se redirige

**Alternativa descartada:** pintar el listado directamente en la respuesta
del POST, sin redirigir.
**Decisión:** *post/redirect/get* — se guarda, se deja el aviso en
`$_SESSION` y se redirige al listado, que lo muestra una vez y lo borra.
**Por qué:** sin redirección, refrescar la página del navegador **vuelve a
enviar el formulario** y crea el producto dos veces; el navegador incluso lo
advierte con un cuadro de diálogo que nadie lee. Redirigir hace que la última
dirección visitada sea un GET inocuo. El precio es que la redirección pierde
la memoria, y de ahí sale la sesión: dos funciones, `redirigir_con()` y
`aviso_pendiente()`.
