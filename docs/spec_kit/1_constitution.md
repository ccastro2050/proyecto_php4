# Constitución del Proyecto PHP

> Principios **innegociables** que gobiernan todo el proyecto. Esta
> constitución es **permanente**: describe el sistema COMPLETO al que se llega
> al final, y no cambia entre versiones.
>
> El proyecto se construye **por versiones** (desarrollo incremental guiado por
> especificaciones): ver el [mapa de versiones](versiones/0_mapa_versiones.md).
> Cada artículo aplica desde la versión que introduce su alcance — por ejemplo,
> en la v1 la API solo conoce `producto` y un motor, así que los artículos
> sobre los otros motores son la META, no el estado actual.
>
> **El front no está entre esas metas: existe desde la v1.** Por qué, en el
> Artículo 1.1.

---

## Artículo 1 — Propósito didáctico ante todo

Este proyecto existe para **enseñar PHP construyendo software real con
arquitectura** a estudiantes universitarios. Ante cualquier disyuntiva entre
"lo más profesional" y "lo más claro para aprender", gana la claridad:

- Todo el código, comentarios, mensajes y documentación se escriben en **español**.
- Cada archivo abre con un comentario que explica su papel en la arquitectura.
- Se prefiere código explícito y repetitivo-pero-legible sobre metaprogramación compacta.

### Artículo 1.1 — Una versión incluye SU FRONT

Una versión **no está cerrada** si la API responde y la pantalla no. El front
de lo que esa versión construye se hace **al mismo tiempo** que la API, no
después.

Esto es una corrección a cómo estaba pensado antes este proyecto, y la razón
de haberlo cambiado vale más que la regla:

- **Un front al final llega tarde para corregir.** Los desajustes entre lo
  que la API devuelve y lo que la pantalla necesita —el número que viaja como
  texto, el campo que se llama distinto, el 204 que nadie sabía interpretar—
  aparecen el día que alguien pinta una tabla. Descubrirlos en la v5, con
  cuatro versiones de API encima, es rehacer; descubrirlos en la v1, con un
  CRUD de una tabla, es corregir en diez minutos.
- **La API sin pantalla se cree terminada sin estarlo.** Un `curl` que
  responde 200 no dice si el dato sirve. La pantalla sí: obliga a decidir qué
  se muestra, cómo se dice el error en español y qué pasa cuando no hay
  filas.
- **Y sobre todo: la pantalla es la única prueba de que son dos procesos.**
  Se apaga la API con la base encendida; si el front pudiera llegar a la base
  por su cuenta, seguiría mostrando datos. No los muestra: muestra su aviso.
  Eso no se puede demostrar sin front.

El front es la mitad del trabajo de la versión, no un adorno que se le cuelga
al final.

## Artículo 2 — PHP puro: sin framework y sin Composer

- **PHP 8.3+ "vanilla"**: cero dependencias externas, cero `composer.json`,
  cero `vendor/`. Lo que el estudiante ve es PHP del lenguaje, no magia de un
  framework.
- El enrutamiento vive en UN front controller (`index.php`) legible completo.
- Los contratos entre capas son **`interface` nativas de PHP** — el lenguaje
  las trae de fábrica; aquí se usan de verdad.
- El acceso a datos es **PDO** con *prepared statements*: el SQL queda
  **visible** (nada de ORM que lo esconda).

## Artículo 3 — Arquitectura de 3 capas estricta

```
CAPA 1: FRONT (PHP, :8088)   — solo pinta HTML y llama la API; NUNCA toca la BD
CAPA 2: API  (PHP, :8090)    — api_facturas; solo JSON, nunca HTML
CAPA 3: DATOS                — PostgreSQL | MariaDB | SQL Server (bdfacturas)
```

