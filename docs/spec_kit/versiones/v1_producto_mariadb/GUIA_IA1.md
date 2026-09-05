# Cómo construir la versión con IA — por chat o con un IDE agéntico

> Guía para trabajar la versión en curso con ayuda de IA por **cualquiera de
> los dos caminos**: un chat web (Gemini, DeepSeek, ChatGPT…) o un IDE
> agéntico (Antigravity, Cursor, Claude Code, Copilot en VS Code…).
> La clave del método es la misma en ambos: la IA no inventa — **sigue el
> spec kit**. Usted verifica; la IA propone (chat) o ejecuta bajo su
> supervisión (IDE).

---

## 0. Los dos caminos, en una tabla

| | **Camino A: chat web** | **Camino B: IDE agéntico** |
|---|---|---|
| Herramientas | Gemini, DeepSeek, ChatGPT, Claude (web) | Antigravity, Cursor, Claude Code, Copilot agente |
| ¿Cómo conoce la spec? | Usted le **sube los 8 archivos** | El agente **lee la carpeta `docs/spec_kit/` de su proyecto** |
| ¿Quién crea la estructura de carpetas? | **USTED la crea a mano** en su proyecto — el chat no puede tocar su disco (los dos comandos, carpetas y archivos vacíos, están en A.2) | El agente crea carpetas y archivos solo |
| ¿Quién escribe los archivos? | Usted copia/pega lo que la IA propone | El agente crea y edita los archivos directamente |
| ¿Quién ejecuta los comandos? | Usted, en un IDE (**preferible**: la terminal integrada de VS Code) o en PowerShell, y pega la salida | El agente (pidiéndole permiso); usted revisa la salida |
| Su papel | Operador: ejecutar y reportar | Supervisor: revisar diffs y aprobar |
| Riesgo típico | La IA "olvida" el contexto en chats largos | El agente avanza demasiado rápido: hace varias fases de un tirón o agrega cosas no pedidas, sin que usted alcance a revisarlas |

> **¿De qué "comandos" habla la tabla?** De los **comandos de verificación de
> cada fase** que pide `8_tasks.md`. Ejemplos reales de la v1:
> `docker compose up -d` (levantar la BD), `php -l archivo.php` (verificar
> sintaxis), `php -S localhost:8022 index.php` (arrancar la API local),
> `php pruebas/prueba_capas.php` (la prueba de capas), los `curl` del smoke
> test y `python pruebas_humo/humo_front.py` (el recorrido por la pantalla). En el chat, la IA se los dicta y USTED los ejecuta; en el IDE
> agéntico, el agente los ejecuta y usted revisa la salida.

En ambos casos, "terminado" significa lo mismo: **los 10 criterios de
aceptación de `2_spec.md` en verde**, verificados con el smoke test de
`7_quickstart.md` — corrido por usted.

---

## Camino A — Chat web (Gemini, DeepSeek, ChatGPT…)

### A.1 Qué subirle (los 8 archivos de la v1)

En el chat (todos aceptan adjuntar archivos; si el suyo no, pegue el contenido
de cada uno en el mismo orden):

| # | Archivo | Papel |
|---|---|---|
| 1 | `docs/spec_kit/1_constitution.md` | Las reglas permanentes (PHP puro, capas, un comando) |
| 2 | `docs/spec_kit/versiones/v1_producto_mariadb/2_spec.md` | QUÉ construir y los criterios de aceptación |
| 3 | `.../v1_producto_mariadb/3_plan.md` | CÓMO: stack, carpetas, capas |
| 4 | `.../v1_producto_mariadb/4_research.md` | Decisiones y alternativas (el porqué del plan) |
| 5 | `.../v1_producto_mariadb/5_data_model.md` | La BD completa (dada) y la tabla producto |
| 6 | `.../v1_producto_mariadb/6_contracts.md` | Los 7 endpoints y las 5 pantallas, exactos |
| 7 | `.../v1_producto_mariadb/7_quickstart.md` | El smoke test de validación |
| 8 | `.../v1_producto_mariadb/8_tasks.md` | Las fases, en orden |

