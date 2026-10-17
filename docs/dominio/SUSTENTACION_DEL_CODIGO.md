# Sustentación del código — diez preguntas, respondidas

> **Qué es este documento.** Diez preguntas sobre **este** código, con su
> respuesta y **con la forma de comprobarla**. Ninguna es de examen teórico:
> todas se responden señalando un archivo, corriendo un comando o mandando una
> petición.
>
> **Para qué existe.** Desde la **versión 2** se califica la
> **interpretabilidad**: el profesor abre un archivo y quien lo entregó cuenta
> qué hace y por qué está así. Es **en voz alta y en vivo**. Este documento es
> el nivel de respuesta que se espera — no el guion que hay que recitar.
>
> **Las mediciones son reales y están fechadas.** La §11 dice cómo repetirlas.
>
> Versión 1.0 · 17 de octubre de 2026.

---

## Antes de las diez: para qué sirve sustentar

> **Porque una IA puede generar código que funciona y que nadie entiende.** Y
> código que nadie entiende no se arregla el día que falle, ni se cambia
> cuando el negocio cambie. Que corra es el requisito más bajo, no el más
> alto.
>
> De ahí la regla: la IA **comenta lo que genera**, y la persona **puede
> explicarlo sin leer los comentarios**. Si solo puede leerlos en voz alta, no
> lo entendió.

---

## 1 · ¿Qué tamaño tiene esto, y quién lo escribió?

**Se cuenta en dos comandos**, y la respuesta hay que saberla antes de que la
pregunten:

```powershell
git shortlog -sne          # quién, y cuántos commits
git rev-list --count HEAD  # 41 commits
```

| Parte | Archivos `.php` | Líneas |
|---|---|---|
| La API | 120 | 17.195 |
| El front | 19 | 5.592 |

> **Y el 70 % de esas líneas son comentarios**, a propósito: este repositorio
> es material de clase, no producción. Un archivo de aquí se lee de arriba
> abajo y cuenta **por qué** está así, no qué hace la línea siguiente.

---

## 2 · Hay tres repositorios por recurso. ¿Quién escoge, y cómo?

**El ensamblador, y en un solo sitio.** `api_facturas/servicios/ensamblador.php`
tiene **17 `match (motorActivo())`**, uno por servicio:

```php
$repositorio = match (motorActivo()) {
    'postgres'  => new RepositorioProductoPostgres($dsn, $usuario, $clave),
    'sqlserver' => new RepositorioProductoSqlServer($dsn, $usuario, $clave),
    default     => new RepositorioProductoMariaDB($dsn, $usuario, $clave),
};
```

Y `motorActivo()` lee **una variable de entorno**:

```php
return match (strtolower(trim((string) getenv('MOTOR')))) {
    'postgres', 'postgresql' => 'postgres',
    'sqlserver', 'mssql'     => 'sqlserver',
    default                  => 'mariadb',
};
```

**Cómo comprobarlo sin creerme:** `GET /` devuelve el motor activo. Cambie
`MOTOR` en el compose, levante otra vez, y vea cambiar esa palabra **y nada
más**: las mismas rutas, los mismos códigos, las mismas respuestas.

> **La pregunta que suele seguir:** ¿por qué no un `if` dentro de cada
> repositorio? Porque entonces cada repositorio conocería los tres motores, y
> agregar el cuarto obligaría a tocar los treinta. Así, el conocimiento de
> «qué motor hay» vive en **una** función, y los demás archivos no se enteran.

---

## 3 · ¿Qué no puede aparecer nunca en un servicio o en un controlador?

**`PDO` y `PDOException`.** La base de datos se queda en la capa de abajo.

**Medido**, y conviene medirlo delante de quien pregunta:

```powershell
# Archivos que mencionan PDO, por capa
Select-String -Path api_facturas\repositorios\*.php  -Pattern "PDO" -List | Measure-Object
#   45 de 60 archivos
Select-String -Path api_facturas\servicios\*.php     -Pattern "PDO" -List
#   3 coincidencias... y las TRES son comentarios
```

Las tres están en `IServicioProducto.php`, `ServicioProducto.php` y
`ControladorProducto.php`, y dicen cosas como *«la `PDOException` sube y se
vuelve 500»*. **Mencionarla para explicar la frontera no es cruzarla**: no hay
un solo `catch (PDOException)` ni un `new PDO` por encima del repositorio.

**Quién decide el 404**, entonces: el **servicio**, que recibe `null` del
repositorio y lanza `NoEncontradoExcepcion`; el controlador la atrapa y
responde 404. El repositorio no sabe qué es un código HTTP y el controlador no
sabe qué es una consulta.

---

## 4 · El CRUD usa `prepare` y las diez consultas usan `query`. ¿Por qué?

