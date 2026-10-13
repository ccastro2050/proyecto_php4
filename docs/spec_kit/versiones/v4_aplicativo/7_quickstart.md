# Quickstart — Versión 4

> **Versión 4** · Validación rápida de la v4 ya construida. Si aún no hay
> nada, empiece por [8_tasks.md](8_tasks.md).

---

## 1. Arrancar TODO (un solo comando)

```powershell
# desde la raíz del proyecto (terminal integrada de VS Code):
docker compose up -d --build
```

**La primera vez tarda más que en las versiones anteriores**, y por dos
motivos que conviene saber para no creer que se colgó:

- la imagen de la API compila el driver de SQL Server, que hay que traer del
  repositorio de Microsoft;
- SQL Server tarda medio minuto en estar listo, y solo entonces
  `sqlserver-init` crea la base y corre el script.

Para ver ese segundo paso:

```powershell
docker compose logs -f sqlserver-init
# Debe terminar con: [init] SQL Server inicializado correctamente.
```

| | Dónde | Qué es |
|---|---|---|
| **La pantalla** | <http://localhost:8088> | Por donde se usa el sistema |
| La API | <http://localhost:8090> | JSON; dice qué motor está activo |
| phpMyAdmin | <http://localhost:8104> | Para mirar **MariaDB** por dentro |
| MariaDB | `localhost:13329` | `paradigmas` / `paradigmas123` |
| PostgreSQL | `localhost:15465` | `paradigmas` / `paradigmas123` |
| **SQL Server** | `localhost:11474` | **`sa` / `Paradigmas123!`** (con SSMS o Azure Data Studio) |

> **Las credenciales de SQL Server son distintas a propósito**, y no es un
> descuido: el motor exige que la clave tenga mayúscula, número y símbolo, y
> su cuenta administrativa se llama `sa`. Está declarado en la constitución.

## 2. Cambiar de motor

```powershell
$env:MOTOR = "sqlserver"      # o "postgres", o "mariadb"
docker compose up -d --no-deps --force-recreate api-facturas
```

Compruébelo:

```powershell
curl http://localhost:8090/
# → "motor": "sqlserver"
```

Y refresque <http://localhost:8088>: la etiqueta del pie dice **SQL Server**.
**Y nada más.** Las mismas seis secciones, los mismos datos, los mismos
botones.

## 3. Smoke test de la API (criterios 1 a 9)

Córralo **tres veces**, una por motor, y compare. La gracia es que las
respuestas sean idénticas.

```powershell
# 1 y 2. El diagnóstico dice el motor
curl http://localhost:8090/

# 3. Los seis recursos, con los mismos totales en los tres motores
curl http://localhost:8090/api/producto      # total: 8
curl "http://localhost:8090/api/producto?limite=3"
#    ↑ Con SQL Server eso NO es un LIMIT: es OFFSET/FETCH. El resultado es el
#      mismo; el SQL que lo produce, no.
curl http://localhost:8090/api/empresa       # total: 3
curl http://localhost:8090/api/persona       # total: 6
curl http://localhost:8090/api/cliente
curl http://localhost:8090/api/vendedor      # total: 3
curl http://localhost:8090/api/factura       # total: 6

# 4. La llave la genera la base — de tres maneras distintas, misma respuesta
curl -X POST http://localhost:8090/api/cliente -H "Content-Type: application/json" `
     -d '{\"credito\":500000,\"fkcodpersona\":\"P004\",\"fkcodempresa\":null}'
#    → { "estado":200, "mensaje":"Cliente creado exitosamente.", "id": N }
#      MariaDB la lee con lastInsertId, PostgreSQL con RETURNING y SQL Server
#      con OUTPUT INSERTED. La respuesta es la misma: eso es lo que se prueba.

# 5. LOS TRES CONFLICTOS — el MISMO texto con los tres motores
curl -i -X POST http://localhost:8090/api/empresa -H "Content-Type: application/json" `
     -d '{\"codigo\":\"E001\",\"nombre\":\"Repetida\"}'
curl -i -X POST http://localhost:8090/api/cliente -H "Content-Type: application/json" `
     -d '{\"credito\":100,\"fkcodpersona\":\"NOEXISTE\",\"fkcodempresa\":null}'
curl -i -X DELETE http://localhost:8090/api/persona/P001

# ↑ Compárelos palabra por palabra entre los TRES motores. Cada uno reporta
#   esos casos con códigos distintos, y DOS de los tres usan el mismo código
#   para dos casos opuestos. Emparejarlos es trabajo de los tres traductores.

