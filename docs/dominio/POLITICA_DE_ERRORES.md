# Política de errores — Facturación (`bdfacturas`), en PHP

> **Qué es este archivo.** Qué código de estado devuelve el sistema ante cada
> clase de fallo, qué forma tiene el cuerpo, y **quién decide cada uno**. Es
> una **política**: rige en las cuatro versiones y no se renegocia en cada
> `6_contracts.md`.
>
> **Por qué existe.** Sin ella, cada versión inventa sus códigos y el mismo
> error acaba respondiendo distinto en la v1 y en la v4. Entonces quien
> consume la API no puede escribir una sola rutina de manejo.
>
> **Cada fila de este documento está medida** contra la API en marcha, en el
> puerto 8090. No hay ninguna que no se pueda repetir con una petición.
>
> Versión 1.0 · 17 de octubre de 2026.

---

## 1. La tabla completa

| Código | Qué significa | Quién lo decide, en este código | Petición que lo produce |
|---|---|---|---|
| **200** | Salió bien | el controlador | `GET /api/producto` |
| **204** | Salió bien y **no hay contenido**: la tabla está vacía | el controlador, con `http_response_code(204)` | `GET /api/empresa` sin empresas |
| **400** | Los **parámetros** no sirven: la forma está bien y la petición no pide nada que se pueda hacer | el **controlador**, antes de llamar al servicio | `PATCH /api/producto/PR001` con `{}` |
| **401** | **No sé quién es usted** | `exigirSesion()`, en `autorizacion/guardia.php` | `GET /api/producto` sin cabecera |
| **403** | **Sé quién es, y no le toca** | `exigirPermiso()`, que consulta `verificar_acceso_ruta` en la base de datos | `GET /api/usuario` con el token de `vendedor1` |
| **404** | **Eso no existe** — y son **dos** cosas distintas con el mismo número (ver §2) | el servicio (la ficha) · el enrutador (la ruta) | `GET /api/factura/9999` · `GET /api/inventado` |
| **405** | La ruta existe, pero **no con ese verbo** | el enrutador, con `responderNoPermitido()` | `PATCH /api/factura/1` |
| **409** | La petición está bien y **choca con lo que ya hay** | el repositorio, traduciendo el error del motor | crear `/home` otra vez · borrar una persona que es cliente |
| **422** | El **body no tiene la forma** pedida | el método `validar()` del controlador | `POST /api/producto` con `{}` |
| **500** | Se rompió algo, o el motor rechazó y nadie lo tradujo | el `catch (Throwable)` final | una factura con un producto que no existe |

Lo medido, textual:

```
GET  /api/producto           sin token   401  "Falta la cabecera Authorization: Bearer <token>."
GET  /api/usuario            vendedor1   403  "El usuario vendedor1@correo.com no tiene acceso a /usuario."
GET  /api/factura/9999                   404  "No existe la factura número 9999"
GET  /api/inventado                      404  "GET /api/inventado"
PATCH /api/factura/1                     405  "Método no permitido para esta ruta."
POST /api/producto           {}          422  cuatro errores, todos a la vez
PATCH /api/producto/PR001    {}          400  "No se envió ningún campo para actualizar."
POST /api/ruta               /home       409  "Ya existe la ruta con esa llave."
DELETE /api/persona/P001                 409  "No se puede eliminar la persona porque hay otras fichas que dependen de ella."
```

---

## 2. Las cuatro parejas que se confunden

### 401 contra 403

| | 401 | 403 |
|---|---|---|
| Qué dice | «No sé quién es usted» | «Sé quién es, y no le toca» |
| Causa | Sin cabecera, con firma alterada, o vencido | Token válido, permiso ausente |
| **Qué hace la persona** | Identificarse | Pedirle el permiso al administrador |

> Confundirlos manda a alguien a arreglar lo que no está roto: si respondiera
> 401 cuando lo que falta es un permiso, la persona volvería a escribir su
> contraseña —y entraría— y seguiría sin poder.

**Y el 401 de esta API dice CUÁL de los tres casos es**, en el `detalle`:

| `detalle` | Qué pasó |
|---|---|
| `Falta la cabecera Authorization: Bearer <token>.` | No mandó nada |
| `El token venció.` | Entró hace más de una hora |
| `La firma del token no es válida.` | El token se alteró |

