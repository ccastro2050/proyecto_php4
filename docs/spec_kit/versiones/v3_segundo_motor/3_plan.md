# Plan técnico — Versión 3: la fábrica y lo que cambia por motor

> **Versión 3** · CÓMO construir lo especificado en [2_spec.md](2_spec.md).
> El porqué de cada decisión: [4_research.md](4_research.md) · contratos:
> [6_contracts.md](6_contracts.md) · orden: [8_tasks.md](8_tasks.md).

---

## 1. Stack

Ni una dependencia nueva de PHP. Lo que se agrega es un motor y un driver.

| Pieza | v2 | v3 |
|---|---|---|
| Lenguaje | PHP 8.3, `declare(strict_types=1)` | igual |
| Acceso a datos | PDO con `pdo_mysql` | PDO con **`pdo_mysql` y `pdo_pgsql`** |
| Motores | MariaDB 11 | **+ PostgreSQL 16** |
| Imagen de la API | `php:8.3-cli` + `pdo_mysql` | **una sola** imagen con los dos drivers |
| Front | sin cambios | + la etiqueta del motor en el pie |

**Una sola imagen para los dos motores**, y no dos. Es deliberado: el punto de
la versión es que **es el mismo programa**. Dos imágenes lo habrían escondido.

## 2. Estructura de carpetas

Solo se muestra lo que cambió respecto de la v2.

```
├── db/
│   ├── mariadb/                      ← la carpeta que tenía la base, ahora con apellido
│   │   ├── init.sql
│   │   └── init_phpmyadmin.sql
│   └── postgres/
│       └── init.sql                  ← LA MISMA base, en dialecto de PostgreSQL
│
├── api_facturas/
│   ├── repositorios/
│   │   ├── IRepositorio*.php               ← NO CAMBIARON (RNF1)
│   │   ├── Repositorio*MariaDB.php         (6, de la v2)
│   │   ├── Repositorio*Postgres.php        ← LOS SEIS NUEVOS
│   │   ├── conflictos.php                  ← NUEVO: las frases, una sola vez
│   │   ├── errores_de_integridad_mariadb.php    ← el archivo de la v2, partido
│   │   └── errores_de_integridad_postgres.php   ← en dos
│   └── servicios/
│       └── ensamblador.php           ← LA FÁBRICA. El archivo de esta versión
│
└── pruebas_humo/
    ├── humo_front.py                 ← el mismo, más la comprobación del motor
    └── humo_los_dos_motores.py       ← NUEVO: lo corre contra los dos
```

**Mover `db/init.sql` a `db/mariadb/init.sql` no es cosmética.** Mientras hubo
un motor, «la base» no necesitaba apellido. Ahora hay dos, y una carpeta
llamada `db/` con un solo `init.sql` adentro habría dejado la duda de a cuál
pertenece.

## 3. Arquitectura: la flecha que se bifurca

```
NAVEGADOR
     → front_php/index.php      (ruta → pantalla)
     → cliente_api.php
     ↓  HTTP + JSON
     → api_facturas/index.php   (método + ruta → controlador)
     → Controlador{Recurso}     ─┐
     → IServicio{Recurso}        │  NADA DE ESTO SE ENTERA
     → IRepositorio{Recurso}    ─┘
                    ↓
            ensamblador.php     ← AQUÍ, y solo aquí, se decide
             ┌──────┴──────┐
             ▼             ▼
   Repositorio*MariaDB   Repositorio*Postgres
             ▼             ▼
         MariaDB       PostgreSQL
```

La bifurcación está **abajo del todo**, y ésa es toda la arquitectura de la
versión. Todo lo que está por encima de `ensamblador.php` recibe una interfaz
y no pregunta qué hay detrás.

## 4. Decisiones de diseño clave

### 4.1 `ensamblador.php`: de armador a fábrica

En la v2 cada función armaba la única combinación que existía. Ahora escoge:

```php
function crearServicioCliente(): IServicioCliente
{
    [$dsn, $usuario, $clave] = datosDeConexion();
    return new ServicioCliente(
        motorActivo() === 'postgres'
            ? new RepositorioClientePostgres($dsn, $usuario, $clave)
            : new RepositorioClienteMariaDB($dsn, $usuario, $clave)
    );
}
```

Y `motorActivo()` es tres líneas:

```php
function motorActivo(): string
{
    return strtolower(trim((string) getenv('MOTOR'))) === 'postgres'
        ? 'postgres' : 'mariadb';
}
```

**Fíjese en que la comparación es contra un valor escrito aquí.** El texto de
la variable no se usa para armar nada — si alguien pone `MOTOR=oracle`, no
pasa nada raro: arranca en MariaDB.