Además de los 8 documentos, la versión trae **un artefacto que NO se sube al
chat ni lo genera la IA**: `db/init.sql` (el script completo de la BD en
dialecto MariaDB) — usted lo **copia tal cual** del repositorio a su proyecto
(ver A.2, paso 5).

> **¿Qué es un "artefacto"?** En ingeniería de software, cualquier archivo
> que el proceso produce o entrega (documentos, código, scripts…). Aquí lo
> usamos para distinguir: los **documentos** se LEEN (la IA construye a partir
> de ellos); el **artefacto** `db/init.sql` se USA tal cual — es insumo dado,
> como la imagen de MariaDB. Analogía: los documentos son el plano de la
> casa; el artefacto es un prefabricado que llega listo a la obra.

**No suba nada más.** El mapa de versiones no hace falta (y le revelaría a la
IA lo que viene — la regla es que la v1 no anticipa).

### A.2 Prepare SU proyecto (ANTES de abrir el chat)

**Ojo: NO se construye dentro de la carpeta clonada.** El repositorio clonado
es el **material de referencia** — contiene la versión y sus especificaciones,
para ver cómo se llegó a lo que existe. Su trabajo de reconstrucción va en un
**proyecto propio, en una carpeta nueva y vacía**:

1. Cree una carpeta para su proyecto (ej.: `mi_v1_producto/`) donde usted
   guarda sus trabajos — fuera de la carpeta clonada.
2. Ábrala en VS Code (*File → Open Folder*).
3. **Cree las CARPETAS** (el chat no puede tocar su disco). En la terminal
   integrada (*Terminal → New Terminal*, PowerShell), parado en su carpeta:

   ```powershell
   mkdir docs\spec_kit\versiones\v1_producto_mariadb, db, api_facturas\modelos, api_facturas\controladores, api_facturas\servicios, api_facturas\repositorios, api_facturas\excepciones, api_facturas\pruebas, front_php\vistas, front_php\publico, pruebas_humo
   ```

