# Quickstart — Versión 3

> **Versión 3** · Validación rápida de la v3 ya construida. Si aún no hay
> nada, empiece por [8_tasks.md](8_tasks.md).

---

## 1. Arrancar TODO (un solo comando)

```powershell
# desde la raíz del proyecto (terminal integrada de VS Code):
docker compose up -d --build
```

| | Dónde | Qué es |
|---|---|---|
| **La pantalla** | <http://localhost:8084> | Por donde se usa el sistema |
| La API | <http://localhost:8086> | JSON; el front habla con ella |
| phpMyAdmin | <http://localhost:8103> | Para mirar **MariaDB** por dentro |
| MariaDB | `localhost:13328` | `paradigmas` / `paradigmas123` |
| **PostgreSQL** | `localhost:15464` | `paradigmas` / `paradigmas123` (con DBeaver o SQLTools) |

Arranca en **MariaDB**, que es el motor por defecto.

## 2. Cambiar de motor (lo que esta versión viene a enseñar)

```powershell
$env:MOTOR = "postgres"
docker compose up -d --no-deps --force-recreate api-facturas
```

Y para volver:

```powershell
$env:MOTOR = "mariadb"
docker compose up -d --no-deps --force-recreate api-facturas
```

`--no-deps` es para no reiniciar las bases: solo se recrea la API.

**Compruébelo, que es la mitad de la versión:**

```powershell
curl http://localhost:8086/
# → "motor": "postgres"
```

Y refresque <http://localhost:8084>: la etiqueta del pie cambió. **Y nada
más.** Las mismas seis secciones, los mismos datos, los mismos botones.

> **Lo que NO hubo que hacer** para cambiar de motor: editar un archivo,
> reconstruir una imagen, tocar el front. Una variable y un reinicio.

## 3. Smoke test de la API (criterios 1 a 9)

Corra esto **dos veces**, una con cada motor, y compare. La gracia es que las
respuestas sean idénticas.

```powershell
# 1 y 2. El diagnóstico dice el motor
curl http://localhost:8086/

# 3. Los seis recursos, con los mismos totales en los dos motores
curl http://localhost:8086/api/producto      # total: 8
curl http://localhost:8086/api/empresa       # total: 3
curl http://localhost:8086/api/persona       # total: 6
curl http://localhost:8086/api/cliente
curl http://localhost:8086/api/vendedor      # total: 3
curl http://localhost:8086/api/factura       # total: 6

# 4. La llave la genera la base — en los dos, con la misma respuesta
curl -X POST http://localhost:8086/api/cliente -H "Content-Type: application/json" `
     -d '{\"credito\":500000,\"fkcodpersona\":\"P004\",\"fkcodempresa\":null}'
#    → { "estado":200, "mensaje":"Cliente creado exitosamente.", "id": N }
#    (con MariaDB se lee con lastInsertId; con PostgreSQL con RETURNING.
#     La respuesta es la misma: eso es lo que se está comprobando.)

# 5. LOS TRES CONFLICTOS — el MISMO texto con los dos motores
curl -i -X POST http://localhost:8086/api/empresa -H "Content-Type: application/json" `
     -d '{\"codigo\":\"E001\",\"nombre\":\"Repetida\"}'
#    → 409 "Ya existe la empresa con esa llave. Las llaves no se repiten."

curl -i -X POST http://localhost:8086/api/cliente -H "Content-Type: application/json" `
     -d '{\"credito\":100,\"fkcodpersona\":\"NOEXISTE\",\"fkcodempresa\":null}'
#    → 409 "Alguno de los códigos a los que apunta el cliente no existe…"

curl -i -X DELETE http://localhost:8086/api/persona/P001
#    → 409 "No se puede eliminar la persona porque hay otras fichas…"

# ↑ Compárelos PALABRA POR PALABRA entre los dos motores. Que digan lo mismo
#   no es casualidad: MariaDB reporta esos tres casos con tres códigos
#   numéricos y PostgreSQL con dos SQLSTATE, uno de los cuales sirve para
#   dos casos opuestos. Emparejarlos es trabajo de los dos traductores.

# 6, 7 y 8. La factura, igual con los dos
curl -X POST http://localhost:8086/api/factura -H "Content-Type: application/json" `
     -d '{\"fkidcliente\":1,\"fkidvendedor\":1,\"detalle\":[{\"codigo\":\"PR001\",\"cantidad\":2},{\"codigo\":\"PR003\",\"cantidad\":1}]}'