**Medido:** 168 `prepare(` en los repositorios del CRUD · **3** `query(` en los
tres de consultas, uno por motor.

| | Lleva datos de fuera | Qué se usa |
|---|---|---|
| CRUD | sí: el código, el nombre, la cantidad | `prepare` + marcadores `?` |
| Las diez consultas | **no**: son SELECT fijos, sin un solo parámetro | `query` |

> **El `prepare` no es un adorno de estilo: es la defensa contra la inyección
> de SQL.** Va donde entra un dato ajeno. Si mañana una consulta acepta un
> filtro que venga de la URL, vuelve el `prepare` con sus marcadores — y eso
> está escrito en los tres archivos de consultas, para que el día que alguien
> agregue el filtro lo lea.

**La pregunta difícil que puede venir:** ¿y si concateno el filtro con
comillas simples y lo escapo? Respuesta: `mysqli_real_escape_string` y sus
parientes dependen de la codificación de la conexión y se han roto por eso.
Con marcadores, **el dato nunca es parte de la sentencia**: viaja aparte y el
motor no lo interpreta como SQL.

---

## 5 · El mismo cuerpo `{}`, dos respuestas: 422 y 400

```powershell
# POST /api/producto con {}   -> 422, con CUATRO errores
# PATCH /api/producto/PR001 con {} -> 400, "No se envió ningún campo para actualizar."
```

| | 422 | 400 |
|---|---|---|
| Qué falló | la **forma**: faltan los cuatro campos | el **sentido**: la forma está bien y no pide ningún cambio |
| Quién lo decide | `validar()` del controlador | el controlador, antes de llamar al servicio |

> **En PHP puro el 422 se escribe a mano**, porque no hay framework que lo
> genere. Eso cuesta código y da algo a cambio: los mensajes están en
> castellano y dicen qué se esperaba —*«El campo codigo es obligatorio: texto
> de 1 a 10 caracteres»*— en vez del volcado de un validador automático.

---

## 6 · La API está cerrada. ¿Dónde está la guardia, y por qué una sola?

En `index.php`, **una lista blanca y una llamada**:

```php
const RUTAS_ABIERTAS = ['/', '/api/sesion'];

if (!in_array($ruta, RUTAS_ABIERTAS, true)) {
    $recurso = explode('/', trim($ruta, '/'))[1] ?? '';
    if (isset(PERMISO_POR_RECURSO[$recurso])) { exigirPermiso(PERMISO_POR_RECURSO[$recurso]); }
    else { exigirSesion(); }
}
```

> **Por qué así y no una llamada en cada bloque de recurso.** En .NET la
> guardia es un atributo sobre la clase y protege **también el método que
> alguien escriba mañana**. En PHP puro no hay quien haga eso, así que se hace
> al revés: en vez de acordarse de poner la guardia veintitantas veces, hay que
> **agregar la ruta a la lista blanca** para dejarla abierta — y eso se ve en
> el `diff`.
>
> Y el `else` es la otra mitad: una ruta que no está en el mapa de permisos
> **exige al menos sesión**. Lo que no está declarado, no pasa.

**Las dos rutas abiertas tienen razón:** `GET /` es el diagnóstico —un
*healthcheck* que pidiera credenciales no serviría de healthcheck— y
`POST /api/sesion` es la puerta: no puede exigir el token que ella entrega.

---

## 7 · El token: ¿qué lo protege, y qué NO protege?

**Está firmado, no cifrado.** Cualquiera lee su contenido —son dos bloques de
base64url—; lo que nadie puede es **cambiarlo** sin romper la firma. Por eso
dentro no va ningún dato secreto: el correo, los roles y dos fechas.

Y hay tres líneas en `autorizacion/jwt.php` que valen la pregunta entera:

```php
if (!hash_equals($esperada, $firma)) { return null; }
```

> **`hash_equals` y no `===`.** La comparación normal se detiene en el primer
> byte distinto, así que **el tiempo que tarda revela cuántos caracteres
> acertó** quien está probando. `hash_equals` tarda lo mismo siempre. Es un
> ataque de tiempo, y la defensa es una función.

**Lo que el token NO lleva: el permiso.** Se consulta en la base de datos en
cada petición, con `verificar_acceso_ruta`. Compruébelo: quítele el rol de
Vendedor a `vendedor1` **con su token ya emitido**, y `GET /api/cliente` pasa
de 200 a **403** sin que el token cambie ni venza.

---

## 8 · Sin una sola transacción escrita en PHP, ¿qué garantiza la base de datos?

Mucho, y conviene saber **qué** exactamente:

| Garantía | Quién la da |
|---|---|
| El subtotal y el total están bien | los disparadores, que los calculan |
| No se vende sin stock | `trg_prodfact_before_insert`, con su `SIGNAL` |
| Anular devuelve el stock | `sp_anular_factura` |
| Crear un usuario con dos roles es **todo o nada** | la transacción **dentro del procedimiento** |