4. **Cree los ARCHIVOS VACÍOS** — **USTED los irá llenando** uno a uno,
   pegando en cada archivo el código que la IA le entregue:

   ```powershell
   New-Item .gitignore, docker-compose.yml, api_facturas\Dockerfile, api_facturas\index.php, api_facturas\modelos\Producto.php, api_facturas\controladores\ControladorProducto.php, api_facturas\servicios\IServicioProducto.php, api_facturas\servicios\ServicioProducto.php, api_facturas\servicios\ensamblador.php, api_facturas\repositorios\IRepositorioProducto.php, api_facturas\repositorios\RepositorioProductoMariaDB.php, api_facturas\excepciones\NoEncontradoExcepcion.php, api_facturas\pruebas\prueba_capas.php, front_php\Dockerfile, front_php\index.php, front_php\cliente_api.php, front_php\vistas\plantilla.php, front_php\vistas\inicio.php, front_php\vistas\productos_lista.php, front_php\vistas\productos_formulario.php, front_php\vistas\no_encontrada.php, front_php\publico\estilos.css, pruebas_humo\humo_front.py
   ```

   > **Fíjese en lo que la lista tiene y en lo que no.**
   >
   > **Tiene** los diez archivos de `front_php\` y `pruebas_humo\`, porque
   > **la versión incluye su pantalla** (Artículo 1.1 de la constitución).
   > Son la mitad del trabajo, no un añadido, y por eso nacen vacíos junto
   > a los de la API: la carpeta vacía a la vista recuerda lo que falta.
   >
   > **No tiene** `db\init.sql` ni los dos archivos de Bootstrap: ésos no
   > nacen vacíos — se copian del repositorio en el paso 5.

5. **Copie y pegue los 9 archivos que vienen dados** (con el explorador de
   Windows: Ctrl+C, Ctrl+V), desde la carpeta clonada del curso hacia SU
   proyecto — cada uno a la misma ruta:

   | Del clon del curso | A su proyecto |
   |---|---|
   | `db\init.sql` | `db\` |
   | `front_php\publico\bootstrap.min.css` | `front_php\publico\` |
   | `front_php\publico\bootstrap.bundle.min.js` | `front_php\publico\` |
   | `docs\spec_kit\1_constitution.md` | `docs\spec_kit\` |
   | Los 7 `.md` de `docs\spec_kit\versiones\v1_producto_mariadb\` | `docs\spec_kit\versiones\v1_producto_mariadb\` |

   (Estos 11 vienen dados — la IA no los genera: las specs se le SUBEN al
   chat, `db/init.sql` es la BD completa ya escrita, y los dos de Bootstrap
   son la hoja de estilos que el proyecto **guarda en vez de traer de
   internet**, para que la pantalla se vea igual en un salón sin red.)

**Antes de abrir el chat, verifique:** `docs\spec_kit\1_constitution.md` debe
existir, `docs\spec_kit\versiones\v1_producto_mariadb\` debe tener **7 archivos**
(2_spec a 8_tasks), `db\init.sql` debe tener contenido (~1.300 líneas) y
`front_php\publico\` debe tener los dos archivos de Bootstrap **con
contenido** (unos 230 KB y 80 KB). Si algo está vacío, falta el paso 5.

La estructura queda lista ANTES de hablar con la IA (es la de `3_plan.md`
§2); al lado, la fase en la que la IA le entregará el código de cada
archivo para que USTED lo pegue:

```
mi_v1_producto/                   ← SU carpeta
├── docs/
│   └── spec_kit/                 ← las especificaciones, IGUAL que en el repo
│       ├── 1_constitution.md     ←   las reglas permanentes (viven en la raíz)
│       └── versiones/
│           └── v1_producto_mariadb/  ← los 7 documentos de la v1 (la v2
│                                       tendrá su propia carpeta al lado)
├── .gitignore                    ← Fase 9 (excluye *.session.sql y archivos de IDE)
├── docker-compose.yml            ← Fase 0 (mariadb), Fase 6 (api-facturas) y Fase 7 (front-php)
├── db/
│   └── init.sql                  ← Fase 0: COPIADO del repo (la BD completa; no la genera la IA)
└── api_facturas/                 ← TODO el código va aquí adentro
    ├── Dockerfile                ← Fase 6 (para el "un solo comando" final)
    ├── index.php                 ← Fase 5 (el front controller)
    ├── modelos/
    │   └── Producto.php               ← Fase 1
    ├── controladores/
    │   └── ControladorProducto.php    ← Fase 5
    ├── servicios/
    │   ├── IServicioProducto.php      ← Fase 2
    │   ├── ServicioProducto.php       ← Fase 4
    │   └── ensamblador.php            ← Fase 4
    ├── repositorios/
    │   ├── IRepositorioProducto.php   ← Fase 2
    │   └── RepositorioProductoMariaDB.php  ← Fase 3
    ├── excepciones/
    │   └── NoEncontradoExcepcion.php  ← Fase 2
    └── pruebas/
        └── prueba_capas.php           ← Fase 4 (criterio 6)
├── front_php/                    ← LA PANTALLA — la otra mitad de la versión
│   ├── Dockerfile                     ← Fase 7 (php:8.3-cli SIN pdo_mysql)
│   ├── index.php                      ← Fase 7 (el front controller del front)
│   ├── cliente_api.php                ← Fase 7 (lo único que habla con la API)
│   ├── vistas/
│   │   ├── plantilla.php              ← Fase 7 (el marco: menú, avisos, pie)
│   │   ├── inicio.php                 ← Fase 7
│   │   ├── productos_lista.php        ← Fase 7
│   │   ├── productos_formulario.php   ← Fase 7 (agregar Y editar, dos botones)
│   │   └── no_encontrada.php          ← Fase 7
│   └── publico/
│       ├── bootstrap.min.css          ← COPIADO del repo (no lo genera la IA)
│       ├── bootstrap.bundle.min.js    ← COPIADO del repo
│       └── estilos.css                ← Fase 7 (los pocos retoques propios)
└── pruebas_humo/
    └── humo_front.py                  ← Fase 8 (criterios 7 a 10)