curl -i -X PATCH http://localhost:8086/api/factura/1 -d '{}'      # 405
curl -X POST http://localhost:8086/api/factura/7/anular
curl -i -X POST http://localhost:8086/api/factura/7/anular        # 409
curl -i -X PUT  http://localhost:8086/api/factura/999 -H "Content-Type: application/json" `
     -d '{\"fkidcliente\":1,\"fkidvendedor\":1,\"detalle\":[{\"codigo\":\"PR001\",\"cantidad\":1}]}'
#    → 404 con los DOS motores.
#      Y aquí hay una diferencia que el repositorio tapa: los procedimientos
#      de los dos motores avisan de formas distintas (SIGNAL contra RAISE), y
#      el mensaje llega envuelto en jerga distinta. Que los dos terminen en
#      404 es trabajo de sus repositorios, no del motor.
```

**9. Prueba de capas** (sin ninguna base corriendo):

```powershell
docker compose exec api-facturas php pruebas/prueba_capas.php
```

Este archivo **no depende del motor**: los repositorios falsos están en
memoria. Que siga pasando sin cambios es parte del criterio 9.

## 4. La prueba de los dos motores (criterio 12)

```powershell
python pruebas_humo/humo_los_dos_motores.py
```

Reinicia la API contra cada motor y corre **el guion de humo completo** —el
mismo de la v2, sin una línea distinta— contra los dos.

Y ahí está la gracia: **`humo_front.py` no sabe nada de motores.** Llena
formularios, oprime botones y lee la pantalla. Que dé el mismo resultado dos
veces demuestra que el motor no se filtra hacia arriba, porque si se filtrara
la pantalla se comportaría distinto y el guion lo notaría.

## 5. EL CRITERIO 13: qué se tocó y qué no

Éste no se comprueba con `curl`, sino con un `diff`. Con las dos carpetas al
lado:

```powershell
diff -rq --exclude=.git ..\proyecto_php2 .
```

La lista de archivos que difieren **no puede incluir ningún controlador ni
ninguna vista**. Los cambios permitidos, con su motivo, están en
[2_spec.md](2_spec.md) §5, criterio 13.b — incluida la excepción que hubo, que
está escrita y no escondida.

> Es el criterio más importante de la versión y el más raro: los demás
> comprueban que el sistema funciona; éste comprueba que la arquitectura
> servía para algo.

## 6. El recorrido a mano

1. Abra <http://localhost:8084> y use el sistema un rato: cree una empresa,
   una factura, anúlela.
2. Mire el pie: dice **MariaDB**.
3. Cambie el motor (§2) y refresque.
4. **Vuelva a hacer exactamente lo mismo.** Todo debe sentirse igual.
5. Fíjese en lo único que sí cambió: los datos. El cliente que creó con
   MariaDB no está aquí — **son dos bases independientes**, y está declarado
   en el alcance.

## 7. Volver al punto de partida

```powershell
docker compose down -v && docker compose up -d --build
```

`down -v` borra los **dos** volúmenes, así que los dos `init.sql` vuelven a
ejecutarse y las dos bases quedan como recién nacidas.

## 8. Si algo falla

| Síntoma | Causa probable |
|---|---|
| `could not find driver` al cambiar a postgres | Falta `pdo_pgsql` en la imagen de la API: hay que reconstruirla (`--build`), no solo recrearla |
| Cambié `MOTOR` y el diagnóstico sigue diciendo el otro | Faltó `--force-recreate`: el contenedor viejo sigue vivo con la variable vieja |
| Todo funciona en un motor y falla en el otro | **Eso es el hallazgo**, no un accidente: algo del motor se filtró hacia arriba. Busque el `SELECT`, el nombre de procedimiento o el código de error que quedó fuera de una clase con apellido de motor |
| Un 409 dice cosas distintas según el motor | Los mensajes se están redactando en los traductores en vez de en `conflictos.php` |
| `PUT` a una factura inexistente da 409 en un motor y 404 en el otro | El `interpretarRechazo` de ese motor no está limpiando bien el mensaje del procedimiento |
| «Cannot execute queries while other unbuffered queries are active» | Falta el `closeCursor()` después del `CALL` — **es cosa de MariaDB**; en PostgreSQL no aparece |
| El listado de facturas trae fechas con formatos distintos | Falta recortar los microsegundos en el repositorio de PostgreSQL ([3_plan.md](3_plan.md) §4.5) |
| Puerto 8084, 8086, 8103, 13328 o 15464 ocupado | Otro proyecto del curso está corriendo — apáguelo (`docker compose down` en ESE proyecto) |
| `all predefined address pools have been fully subnetted` | Docker se quedó sin rangos de red de tantos proyectos encendidos: `docker network prune -f`, y apague los que no esté usando |