- El front **no abre conexiones de base de datos**; solo habla HTTP con la API.
  En este proyecto la tentación es mayor que en otros, porque el front y la
  API están **los dos escritos en PHP**: un `require_once` de una clase de la
  API funcionaría. Y estaría mal — dejarían de ser dos procesos, y renombrar
  un método adentro de la API rompería la pantalla **sin que nadie tocara el
  contrato**. Lo único que comparten es el JSON.
- El compose hace cumplir esto y no solo lo pide: el servicio del front no
  recibe las credenciales de la base ni depende de ella.
- Las APIs no generan HTML; solo JSON.
- Dentro de cada API: controlador → servicio → repositorio, comunicados por
  interfaces; solo el ensamblador conoce clases concretas.

## Artículo 4 — Un solo comando para arrancar

`docker compose up -d --build` debe dejar TODO funcionando. Sin pasos
manuales, sin instalar PHP ni PostgreSQL local más allá de Docker. Los
estudiantes tienen máquinas heterogéneas: el entorno vive completo en
contenedores.

## Artículo 5 — Independencia del motor de base de datos

> **Desde la v3 este artículo dejó de ser una meta y pasó a ser estado, y la
> v4 lo confirmó con un tercer motor.** Se puede comprobar: cambie la
> variable `MOTOR`, reinicie la API, y el sistema entero sigue funcionando
> igual. El criterio 13 de las dos versiones lo verifica con un `diff`, no
> con una opinión.
>
> Y hay un matiz que solo aparece con tres: entre MariaDB y PostgreSQL hasta
> el SQL coincidía, lo que dejaba la duda de si la separación por motor era
> ceremonia. Con SQL Server el SQL cambia —no existe `LIMIT`— y el sistema
> siguió funcionando sin tocar una capa de arriba. Ahí la duda se cierra.

- El motor activo se elige con **configuración**, nunca con cambios de código.
- Los tres motores contienen la **misma base de datos** (`bdfacturas_*_local`):
  mismas 12 tablas, mismos datos, mismos triggers y procedimientos.
- Todo acceso a datos pasa por interfaces + un punto único de ensamblaje
  (inversión de dependencias — la D de SOLID).
- **El detalle de cada motor vive en clases con el nombre del motor como
  apellido** (`RepositorioClientePostgres`). Ninguna clase sin ese apellido
  puede contener SQL de un motor concreto, ni un código de error suyo, ni el
  nombre de un procedimiento almacenado.
- **Lo que el usuario lee es igual con cualquier motor.** Los mensajes se
  escriben una vez y se comparten; los traductores por motor solo deciden
  cuál corresponde. Un contrato que cambia según lo que haya detrás no es un
  contrato.

## Artículo 6 — Persistencia y reproducibilidad

- Los datos viven en **volúmenes** Docker: sobreviven a `docker compose down`.
- `docker compose down -v` devuelve la BD a su estado original (`db/init.sql`
  se re-ejecuta sobre volumen vacío). Ese es el "botón de pánico" oficial.

## Artículo 7 — Desarrollo con recarga natural

El código se monta como volumen dentro del contenedor. En PHP no hay "reload"
que configurar: **cada petición HTTP reinterpreta los archivos** — guardar un
`.php` y refrescar el navegador ES el ciclo de desarrollo. Reconstruir la
imagen (`--build`) solo es necesario si cambia el `Dockerfile`.

## Artículo 8 — Convenciones fijas

| Cosa | Convención |
|---|---|
| Puertos públicos | front **8088** · api_facturas **8090** |
| Puertos de BD hacia el host | MariaDB **13329** · PostgreSQL **15465** · SQL Server **11474** |
| Hosts internos (entre contenedores) | `postgres:5432` · `mariadb:3306` · `sqlserver:1433` |
| Credenciales BD | usuario `paradigmas` / clave `paradigmas123`. **Excepción declarada:** SQL Server usa `sa` / `Paradigmas123!`, porque su cuenta administrativa no se puede renombrar y su clave debe tener mayúscula, número y símbolo — con `paradigmas123` el contenedor no arranca |
| Bases de datos | `bdfacturas_postgres_local` · `bdfacturas_mariadb_local` · `bdfacturas_sqlserver_local` |
| Nombres de código | Clases e interfaces en PascalCase con prefijo `I` para interfaces; métodos y variables en camelCase; archivos = nombre de su clase |
| Estructura por API | `index.php` · `modelos/` · `controladores/` · `servicios/` · `repositorios/` · `excepciones/` |
| Estructura del front | `index.php` (front controller) · `cliente_api.php` · `vistas/` · `publico/` |