```

**Cómo pegar lo que la IA entregue** (VS Code): el archivo **ya existe
vacío** (lo creó el comando del paso 4) — ábralo desde el explorador, pegue
el contenido COMPLETO que entregó la IA y guarde (`Ctrl+S`). Un bloque de la
IA = un archivo completo (reemplaza todo, nunca "agregue al final").

**Qué le entrega la IA y qué hace usted con eso** — en cada fase la IA
entrega tres tipos de cosas:

| La IA le entrega | Usted lo pone en |
|---|---|
| Un bloque de código con su ruta (ej.: "Archivo: `api_facturas/modelos/Producto.php`") | Ese archivo, que ya existe vacío en ESA ruta |
| (La BD no la entrega la IA) | `db/init.sql` se **copia del repositorio** tal cual, en el paso 5 — si la IA intenta escribirle un `CREATE TABLE`, recuérdele que la BD ya viene dada |
| (Bootstrap tampoco) | `front_php/publico/bootstrap.min.css` y `bootstrap.bundle.min.js` se **copian** en el paso 5 — si la IA le propone un `<link>` a un CDN, cámbielo por la ruta local: el salón puede quedarse sin internet |
| Comandos (docker compose, php -l, php -S, curl) | La terminal integrada del IDE, parado en la carpeta correcta (ver abajo) |

Si un bloque llega **sin ruta**, no adivine: pregúntele "¿en qué archivo va
esto?". Y si le dice "modifica la línea X", pídale mejor el archivo completo
actualizado — copiar archivos enteros evita errores de edición manual.

**A la terminal SOLO se le pegan COMANDOS** — lo que viene en las cajitas
de código del chat, uno a la vez. Si pega el texto del mensaje (las
frases), la terminal intentará ejecutar cada palabra y llenará la pantalla
de errores tipo `'Te' no se reconoce como nombre de un cmdlet` (no daña
nada, pero asusta). Al chat, texto; a la terminal, comandos.

**¿Desde qué carpeta se corre cada comando?** (el otro error común es correr
un comando parado en la carpeta equivocada — "no encuentro el archivo"):

| Comando | Se corre desde |
|---|---|
| `docker compose ...` | La **raíz** de su proyecto (ahí vive `docker-compose.yml`) |
| `php -S localhost:8022 index.php` · `php pruebas\prueba_capas.php` | `api_facturas\` (ahí vive el código) — primero `cd api_facturas` |
| `python pruebas_humo\humo_front.py` | La **raíz** de su proyecto |

> Nota: no hay que instalar nada más — PHP puro no usa librerías externas
> (decisión de la constitución).

### A.3 El prompt (cópielo tal cual como PRIMER mensaje)

**Antes de enviar el primer mensaje, tres chequeos en el chat:**

1. **Los 8 adjuntos**: verifique en el chat que aparecen los 8 documentos
   (deslice el carrusel de adjuntos si no se ven todos).
2. **Active el modo de razonamiento** si el chat lo tiene (en DeepSeek se
   llama **"Pensamiento Profundo"**; en otros, "Thinking" o "Razonar"):
   sigue mucho mejor las reglas estrictas de este prompt.
3. **Apague la búsqueda web** si el chat la tiene (en DeepSeek,
   **"Búsqueda inteligente"**): no se necesita y puede traer código de
   internet por fuera de la spec — justo lo que la regla 1 prohíbe.

```
Actúa como mi asistente de programación para construir la VERSIÓN 1 de un
proyecto universitario, partiendo de cero. Te adjunto 8 documentos: una
constitución (reglas permanentes) y el spec kit de la versión 1 (spec, plan,
research con las decisiones, modelo de datos, contratos, quickstart y tareas).

El proyecto es PHP 8.3 puro (sin frameworks ni Composer) + MariaDB — así lo
fija 3_plan.md. Son DOS programas que se construyen en esta misma versión:
la API (api_facturas) y su front (front_php), que habla con ella por HTTP. Si en tu respuesta aparece OTRO lenguaje o framework (Python,
Java, Node, Laravel, Symfony…), significa que no leíste los documentos
adjuntos: detente y dímelo en vez de continuar.

REGLAS DE TRABAJO (no negociables):

1. La especificación manda. No agregues NADA que los documentos no pidan:
   ni frameworks, ni Composer, ni librerías, ni tablas extra, ni motores
   extra, ni fábricas "por si acaso", ni mejoras de tu cosecha. Si crees que
   falta algo, pregúntame antes.