# 6, 7 y 8. La factura, igual con los tres
curl -X POST http://localhost:8090/api/factura -H "Content-Type: application/json" `
     -d '{\"fkidcliente\":1,\"fkidvendedor\":1,\"detalle\":[{\"codigo\":\"PR001\",\"cantidad\":2},{\"codigo\":\"PR003\",\"cantidad\":1}]}'
curl -i -X PATCH http://localhost:8090/api/factura/1 -d '{}'      # 405
curl -X POST http://localhost:8090/api/factura/7/anular
curl -i -X POST http://localhost:8090/api/factura/7/anular        # 409
curl -i -X PUT  http://localhost:8090/api/factura/999 -H "Content-Type: application/json" `
     -d '{\"fkidcliente\":1,\"fkidvendedor\":1,\"detalle\":[{\"codigo\":\"PR001\",\"cantidad\":1}]}'
#    → 404 con los TRES. Y los tres procedimientos avisan de forma distinta:
#      SIGNAL en MariaDB, RAISE en PostgreSQL, THROW en SQL Server, cada uno
#      con su propio envoltorio de jerga alrededor del mensaje.
```

**9. Prueba de capas** (sin ninguna base corriendo):

```powershell
docker compose exec api-facturas php pruebas/prueba_capas.php
```

Este archivo **no cambió en dos versiones**. Los repositorios falsos están en
memoria: no dependen de ningún motor.

## 4. La prueba de los tres motores (criterio 12)

```powershell
python pruebas_humo/humo_los_tres_motores.py
```

Reinicia la API contra cada motor y corre **el guion de humo completo** contra
los tres. `humo_front.py` no sabe nada de motores: llena formularios.

## 5. EL CRITERIO 13: qué se tocó y qué no

```powershell
diff -rq --exclude=.git --exclude=docs ..\proyecto_php3 .
```

La lista **no puede incluir ningún controlador, ningún servicio ni ninguna
interfaz**. Los cambios permitidos están en [2_spec.md](2_spec.md) §5,
criterio 13.b.

> **Compárela con la de la v3.** Es más corta, y no porque se haya hecho
> menos: es porque la v3 dejó el sitio preparado. Ésa es la diferencia entre
> una arquitectura que aguantó una extensión y una que aguanta las que vengan.

## 6. El recorrido a mano

1. Abra <http://localhost:8088> y use el sistema: cree una empresa, una
   factura, anúlela.
2. Mire el pie: dice **MariaDB**.
3. Cambie a `postgres` y repita. Después a `sqlserver` y repita.
4. Todo debe sentirse igual las tres veces.
5. Lo único que cambia son los datos: **son tres bases independientes**.

## 7. Volver al punto de partida

```powershell
docker compose down -v && docker compose up -d --build
```

`down -v` borra los **tres** volúmenes. SQL Server vuelve a crearse desde
cero, y eso tarda más que los otros dos: déle su minuto y revise
`docker compose logs sqlserver-init`.

## 8. Si algo falla

| Síntoma | Causa probable |
|---|---|
| El build falla trayendo el driver de Microsoft | Sin internet, o el repositorio de Microsoft caído. Es la única dependencia externa del proyecto |
| `could not find driver` con `MOTOR=sqlserver` | La imagen es vieja: hay que reconstruirla (`--build`), no solo recrearla |
| La API no arranca contra SQL Server y dice algo de `EMULATE_PREPARES` | El driver de Microsoft **no admite** esa opción de PDO. Los otros dos la necesitan; éste falla si se pone |
| `SQLSTATE[HY104]: Invalid precision value` | Se está intentando enlazar el parámetro de salida de un procedimiento. No se puede con `NVARCHAR(MAX)`: hay que declarar la variable en el propio SQL ([3_plan.md](3_plan.md) §4.3) |
| Un procedimiento devuelve basura en vez del JSON | Falta `SET NOCOUNT ON`: los avisos de «N filas afectadas» llegan como resultados intermedios |
| `Invalid usage of the option NEXT in the FETCH statement` | Falta el `ORDER BY`: SQL Server se niega a paginar sin un orden definido |
| `sqlserver-init` termina con error la primera vez | Probablemente SQL Server no estaba listo. El `depends_on` con `condition: service_healthy` debería evitarlo — revise el healthcheck |
| Levanté el sistema dos veces y se duplicaron datos | El `init.sh` no está comprobando si la base ya existe |
| Todo funciona en dos motores y falla en el tercero | **Eso es el hallazgo.** Busque el SQL, el nombre de procedimiento o el código de error que quedó fuera de una clase con apellido de motor |
| Puertos 8088, 8090, 8104, 13329, 15465 u 11474 ocupados | Otro proyecto del curso está corriendo — apáguelo |
| `all predefined address pools have been fully subnetted` | Docker se quedó sin rangos de red: `docker network prune -f`, y apague los proyectos que no esté usando. Con seis servicios, éste es el proyecto más pesado de la ruta |


---

# ANEXO A LA v4 — PROBAR EL CONTROL DE ACCESO Y LAS CONSULTAS

Todo esto es PowerShell, y con `Invoke-RestMethod`: en PowerShell `curl` es
un alias de `Invoke-WebRequest` y **no acepta las banderas de curl**, así que
un `curl -X POST -d ...` copiado de internet falla con un mensaje que no
ayuda.

## 1. Entrar y guardar el token

```powershell
$r = Invoke-RestMethod -Method Post -Uri http://localhost:8090/api/sesion `
     -ContentType 'application/json' `
     -Body '{"email":"admin@correo.com","contrasena":"admin123"}'

$cab = @{ Authorization = "Bearer $($r.token)" }
$r.roles       # Administrador
$r.expira      # cuando vence
```

