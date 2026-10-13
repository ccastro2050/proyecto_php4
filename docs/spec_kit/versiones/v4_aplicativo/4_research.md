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


---

# ANEXO A LA v4 — LOS SEIS TROPIEZOS QUE SE MIDIERON

Ninguno de estos seis se encontró leyendo. Los seis aparecieron ejecutando, y
cuatro de ellos **no fallaban**: respondían algo razonable y equivocado. Van
aquí porque la investigación de una versión es esto, no una lista de enlaces.

## 1. `bool("0")` en PHP y el acceso que se daba por casualidad

`verificar_acceso_ruta` devuelve un 0 o un 1. MariaDB lo entregaba como
**texto**, y en PHP —igual que en Python— la cadena `"0"` no está vacía, así
que `(bool) "0"` es **verdadero**. El resultado: con MariaDB **todo el mundo
tenía acceso a todo**, y la API no se quejaba.

PostgreSQL y SQL Server devolvían un entero y funcionaban. Dos de los tres
motores funcionaban por casualidad, y el tercero mentía con un 200.

La corrección es una función de tres líneas en los tres repositorios, que
convierte explícitamente en vez de dejar que el lenguaje decida. **Donde el
tipo lo pone el motor, no se confía en la conversión automática.**

## 2. El error 1267 de MariaDB: la colación de un parámetro

El mismo script creaba las tablas con `utf8mb4_unicode_ci` y los parámetros
de los procedimientos tomaban **la colación por defecto del servidor**, que
MariaDB 11.5 cambió a `utf8mb4_uca1400_ai_ci`. Comparar dos textos de
colaciones distintas con `=` es un **error**, no una advertencia:

```
Illegal mix of collations (utf8mb4_unicode_ci,IMPLICIT)
and (utf8mb4_uca1400_ai_ci,IMPLICIT) for operation '='
```

El mismo script funcionaba en MariaDB 10 y reventaba en la 11.8 **sin que
nadie tocara el SQL**: cambió el motor debajo. La solución es declarar la
colación en el parámetro, para que no dependa de quién lo ejecute.

## 3. Un `CREATE PROCEDURE` a medias no falla al crearse

Al corregir lo anterior, los procedimientos se extrajeron con una expresión
regular escrita dentro de un *heredoc* de bash, y el `$$` del delimitador se
lo comió la expansión de variables del shell. Los cuerpos quedaron
**cortados** justo después de los `DECLARE`.

El motor los aceptó sin una sola queja. El fallo apareció al llamarlos, con
un mensaje que no tenía ninguna relación con la causa. **Crear un
procedimiento y que el procedimiento esté completo son dos cosas distintas, y
el motor solo comprueba la primera.**

## 4. El reemplazo que buscó la primera coincidencia

Para sembrar contraseñas con hash conocidos, un script buscó la **primera**
aparición de `INSERT INTO usuario (email, contrasena) VALUES`... que en
MariaDB y en SQL Server está **dentro** de `crear_usuario_con_roles`: los
procedimientos se declaran antes de los datos.

El procedimiento perdió su propio INSERT y se quedó con los ocho usuarios de
la siembra, así que crear cualquier usuario respondía `409 ya existe`
—insertando siempre a `admin@correo.com`, pidiera uno el que pidiera—. En
PostgreSQL no pasó: ahí los datos van primero.

**El mismo script, tres motores, dos estropicios y un acierto por
casualidad.** Buscar por texto para reemplazar código necesita un ancla que
distinga el sitio, no la primera coincidencia.

## 5. Los ocho procedimientos de MariaDB no tenían transacción

El más serio de los seis. Medido así: `PATCH /api/usuario-con-roles/admin@correo.com`
con `roles: [99]` —un rol que no existe—. La respuesta fue la correcta, 409.
Lo que no se esperaba:

```sql
SELECT COUNT(*) FROM rol_usuario WHERE fkemail='admin@correo.com';  ->  0
```

**El administrador se quedó sin roles por una petición que falló**, y con
cero roles la petición siguiente le respondió 403. El procedimiento hace
primero `DELETE` y después los `INSERT`: el DELETE se confirmó, el INSERT
chocó con la llave foránea, y el DELETE se quedó hecho. Lo mismo al crear: un
POST con un rol inexistente dejaba el usuario creado y huérfano.

Y aquí está la lección de las tres tecnologías, porque **solo uno de los tres
motores perdía datos**:

| Motor | Qué traía | Resultado |
|---|---|---|
| SQL Server | `BEGIN TRY` + `BEGIN TRANSACTION` + `ROLLBACK` en el `CATCH` | No perdía nada |
| PostgreSQL | una FUNCIÓN es atómica por definición | No hay que escribir nada |
| MariaDB | nada: cada sentencia se confirma sola | **Dejaba la mitad hecha** |

Nadie lo había notado porque el camino feliz funciona igual en los tres. Se
nota cuando algo falla, y **solo si se mira la base de datos después del
error** en vez del código de respuesta.

La corrección, en los ocho procedimientos que escriben en más de un sitio:

```sql
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    ROLLBACK;
    RESIGNAL;
END;

START TRANSACTION;
...
COMMIT;
```

`RESIGNAL` es la pieza fina: vuelve a lanzar el error **original**, con su
código intacto —1452, 1062—, que es lo que el repositorio traduce a 409. Sin
él, el `ROLLBACK` salvaría los datos y rompería la respuesta: 500 donde debe
haber 409.

Los dos procedimientos de `rutarol` quedan sin transacción a propósito:
escriben una fila en una tabla, y una sentencia sola ya es atómica.

## 6. La explicación del CAST que llevaba cuatro repos siendo falsa

Los cuatro repos de la v4 llevaban escrito, en el repositorio de consultas de
SQL Server, que el `CAST` del ticket promedio era obligatorio porque **«en
T-SQL dividir un DECIMAL entre un INT trunca»**. Suena razonable. Se midió
contra el motor:

```sql
SELECT CAST(3400000.00 AS DECIMAL(18,2)) / 3   -->  1133333.3333333333333
SELECT 3400000 / 3                             -->  1133333
```

Lo que trunca es **INT entre INT**: T-SQL devuelve el tipo de los operandos.
Un DECIMAL entre un INT promueve, igual que en los otros dos motores. Y como
`subtotal` está declarado `DECIMAL(18,2)`, la consulta **con CAST y sin CAST
devuelve exactamente lo mismo** —comprobado fila por fila—.

El CAST se queda, porque deja la intención escrita y porque el día que la
división sea entre dos enteros el truncado llega de verdad. Lo que se
corrigió es la explicación.

**La lección no es el CAST.** Es cómo se encontró: la afirmación venía
copiada de repo en repo, sonaba bien y nadie la había ejecutado. Se cayó con
dos consultas de una línea. Lo que se cree de un motor y lo que el motor
responde son dos cosas distintas, y solo una de las dos se puede medir.

(Lo que sí se confirmó, en el mismo motor y de una vez:
`SELECT STRING_AGG(DISTINCT nombre, ', ') FROM rol` responde
`Incorrect syntax near ','`. Esa diferencia es real, y de ahí la subconsulta.)
