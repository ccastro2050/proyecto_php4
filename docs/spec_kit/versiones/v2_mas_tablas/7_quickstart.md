# Quickstart — Versión 2

> **Versión 2** · Validación rápida de la v2 ya construida. Si aún no hay
> nada, empiece por [8_tasks.md](8_tasks.md).

---

## 1. Arrancar TODO (un solo comando)

```powershell
# desde la raíz del proyecto (terminal integrada de VS Code):
docker compose up -d --build
```

| | Dónde | Qué es |
|---|---|---|
| **La pantalla** | <http://localhost:8024> | Por donde se usa el sistema |
| La API | <http://localhost:8026> | JSON; el front habla con ella |
| phpMyAdmin | <http://localhost:8102> | Para mirar la base por dentro |
| MariaDB | `localhost:13327` | `paradigmas` / `paradigmas123` |

**Empiece por el 8024**, que es como lo ve alguien que no programó esto.

> **Los puertos son distintos a los de la v1.** Es a propósito: así se pueden
> tener las dos versiones encendidas al mismo tiempo y compararlas.

## 2. Smoke test de la API (criterios 1 a 9)

```powershell
# 1. Diagnóstico: deben aparecer los SEIS recursos
curl http://localhost:8026/

# 2. Producto sigue igual que en la v1 (criterio 2)
curl http://localhost:8026/api/producto                     # total: 8
curl -i -X PUT   http://localhost:8026/api/producto/PR001 -H "Content-Type: application/json" `
     -d '{\"stock\":99}'    # 422: a PUT le faltan nombre y valorunitario
curl -i -X PATCH http://localhost:8026/api/producto/PR001 -H "Content-Type: application/json" `
     -d '{\"stock\":17}'    # 200: PATCH acepta el subconjunto

# 3. Los cuatro recursos de ficha nuevos (criterio 3)
curl http://localhost:8026/api/empresa                      # total: 3
curl http://localhost:8026/api/persona                      # total: 6
curl "http://localhost:8026/api/cliente?limite=2"           # total: 2
curl http://localhost:8026/api/vendedor                     # total: 3

# 4. La llave la genera la base (criterio 4)
#    Fíjese en que el body NO lleva id, y la respuesta SÍ lo trae:
curl -X POST http://localhost:8026/api/cliente -H "Content-Type: application/json" `
     -d '{\"credito\":500000,\"fkcodpersona\":\"P004\",\"fkcodempresa\":null}'
#    → { "estado":200, "mensaje":"Cliente creado exitosamente.", "id": 5 }
curl http://localhost:8026/api/cliente/5
curl -i http://localhost:8026/api/cliente/abc               # 400, no 404

# 5. LOS TRES RECHAZOS DE INTEGRIDAD — los tres deben dar 409 (criterio 5)
curl -i -X POST http://localhost:8026/api/empresa -H "Content-Type: application/json" `
     -d '{\"codigo\":\"E001\",\"nombre\":\"Repetida\"}'
#    → 409 "Ya existe la empresa con esa llave."

curl -i -X POST http://localhost:8026/api/cliente -H "Content-Type: application/json" `
     -d '{\"credito\":100,\"fkcodpersona\":\"NOEXISTE\",\"fkcodempresa\":null}'
#    → 409 "Alguno de los códigos a los que apunta el cliente no existe."

curl -i -X DELETE http://localhost:8026/api/persona/P001
#    → 409 "No se puede eliminar la persona porque hay otras fichas que dependen de ella."

# ↑ NINGUNO de los tres debe responder 500. Si alguno lo hace, la traducción
#   de `errores_de_integridad.php` no está atrapando ese código del motor.

# 6. MAESTRO-DETALLE (criterio 6)
#    Anote el stock antes:
curl http://localhost:8026/api/producto/PR001
curl -X POST http://localhost:8026/api/factura -H "Content-Type: application/json" `
     -d '{\"fkidcliente\":1,\"fkidvendedor\":1,\"detalle\":[{\"codigo\":\"PR001\",\"cantidad\":2},{\"codigo\":\"PR003\",\"cantidad\":1}]}'
#    → la factura COMPLETA, con su número, su total y sus dos renglones.
#      El total NO lo mandó nadie: lo calculó el trigger.
curl http://localhost:8026/api/producto/PR001    # el stock bajó 2

# 7. Lo que la factura NO admite (criterio 7)
curl -i -X PATCH http://localhost:8026/api/factura/1 -H "Content-Type: application/json" -d '{}'
#    → 405 (sale del enrutador; el número no está escrito en ninguna parte)
curl -i -X POST http://localhost:8026/api/factura -H "Content-Type: application/json" `
     -d '{\"fkidcliente\":1,\"fkidvendedor\":1,\"detalle\":[]}'
#    → 422 "El detalle debe traer al menos un producto."

