# postman — la colección de la API, lista para importar

Esta API no tiene documentación interactiva integrada (PHP puro, sin
framework: no hay quién la genere — el contrato vive en
[6_contracts.md](../docs/spec_kit/versiones/v4_aplicativo/6_contracts.md)).
**Postman cumple ese papel**: aquí está la colección con los **once
recursos**, las **diez consultas** y el control de acceso ya armados —118
peticiones—, para verlos y probarlos con clics.

## Cómo usarla (3 pasos)

1. Instale **Postman** (postman.com/downloads). Si le pide cuenta, puede
   usar la opción de cliente ligero sin registrarse.
2. **Import** (botón arriba a la izquierda) → arrastre el archivo
   `coleccion_v4.postman_collection.json` de esta carpeta.
3. Con el proyecto corriendo (`docker compose up -d`), abra cualquier
   petición y dele **Send**.

## Primero la carpeta 0: sin token no pasa nada

Desde la v4 la API está **cerrada**. Solo dos rutas responden sin
identificarse —`GET /` porque es el diagnóstico, y `POST /api/sesion` porque
es la puerta—, así que si importa la colección y le da *Send* a cualquier
otra cosa, le responde **401**.

Corra **«0 · ENTRAR → 1 · Entrar como admin»**. Esa petición trae un script
que guarda el token en la variable `token` de la colección, y **todas las
demás lo heredan**: la autenticación está puesta en la colección, no en cada
petición. No hay que copiar ni pegar nada.

Tres respuestas que conviene saber leer antes de empezar:

| Si ve | Qué pasó |
|---|---|
| **401** | El token está vacío o venció. Vuelva a correr la 1 |
| **403** | El token es válido y a ese usuario **no le toca** esa ruta. No es un error: es la versión funcionando |
| **409** | La operación choca con los datos que ya existen. Casi siempre es a propósito: hay peticiones de ejemplo que buscan ese choque |

## Las cuatro carpetas de la v4

| Carpeta | Qué hay |
|---|---|
| **0 · ENTRAR** | Entrar como `admin` y como `vendedor1`, quién soy, mis permisos, renovar, salir, y el 401 sin token |
| **v4 · El control de acceso** | Los cinco recursos nuevos. Fíjese en los dos puentes: tienen **dos verbos y no cinco** |
| **v4 · usuario-con-roles** | El recurso que escribe en dos tablas con una petición. **La peticíon del rol inexistente es la que hay que mirar**: responde 409, y la siguiente demuestra que el usuario conserva sus roles —eso es el ROLLBACK— |
| **v4 · Las diez consultas** | Las diez, con el índice y el 405 del POST |
| **v4 · El 401 y el 403** | La demostración de que **el permiso no viaja en el token**: se le quita un rol a `vendedor1` y su token —el que ya tenía— pasa de 200 a 403 sin vencer |

## La colección se puede correr dos veces seguidas, y eso costó

Se corrió completa contra la API —118 peticiones— y salieron dos fallos que
no se ven leyendo el archivo:

1. **Seis peticiones tenían el identificador escrito a mano** (`/api/factura/7`,
   `/api/rol/6`). Respondieron **404**: la factura 7 ya no existía y el rol
   nuevo recibió otro id. Un identificador que lo genera la base de datos no
   se puede escribir en la colección, así que ahora la petición que crea lo
   guarda en una variable —`{{num_factura}}`, `{{id_rol}}`, `{{id_ruta}}`— y
   las siguientes lo usan.

2. **Una petición borraba una fila sembrada.** La demostración del permiso
   usaba la pareja (ruta 1, rol 5) —`/home` para el rol Cliente—, que está
   en la siembra: al correr la colección, `cliente1` se quedaba sin `/home`.
   Ahora trabaja sobre la ruta que la propia colección crea, y la borra al
   final.

**Una colección de pruebas no puede borrar datos sembrados, y tiene que
poder correrse dos veces.** Las dos cosas se comprobaron corriéndola dos
veces seguidas y verificando que las cinco tablas quedaran con el mismo
número de filas.

