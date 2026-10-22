# Arquitectura — Facturación (`bdfacturas`), en PHP

> **Qué es este documento.** Cómo está partido el sistema, por qué así, y
> **qué pasa cuando llega una petición**, pieza por pieza.
>
> Los números están contados del repositorio, no estimados.
>
> Versión 1.0 · 21 de octubre de 2026.

---

## 1. Dos procesos separados, no uno

```
  Navegador
     │  http://localhost:8088
     ▼
  FRONT (PHP)  ──── HTTP + JSON ────▶  API (PHP)  ──── PDO ────▶  Motor
  :8088                                :8090                      MariaDB
                                                                  PostgreSQL
                                                                  SQL Server
```

**El front NO toca la base de datos.** No hay un solo `new PDO` en
`front_php/`, y no puede haberlo: habla con la API por HTTP, como hablaría
cualquier otro cliente.

> **Y aquí la tentación es mayor que en los otros repositorios del curso,
> porque los dos lados son PHP.** Bastaría un
> `require '../api_facturas/modelos/Cliente.php'` para usar las clases de la
> API en el front. Funcionaría — y sería el final de la separación: los dos
> dejarían de ser procesos independientes y renombrar un método dentro de la
> API rompería el front **sin que nadie tocara el contrato**. Lo único que
> comparten es el JSON.

---

## 2. Las capas de la API, contadas

| Capa | Archivos | Líneas | Qué sabe |
|---|---|---|---|
| `index.php` | 1 | 867 | El enrutador: método, ruta y guardia |
| `controladores/` | 14 | 3.667 | **HTTP**: valida la forma, arma el sobre, decide el código |
| `servicios/` | 29 | 2.500 | **Negocio**: las reglas, y el ensamblador |
| `repositorios/` | 60 | 8.407 | **La base de datos**: SQL y PDO, tres por recurso |
| `modelos/` | 10 | 916 | Las fichas del dominio |
| `autorizacion/` | 2 | 324 | El JWT y la guardia |
| `excepciones/` | 2 | 44 | Las dos excepciones de negocio |
| `pruebas/` | 2 | 470 | Capas sin base de datos · las diez consultas en tres motores |

**Por qué los repositorios pesan tanto**: son **tres por recurso**, uno por
motor. Esa es la decisión que hace que cambiar de motor no toque nada más.

El front:

| | Líneas | Qué hace |
|---|---|---|
| `index.php` | 1.534 | El enrutador del front: una pantalla por dirección |
| `cliente_api.php` | 872 | **Lo único que habla HTTP** |
| `vistas/` (17) | 3.186 | El HTML, una vista por entidad |

---

## 3. La regla de dependencias

```
  index.php  ──▶  controlador  ──▶  IServicio  ──▶  IRepositorio
                                         │                │
                                    (servicio)      (3 implementaciones)
                                                           │
                                                      PDO · el motor
```

**Las flechas van en un solo sentido, y cruzan por interfaces.** Lo que eso
compra, dicho en concreto:

| Afirmación | Cómo se comprueba |
|---|---|
| El controlador **no conoce** la base de datos | `PDO` no aparece en `controladores/` salvo en un comentario |
| El servicio **no conoce** HTTP | no hay `http_response_code` ni `header()` en `servicios/` |
| El repositorio **no conoce** los códigos de estado | traduce el error del motor a una **excepción de negocio**, y ahí termina su trabajo |
| El servicio funciona **sin base de datos** | `pruebas/prueba_capas.php` lo corre con repositorios falsos |

---

## 4. El ensamblador: dónde se escoge el motor

Es el único archivo que nombra clases concretas. **17 `match`**, uno por
servicio:

```php
function crearServicioProducto(): IServicioProducto
{
    [$dsn, $usuario, $clave] = datosDeConexion();

    $repositorio = match (motorActivo()) {
        'postgres'  => new RepositorioProductoPostgres($dsn, $usuario, $clave),
        'sqlserver' => new RepositorioProductoSqlServer($dsn, $usuario, $clave),
        default     => new RepositorioProductoMariaDB($dsn, $usuario, $clave),
    };

    return new ServicioProducto($repositorio);
}
```