**Y siguen siendo seis funciones, no una con un `switch`.** La tentación creció
con el segundo motor: se podría escribir `crearRepositorio($recurso, $motor)`
y armar el nombre de la clase con texto. Doce clases en cuatro líneas. Sigue
estando mal por lo mismo que la API no es genérica (Artículo 10):

- con las clases escritas, PHP verifica los tipos al llamar la función;
- con nombres armados en texto, el error sale **en producción**, el día que
  alguien pida el recurso que nadie probó **con el motor que nadie probó** —
  y ahora las combinaciones sin probar son el doble;
- y quien lea el archivo ve, de un vistazo, qué doce clases existen.

### 4.2 Una clase por motor, y no un `if` adentro de una sola

La alternativa era un `RepositorioCliente` único con `if ($motor === 'postgres')`
en cada método. Se descartó, y §4.3 explica por qué mirando lo que de verdad
cambia: no es una línea por método, son **cinco cosas distintas** repartidas.

### 4.3 Lo que cambia entre los dos motores (la tabla que justifica todo)

Ésta es la tabla que hay que mirar antes de opinar sobre si hacía falta
separar las clases:

| | MariaDB | PostgreSQL |
|---|---|---|
| Prefijo del DSN | `mysql:` | `pgsql:` |
| `rowCount()` de un UPDATE | cuenta filas **cambiadas** — hay que pedir `MYSQL_ATTR_FOUND_ROWS` para que cuente las encontradas | cuenta las encontradas, sin pedir nada |
| Leer una llave autogenerada | `lastInsertId()`, **en otra consulta** | `INSERT … RETURNING id`, en la misma |
| Llave duplicada | código `1062` en `errorInfo[1]` | SQLSTATE `23505` en `getCode()` |
| Referencia rota | `1452` | `23503` |
| Borrar algo con dependientes | `1451` | **`23503` — el mismo**; hay que mirar el texto |
| Parámetro de salida de un procedimiento | `OUT`; PDO no lo lee: hay que usar `@resultado` y **otra consulta** | `INOUT`; **llega como fila del propio `CALL`** |
| Después de un `CALL` | `closeCursor()` obligatorio | no hace falta |
| Rechazo de un procedimiento | `SIGNAL SQLSTATE '45000'` | `RAISE EXCEPTION` → `P0001` |
| El mensaje de ese rechazo | trae el número del motor **delante** | trae un bloque `CONTEXT:` **detrás** |
| `INT` al leerlo | llega como **texto** | llega como **entero** |
| `NUMERIC`/`DECIMAL` | texto | texto |

Doce diferencias, y ninguna en el `SELECT`. **Ése es el hallazgo que más
sorprende de esta versión:** las consultas resultaron idénticas —esta base usa
SQL estándar— y aun así hacían falta clases separadas, porque **todo lo de
alrededor cambia**.

Un solo `RepositorioCliente` con `if`s habría tenido que acumular esas doce
diferencias adentro, y habría hecho imposible el criterio 13.

### 4.4 Los errores de integridad: un archivo se partió en tres

La v2 tenía `errores_de_integridad.php`, que hacía dos cosas a la vez:
**reconocer** el problema (mirando los códigos de MariaDB) y **redactarlo** en
español. Mientras hubo un motor eso estaba bien; con dos se vio que eran dos
trabajos distintos, porque uno cambia con el motor y el otro no.

| | ¿Cambia con el motor? | Dónde vive ahora |
|---|---|---|
| Reconocer que fue una llave duplicada | **Sí** (`1062` vs `23505`) | `errores_de_integridad_{motor}.php` |
| Explicárselo al usuario | **No** | `conflictos.php`, compartido |

Si cada motor redactara sus propios mensajes, la MISMA petición contra dos
motores le diría cosas distintas al usuario. El contrato promete un texto, no
un código.

**Ésta es la clase de costura que solo se ve cuando llega el segundo motor.**
No es que la v2 estuviera mal: es que con un solo motor no había forma de
saber dónde estaba la frontera.

### 4.5 La fecha, y otras uniformidades que hay que forzar

PostgreSQL devuelve la fecha de una factura con microsegundos
(`2025-12-03T12:57:19.27592`); MariaDB, con segundos. El contrato promete un
formato, así que el repositorio de PostgreSQL la recorta:

```php
substr((string) ($cabeza['fecha'] ?? ''), 0, 19),
```

Es una línea, y vale un párrafo: **quien consuma la API no tiene por qué
notar cuál motor hay detrás.** Cada vez que un motor entrega algo con otra
forma, el trabajo de emparejarlo es del repositorio — nunca del servicio, y
mucho menos de la pantalla.

Lo mismo con `json_agg` de PostgreSQL, que devuelve `NULL` en vez de una lista
vacía cuando no hay filas que agregar: el `?? []` está para que un dato raro
no tumbe la pantalla.