**Y la última se ganó fallando**, que es la mejor parte de esta pregunta: en
MariaDB los procedimientos **no tenían transacción**, y un `PATCH` con un rol
inexistente respondía 409 **dejando al administrador sin un solo rol** —el
`DELETE` se confirmaba y el `INSERT` fallaba—. SQL Server ya traía su
`BEGIN TRY` y en PostgreSQL una función es atómica sola: **de los tres
motores, solo uno perdía datos**, y el camino feliz se veía idéntico en los
tres.

Hoy los ocho procedimientos que escriben en más de un sitio llevan
`START TRANSACTION`, `COMMIT` y:

```sql
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    ROLLBACK;
    RESIGNAL;
END;
```

> **`RESIGNAL` es la pieza fina.** Vuelve a lanzar el error **original** con su
> código —1452, 1062—, que es lo que el repositorio traduce a 409. Sin él, el
> `ROLLBACK` salvaría los datos y rompería la respuesta: 500 donde debe ir 409.

---

## 9 · ¿Por qué el hash de la contraseña no se hace en el front?

**Porque entonces el hash *sería* la contraseña.** Quien lo copiara de la red
entraría sin saber la clave: el servidor compara contra lo que recibe.

En este código la contraseña viaja en el cuerpo del POST y el **repositorio**
la hashea antes del `INSERT`:

```php
password_hash($contrasena, PASSWORD_BCRYPT, ['cost' => self::COSTO_BCRYPT]);  // 12
```

Tres cosas que vale saber de esa línea:

1. **El costo 12 es una decisión**, no un número mágico: cada punto **duplica**
   el tiempo de cálculo. Bcrypt se diseñó para encarecerse cuando las máquinas
   sean más rápidas, sin cambiar de función.
2. **Dos usuarios con la misma clave tienen hash distintos**, porque bcrypt
   lleva *salt* adentro. Sin él, dos hash iguales delatarían que esas dos
   personas usan la misma contraseña.
3. **`password_verify` devuelve `false` ante un hash malformado, sin lanzar
   nada.** Se descubrió con dos filas sembradas que tenían la contraseña en
   texto plano: la API respondía «no coincide» en vez de caerse. Ese
   comportamiento se dejó: un dato malo en la base de datos no puede tumbar la
   API.

---

## 10 · La prueba hueca

**Pregunta:** esta prueba, ¿qué garantiza?

```php
$filas = $servicio->ventasPorProducto();
assert($filas !== null);
```

**Respuesta: casi nada.** Garantiza que el método existe y que no reventó.
Pasaría igual con una consulta que devolviera las columnas equivocadas, o
cero filas por un `WHERE` mal escrito, o el ticket promedio truncado.

Por eso `pruebas/prueba_consultas_tres_motores.php` **no mira el código de
respuesta**: compara las **columnas** contra el contrato, una por una, y
recalcula el `ticket_promedio` como `total / facturas` para ver si coincide.

> **Y esa prueba encontró algo real.** La explicación del `CAST` de T-SQL que
> llevaba cuatro repositorios copiándose —«dividir un DECIMAL entre un INT
> trunca»— era **falsa**: lo que trunca es INT entre INT. Se cayó con dos
> consultas de una línea:
>
> ```sql
> SELECT CAST(3400000.00 AS DECIMAL(18,2)) / 3   -->  1133333.3333333333333
> SELECT 3400000 / 3                             -->  1133333
> ```
>
> **Lo que se cree de un motor y lo que el motor responde son dos cosas
> distintas**, y solo una de las dos se puede medir.

---

## 11 · Cómo repetir las mediciones de este documento

Todo lo de arriba se vuelve a comprobar en tres comandos:

```powershell
# Las diez consultas contra los tres motores
docker exec -e MOTOR=mariadb   proyecto_php4-api-facturas-1 php /app/pruebas/prueba_consultas_tres_motores.php
docker exec -e MOTOR=postgres  proyecto_php4-api-facturas-1 php /app/pruebas/prueba_consultas_tres_motores.php
docker exec -e MOTOR=sqlserver proyecto_php4-api-facturas-1 php /app/pruebas/prueba_consultas_tres_motores.php

# Los servicios, sin base de datos
docker exec proyecto_php4-api-facturas-1 php /app/pruebas/prueba_capas.php
```

Y los códigos de cada error, uno por uno, están en
[`POLITICA_DE_ERRORES.md`](POLITICA_DE_ERRORES.md), con la petición que los
produce.

> **Si una medición de este documento deja de ser cierta, el que está mal es
> el documento.** Córrijalo y diga desde cuándo: un documento que miente es
> peor que uno que falta, porque el que lo lee cree que ya sabe.
