# Quickstart — Versión 1: producto de punta a punta

> **Versión 1** · Validación rápida de la v1 ya construida. Si aún no hay
> nada, empiece por [8_tasks.md](8_tasks.md).

---

## 1. Arrancar TODO (un solo comando)

```powershell
# desde la raíz del proyecto (terminal integrada de VS Code):
docker compose up -d --build
```

Eso deja corriendo tres cosas:

| | Dónde | Qué es |
|---|---|---|
| **La pantalla** | <http://localhost:8020> | Por donde se usa el sistema |
| La API | <http://localhost:8022> | JSON; el front habla con ella |
| phpMyAdmin | <http://localhost:8101> | Para mirar la base por dentro |

No hay venv ni dependencias que instalar: **PHP no necesita nada más**.
Empiece por el 8020 — que es como lo va a ver alguien que no programó esto.

Alternativa para desarrollo fase a fase — la API local (requiere PHP 8.3 con
`pdo_mysql`) contra la BD del compose:

```powershell
docker compose up -d mariadb
$env:DB_DSN = "mysql:host=localhost;port=13326;dbname=bdfacturas_mariadb_local"
$env:DB_USUARIO = "paradigmas"
$env:DB_CLAVE = "paradigmas123"
cd api_facturas
php -S localhost:8022 index.php
```

## 2. Smoke test de la API (criterios 1 a 6)

```powershell
# 1. Diagnóstico (y de paso: edite el mensaje en index.php, guarde y
#    refresque — el cambio aparece SIN reiniciar nada: PHP reinterpreta)
curl http://localhost:8022/

# 2. Listar — los 8 productos, y el query string en acción
curl http://localhost:8022/api/producto                      # total: 8
curl "http://localhost:8022/api/producto?limite=3"           # total: 3

# 3. Obtener uno / inexistente (parámetro de ruta)
curl http://localhost:8022/api/producto/PR001    # 200 Laptop Lenovo
curl -i http://localhost:8022/api/producto/PR999 # 404

# 4. Ciclo con los 5 verbos
curl -X POST http://localhost:8022/api/producto -H "Content-Type: application/json" `
     -d '{\"codigo\":\"PR009\",\"nombre\":\"Webcam Logitech\",\"stock\":5,\"valorunitario\":120000}'
curl -X PUT  http://localhost:8022/api/producto/PR009 -H "Content-Type: application/json" `
     -d '{\"nombre\":\"Webcam Logitech C920\",\"stock\":10,\"valorunitario\":150000}'   # reemplazo COMPLETO
curl -X PATCH http://localhost:8022/api/producto/PR009 -H "Content-Type: application/json" `
     -d '{\"stock\":7}'                                       # parcial: solo el stock
curl http://localhost:8022/api/producto/PR009    # nombre C920, stock = 7
curl -X DELETE http://localhost:8022/api/producto/PR009
curl -i -X DELETE http://localhost:8022/api/producto/PR009   # 404 (ya no existe)

# 4b. La diferencia PUT vs PATCH — el MISMO body, distinto veredicto
curl -i -X PUT   http://localhost:8022/api/producto/PR001 -H "Content-Type: application/json" `
     -d '{\"stock\":99}'    # 422: a PUT le faltan nombre y valorunitario
curl -i -X PATCH http://localhost:8022/api/producto/PR001 -H "Content-Type: application/json" `
     -d '{\"stock\":17}'    # 200: PATCH acepta el subconjunto

# 5. La validación como frontera — nunca llega a la BD
curl -i -X POST http://localhost:8022/api/producto -H "Content-Type: application/json" `
     -d '{\"codigo\":\"PRX\",\"nombre\":\"Test\",\"stock\":-5,\"valorunitario\":100}'   # 422 con errores[]
curl -i -X POST http://localhost:8022/api/producto -H "Content-Type: application/json" `
     -d '{\"codigo\":\"PR001\",\"nombre\":\"Dup\",\"stock\":1,\"valorunitario\":1}' # 500 (PK duplicada)

