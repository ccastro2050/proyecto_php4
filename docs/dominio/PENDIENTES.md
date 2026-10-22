# Pendientes — Proyecto PHP v4

> **Para qué sirve este archivo.** Lo que no se hizo también se escribe. Un
> pendiente anotado es una decisión; uno no anotado es un olvido que alguien
> va a encontrar en la sustentación —y el que lo encuentra siempre pregunta
> si había más—.
>
> Cada entrada dice **qué falta**, **por qué no se hizo** y **cómo se
> comprueba** que sigue pendiente. Las que están medidas traen el número.
>
> *Última revisión: 10 de octubre de 2026, con los tres motores arriba y la
> v4 cerrada.*

---

## 1. Divergencias entre las cinco v4 del semestre

Las cinco rutas llegan a su v4 con el mismo alcance —ésa es la regla—, y al
medirlas lado a lado aparecieron tres diferencias de **contrato**, no de
stack. Están aquí porque arreglarlas toca más de un repositorio y eso se
decide de una vez para todos, no repo por repo.

### 1.1 El nombre del campo del detalle de la factura

| Repo | Campo que espera |
|---|---|
| `proyecto_php4` | **`detalle`** |
| `proyecto_paradigmas4`, `proyecto_construccion_de_software4` | **`productos`** |

Medido: `POST /api/factura` con `productos` en php4 responde 422 «el campo
detalle es obligatorio», y con `detalle` en los otros dos responde 422 «el
campo productos es obligatorio».

**Por qué no se cambió ya:** renombrarlo toca el controlador, el servicio, el
front, la colección de Postman y los contratos de las cuatro versiones de
PHP —la v2 ya lo usaba así—. Es un cambio de contrato, y un contrato se
cambia a propósito y de una vez, no en el último commit de una versión.

**Cómo se comprueba:** las dos peticiones de arriba.

### 1.2 La ruta para entrar

| Repo | Ruta |
|---|---|
| `proyecto_php4`, los tres de .NET | `POST /api/sesion` |
| `proyecto_paradigmas4` | `POST /api/sesion/entrar` |

Medido: `POST /api/sesion` responde 200 en los puertos 8035, 8045, 8055 y
8090, y **405** en el 8005; ahí la ruta es `/api/sesion/entrar`.

**Cuál es la correcta:** `POST /api/sesion`, que es la de cuatro de los cinco
y la que trata la sesión como un recurso —se crea con POST, se consulta con
GET, se cierra con DELETE—. Lo que hay que corregir es paradigmas4, no php4.

### 1.3 El código de «creado»: 200 en unos recursos y 201 en otros

En php4 los seis recursos de la v2 responden **200** al crear y los cinco de
la v3 responden **201**. El 201 es el correcto para una creación, y la
diferencia es herencia: los seis primeros se escribieron antes de que la
convención quedara escrita.

**Por qué no se igualó:** cambiar los seis primeros a 201 rompería la
colección de Postman de las v1, v2 y v3 y los contratos de esas tres
versiones, que dicen 200 con todas las letras. El front ya acepta los dos
—`in_array($r['codigo'], [200, 201], true)`—, con un comentario que apunta a
este pendiente.

---

## 2. Lo que la v4 dejó medido y sin corregir

### 2.1 Un producto inexistente en una factura responde 500, no 409

Medido en los tres repos que lo tienen:

```
php4            500  "1048 Column 'subtotal' cannot be null"
paradigmas4     500  "NotNullViolationError: null value in column \"total\""
construccion4   500  "23502: null value in column \"total\" ..."
```

Es el mismo fallo en los tres, y **no es específico de PHP**: el trigger
calcula el subtotal con el precio del producto, que no existe, así que la
columna queda nula **antes** de que la llave foránea tenga ocasión de
quejarse. El motor reporta «no puede ser nulo» en lugar de «ese código no
existe».

**Lo correcto sería 409** —o 422, si se valida antes de llegar a la base de
datos—. Está sin corregir porque la corrección honesta es la misma en los
cinco repos y conviene hacerla a la vez: traducir el error de nulidad cuando
venga de ese trigger, o comprobar la existencia de cada producto en el
servicio antes de llamar al procedimiento.

**Cómo se comprueba:** `POST /api/factura` con `{"codigo":"NOEXISTE","cantidad":1}`.

### 2.2 Tres de las diez consultas responden cero filas con los datos sembrados

`productos-sin-vender`, `anulaciones-por-cliente` e
`interfaces-sin-usuarios` preguntan por lo que **no** existe, y con la
siembra todo está vendido, ninguna factura está anulada y todas las rutas
tienen rol. **No es un fallo**: las tres corren y devuelven `total: 0`, y la
pantalla lo dice con esas palabras.

Se comprobó que funcionan creando los tres casos por la API —un producto que
nadie compra, una factura anulada y una ruta sin rol— y las tres respondieron
con una fila. Las peticiones están en `7_quickstart.md`, sección 5.

**Por qué no se sembraron esos tres casos:** la siembra es la misma en los
cinco repos y se deriva del Excel del curso. Agregarle filas inventadas a uno
solo rompería la equivalencia y el rastro de procedencia de los datos.

### 2.3 El front no renueva el token

Hay `POST /api/sesion/renovar` y el front no lo llama. Cuando el token vence,
la siguiente petición responde 401 y la guardia manda a `/entrar`. Es honesto
—nadie se queda con una pantalla a medias— y es suficiente para un proyecto
de aula, pero en un sistema de verdad el token se renueva antes de vencer.