Las tres cuentas que importan, y las contraseñas están escritas a propósito
—de un hash no se vuelve a la clave, y sin la clave no se comprueba nada—:

| Correo | Contraseña | Alcanza |
|---|---|---|
| `admin@correo.com` | `admin123` | las 15 rutas |
| `vendedor1@correo.com` | `vendedor123` | `/home`, `/factura`, `/cliente` |
| `cliente1@correo.com` | `cliente123` | `/home` y `/producto` |

## 2. Ver el 401 y el 403, que son distintos

```powershell
# Sin cabecera: 401, "no sé quién es usted"
Invoke-RestMethod http://localhost:8090/api/producto

# Con el token de vendedor1: 403, "sé quién es y no le toca"
$v = Invoke-RestMethod -Method Post -Uri http://localhost:8090/api/sesion `
     -ContentType 'application/json' `
     -Body '{"email":"vendedor1@correo.com","contrasena":"vendedor123"}'
$cv = @{ Authorization = "Bearer $($v.token)" }

Invoke-RestMethod -Headers $cv http://localhost:8090/api/factura   # 200
Invoke-RestMethod -Headers $cv http://localhost:8090/api/usuario   # 403
```

## 3. La prueba que de verdad enseña: el permiso no está en el token

Con el token de `vendedor1` ya en la mano, **quítele el rol y no toque el
token**. Y hay que escoger bien la ruta, porque este paso falla si se escoge
mal —a mí me falló al escribirlo—: `vendedor1` tiene **dos** roles, Vendedor
y Cajero, y `/factura` lo dan los dos. Quitarle uno no le cierra nada.

Mire primero quién da qué:

```powershell
(Invoke-RestMethod -Headers $cab "http://localhost:8090/api/rutarol?limite=100").datos |
  Where-Object { $_.ruta -in '/factura','/cliente' } |
  Select-Object ruta, rol | Sort-Object ruta
```

| Ruta | La dan |
|---|---|
| `/factura` | Administrador, Cajero, **Vendedor** |
| `/cliente` | Administrador, Contador, **Vendedor** |

Así que la ruta de la demostración es **`/cliente`**: de los dos roles de
`vendedor1`, solo Vendedor la da.

```powershell
# 1. Ahora pasa
Invoke-RestMethod -Headers $cv http://localhost:8090/api/cliente     # 200

# 2. El administrador le quita el rol de Vendedor (id 2)
Invoke-RestMethod -Method Delete -Headers $cab `
  "http://localhost:8090/api/rol-usuario/vendedor1%40correo.com/2"

# 3. EL MISMO token de antes, la MISMA petición
Invoke-RestMethod -Headers $cv http://localhost:8090/api/cliente     # 403
#    detalle: "El usuario vendedor1@correo.com no tiene acceso a /cliente."

# 4. Y /factura sigue abierta, porque eso lo da Cajero
Invoke-RestMethod -Headers $cv http://localhost:8090/api/factura     # 200
```

Pasó de 200 a 403 **sin que el token cambiara ni venciera**, porque el
permiso se consulta en la base de datos en cada petición. Si el permiso
viajara dentro del token, el cambio no se notaría hasta que venciera.

Y el paso 4 enseña la otra mitad, que es igual de importante: **los permisos
de los roles se suman.** Quien tiene dos roles alcanza la unión de sus rutas,
así que cerrarle una exige quitarle TODOS los roles que la dan. Por eso la
consulta `alcance-de-usuarios` del tablero existe: dice a cuántas interfaces
llega cada quien, ya sumadas.

Y para dejarlo como estaba:

```powershell
Invoke-RestMethod -Method Post -Headers $cab -ContentType 'application/json' `
  -Uri http://localhost:8090/api/rol-usuario `
  -Body '{"fkemail":"vendedor1@correo.com","fkidrol":2}'