Y un detalle de Postman que cuesta un rato: en el cuerpo, una variable
numérica va **sin comillas** —`"fkidruta": {{id_ruta}}`—. Con comillas llega
el texto `"19"` y la API responde 422, porque pide un entero.

## El orden de las carpetas del CRUD cuenta una historia

Las carpetas del CRUD están numeradas, y ese orden **no es solo pedagógico:
es obligatorio**.

Empiece por **Empresas** y **Personas**. Son las tablas de las que las demás
dependen, así que tienen que existir antes: un cliente necesita una persona.
Si lo intenta al revés, la base dice que no — y esa negativa también está en
la colección, como petición de ejemplo.

Después, **Clientes** y **Vendedores** (donde la llave la genera la base) y
al final **Facturas**, que necesita a los tres anteriores más un producto.

## Las peticiones que dan 409 no están rotas

Hay una en casi cada carpeta, y son las que más enseñan:

| Petición | Qué demuestra |
|---|---|
| Crear una empresa con un código que ya existe | La llave primaria la defiende la base |
| Crear un cliente apuntando a `NOEXISTE` | La API **no comprobó antes**: intentó, y tradujo el veredicto |
| Eliminar la persona `P001` | No se puede: ya es cliente. Y la API lo dice en español |
| Anular dos veces la misma factura | La regla la puso un procedimiento almacenado, no un `if` de PHP |

**Ninguna responde 500.** Un 500 diría «se nos dañó algo», y no se dañó nada:
la petición estaba bien escrita y lo que no cabía era en los datos. Ésa es la
diferencia que introduce esta versión.

## Úsela TRES VECES, una por motor

Es lo que la ruta de versiones viene a enseñar, y la colección es la forma más
cómoda de comprobarlo.

```powershell
$env:MOTOR = "sqlserver"     # o "postgres", o "mariadb"
docker compose up -d --no-deps --force-recreate api-facturas
```

Vuelva a correr las mismas peticiones. **Las respuestas deben ser idénticas**,
incluidos los textos de los 409 — compárelos palabra por palabra.

Que digan lo mismo no es casualidad: cada motor reporta esos tres casos a su
manera, y **dos de los tres usan el mismo código para dos casos opuestos**
(apuntar a algo que no existe y borrar algo del que otros dependen).
Emparejarlos es trabajo de los tres traductores de errores.

Lo único que cambia es la respuesta del **Diagnóstico**, que dice el motor. Y
los datos: son tres bases independientes, así que las fichas que cree con una
no aparecen con las otras.

## La carpeta 6 es la que hay que mirar con calma

Las facturas **no son un CRUD**, y la colección lo enseña por contraste:
compare esa carpeta con las cinco de arriba. No tiene PATCH —hay una
petición que lo intenta, y responde 405—, tiene una operación `/anular` que
ningún otro recurso tiene, y sus respuestas devuelven la factura completa.

Al crear una factura, **anote antes el stock de los productos**. El `total`
de la respuesta y el stock de después son la prueba de que hay lógica
corriendo dentro de la base de datos.

## La variable {{base}}

La colección usa `base` = `http://localhost:8090` (el proyecto del curso). Si
está probando **SU reconstrucción** (la de la
[GUIA_IA4](../docs/spec_kit/versiones/v4_aplicativo/GUIA_IA4.md), que corre
con los puertos +100): clic en la colección → pestaña **Variables** → cambie
`base` a `http://localhost:8190`. Una sola edición y todas las peticiones
apuntan a su proyecto.

## Los números de ejemplo: unos son variables y otros no

Las llaves que **genera la base de datos** ya no están escritas a mano donde
se podía evitar: la factura, el rol y la ruta que la colección crea guardan
su identificador en una variable, y las peticiones siguientes lo usan.

Donde sí quedan números fijos es en las peticiones que leen fichas de la
**siembra** —`/api/cliente/5`, `/api/rutarol/ruta/4`—, y ahí es correcto:
esas filas vienen del script y su llave es conocida.

Que haya que distinguir los dos casos es, en sí mismo, la lección de las
llaves autogeneradas: **la que usted no eligió, no la puede escribir.**