## Artículo 9 — Seguridad en su justa medida académica

- Los valores SQL siempre van en **prepared statements** (nunca concatenados).
- Las contraseñas de usuarios de la aplicación se almacenan con **hash**
  (nunca texto plano en código nuevo).
- Las credenciales de infraestructura (paradigmas/paradigmas123) son públicas
  y didácticas **a propósito**: este entorno jamás se despliega a producción.

## Artículo 10 — La API es específica, nunca genérica

Cada recurso tiene **su propia ruta escrita a mano**: `/api/producto`,
`/api/factura`, `/api/cliente`. Nunca una ruta con el nombre de la tabla como
parámetro (`/api/{tabla}`) que sirva para todas.

**El porqué, que es lo que hay que entender —y con una excepción honesta.**

Una API genérica es **buena para prototipar**: se escribe una vez y de una vez
sirve para las doce tablas. Ahí gana de verdad, y decir lo contrario sería
falso. Lo que pasa es que ese ahorro se cobra después:

| | Genérica `/api/{tabla}` | Específica `/api/producto` |
|---|---|---|
| Escribirla | Una vez, y sirve para todo | Una vez **por recurso** |
| Documentación | Dice «tabla: texto». No dice cuáles ni qué campos lleva cada una | Dice los campos de producto, con su tipo |
| Un campo que solo tiene una tabla | No cabe: obligaría a un `if` adentro de lo genérico | Se agrega en su ruta y no afecta a nadie |
| Una regla que solo aplica a una tabla | Igual: el `if` crece con cada excepción | Vive donde corresponde |
| Un cambio en un recurso | Toca el código de TODOS | Toca ese archivo |
| Quien la consume | Tiene que preguntar qué valores acepta el parámetro | Lo lee en la ruta |

En corto: **lo genérico es barato de escribir y caro de vivir; lo específico
es caro de escribir una vez y barato de vivir.** Un prototipo se tira a los
dos meses, así que solo paga la primera columna. Un sistema que se mantiene
paga la segunda todos los días.

**La excepción honesta:** PostgREST, Hasura y compañía son genéricos y son
excelentes. La diferencia es que ahí **lo genérico es el producto entero**:
lo mantiene un equipo dedicado, y quien lo usa no escribe endpoints, escribe
consultas. Eso no es «una API genérica hecha en casa» — es otra cosa.

En este curso, entonces: una ruta por recurso, con sus campos escritos.

## Artículo 11 — La base de datos también es parte del sistema

Parte de las reglas del negocio **vive en la base**: llaves foráneas que
impiden referencias rotas, triggers que calculan totales y mueven stock,
procedimientos que hacen varias escrituras como si fueran una.

La API **no las repite y no las contradice**:

- No comprueba en PHP lo que una llave foránea ya defiende. Preguntar antes
  («¿existe esa persona?») no evita el problema —entre la pregunta y la
  escritura, otro puede borrarla— y además obliga a mantener la misma regla
  en dos idiomas.
- No calcula lo que un trigger calcula. Dos cuentas del mismo número acaban
  discrepando, y ese día nadie sabe cuál creer.
- Sí **traduce** lo que la base responde: el rechazo del motor sale de la API
  como un mensaje en español, no como un número de error ni como un 500.

El precio es real y hay que decirlo: parte del sistema queda escrita en un
dialecto de SQL, y por eso vive en clases que llevan el nombre del motor. La
versión 3 pondrá esa decisión a prueba.