```

## 4. El usuario con sus roles, y la transacción

```powershell
# Crear con dos roles, en UNA petición
Invoke-RestMethod -Method Post -Headers $cab -ContentType 'application/json' `
  -Uri http://localhost:8090/api/usuario-con-roles `
  -Body '{"email":"prueba@correo.com","contrasena":"prueba123","roles":[2,3]}'

# Y ahora el que importa: un rol que NO existe
Invoke-RestMethod -Method Patch -Headers $cab -ContentType 'application/json' `
  -Uri http://localhost:8090/api/usuario-con-roles/prueba@correo.com `
  -Body '{"roles":[99]}'
# -> 409

# ¿Se quedó sin roles?
Invoke-RestMethod -Headers $cab `
  http://localhost:8090/api/usuario-con-roles/prueba@correo.com
# -> sigue con Vendedor y Cajero: el procedimiento deshizo el DELETE

# Limpiar
Invoke-RestMethod -Method Delete -Headers $cab `
  http://localhost:8090/api/usuario-con-roles/prueba@correo.com
```

## 5. Las diez consultas

```powershell
# El índice
(Invoke-RestMethod -Headers $cab http://localhost:8090/api/consultas).datos

# Una cualquiera
$c = Invoke-RestMethod -Headers $cab `
     http://localhost:8090/api/consultas/ventas-por-producto
$c.total
$c.datos | Format-Table

# Y las diez de un tirón
'ventas-por-producto','ventas-por-cliente','ventas-por-vendedor',
'ventas-por-empresa','ticket-por-vendedor','productos-sin-vender',
'anulaciones-por-cliente','alcance-de-usuarios','interfaces-sin-usuarios',
'credito-contra-consumo' | ForEach-Object {
    $r = Invoke-RestMethod -Headers $cab "http://localhost:8090/api/consultas/$_"
    "{0,-26} {1,3} filas" -f $_, $r.total
}
```

Tres de las diez pueden responder `0 filas` con los datos sembrados, y **no
es un error**: preguntan por lo que no existe. Para que respondan:

```powershell
# Un producto que nadie compra
Invoke-RestMethod -Method Post -Headers $cab -ContentType 'application/json' `
  -Uri http://localhost:8090/api/producto `
  -Body '{"codigo":"PRZZ","nombre":"Sin vender","stock":5,"valorunitario":99000}'

# Una factura anulada
$f = Invoke-RestMethod -Method Post -Headers $cab -ContentType 'application/json' `
     -Uri http://localhost:8090/api/factura `
     -Body '{"fkidcliente":1,"fkidvendedor":1,"detalle":[{"codigo":"PR002","cantidad":1}]}'
Invoke-RestMethod -Method Post -Headers $cab `
  "http://localhost:8090/api/factura/$($f.factura.numero)/anular"

# Una ruta a la que no llega nadie
Invoke-RestMethod -Method Post -Headers $cab -ContentType 'application/json' `
  -Uri http://localhost:8090/api/ruta `
  -Body '{"ruta":"/reportes-huerfanos","descripcion":"Sin rol asignado"}'
```

## 6. Las diez contra los TRES motores, sin tocar nada

```powershell
docker exec -e MOTOR=mariadb   proyecto_php4-api-facturas-1 php /app/pruebas/prueba_consultas_tres_motores.php
docker exec -e MOTOR=postgres  proyecto_php4-api-facturas-1 php /app/pruebas/prueba_consultas_tres_motores.php
docker exec -e MOTOR=sqlserver proyecto_php4-api-facturas-1 php /app/pruebas/prueba_consultas_tres_motores.php
```

La prueba no mira el código de respuesta: mira las **columnas** y el
**valor** del ticket promedio. Un error que revienta se arregla el mismo día;
uno que responde 200 con un número redondo se queda meses.

## 7. El front

```
http://localhost:8088
```

Sin sesión, cualquier dirección lleva a `/entrar`. Entre como `vendedor1` y
haga una cosa: **escriba a mano `http://localhost:8088/productos`**. El menú
no tiene ese enlace y la pantalla se abre igual... con el 403 que respondió
la API adentro. Esconder un botón no es control de acceso.