# 8. ANULAR no es eliminar (criterio 8) — use el número que creó en el paso 6
curl -X POST http://localhost:8026/api/factura/7/anular
curl http://localhost:8026/api/factura/7          # SIGUE ahí, estado "anulada"
curl http://localhost:8026/api/producto/PR001     # el stock VOLVIÓ
curl -i -X POST http://localhost:8026/api/factura/7/anular          # 409, ya estaba
curl -i -X PUT http://localhost:8026/api/factura/7 -H "Content-Type: application/json" `
     -d '{\"fkidcliente\":1,\"fkidvendedor\":1,\"detalle\":[{\"codigo\":\"PR002\",\"cantidad\":1}]}'
#    → 409 "Factura 7 esta anulada: no se puede modificar"
```

**9. Prueba de capas** (criterio 9, sin MariaDB): los servicios instanciados
con repositorios **falsos** en memoria.

```powershell
docker compose exec api-facturas php pruebas/prueba_capas.php
```

## 3. La pantalla (criterios 10 a 14)

### 3.1 Automático

```powershell
python pruebas_humo/humo_front.py
```

Recorre todo: las nueve pantallas, que los estilos lleguen de verdad, el menú,
que no haya jerga, el ciclo de una ficha con sus dos botones, **los dos
rechazos de integridad explicados en español**, **la factura de dos renglones
con su total y su stock**, **anular**, y la prueba de apagar la API.

### 3.2 A mano, que es lo que un guion no ve

Abra <http://localhost:8024> y haga esto **leyendo lo que dice la pantalla**.
Lo que se juzga aquí no es si funciona —eso ya lo dijo el guion— sino si se
entiende:

1. **El menú.** Las seis secciones, y cada una con su dirección propia en la
   barra del navegador.
2. **Una ficha sencilla.** Cree una empresa, edítela con los dos botones y
   bórrela. Es lo mismo que en la v1: si algo se siente distinto, es un error.
3. **La llave que pone la base.** Entre a crear un cliente: **no hay casilla
   de identificador**. Guarde, y fíjese en que la ficha aparece con un número
   que usted no escribió.
4. **Los desplegables.** En ese mismo formulario, «Persona» es una lista con
   nombres, no una casilla donde teclear `P003`.
5. **La integridad, que es el corazón de esta versión.** Vaya a Personas e
   intente eliminar a **Ana Torres**. La pantalla debe decir, en español, que
   hay otras fichas que dependen de ella — y no debe aparecer ningún número
   raro ni la palabra «error interno».
6. **La factura.** Cree una con dos productos. Al guardar va a la pantalla del
   detalle: mire el total y compárelo con la multiplicación. **Ese número no
   lo escribió nadie.** Vuelva a Productos y compruebe que el stock bajó.
7. **Anular contra eliminar.** Anule esa factura. Sigue en el listado, marcada,
   y ya no ofrece editarse; el stock volvió. Ahora fíjese en que el botón
   «Anular» desapareció de esa fila: una acción que no se puede hacer no se
   muestra apagada, no se muestra.
8. **La prueba de los dos procesos.** Con la base de datos encendida, apague
   solo la API:

   ```powershell
   docker compose stop api-facturas
   ```

   Refresque cualquier pantalla. Sigue en pie, con su menú y con un aviso de
   que el servicio no está disponible — **y sin una sola fila**. Los datos
   siguen ahí, a un puerto de distancia; si aparecieran, sería porque el front
   llegó a la base por su cuenta.

   ```powershell
   docker compose start api-facturas
   ```

## 4. Volver al punto de partida

Las pruebas de arriba mueven stock y dejan facturas. Para dejar la base como
recién nacida:

```powershell
docker compose down -v && docker compose up -d --build
```

`down -v` borra el volumen, así que `db/init.sql` vuelve a ejecutarse.

## 5. Si algo falla

| Síntoma | Causa probable |
|---|---|
| Un rechazo de integridad responde **500** en vez de 409 | `errores_de_integridad.php` no reconoce ese código del motor. Ojo: el número útil está en `$e->errorInfo[1]`, **no** en `$e->getCode()` — ese devuelve `'23000'` para todas las violaciones ([3_plan.md](3_plan.md) §4.1) |
| `CALL sp_listar…` da error de sintaxis | El procedimiento no recibe parámetros de entrada y se armó un `CALL sp(, @resultado)` con una coma suelta ([3_plan.md](3_plan.md) §4.4) |
| «Cannot execute queries while other unbuffered queries are active» | Falta el `closeCursor()` después del `CALL`, antes del `SELECT @resultado` |
| Un `PUT` a una factura inexistente da 409 en vez de 404 | Falta distinguir los rechazos por su mensaje (`interpretarRechazo`, [3_plan.md](3_plan.md) §4.5) |
| El listado de facturas no muestra el estado | Es la corrección 1 de [4_research.md](4_research.md) D6: el procedimiento de listar no lo devolvía |
| El stock cambia en una factura ya anulada | Es la corrección 2 de D6: falta la guarda en el procedimiento de actualizar |
| La pantalla se ve sin estilos | El router se está tragando los archivos estáticos: falta el `return false` cuando el archivo existe |
| 500 en todos los endpoints | MariaDB apagada o DSN mal apuntado (Docker: host `mariadb`; local: `localhost:13327`) |
| Puerto 8024, 8026, 8102 o 13327 ocupado | Otro proyecto del curso está corriendo — apáguelo (`docker compose down` en ESE proyecto) |