# extra: PATCH body vacío y límite inválido (reglas de negocio → 400)
curl -i -X PATCH http://localhost:8022/api/producto/PR001 -H "Content-Type: application/json" -d '{}'
curl -i "http://localhost:8022/api/producto?limite=0"
```

**6. Prueba de capas** (sin MariaDB): un script que instancie
`ServicioProducto` con un repositorio falso en memoria que implemente
`IRepositorioProducto` y verifique crear/listar/eliminar — si funciona, las
capas quedaron bien cortadas ([8_tasks.md](8_tasks.md) Fase 4). Se corre con
`php` a secas (dentro o fuera del contenedor):

```powershell
docker compose exec api-facturas php pruebas/prueba_capas.php
```

## 3. La pantalla (criterios 7 a 10)

### 3.1 Automático

```powershell
python pruebas_humo/humo_front.py
```

Recorre **todo**: que cada pantalla responda por su dirección, que muestre lo
que la API devolvió, que no hable en jerga, el ciclo completo con los dos
botones de guardar, y la prueba de apagar la API. Termina en verde o dice
exactamente qué falló.

> Que un guion pueda hacer el recorrido completo es una ventaja de haber
> hecho el front con formularios corrientes: cada botón manda un POST que se
> puede enviar desde fuera del navegador. Con un front de los que llevan el
> estado en el navegador, esta parte habría que hacerla a mano.

### 3.2 A mano, que es lo que un guion no ve

Abra <http://localhost:8020> y haga esto **leyendo lo que dice la pantalla**.
Lo que se juzga aquí no es si funciona —eso ya lo dijo el guion— sino si se
entiende:

1. **El menú.** Desde el inicio se llega a Productos con un clic, y la
   dirección que queda en la barra es `/productos`: se puede guardar como
   marcador y mandar por correo.
2. **El listado.** Están los 8 productos con sus cuatro columnas.
3. **Agregar.** Cree `PR100`. Vuelve al listado con el aviso verde y la ficha
   nueva.
4. **El formulario a medio llenar, con cada botón.** Entre a editar `PR100` y
   **borre el nombre**:
   - con **«Guardar la ficha completa»** → lo rechaza, y el motivo está en
     español;
   - con **«Guardar solo lo que cambié»**, dejando solo el stock → guarda, y
     **el nombre no se borró**.

   Ése es el corazón de la versión: el mismo formulario, dos resultados, y la
   diferencia está en qué se envía.
5. **El código no se deja cambiar** al editar. Es la identidad de la ficha.
6. **Eliminar** `PR100`: pregunta antes, y después desaparece.
7. **La prueba de los dos procesos.** Con la base de datos encendida, apague
   solo la API:

   ```powershell
   docker compose stop api-facturas
   ```

   Refresque `/productos`. La pantalla **sigue en pie**, con su menú, y con un
   aviso de que el servicio no está disponible — **y sin una sola fila**. Los
   datos siguen ahí, en MariaDB, a un puerto de distancia: si aparecieran,
   sería porque el front llegó a la base por su cuenta. No aparecen.

   ```powershell
   docker compose start api-facturas
   ```

   Espere unos segundos, refresque, y los datos vuelven sin tocar nada.

## 4. Si algo falla

| Síntoma | Causa probable |
|---|---|
| `could not find driver` | Falta la extensión `pdo_mysql` (en Docker ya viene; local: habilítela en `php.ini`) |
| 500 en todos los endpoints | MariaDB apagada o DSN mal apuntado (Docker: host `mariadb`; local: `localhost:13326`) |
| 204 donde esperaba los 8 productos | La tabla está vacía: `docker compose down -v` y `up -d` recargan `db/init.sql` |
| El `LIMIT` falla con error de sintaxis | El `:limite` se enlazó como string — debe ser `bindValue(..., PDO::PARAM_INT)` ([3_plan.md](3_plan.md) §4.4) |
| PATCH con el mismo valor responde 404 | Falta `PDO::MYSQL_ATTR_FOUND_ROWS => true` en la conexión: sin él, MariaDB reporta 0 filas cuando el valor no cambió ([3_plan.md](3_plan.md) §4.4) |
| Edité un `.php` y no veo el cambio | Caché del navegador: fuerce con Ctrl+F5 (PHP siempre reinterpreta; el navegador a veces no) |
| Puerto 8020, 8022 o 13326 ocupado | Otro proyecto del curso está corriendo — apáguelo (`docker compose down` en ESE proyecto) |
| La pantalla abre pero dice «El servicio no está disponible» | La API está caída, o `URL_API` apunta mal. Dentro de Docker debe ser `http://api-facturas:8022` — el **nombre del servicio**, nunca `localhost` (allí `localhost` es el contenedor del front) |
| La pantalla se ve sin estilos (letra con serifas, todo pegado a la izquierda) | El router se está tragando los archivos estáticos. Con `php -S … index.php`, el router se ejecuta para **todas** las peticiones, incluidas las de `/publico/`: hay que **devolver `false`** cuando el archivo existe en el disco ([3_plan.md](3_plan.md) §4.7). Compruébelo abriendo <http://localhost:8020/publico/estilos.css>: si sale una página HTML en vez del CSS, es esto |
| El aviso verde sale dos veces, o refrescar vuelve a guardar | Falta la redirección después de guardar (`redirigir_con`): sin ella, refrescar reenvía el formulario ([4_research.md](4_research.md) D12) |