2. Vamos a seguir 8_tasks.md FASE POR FASE, en orden. En cada fase:
   a. Me explicas en 3-5 líneas qué vamos a hacer y por qué.
   b. Me entregas los archivos de la fase DE A UNO: primero la ruta exacta
      y el contenido COMPLETO de UN solo archivo (listo para copiar y
      pegar, con los comentarios didácticos en español que exige la
      constitución). Esperas mi "listo" y solo entonces me das el
      siguiente archivo de la fase.
   c. Al cerrar la fase me dices su comando de verificación y QUÉ salida
      esperar. Correr esa verificación en el momento es OPCIONAL: puedo
      dejarla para el final — tú sigues con la fase siguiente cuando yo
      diga "listo".
   NOTA: la estructura de carpetas y los archivos vacíos YA EXISTEN en mi
   proyecto — no me des comandos para crearlos; tu trabajo es dictarme el
   CONTENIDO de cada archivo.
3. Los errores NO nos frenan. Si te pego un error, lo diagnosticas y me
   das el archivo completo corregido; si no sale rápido, seguimos con las
   fases y lo retomamos al final. Al terminar todas las fases me guías
   para correr el smoke test de 7_quickstart.md y corregimos juntos todo
   lo que salga.
4. El código debe cumplir los contratos de 6_contracts.md al pie de la letra:
   mismos verbos, mismas rutas, mismos códigos de estado, mismos formatos de
   respuesta (incluido el contraste PUT=reemplazo completo vs PATCH=parcial,
   y el 422 con la lista de errores de validación). Del lado del front, las
   direcciones y las pantallas de la sección 9 del mismo documento.
9. El front NO toca la base de datos: no escribas ningún new PDO en
   front_php, y NO le hagas require de ningún archivo de api_facturas
   aunque los dos estén en PHP y funcionaría. Lo único que comparten es el
   JSON: el front trabaja con los arrays que devuelve json_decode.
10. Ninguna pantalla le habla al usuario en jerga: nada de PUT, PATCH,
   DELETE, /api/, códigos 404/422/500, PDO ni MariaDB en el texto que se
   ve. Los errores de la API se traducen a español en un solo archivo
   (cliente_api.php), no en cada vista.
5. Todo en español: nombres, comentarios y mensajes. PHP 8.3 con
   declare(strict_types=1) en cada archivo.
6. Yo trabajo en Windows con un IDE (VS Code, usando su terminal integrada
   de PowerShell) y Docker Desktop. Dame los comandos para ese entorno.
7. La base de datos YA VIENE DADA en db/init.sql — se monta tal cual en el
   compose; no escribas ni modifiques SQL de creación de tablas. Bootstrap
   también viene dado en front_php/publico/: enlaza esos archivos locales
   (/publico/bootstrap.min.css), NUNCA un CDN, y no me dictes su contenido.