Los tres son 401 porque los tres se arreglan igual —volver a entrar—, pero
quien programa necesita distinguirlos para saber si su código perdió el
token o si lo está mandando mal.

### Los dos 404

| | El 404 de la **ficha** | El 404 de la **ruta** |
|---|---|---|
| Qué no existe | La fila que se pidió | La dirección |
| Quién lo decide | El servicio | El enrutador, al final de `index.php` |
| `mensaje` | `Factura no encontrada.` | `Ruta no encontrada.` |
| `detalle` | `No existe la factura número 9999` | `GET /api/inventado` |

> **El mismo número y dos causas**, y el `mensaje` es lo que las separa. Vale
> la pena mirarlo cuando algo «no aparece»: si dice «Ruta no encontrada», el
> problema está en la dirección —un plural de más, una barra de menos—, no en
> los datos.

### 422 contra 400

| | 422 | 400 |
|---|---|---|
| Qué falló | La **forma**: falta un campo, o es del tipo equivocado | El **sentido**: la forma está bien y aun así no se puede hacer nada |
| Quién lo decide | `validar()` del controlador | El controlador, antes de llamar al servicio |
| Ejemplo medido | `POST /api/producto` con `{}` | `PATCH /api/producto/PR001` con `{}` |

> **El `{}` del PATCH es el caso que lo enseña.** Es un JSON perfectamente
> formado: por la forma no hay nada que reprocharle. Lo que pasa es que no
> pide ningún cambio, y «actualizar nada» no es actualizar. Eso ya no es
> forma, es sentido.

**Y aquí hay una diferencia con los otros repositorios del curso que conviene
saber:** en FastAPI el 422 lo produce el framework antes de que el
controlador se entere. **En PHP puro no hay framework que lo haga**, así que
el 422 se escribe a mano, en el método `validar()` de cada controlador. Es
más código y tiene una ventaja: los mensajes están en castellano y dicen qué
se esperaba —`"El campo codigo es obligatorio: texto de 1 a 10 caracteres."`—
en vez del volcado técnico que genera un validador automático.

### 409 contra 500

| | 409 | 500 |
|---|---|---|
| Qué pasó | Choca con lo que ya hay, y el sistema **lo esperaba** | El motor rechazó y **nadie lo tradujo** |
| Ejemplo medido | Crear `/home` otra vez · borrar `P001`, que es cliente | Una factura con `"codigo": "NOEXIST"` |

> **Ese 500 de la factura está anotado como pendiente**, con su razón, en
> [`PENDIENTES.md`](PENDIENTES.md) §2.1: el disparador calcula el subtotal con
> el precio del producto, que no existe, así que la columna queda nula
> **antes** de que la llave foránea alcance a quejarse. El motor reporta «no
> puede ser nulo» en lugar de «ese código no existe». Debería ser 409, y la
> corrección es la misma en los cinco repositorios de la v4, así que se hace
> de una vez o no se hace.

---

## 3. La forma del cuerpo

**Siempre JSON**, también en el error. Nunca una página de error de PHP: el
`index.php` pone `header('Content-Type: application/json')` antes de
cualquier cosa, y los avisos del intérprete van al registro, no a la
respuesta.

### El error corriente

```json
{ "estado": 404,
  "mensaje": "Factura no encontrada.",
  "detalle": "No existe la factura número 9999" }
```

| Campo | Para quién |
|---|---|
| `estado` | Redundante con el código HTTP **a propósito**: quien lee un registro de texto no tiene el código a la vista |
| `mensaje` | Para la persona. En castellano, sin jerga |
| `detalle` | Para quien programa. Puede traer el mensaje del motor |

### El 422, que trae la lista

```json
{ "estado": 422,
  "mensaje": "Datos inválidos.",
  "errores": [
    "El campo codigo es obligatorio: texto de 1 a 10 caracteres.",
    "El campo nombre es obligatorio.",
    "El campo stock es obligatorio.",
    "El campo valorunitario es obligatorio."
  ] }
```

> **Los errores de forma llegan TODOS a la vez.** Devolver el primero y callar
> los otros obliga a corregir de a uno y volver a enviar: cuatro viajes para
> cuatro errores que se sabían desde el principio.

### El 403, que dice quién y a dónde

```json
{ "estado": 403,
  "mensaje": "Su rol no tiene permiso para esta operacion.",
  "detalle": "El usuario vendedor1@correo.com no tiene acceso a /usuario." }
```

---

