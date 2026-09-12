# Proyecto PHP — versión 4: el mismo sistema contra tres motores

Proyecto de curso (USB Medellín). Aquí NO se descarga un sistema terminado:
**se construye un sistema real por versiones en PHP puro**, guiado por
especificaciones. El repositorio siempre contiene la **versión en curso,
funcionando** — usted la ejecuta, la estudia y luego la **reconstruye desde
cero** en su propio proyecto.

> 🐳 Esta variante corre sobre **Docker**. Para las salas SIN Docker existe
> el repositorio gemelo
> [proyecto_php_sin_docker](https://github.com/ccastro2050/proyecto_php_sin_docker)
> (XAMPP + PHP 8.3) — misma API, misma spec, otra infraestructura.

---

## 1. Cómo le trabaja el estudiante (léame primero)

### Qué necesita instalado (una sola vez)

| Herramienta | Para qué |
|---|---|
| **Git** | Clonar el repositorio y traer versiones nuevas |
| **Docker Desktop** | La BD y la API corren en contenedores (no se instala MariaDB ni PHP) |
| **VS Code** | El editor — y su terminal integrada (*Terminal → New Terminal*) |

> PHP local es **opcional** (solo para desarrollar fase a fase sin Docker):
> PHP 8.3 con la extensión `pdo_mysql` habilitada.

### Primera vez: cargar y EJECUTAR la versión (un solo comando)

En la terminal integrada de VS Code (*Terminal → New Terminal*, PowerShell):

> ⚠️ **ANTES de clonar — solo si usted ya corrió OTRO proyecto de estos
> cursos en este PC:** puede quedar un contenedor viejo encendido ocupando
> el puerto 8090 (pasa al reiniciar el PC: la API vieja revive sin su
> base de datos y "secuestra" el puerto — el contenedor huérfano). El
> síntoma: Swagger abre, pero todo responde 500 con *"No address
> associated with hostname"*, y usted cree que el error es de ESTE
> proyecto cuando en realidad está hablando con el viejo. Verifíquelo y
> apáguelo primero:
>
> **Los dos comandos se copian y se pegan TAL CUAL.** No hay nada que
> reemplazar — ni el `proyecto_`, ni el `$_`. Ese `$_` es de PowerShell y
> significa «cada uno de los que vinieron por la tubería»; si usted lo
> cambia por algo, deja de funcionar.
>
> **Paso 1 — VERIFICAR.** ¿Quedó algo del curso encendido?
>
> ```powershell
> docker ps --filter "name=proyecto_"
> ```
>
> Si hay algo, se ve así:
>
> ```
> NAMES                          STATUS                    PORTS
> proyecto_php4-api-facturas-1   Up 2 hours                0.0.0.0:8090->8090/tcp
> proyecto_php4-mariadb-1        Up 2 hours (healthy)      0.0.0.0:13329->3306/tcp
> ```
>
> **Si no hay nada, sale solo el encabezado** —`NAMES  STATUS  PORTS`— y
> ninguna línea debajo. En ese caso no tiene que limpiar nada: siga.
>
> **Paso 2 — LIMPIAR.** Apaga de una vez todos los del curso:
>
> ```powershell
> docker ps --filter "name=proyecto_" -q | ForEach-Object { docker stop $_ }
> ```
>
> Va imprimiendo el identificador de cada uno que apaga. Para comprobar que
> quedó limpio, repita el paso 1: debe salir solo el encabezado.
>
> **Qué efecto tiene:** apaga los contenedores. **No borra nada** — los datos
> quedan en sus volúmenes y cada proyecto se vuelve a encender con su
> `docker compose up -d`. Funciona aunque ya no tenga la carpeta vieja.
> También sirve el botón **Stop** de Docker Desktop, uno por uno.
>
> Solo entonces continúe.

```powershell
git clone https://github.com/ccastro2050/proyecto_php4.git
cd proyecto_php4
docker compose up -d --build
```

**Eso es todo.** La primera vez tarda unos minutos (descarga imágenes). Al
terminar queda corriendo el sistema completo:

| Qué | Dónde |
|---|---|
| **La pantalla** — empiece por aquí | **http://localhost:8088** |
| **API Facturas** — diagnóstico (dice qué motor está activo) | http://localhost:8090/ |
| Listar facturas (JSON) | http://localhost:8090/api/factura |
| **phpMyAdmin** (administrar MariaDB desde el navegador) | http://localhost:8104 |
| MariaDB (opcional) | `localhost:13329` · `paradigmas`/`paradigmas123` |
| PostgreSQL (opcional) | `localhost:15465` · `paradigmas`/`paradigmas123` |
| **SQL Server** (opcional) | `localhost:11474` · **`sa`/`Paradigmas123!`** |

> **Los puertos son distintos a los de las otras versiones** a propósito: así
> se pueden tener varias encendidas al mismo tiempo y compararlas.

**Empiece por el 8088**, que es como lo ve alguien que no programó esto. Use
el sistema un rato: cree una empresa, haga una factura de dos productos,
anúlela.

Y ahora, tres veces lo mismo. **Mire el pie de la pantalla: dice MariaDB.**

```powershell
$env:MOTOR = "postgres"      # después "sqlserver", después "mariadb"
docker compose up -d --no-deps --force-recreate api-facturas
```

Refresque. La etiqueta del pie cambió — **y nada más**. Las mismas seis
secciones, los mismos botones, los mismos mensajes de error palabra por
palabra. Vuelva a hacer todo lo de arriba: se siente igual.

**Lo que NO hubo que hacer para eso:** editar un archivo, reconstruir una
imagen, tocar el front. Una variable y un reinicio.

Y lo que hay debajo de esa etiqueta no se parece en nada entre sí: uno no
tiene `LIMIT`, otro devuelve los enteros como enteros y el tercero como
texto, y cada uno reporta un error de integridad con un código distinto. Todo
eso vive encerrado en dieciocho clases de la capa de datos, y ninguna capa de
arriba se entera.

> Lo único que sí cambia son los datos: el cliente que creó con MariaDB no
> está aquí. **Son tres bases independientes**, y está declarado en el
> alcance: esto demuestra que el *programa* funciona contra las tres, no que
> las tres sean la misma.

Y la demostración de que son dos procesos, en dos comandos:

```powershell
docker compose stop api-facturas    # la BASE DE DATOS sigue encendida
```

Refresque <http://localhost:8088/facturas>: la pantalla sigue en pie, con su
menú y un aviso de que el servicio no está disponible — **y sin una sola
fila**. Los datos siguen ahí, a un puerto de distancia; si aparecieran, sería
porque el front llegó a la base por su cuenta. `docker compose start
api-facturas` y vuelven.

> ℹ️ Este proyecto usa los puertos 8088, 8090, 8104, 13329, 15465 y 11474:
> si alguno ya está ocupado en su máquina, cámbielo en `docker-compose.yml`
> (el lado izquierdo del `"puerto:puerto"`).
>
> ⏱️ **La primera vez tarda más que las otras versiones**: la imagen de la API
> compila el driver de SQL Server, y SQL Server tarda medio minuto en estar
> listo antes de que se pueda crear su base. Mírelo con
> `docker compose logs -f sqlserver-init`.

### Los días siguientes (volver a encender)

```powershell
docker compose up -d        # segundos; los datos se conservan
```

### Cuando hay cambios

| Qué cambió | Qué hacer |
|---|---|
| **Usted edita un `.php`** | **Nada** — el código está montado como volumen y PHP reinterpreta cada petición: guardar y refrescar (F5) |
| **El profesor publicó una versión nueva** | `git pull` y `docker compose up -d --build` |
| **Cambió el `Dockerfile`** | `docker compose up -d --build` (reconstruye la imagen) |
| **Quiere resetear la BD** a sus datos originales | `docker compose down -v` y luego `docker compose up -d` (⚠️ borra los datos) |
| **Apagar todo** | `docker compose down` (los datos se conservan) |

### Y ahora, SU trabajo: reconstruirla desde cero

Ejecutar la versión del repo es solo el punto de partida. Lo que se evalúa es
**reconstruirla usted mismo, en una carpeta propia (fuera del clon)**,
siguiendo las especificaciones — con o sin ayuda de IA:

> 🤖 ¿Va a trabajar con IA? Siga la **[Guía para construir la versión 4 con
> IA](docs/spec_kit/versiones/v4_sqlserver/GUIA_IA4.md)** — cubre los dos
> caminos con su prompt exacto listo para copiar: **chat web** (Gemini,
> DeepSeek, ChatGPT: qué archivos subirle) e **IDE agéntico** (Antigravity,
> Cursor, Claude Code: cómo supervisar al agente).
>
> Trae además una sección que la guía de la v1 no tenía: **las tres
> tentaciones de esta versión** —el enrutador genérico, la comprobación
> previa de llaves foráneas y el total calculado en PHP—, que una IA propone
> con toda naturalidad porque las tres funcionan, y que las tres están mal.

### Conceptos resumidos (los que acaba de usar)

| Concepto | En una frase |
|---|---|
| **Clonar** | Descargar el repositorio con su historial; `git pull` trae lo nuevo |
| **Contenedor** | BD y API corren en "cajas" de Docker: nada que instalar, se borran y recrean sin miedo |
| **docker compose** | UN archivo declara todo el sistema y UN comando lo levanta (`up -d`) |
| **Volumen** | Donde viven los datos: `down` los conserva, `down -v` los borra (reset) |
| **PHP reinterpreta** | No hay "reload": cada petición vuelve a leer los `.php` — guardar y refrescar ES el ciclo |
| **Spec kit** | Los documentos que dicen QUÉ/CÓMO/EN QUÉ ORDEN — la fuente de verdad |
| **Versión / tag** | Un incremento cerrado y verificado (`v1`, `v2`, …): se avanza solo en verde |

> Detalle de los conceptos Docker: [docs/CONCEPTOS_DOCKER.md](docs/CONCEPTOS_DOCKER.md).

---

## 2. Estructura del repositorio

Qué es cada carpeta y cada archivo, y para qué sirve:

```
proyecto_php4/
├── docker-compose.yml           # TODO el sistema declarado: MariaDB + API + FRONT
│                                #   + phpMyAdmin (el "un solo comando" del proyecto)
├── db/                          # LA MISMA base, en TRES dialectos
│   ├── mariadb/init.sql         # Crea bdfacturas COMPLETA (12 tablas, triggers, datos).
│   │                            #   MariaDB lo ejecuta sola la PRIMERA vez (volumen vacío)
│   ├── mariadb/init_phpmyadmin.sql  # BD interna de phpMyAdmin (habilita el Diseñador)
│   ├── postgres/init.sql        # La misma base, en dialecto PostgreSQL
│   └── sqlserver/               # bdfacturas.sql en T-SQL + init.sh, que la
│                                #   crea (su imagen no lo hace sola)
│
├── backupdb/                    # Respaldos (dumps) de la BD — su README explica
│                                #   cómo hacer el backup y cómo restaurarlo
│
├── postman/                     # La colección de Postman lista para importar:
│                                #   los endpoints de la v1 con clics (no hay Swagger
│                                #   en PHP puro — Postman cumple ese papel)
│
├── api_facturas/                # LA API — PHP puro, sin framework (puerto 8090)
│   ├── Dockerfile               # Su imagen: php:8.3-cli + extensión pdo_mysql
│   ├── index.php                # Front controller: TODA petición entra aquí y se enruta
│   ├── controladores/           # Capa 1 — HTTP: valida el body (422) y traduce a
│   │                            #   códigos de estado y JSON
│   ├── servicios/               # Capa 2 — negocio: interfaz, reglas y el ensamblador
│   │                            #   (la proto-fábrica que arma las capas)
│   ├── repositorios/            # Capa 3 — datos: interfaces + TRES implementaciones
│   │                            #   por recurso, una por motor. Ábralas lado a lado
│   ├── modelos/                 # Producto: la clase entidad clásica (propiedades
│   │                            #   privadas + getters/setters + toArray)
│   ├── excepciones/             # NoEncontradoExcepcion (el servicio la lanza → 404)
│   └── pruebas/                 # prueba_capas.php: repositorio FALSO en memoria
│                                #   (demuestra que las capas se desacoplan de verdad)
│
├── front_php/                   # LA PANTALLA — PHP puro también (puerto 8088)
│   ├── Dockerfile               # Su imagen: php:8.3-cli SIN pdo_mysql — la ausencia
│   │                            #   es la comprobación: no puede llegar a la BD
│   ├── index.php                # Front controller del front: ruta → pantalla
│   ├── cliente_api.php          # Lo ÚNICO que habla con la API (y traduce sus errores)
│   ├── vistas/                  # Las plantillas: el marco, el listado y el formulario
│   └── publico/                 # Bootstrap GUARDADO aquí (no traído de un CDN:
│                                #   el salón puede quedarse sin internet) + estilos.css
│
├── pruebas_humo/                # humo_front.py: recorre el sistema DESDE LA PANTALLA
│                                #   y apaga la API para probar que son dos procesos
│                                # humo_los_tres_motores.py: corre lo anterior contra
│                                #   los tres motores — la prueba de la v4
├── docs/
│   ├── spec_kit/                # LAS ESPECIFICACIONES: constitución permanente +
│   │                            #   una carpeta de specs por versión (v1, v2, …)
│   │                            #   + la GUIA_IA de ESA versión (GUIA_IA1, GUIA_IA2…):
│   │                            #   cómo reconstruirla desde 0 con ayuda de una IA
│   ├── ARRAYS_Y_SUPERGLOBALES.md  # Material conceptual: arreglos y superglobales,
│   ├── PARADIGMA_POO.md         #   POO, SOLID+capas, ACID,
│   ├── SOLID_CAPAS_PATRONES.md         #   Docker y SDD (un .md por tema)
│   ├── PRINCIPIOS_ACID.md       #
│   ├── CONCEPTOS_DOCKER.md      #
│   ├── SDD_SPECKIT.md           #
│   ├── TUTORIAL_PHPMYADMIN.md   # Tutoriales de administración de la BD, paso a paso
│   ├── TUTORIAL_VSCODE_SQLTOOLS.md  #   con capturas reales
│   └── img_phpmyadmin/ img_sqltools/  # Las capturas de esos tutoriales
│
├── .gitignore / .gitattributes  # Higiene del repo (ignora .session.sql, normaliza EOL)
└── README.md                    # Este archivo
```

La regla de lectura: **el sistema vive en `docker-compose.yml`**, la API
vive en `api_facturas/` (una carpeta por capa), la pantalla en `front_php/`,
y **todo lo que explica** vive en `docs/`. Cuando lleguen las versiones siguientes, aquí aparecerán
más carpetas de componentes (y el compose crecerá con ellas).

## 3. La ruta de versiones

```
v1  producto de punta a punta                    repo proyecto_php1 (cerrada)
v2  seis recursos, integridad y maestro-detalle  repo proyecto_php2 (cerrada)
v3  segundo motor (PostgreSQL) — la fábrica      repo proyecto_php3 (cerrada)
v4  tercer motor (SQL Server) + compose completo ← USTED ESTÁ AQUÍ
                                                   (cerrada: tag v4)
```

**Y aquí termina la ruta.** No hay v5, y es una decisión: un cuarto motor no
enseñaría nada que el tercero no haya mostrado ya. El porqué está en el
[mapa de versiones](docs/spec_kit/versiones/0_mapa_versiones.md).

**Cada versión vive en su propio repositorio**, y no en una rama. La razón es
práctica: así se pueden tener dos versiones encendidas al mismo tiempo y
compararlas — por eso cada una usa puertos propios. La v1 sigue siendo un
ejemplo completo y ejecutable aunque ésta ya exista.

**Cada versión incluye su front.** No hay una versión final que "agregue la
pantalla": una versión no está cerrada si la API responde y la pantalla no
(Artículo 1.1 de la constitución). Antes este proyecto lo tenía al revés, con
el front en una v5, y se cambió por una razón concreta: los desajustes entre
la API y la pantalla aparecen el día que alguien pinta una tabla — y
descubrirlos con cuatro versiones de API encima ya no es corregir, es
rehacer.

La regla del juego: la **constitución** es permanente, cada versión tiene su
propia spec, y una versión está TERMINADA solo cuando pasa sus criterios de
aceptación (se cierra con tag). Detalle completo:
**[mapa de versiones](docs/spec_kit/versiones/0_mapa_versiones.md)**.

## 4. Las especificaciones de la versión actual (v1)

| Documento | Qué contiene |
|---|---|
| [Constitución](docs/spec_kit/1_constitution.md) | Las reglas permanentes del proyecto (PHP puro, capas, un comando) |
| [2_spec.md](docs/spec_kit/versiones/v4_sqlserver/2_spec.md) | QUÉ construir y los 13 criterios — **incluido el 13, que dice qué NO tocar** |
| [3_plan.md](docs/spec_kit/versiones/v4_sqlserver/3_plan.md) | CÓMO: el tercer motor, y **la tabla de los tres lado a lado** |
| [4_research.md](docs/spec_kit/versiones/v4_sqlserver/4_research.md) | Las decisiones y sus alternativas descartadas *(lectura opcional)* |
| [5_data_model.md](docs/spec_kit/versiones/v4_sqlserver/5_data_model.md) | La misma base, en tres dialectos: en qué se parecen y en qué no |
| [6_contracts.md](docs/spec_kit/versiones/v4_sqlserver/6_contracts.md) | Los mismos de la v2, por tercera vez — **y que no cambien es el resultado** |
| [7_quickstart.md](docs/spec_kit/versiones/v4_sqlserver/7_quickstart.md) | Smoke test para validar lo construido |
| [8_tasks.md](docs/spec_kit/versiones/v4_sqlserver/8_tasks.md) | Las fases de construcción, en orden |

## 5. Material conceptual del curso

| Documento | Qué cubre |
|---|---|
| [El flujo de una petición](docs/FLUJO_DE_UNA_PETICION.md) | **Léalo primero:** dónde está el GET, dónde se captura el POST, y el viaje completo de una petición por las capas — con los comandos para probar los 5 verbos |
| [Colección de Postman](postman/README.md) | Los 13 endpoints de la v1 listos para importar y probar con clics — incluida la pareja PUT=422 vs PATCH=200 |
| [SDD y Spec Kit](docs/SDD_SPECKIT.md) | La metodología con la que se trabaja este curso: la spec manda sobre el código |
| [Calidad de las pruebas](docs/CALIDAD_DE_PRUEBAS.md) | Cobertura, la métrica CRAP y mutation testing: cómo saber si sus pruebas de verdad protegen — y por qué hoy es reto opcional, no alcance del proyecto |
| [Programación asincrónica](docs/PROGRAMACION_ASINCRONICA.md) | Qué resuelve el async/await en la web, qué se daña sin él (con diagramas), y cómo se ve en el código de este proyecto |
| [Arreglos y superglobales en PHP](docs/ARRAYS_Y_SUPERGLOBALES.md) | Qué es un `array` en PHP y en qué se diferencia del de Java; el CRUD sobre un arreglo; las funciones de arreglo que este proyecto usa, con el conteo real; y las superglobales —`$_GET`, `$_POST`, `$_SERVER`, `$_SESSION`— con la línea del proyecto donde aparece cada una |
| [El paradigma P.O.O. en PHP](docs/PARADIGMA_POO.md) | Qué es un paradigma, los 4 pilares, y las `interface` de PHP + la validación como frontera |
| [SOLID, capas y patrones de diseño](docs/SOLID_CAPAS_PATRONES.md) | Los 5 principios y las capas — y en qué versión se demuestra cada uno |
| [Principios ACID](docs/PRINCIPIOS_ACID.md) | Las 4 garantías transaccionales, por qué una facturación las exige, y el contraste con BASE |
| [Conceptos de Docker](docs/CONCEPTOS_DOCKER.md) | Imagen, contenedor, volumen, compose (con el `docker-compose.yml` del proyecto explicado línea por línea) y por qué NO se necesita Kubernetes |
| [Tutorial phpMyAdmin](docs/TUTORIAL_PHPMYADMIN.md) | Administrar bdfacturas desde el navegador: estructura, Diseñador, SQL, edición y respaldo — paso a paso con capturas |
| [Tutorial SQLTools (VS Code)](docs/TUTORIAL_VSCODE_SQLTOOLS.md) | La misma BD sin salir del editor: instalación, conexión, explorar y consultar — paso a paso con capturas |

---

*Proyecto PHP · USB Medellín · Base de datos bdfacturas (facturación + RBAC).*