---

## 3. Lo que el control de acceso NO hace, a propósito

Cada uno de estos cabía y se dejó fuera. Van aquí para que la respuesta en la
sustentación sea «no está, por esto» y no un silencio.

| Qué falta | Por qué |
|---|---|
| **Lista negra de tokens** (que `DELETE /api/sesion` de verdad apague el token) | Un JWT firmado no se puede apagar desde el servidor: habría que guardar los tokens revocados y consultarlos en cada petición, que es exactamente el estado que el JWT vino a evitar. Lo que cierra la sesión es que el cliente tire el token, y el front lo hace |
| **Bloqueo por intentos fallidos** | Necesita contar intentos por cuenta y por origen, con su ventana de tiempo: es una tabla más y una política que discutir. Hoy el único freno es que bcrypt costo 12 tarda ~250 ms por intento |
| **Registro de auditoría** (quién entró, quién cambió qué) | Es una tabla y un punto de escritura en cada operación. Vale un tema aparte |
| **Permisos por verbo** (leer sí, escribir no) | La tabla `ruta` guarda la ruta, no el método. Cambiarlo es cambiar el modelo de datos, y eso no se decide en el último commit de una versión |
| **Recuperar la contraseña** | Necesita correo saliente, que es infraestructura nueva |

---

## 4. Lo que falta de documentación

### 4.0 El front no aplica el manual de marca

Es **criterio de la v4** —«imagen corporativa con su manual de marca, y la
interfaz gráfica lo cumple»— y hoy está en rojo: el front usa Bootstrap sin
personalizar. `publico/estilos.css` tiene 23 líneas con dos reglas y ningún
color de marca; `publico/marca.css` no existe; la barra dice «Empresa de
Ejemplo» en el gris de Bootstrap.

El manual con la paleta, las tipografías y los contrastes calculados está
escrito en [`MANUAL_DE_MARCA.md`](MANUAL_DE_MARCA.md), §6, con los cinco
pasos para aplicarlo.

### 4.1 La carpeta `docs/dominio/` está incompleta

Los otros cuatro repos de la v4 traen 18 documentos de dominio —requisitos,
reglas de negocio, diseño de la base de datos, glosario, política de errores,
datos de prueba, cronograma, manual de marca, los planes de cada versión y la
sustentación del código—. Este repo tiene, por ahora, **solo este archivo**.

**Por qué:** la ruta de PHP se construyó con el spec kit por versiones
(`docs/spec_kit/versiones/vN/`), que cubre lo mismo pero organizado por
versión en lugar de por tema. Los dos esquemas no se contradicen; falta
escribir los de dominio para que el repo se pueda leer también por tema, como
los otros cuatro.

Lo que falta, en orden de utilidad para el estudiante:

1. `SUSTENTACION_DEL_CODIGO.md` — las preguntas que se van a hacer en la
   sustentación, con el archivo y la línea donde está la respuesta;
2. `POLITICA_DE_ERRORES.md` — los siete códigos y quién decide cada uno;
3. `REGLAS_DE_NEGOCIO.md` — las reglas y dónde vive cada una (base de datos,
   servicio o controlador);
4. `DISENO_BD.md` — las once tablas y las diferencias entre los tres motores;
5. los demás.

### 4.2 Los tutoriales de phpMyAdmin y SQLTools son de MariaDB

`docs/TUTORIAL_PHPMYADMIN.md` y `docs/TUTORIAL_VSCODE_SQLTOOLS.md` enseñan a
mirar la base de datos, y los dos están escritos contra MariaDB. Con tres
motores, faltan las dos conexiones equivalentes —pgAdmin y el driver de SQL
Server en SQLTools—. Las capturas son lo que cuesta.

---

## 5. Cerrado en esta revisión (y cómo se comprobó)

Lo que entró a la v4 y quedó medido, para que no se vuelva a abrir por
desconfianza:

| Qué | Comprobación |
|---|---|
| Los ocho procedimientos de MariaDB, con transacción | PATCH con un rol inexistente: 409, y el administrador **conserva** sus roles. Antes se quedaba con cero |
| El script de MariaDB y el de SQL Server, reparados | `CALL crear_usuario_con_roles(...)` crea el usuario que se le pide, no `admin@correo.com` |
| Las diez consultas en los tres motores | `prueba_consultas_tres_motores.php`: 10/10 en `mariadb`, `postgres` y `sqlserver`, comparando columnas y el valor del ticket |
| El `catch` que faltaba en el PATCH de `usuario-con-roles` | 409 donde antes había 500 |
| La explicación falsa del `CAST` en T-SQL | `SELECT CAST(3400000.00 AS DECIMAL(18,2)) / 3` → `1133333.3333`, y `3400000 / 3` → `1133333`. Corregida en el código y contada en `4_research.md` |
| El permiso no viaja en el token | Quitarle el rol de Vendedor a `vendedor1` con su token ya emitido: `/api/cliente` pasa de 200 a **403** sin reentrar, y `/api/factura` sigue en 200 porque eso lo da Cajero |
| El front no ofrece lo que la API rechaza | Con `vendedor1`: tres tarjetas en el inicio y sin «Administrar»; escribiendo `/productos` a mano, la pantalla muestra el 403 de la API |