> **Es inyección de dependencias a mano.** En .NET esto lo hace el contenedor
> del framework y en FastAPI el sistema de dependencias; aquí se ve el
> alambre. Tiene una ventaja para aprender: **no hay magia que explicar**,
> hay una función que se puede leer entera.

---

## 5. El viaje de una petición, paso por paso

`POST /api/factura` con su detalle:

| # | Dónde | Qué pasa |
|---|---|---|
| 1 | `index.php` | Lee método y ruta. **La guardia**: la ruta no está en la lista blanca → `exigirPermiso('/factura')` |
| 2 | `autorizacion/guardia.php` | Lee la cabecera, valida la firma del token, y pregunta `verificar_acceso_ruta` **a la base de datos**. 401 o 403 cortan aquí |
| 3 | `index.php` | Arma el controlador con `crearServicioFactura()` |
| 4 | `ControladorFactura::crear()` | **Valida la forma**: cliente, vendedor y un detalle con al menos un renglón → 422 si falta algo |
| 5 | `ServicioFactura` | Las reglas de negocio; arma el JSON del detalle |
| 6 | `RepositorioFacturaMariaDB` | `CALL sp_insertar_factura_y_productosporfactura(...)` con marcadores `?` |
| 7 | **El motor** | El procedimiento abre transacción; los disparadores calculan subtotales, descuentan stock y recalculan el total; `COMMIT` |
| 8 | De vuelta | El repositorio traduce un rechazo a excepción de negocio; el controlador la convierte en 409 o 404; si todo salió bien, 200 con la factura **como quedó guardada** |

> **Fíjese en el paso 7:** la mitad de las columnas no las escribió quien
> llamó a la API. El total y los subtotales los puso la base de datos, y eso
> es deliberado — el dato que se puede calcular mal se calcula en un solo
> sitio.

---

## 6. La autorización, aparte de las tres capas

`autorizacion/` no es repositorio —no habla con la base de datos como dueña
de un recurso—, no es servicio —no tiene regla de negocio— y no es
controlador —no atiende una ruta—. **Es la aduana**: mira la cabecera,
pregunta por el permiso y deja pasar o responde.

| Archivo | Qué hace |
|---|---|
| `jwt.php` | Firma y lee el token: base64url, HMAC-SHA256, `hash_equals` y el vencimiento |
| `guardia.php` | `exigirSesion()` y `exigirPermiso()`, que responden y **cortan la ejecución** |

---

## 7. Qué hay en cada contenedor

| Servicio | Imagen | Puerto | Para qué |
|---|---|---|---|
| `api-facturas` | `php:8.3-cli` | 8090 | La API |
| `front-php` | `php:8.3-cli` | 8088 | El front |
| `mariadb` | `mariadb:11` | 13329 | Motor por defecto |
| `postgres` | `postgres:16-alpine` | 15465 | Segundo motor |
| `sqlserver` | `mssql/server:2022` | 11474 | Tercer motor |
| `sqlserver-init` | la misma | — | Corre **una vez** el script: esa imagen no ejecuta sola los scripts de inicio, como sí hacen MariaDB y PostgreSQL |
| `phpmyadmin` | `phpmyadmin` | 8104 | Mirar MariaDB desde el navegador |

> **Los dos servidores de PHP son el embebido** (`php -S`), no Apache ni
> Nginx. Para un proyecto de aula alcanza y evita una pieza más que explicar;
> para producción, no: ahí va PHP-FPM detrás de un servidor de verdad.

---

## 8. Dónde seguir

| Qué | Dónde |
|---|---|
| El esquema y las diferencias entre motores | [`DISENO_BD.md`](DISENO_BD.md) |
| Qué responde el sistema ante cada fallo | [`POLITICA_DE_ERRORES.md`](POLITICA_DE_ERRORES.md) |
| Las preguntas de la sustentación | [`SUSTENTACION_DEL_CODIGO.md`](SUSTENTACION_DEL_CODIGO.md) |
| El viaje de una petición, con más detalle | `docs/FLUJO_DE_UNA_PETICION.md` |
| Capas, SOLID y patrones | `docs/SOLID_CAPAS_PATRONES.md` |
