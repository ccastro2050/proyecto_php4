# Investigación y decisiones — Versión 4

> **Versión 4** · **Lectura opcional** (el porqué de las decisiones del plan,
> con las alternativas que se evaluaron y descartaron).

---

## D1 — El driver oficial de Microsoft, no `pdo_dblib`

**Alternativa descartada:** `pdo_dblib`, que se instala con
`apt-get install freetds-dev && docker-php-ext-install pdo_dblib` — dos líneas
en vez de diez.
**Decisión:** `pdo_sqlsrv`, el que publica Microsoft.
**Por qué:** `pdo_dblib` funciona para consultas sencillas y empieza a fallar
justo donde este sistema lo necesitaría: parámetros con tipos, procedimientos
almacenados con salida, `NVARCHAR`. Habríamos ahorrado ocho líneas de
`Dockerfile` para después pelearnos con el driver en cada procedimiento.

Y hay una razón didáctica: el oficial es el que se usaría en un sistema real.
Que su instalación sea molesta —un repositorio de paquetes de Microsoft, un
ODBC del sistema, PECL para compilar la extensión— **es información**, no un
obstáculo que haya que esconder. Los tres motores no se instalan igual, y eso
se nota antes de escribir una línea de PHP.

**Precio asumido:** el build de la imagen tarda más y depende de que el
repositorio de Microsoft esté disponible.

## D2 — El parámetro de salida se resuelve en el SQL, no cambiando la base

**Qué pasó:** enlazar el parámetro de salida de los procedimientos con
`bindParam(..., PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 8000)` —el camino
que documenta PHP— **falla** con `SQLSTATE[HY104]: Invalid precision value`.
La causa: el parámetro está declarado `NVARCHAR(MAX)`, y a un MAX no se le
puede dar una longitud fija.

**Alternativa descartada:** cambiar los seis procedimientos para que el
parámetro fuera `NVARCHAR(8000)`.
**Decisión:** escribir el bloque de T-SQL completo y pedir el valor con un
`SELECT`.
**Por qué:** cambiar la base habría sido acomodarla a una limitación de un
driver, y además habría puesto un techo al tamaño del JSON — una factura con
muchos renglones lo habría reventado en silencio.

El bloque que se manda es cuatro instrucciones en una sentencia, y **`SET
NOCOUNT ON` es indispensable**: sin él, cada `INSERT` de adentro del
procedimiento manda un aviso de «N filas afectadas» que PDO entrega como
resultado intermedio.

**Las dos cosas se encontraron probando.** No están en ningún tutorial, y por
eso quedaron escritas en el repositorio además de aquí.

## D3 — `TrustServerCertificate=yes`, con su advertencia al lado

**Alternativa descartada:** montar un certificado de verdad en el contenedor
de SQL Server.
**Decisión:** aceptar el que el servidor se genera solo, **y decir en el
compose que en producción no se hace**.
**Por qué:** un certificado propio en un entorno de curso es infraestructura
que no enseña nada y que se rompe en la máquina de cada estudiante. Lo que sí
enseña es saber **qué se está desactivando**: la comprobación de con quién se
está hablando.

Esconderlo habría sido peor que ponerlo. Un estudiante que copie esta línea a
un sistema real tiene que haber leído por qué no debe.

## D4 — Un contenedor de un solo uso para crear la base

**Alternativa descartada:** un `entrypoint` propio en el contenedor de SQL
Server que arranque el servidor y corra el script.
**Decisión:** un servicio aparte, `sqlserver-init`, que espera a que el
servidor esté sano, crea la base, corre el script y termina.
**Por qué:** meter el arranque del servidor y la carga de datos en el mismo
contenedor obliga a escribir un script que haga las dos cosas y no se estorben
—arrancar en segundo plano, esperar, cargar, y quedarse vivo—, que es
exactamente la clase de guion frágil que se rompe cuando el servidor tarda un
segundo más.

Con dos contenedores, cada uno hace una cosa: uno es el servidor y otro es el
encargo. Y el `healthcheck` del primero es lo que le dice al segundo cuándo
empezar.

**Y el script comprueba si la base ya existe.** Un inicializador que solo
funcione con el volumen vacío es una trampa esperando a que alguien vuelva a
levantar el sistema.

## D5 — Se aceptaron credenciales distintas para SQL Server

**Alternativa descartada:** forzar `paradigmas` / `paradigmas123` también aquí.
**Decisión:** `sa` / `Paradigmas123!`, declarado como excepción.
**Por qué:** no se podía. SQL Server exige que la clave tenga mayúscula,
número y símbolo, y con `paradigmas123` el contenedor **no arranca**. La
cuenta administrativa se llama `sa` y no se puede renombrar.

Forzar la convención habría significado crear un usuario adicional dentro del
script de la base solo para que se llamara igual que en los otros dos — más
piezas, para nada.

**La excepción está en la constitución y en el compose**, no escondida en una
variable. Una convención con una excepción explicada sigue siendo una
convención; una convención que alguien rompió en silencio deja de serlo.

## D6 — `match` en vez de encadenar ternarios

**Alternativa descartada:** seguir con el ternario de la v3, anidado.
**Decisión:** `match` de PHP 8.
**Por qué:** con dos opciones, un ternario se lee. Con tres, anidarlo produce
la clase de línea que hay que leer dos veces. `match` dice lo que de verdad
está pasando —es una tabla de correspondencia, no una cadena de preguntas—,
compara de forma estricta, y deja los tres casos alineados a la vista.

## D7 — Lo que la tercera columna reveló, y que con dos no se podía saber

Esto no es una decisión, sino un hallazgo, y es la razón por la que valía la
pena hacer esta versión.

**1. MariaDB es la excepción, no la regla.** En la v3 parecía que PostgreSQL
era el motor raro por usar un solo código (`23503`) para los dos sentidos de
una llave foránea, mientras MariaDB los separaba en `1452` y `1451`. Con SQL
Server —que también usa uno solo, el `547`— se ve que **el raro era MariaDB**.

Con dos puntos de comparación uno no distingue una regla de una coincidencia.
Con tres empieza a poder.

**2. El SQL sí cambia.** Entre MariaDB y PostgreSQL, las consultas de este
sistema se copiaron tal cual, lo que dejaba una duda razonable: ¿de verdad
hacían falta clases separadas por motor? Con SQL Server no existe `LIMIT`, la
llave generada se lee con una cláusula en medio del `INSERT`, y el driver ni
siquiera admite una de las opciones de PDO que los otros dos necesitan.

**La duda queda resuelta, y no por argumento sino por evidencia.**

## D8 — La ruta se cierra aquí

**Alternativa considerada:** una v5 con un cuarto motor (Oracle, SQLite), o
con las seis tablas de control de acceso que la base trae y nadie usa.
**Decisión:** cerrar.
**Por qué:** un cuarto motor no enseñaría nada nuevo — la tercera columna ya
mostró lo que la segunda no podía. Y el control de acceso es un tema entero
(sesiones, hashing, permisos por ruta) que merece su propio curso, no un
apéndice.

Una ruta de versiones también se diseña sabiendo dónde parar. Estirarla para
que parezca más completa es la forma más común de arruinar un buen ejemplo.