## 4. Lo que el `detalle` NO puede traer

| Nunca | Por qué |
|---|---|
| Una contraseña o un hash | La API no los devuelve ni cuando salen bien: `GET /api/usuario` trae el correo y nada más |
| La cadena de conexión | Trae la clave del motor |
| La traza completa del error | Le dice a un desconocido qué versión de PHP y qué extensiones corren |
| Si un correo **existe** | El login responde lo mismo en los dos casos — §5 |

> **Y una advertencia honesta sobre el 500 de esta versión:** su `detalle`
> trae el mensaje del motor tal cual —`"SQLSTATE[23000]… 1048 Column 'subtotal'
> cannot be null"`—. En un sistema en producción eso se registra y no se
> devuelve. **Aquí se devuelve a propósito**, porque es un proyecto de aula y
> ese mensaje es justo lo que hay que aprender a leer. Si este sistema saliera
> a internet, esa línea es la primera que hay que cambiar.

---

## 5. El login, que parece un error y es una decisión

`POST /api/sesion` responde **401 con el mismo mensaje** en los dos casos.
Medido:

```
correo que no existe   → 401  {"estado":401,"mensaje":"El correo o la contrasena no son correctos."}
contraseña equivocada  → 401  {"estado":401,"mensaje":"El correo o la contrasena no son correctos."}
```

> **No es pereza.** Si respondiera 404 cuando el correo no existe, cualquiera
> podría averiguar **qué correos están registrados** probando uno por uno. El
> precio es que quien se equivocó de contraseña no sabe cuál de las dos cosas
> falló, y ese precio se paga a propósito.

**Y `POST /api/usuario/verificar-contrasena` SÍ existe en este repositorio** y
responde `200 "La contraseña coincide."`. No contradice lo anterior, y vale
explicar por qué: esa ruta **exige el permiso `/usuario`**, que solo tiene el
Administrador. No es un oráculo abierto —quien no ha entrado recibe 401 antes
de llegar a ella—, y sirve para pedir confirmación antes de una operación
delicada sin entregar un token nuevo.

---

## 6. Cómo se traduce un error del motor

El motor habla en números. El **repositorio** los traduce, y **nadie por
encima de él conoce `PDOException`**: el servicio recibe excepciones de
negocio y el controlador recibe códigos.

| El motor dice | Se traduce a | Dónde |
|---|---|---|
| MariaDB **1062** · PostgreSQL **23505** · SQL Server **2627** — llave duplicada | **409** `conflictoLlaveDuplicada()` | `errores_de_integridad_<motor>.php` |
| MariaDB **1452** · PostgreSQL **23503** · SQL Server **547** — referencia rota | **409** `conflictoReferenciaRota()` | el mismo |
| MariaDB **1451** — tiene dependientes | **409** `conflictoTieneDependientes()` | el mismo |
| `SIGNAL SQLSTATE '45000'` con «no existe» | **404** | el repositorio de `usuario-con-roles` |
| Cualquier otro | **500**, y sube tal cual | el `catch` final |

> **Cada motor numera sus errores a su manera**, y por eso hay **tres**
> traductores y no uno. Es la misma razón por la que hay tres repositorios por
> recurso: lo que cambia entre motores se queda en la capa de abajo, y las
> otras dos no se enteran.
>
> Y hay un detalle que se midió y costó encontrar: `errorInfo[1]` trae el
> código **del motor** —1062, 1452—, mientras que `getCode()` de PDO trae el
> SQLSTATE genérico, `'23000'` para **todas** las violaciones de integridad.
> Con `getCode()` no se puede distinguir una llave duplicada de una referencia
> rota.

---

## 7. Comprobarlo

Las dos que no necesitan sesión:

```powershell
# 401 — sin token
Invoke-RestMethod http://localhost:8090/api/producto

# 404 de RUTA — la dirección no existe
Invoke-RestMethod http://localhost:8090/api/inventado
```

El resto van con el token, y están en el `7_quickstart.md` de la v4, que es
donde se corren de verdad. La colección de Postman
—[`postman/`](../../postman/README.md)— trae una carpeta dedicada al 401 y al
403, y las peticiones que terminan en 405, 409 y 422 están marcadas con su
código en el nombre.

En PowerShell, **`curl` no es curl**: es un alias de `Invoke-WebRequest` y no
acepta las banderas de curl. Por eso todos los ejemplos usan
`Invoke-RestMethod`.