### 4.6 El motor activo, publicado

`GET /` gana un campo `motor`. Es **el único sitio de la API** donde el motor
se menciona; ninguna otra respuesta lo nombra ni cambia de forma según cuál
sea.

Y el front lo muestra en el pie, en letra pequeña. El sitio es la mitad del
mensaje: al usuario no le importa. Está para poder comprobar la promesa de la
versión sin ir a leer el compose.

**Lo que el front NO hace con ese dato es lo que importa: nada.** No cambia
una consulta, no oculta un botón, no toma ninguna decisión. El día que el
front pregunte «¿estoy contra PostgreSQL?» para hacer algo distinto, la
separación de capas se rompió.

### 4.7 Cuándo se lee la variable: al arrancar, no en cada petición

Leerla en cada petición habría permitido cambiar de motor sin reiniciar — y
también que dos peticiones simultáneas usaran motores distintos, dejando la
mitad de los datos en cada base. Se lee al arrancar, y cambiar de motor es
reiniciar la API.

## 5. Docker: cuatro servicios y una variable

```yaml
services:
  mariadb:             # 13328 al host · db/mariadb/init.sql · healthcheck
  postgres:            # 15464 al host · db/postgres/init.sql · pg_isready
  api-facturas:        # 8086 · MOTOR: ${MOTOR:-mariadb}
                       #   y los DOS DSN, que viajan siempre
    depends_on: los dos, con condition: service_healthy
  front-php:           # 8084 · URL_API → http://api-facturas:8086
                       #   sin credenciales de base, como siempre
  phpmyadmin:          # 8103 (solo mira MariaDB)
volumes:
  mariadbdata:
  pgdata:
```

**`${MOTOR:-mariadb}`** significa: usa la variable `MOTOR` del entorno, y si no
está, usa `mariadb`. Así el comando de siempre sigue funcionando:

```powershell
docker compose up -d --build            # arranca en MariaDB
$env:MOTOR = "postgres"; docker compose up -d    # lo mismo, contra PostgreSQL
```

**Los dos juegos de credenciales viajan siempre**, y el ensamblador usa el que
corresponda. Se prefirió esto a tener dos servicios de API porque el punto de
la versión es que es el mismo programa.

## 6. Convenciones

Las de la constitución, más una que estrena esta versión: **el nombre del
motor es el apellido de la clase** (`RepositorioClientePostgres`), y ninguna
clase sin ese apellido puede contener SQL de un motor concreto.

## 7. Chequeo de constitución

| Artículo | Cómo lo cumple esta versión |
|---|---|
| **1** — Propósito didáctico | Todo en español; y la versión entera existe para hacer *visible* un principio que hasta ahora era una frase. |
| **1.1** — Una versión incluye su front | Cumple, aunque el front casi no cambió: lo que se le agregó es la etiqueta del motor, que es lo que permite comprobar la versión desde la pantalla. |
| **2** — PHP puro | Sin framework ni Composer. Lo único que se agregó es una extensión de PDO, que es del lenguaje. |
| **3** — Tres capas estrictas | Cumple, y **ahora se puede demostrar**: el criterio 13 se aprueba con un `diff`. |
| **4** — Un solo comando | `docker compose up -d --build` deja los cinco servicios funcionando. |
| **5** — Independencia del motor | **Es el artículo de esta versión.** Deja de ser meta y pasa a ser estado: el motor se escoge con configuración y el resto del sistema no se entera. |
| **6** — Persistencia | Dos volúmenes, uno por motor; `down -v` devuelve las dos bases a su estado original. |
| **7** — Recarga natural | PHP reinterpreta cada petición. (Cambiar de motor sí exige reiniciar: es configuración de arranque, no código — §4.7.) |
| **8** — Convenciones fijas | Puertos de esta versión, más la convención del apellido de motor (§6). |
| **9** — Seguridad académica | Credenciales didácticas; `htmlspecialchars` en todo lo que se pinta. |
| **10** — API específica | Las rutas no cambiaron. Y la fábrica siguió la misma regla: doce clases escritas, ningún nombre armado con texto (§4.1). |
| **11** — La base también es parte del sistema | Se cumple **dos veces**: los mismos triggers y procedimientos existen en los dos motores, y la API los usa en los dos sin recalcular nada. |

**Complejidad justificada:** la desviación consciente sigue siendo la de la
v2 —depender del texto de un mensaje para distinguir un 404 de un 409—, y en
esta versión se dobló: ahora hay que mirar el texto **también** para saber en
qué sentido falló una llave foránea en PostgreSQL (§4.3). Las dos están
aisladas en un método por motor, con su fragilidad escrita al lado.