8. En mi máquina TAMBIÉN corre el proyecto clonado del curso con sus
   puertos originales. Para que ambos convivan, MI proyecto:
   a. Publica los puertos del host con +100: en el docker-compose.yml el
      front va "8120:8020", la API va "8122:8022" y MariaDB va
      "13426:3306" (adentro de los contenedores todo queda igual que en
      los documentos — incluido URL_API, que sigue apuntando a
      http://api-facturas:8022 porque esa es la red interna).
   b. El docker-compose.yml empieza con la línea `name: mi_v1_producto`
      (antes de services:) — así Docker lo trata como un proyecto
      distinto al del curso, con sus propios contenedores y volúmenes,
      aunque las carpetas se llamen parecido.
   El DSN por defecto del ensamblador (para correr sin Docker) apunta a
   localhost:13426. Cuando me des URLs o comandos de prueba, usa
   localhost:8120 (la pantalla), localhost:8122 (API) y localhost:13426 (BD).

Al final, la versión 1 está TERMINADA solo cuando pasan los 10 criterios de
aceptación de 2_spec.md, verificados con el smoke test de 7_quickstart.md —
incluidos los cuatro que solo se pueden comprobar desde la pantalla.

Empieza: resume en máximo 10 líneas qué vamos a construir (para confirmar que
entendiste el alcance) y luego arranca con la Fase 0.
```

### A.4 El método de la conversación

1. **Pegue primero, ejecute cuando quiera.** Lo obligatorio es pegar cada
   archivo en su ruta y responder "listo". Las verificaciones de cada fase
   puede correrlas en el momento (ideal: detecta errores temprano) o
   dejarlas todas para el final — **no son un peaje para avanzar**.
2. **No se quede varado en un error.** Si algo falla en una fase y no sale
   rápido, anótelo, siga copiando las fases siguientes y retómelo al
   final — muchos errores desaparecen cuando el sistema está completo, y
   los que queden se corrigen en el paso siguiente.
3. **El punto de control real es el smoke test final** (7_quickstart.md):
   al terminar las fases, córralo en la terminal integrada del IDE y pegue
   en el chat CADA error tal cual salga (completo). La IA le entrega el
   archivo corregido, usted lo pega y repite hasta que los 10 criterios
   estén en verde. **Ojo con los puertos**: SU proyecto corre con +100
   (regla 8 del prompt) — donde el quickstart diga `localhost:8020` use
   `localhost:8120`, donde diga `localhost:8022` use `localhost:8122`, y
   donde diga `13326` use `13426`. En `pruebas_humo\humo_front.py` eso son
   dos líneas al principio del archivo (`FRONT` y `API`).
4. **Si la IA se acelera** y entrega varios archivos de un tirón,
   recuérdele la regla 2b: "de a uno, espera mi listo".
5. **Si la primera respuesta llega en OTRO lenguaje** (Python, Java, Node…),
   no corrija sobre eso: es la señal inequívoca de que la IA **no leyó los
   adjuntos**. Cierre ese chat, verifique que los 8 documentos realmente
   cargaron (deslice el carrusel de adjuntos) y que son los de ESTE proyecto
   (3_plan.md debe decir PHP 8.3 + MariaDB), y empiece de nuevo con el
   prompt tal cual.
6. **Si el chat pierde el contexto** (conversaciones largas): abra un chat
   nuevo, vuelva a subir los 8 documentos y agregue al prompt: "Ya tengo
   construidas las fases 0 a N; te pego el código actual. Continuemos en la
   fase N+1" (y pegue sus archivos).

---

## Camino B — IDE agéntico (Antigravity, Cursor, Claude Code…)

Un IDE agéntico tiene a la IA **dentro del proyecto**: lee los archivos del
proyecto por sí misma, crea y edita código directamente, y puede ejecutar
comandos en la terminal (pidiendo permiso). Usted pasa de operador a
**supervisor**.

### B.1 Preparación

**Igual que en el chat: NO se trabaja dentro de la carpeta clonada** (esa es
la referencia). El agente construye en SU proyecto:

1. Cree una carpeta nueva y vacía para su proyecto (ej.: `mi_v1_producto/`) y
   copie dentro: los 8 documentos de la tabla A.1 en una carpeta `docs\spec_kit\`
   replicando la estructura por versiones (`docs\spec_kit\1_constitution.md` +
   `docs\spec_kit\versiones\v1_producto_mariadb\` con los 7 de la versión — los
   mismos comandos de A.2, pasos 3 a 5), y el script `db/init.sql` del
   repositorio en `db/init.sql` (la BD completa viene dada — el agente no
   debe generarla).
2. Abra SU carpeta en el IDE (en Antigravity: *Open Folder*; el agente verá
   `docs/spec_kit/` — no hay que subirle nada).
3. Tenga Docker Desktop corriendo (el agente necesitará levantar MariaDB).
4. Active el modo agente (en Antigravity, el *Agent Manager*; en otros IDE,
   el chat en modo "agent").

### B.2 El prompt para el agente (cópielo tal cual)

```
Construye la VERSIÓN 1 de este proyecto, partiendo de cero.

Primero lee, en este orden, los 8 documentos que están bajo docs/spec_kit/
(1_constitution.md en la raíz; los demás en versiones/v1_producto_mariadb/):
1_constitution, 2_spec, 3_plan, 4_research, 5_data_model, 6_contracts,
7_quickstart y 8_tasks. Después resume en máximo 10 líneas qué vas a
construir y espera mi confirmación antes de tocar nada. El código va en la
raíz de este proyecto según la estructura de 3_plan.md (docs/spec_kit/ es solo
lectura: no la modifiques). La base de datos YA VIENE DADA en db/init.sql —
úsala tal cual para montar MariaDB; no escribas ni modifiques SQL de creación
de tablas.

REGLAS (no negociables):

1. La especificación manda. No agregues NADA que los documentos no pidan:
   ni frameworks, ni Composer, ni librerías, ni tablas extra, ni motores
   extra, ni fábricas "por si acaso". Si crees que falta algo, pregúntame.
2. Sigue 8_tasks.md FASE POR FASE. Al terminar cada fase, EJECUTA su
   verificación (la que dice la propia fase), muéstrame el resultado real,
   y espera mi OK antes de pasar a la siguiente.
3. El código debe cumplir 6_contracts.md al pie de la letra: verbos, rutas,
   códigos de estado y formatos de respuesta exactos (incluido el contraste
   PUT=reemplazo completo vs PATCH=parcial y el 422 con la lista de errores
   de la validación del controlador). Del lado del front, las direcciones y
   las pantallas de la sección 9 del mismo documento.
4. Todo en español, PHP 8.3 con declare(strict_types=1), con los comentarios
   didácticos que exige la constitución.
5. Bootstrap ya está en front_php/publico/: enlaza esos archivos locales
   (/publico/bootstrap.min.css), nunca un CDN, y no los reescribas.
6. Esta versión incluye SU FRONT (front_php), no solo la API. El front no
   toca la base de datos: nada de new PDO ahí, y NO le hagas require de
   ningún archivo de api_facturas aunque los dos estén en PHP y funcionaría
   — lo único que comparten es el JSON. En el compose, el servicio del
   front no lleva credenciales de base de datos ni depends_on de mariadb.
7. Ninguna pantalla le habla al usuario en jerga: nada de verbos HTTP,
   códigos de estado, /api/, PDO ni MariaDB en el texto que se ve.
8. Al final, corre el smoke test completo de 7_quickstart.md —incluida la
   prueba de apagar la API con la base encendida— y muéstrame la evidencia
   de los 10 criterios de aceptación de 2_spec.md. La versión no está
   terminada hasta que los 10 estén en verde.
```

### B.3 El método de supervisión

1. **Revise cada diff antes de aceptar.** El IDE muestra qué archivos creó o
   cambió el agente — léalos. Si un archivo no está en la estructura de
   `3_plan.md` §2, pregunte por qué existe.
2. **Exija la evidencia, no el relato.** "Ya pasa la fase 3" no vale: pida la
   salida real del comando de verificación. Los agentes a veces declaran
   éxito sin ejecutar.
3. **Vigile el alcance igual que en el chat.** Si aparece un `composer.json`,
   un framework, una fábrica multi-motor o una tabla `persona`, el agente se
   salió de la v1: "eso no está en la spec de esta versión, quítalo".
4. **El cierre lo corre usted.** Aunque el agente haya corrido el smoke test,
   ejecútelo usted mismo de principio a fin (`7_quickstart.md`): esa es SU
   verificación de que la versión está terminada. Y el recorrido a mano de
   la §3.2 **no lo puede hacer el agente por usted**: juzga si la pantalla
   se entiende, y eso no lo mide ningún guion.
5. **Consejo de Antigravity:** el agente genera "walkthroughs"/artefactos de
   lo que hizo — guárdelos: son evidencia de su proceso para la entrega.

---

## Por qué funciona (la lección del curso)

Esto ES spec-driven development ([SDD_SPECKIT.md](SDD_SPECKIT.md)): la misma
IA que con "hazme una API de productos en PHP" produce cualquier cosa, con
una constitución + spec + plan + tareas produce EL sistema especificado — y
usted puede verificarlo contra criterios escritos antes de la primera línea
de código. Note que **las reglas de los dos prompts son las mismas**; solo
cambia quién ejecuta. La habilidad que está practicando no es "pedirle código
a la IA": es **dirigirla con especificaciones** — y eso funciona igual en un
chat gratuito que en el IDE agéntico más sofisticado.
