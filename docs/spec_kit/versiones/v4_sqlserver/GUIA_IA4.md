# Cómo construir la versión 4 con IA — por chat o con un IDE agéntico

> Guía para trabajar la última versión de la ruta con ayuda de IA, por
> cualquiera de los dos caminos. La clave del método es la misma: la IA no
> inventa — **sigue el spec kit**.

---

## 0. Esta versión se parece mucho a la anterior, y ahí está el riesgo

La v3 agregó un motor y la v4 agrega otro. El procedimiento es el mismo, las
fases de `8_tasks.md` son casi las mismas, y el criterio 13 vuelve a ser el
que manda.

**Y por eso hay que estar más atento, no menos.** Cuando algo se parece a lo
que ya se hizo, tanto una persona como una IA bajan la guardia — y este motor
es el que menos se parece a los otros dos.

Las tentaciones de la v3 siguen todas vigentes:

1. **`if ($motor === 'sqlserver')` dentro de los repositorios que ya existen.**
2. **Cambiar una interfaz** para acomodar alguna diferencia de SQL Server.
3. **Un ensamblador «inteligente»** que arme el nombre de la clase con texto —
   y ahora con más razón, porque son **dieciocho** combinaciones.

Y aparecen tres nuevas, propias de este motor:

4. **Cambiar los procedimientos almacenados** para que el parámetro de salida
   se deje enlazar. Es lo que la IA va a proponer cuando aparezca
   `SQLSTATE[HY104]: Invalid precision value`, y es acomodar la base a una
   limitación de un driver.
5. **Usar `pdo_dblib`** porque se instala con un comando. Se instala fácil y
   falla justo donde este sistema lo necesita.
6. **Quitar `ATTR_EMULATE_PREPARES` de los tres repositorios** porque SQL
   Server no lo admite. Los otros dos SÍ lo necesitan: la opción se quita
   **solo** del repositorio nuevo.

## 1. Los dos caminos, en una tabla

| | **Camino A: chat web** | **Camino B: IDE agéntico** |
|---|---|---|
| ¿Cómo conoce la spec? | Usted le **sube los 9 archivos** | El agente **lee `docs/spec_kit/`** |
| ¿Quién crea la estructura? | **USTED**, con los comandos de §3 | El agente |
| Riesgo típico de ESTA versión | Aceptar un cambio a los procedimientos para «arreglar» el driver | Refactorizar los tres repositorios «para unificar» |

## 2. Qué subirle

| # | Archivo | Papel |
|---|---|---|
| 1 | `docs/spec_kit/1_constitution.md` | Las reglas permanentes |
| 2 | `.../v4_sqlserver/2_spec.md` | QUÉ construir y los 13 criterios |
| 3 | `.../v4_sqlserver/3_plan.md` | **La tabla de los tres motores lado a lado** |
| 4 | `.../v4_sqlserver/4_research.md` | Las decisiones, con lo que se descartó y por qué |
| 5 | `.../v4_sqlserver/5_data_model.md` | La misma base, en tres dialectos |
| 6 | `.../v4_sqlserver/6_contracts.md` | Los contratos — que siguen sin moverse |
| 7 | `.../v4_sqlserver/7_quickstart.md` | El smoke test, con los tres motores |
| 8 | `.../v4_sqlserver/8_tasks.md` | Las fases, en orden |
| 9 | `.../v3_segundo_motor/2_spec.md` | La spec de la v3, para saber qué NO romper |

Y los artefactos **dados**: los tres scripts de base (uno por motor), el
`init.sh` de SQL Server y los dos de Bootstrap.

---

## 3. Prepare SU proyecto (ANTES de abrir el chat)

### 3.1 La carpeta y las subcarpetas

```powershell
mkdir docs\spec_kit\versiones\v4_sqlserver, db\mariadb, db\postgres, db\sqlserver, api_facturas, api_facturas\controladores, api_facturas\excepciones, api_facturas\modelos, api_facturas\pruebas, api_facturas\repositorios, api_facturas\servicios, front_php, front_php\publico, front_php\vistas, pruebas_humo
```

### 3.2 Los archivos VACÍOS

```powershell
New-Item .gitattributes, .gitignore, api_facturas\Dockerfile, api_facturas\controladores\ControladorCliente.php, api_facturas\controladores\ControladorEmpresa.php, api_facturas\controladores\ControladorFactura.php, api_facturas\controladores\ControladorPersona.php, api_facturas\controladores\ControladorProducto.php, api_facturas\controladores\ControladorVendedor.php, api_facturas\excepciones\ConflictoDeIntegridadExcepcion.php, api_facturas\excepciones\NoEncontradoExcepcion.php, api_facturas\index.php, api_facturas\modelos\Cliente.php, api_facturas\modelos\Empresa.php, api_facturas\modelos\Factura.php, api_facturas\modelos\LineaFactura.php, api_facturas\modelos\Persona.php, api_facturas\modelos\Producto.php, api_facturas\modelos\Vendedor.php, api_facturas\pruebas\prueba_capas.php, api_facturas\repositorios\IRepositorioCliente.php, api_facturas\repositorios\IRepositorioEmpresa.php, api_facturas\repositorios\IRepositorioFactura.php, api_facturas\repositorios\IRepositorioPersona.php, api_facturas\repositorios\IRepositorioProducto.php, api_facturas\repositorios\IRepositorioVendedor.php, api_facturas\repositorios\RepositorioClienteMariaDB.php, api_facturas\repositorios\RepositorioClientePostgres.php, api_facturas\repositorios\RepositorioClienteSqlServer.php, api_facturas\repositorios\RepositorioEmpresaMariaDB.php, api_facturas\repositorios\RepositorioEmpresaPostgres.php, api_facturas\repositorios\RepositorioEmpresaSqlServer.php, api_facturas\repositorios\RepositorioFacturaMariaDB.php, api_facturas\repositorios\RepositorioFacturaPostgres.php, api_facturas\repositorios\RepositorioFacturaSqlServer.php, api_facturas\repositorios\RepositorioPersonaMariaDB.php, api_facturas\repositorios\RepositorioPersonaPostgres.php, api_facturas\repositorios\RepositorioPersonaSqlServer.php, api_facturas\repositorios\RepositorioProductoMariaDB.php, api_facturas\repositorios\RepositorioProductoPostgres.php, api_facturas\repositorios\RepositorioProductoSqlServer.php, api_facturas\repositorios\RepositorioVendedorMariaDB.php, api_facturas\repositorios\RepositorioVendedorPostgres.php, api_facturas\repositorios\RepositorioVendedorSqlServer.php, api_facturas\repositorios\conflictos.php, api_facturas\repositorios\errores_de_integridad_mariadb.php, api_facturas\repositorios\errores_de_integridad_postgres.php, api_facturas\repositorios\errores_de_integridad_sqlserver.php, api_facturas\servicios\IServicioCliente.php, api_facturas\servicios\IServicioEmpresa.php, api_facturas\servicios\IServicioFactura.php, api_facturas\servicios\IServicioPersona.php, api_facturas\servicios\IServicioProducto.php, api_facturas\servicios\IServicioVendedor.php, api_facturas\servicios\ServicioCliente.php, api_facturas\servicios\ServicioEmpresa.php, api_facturas\servicios\ServicioFactura.php, api_facturas\servicios\ServicioPersona.php, api_facturas\servicios\ServicioProducto.php, api_facturas\servicios\ServicioVendedor.php, api_facturas\servicios\ensamblador.php, docker-compose.yml, front_php\Dockerfile, front_php\cliente_api.php, front_php\index.php, front_php\publico\estilos.css, front_php\vistas\clientes_formulario.php, front_php\vistas\clientes_lista.php, front_php\vistas\empresas_formulario.php, front_php\vistas\empresas_lista.php, front_php\vistas\facturas_detalle.php, front_php\vistas\facturas_formulario.php, front_php\vistas\facturas_lista.php, front_php\vistas\inicio.php, front_php\vistas\no_encontrada.php, front_php\vistas\personas_formulario.php, front_php\vistas\personas_lista.php, front_php\vistas\plantilla.php, front_php\vistas\productos_formulario.php, front_php\vistas\productos_lista.php, front_php\vistas\vendedores_formulario.php, front_php\vistas\vendedores_lista.php, pruebas_humo\humo_front.py, pruebas_humo\humo_los_tres_motores.py
```

> **Cuente los repositorios: son dieciocho**, seis por motor, más los cuatro
> archivos de errores. Si alguien propone reducirlos con un `if` adentro, lea
> otra vez §0.

### 3.3 Los archivos que vienen DADOS

| Del clon del curso | A su proyecto |
|---|---|
| `db\mariadb\init.sql` | `db\mariadb\` |
| `db\mariadb\init_phpmyadmin.sql` | `db\mariadb\` |
| `db\postgres\init.sql` | `db\postgres\` |
| `db\sqlserver\bdfacturas.sql` | `db\sqlserver\` |
| `db\sqlserver\init.sh` | `db\sqlserver\` |
| `front_php\publico\bootstrap.min.css` | `front_php\publico\` |
| `front_php\publico\bootstrap.bundle.min.js` | `front_php\publico\` |
| `docs\spec_kit\1_constitution.md` | `docs\spec_kit\` |
| Los `.md` de `docs\spec_kit\versiones\v4_sqlserver\` | la misma ruta |

### 3.4 Compruebe antes de empezar

- [ ] Los **tres** scripts de base tienen contenido, y `db\sqlserver\init.sh`
      también.
- [ ] `docs\spec_kit\versiones\v4_sqlserver\` tiene **8 archivos**.
- [ ] Tiene a mano **el código de la v3**: el criterio 13 se comprueba
      comparando contra él.
- [ ] **Y tiene internet.** El driver de SQL Server se trae del repositorio de
      Microsoft; es la única dependencia externa de todo el proyecto.

---

## 4. Camino A — El prompt del chat (cópielo tal cual)

```
Actúa como mi asistente de programación para construir la VERSIÓN 4 de un
proyecto universitario. Te adjunto 9 documentos.

El proyecto es PHP 8.3 puro (sin frameworks ni Composer). Ya tiene seis
recursos funcionando contra MariaDB y PostgreSQL, con su front. Esta versión
NO agrega ninguna funcionalidad: agrega SQL Server como tercer motor.

LO PRIMERO, PORQUE CAMBIA TODO:

El criterio de aceptación más importante (el 13 de 2_spec.md) no dice qué
construir: dice QUÉ NO SE PUEDE HABER TOCADO. Al terminar, un diff contra la
versión 3 no puede mostrar NINGÚN controlador, NINGÚN servicio ni NINGUNA
interfaz modificados.

REGLAS DE TRABAJO (no negociables):

1. La especificación manda. Esta versión no tiene recursos, endpoints ni
   pantallas nuevas.
2. UNA CLASE POR MOTOR. Los seis repositorios nuevos se llaman
   Repositorio{Recurso}SqlServer y cumplen LAS MISMAS interfaces.
   PROHIBIDO meter `if ($motor === 'sqlserver')` en los repositorios que ya
   existen.
3. LAS INTERFACES NO SE TOCAN. Si crees que alguna debe cambiar para que SQL
   Server entre, PARA y dímelo.
4. NADA GENÉRICO (Artículo 10). El ensamblador tiene DIECIOCHO combinaciones
   escritas a mano. PROHIBIDO armar nombres de clase con texto.
5. NO SE TOCAN LOS PROCEDIMIENTOS ALMACENADOS para acomodar el driver.
   Cuando aparezca `SQLSTATE[HY104]: Invalid precision value` al enlazar el
   parámetro de salida, la solución NO es cambiar el NVARCHAR(MAX) a un
   tamaño fijo: es declarar la variable dentro del propio bloque de T-SQL y
   pedirla con un SELECT (está en 3_plan.md §4.3). Y hace falta
   `SET NOCOUNT ON` al principio del bloque.
6. EL DRIVER ES EL DE MICROSOFT (pdo_sqlsrv), no pdo_dblib. Sí, son diez
   líneas de Dockerfile en vez de dos; está decidido en 4_research.md D1.
7. `ATTR_EMULATE_PREPARES` se quita SOLO del repositorio nuevo: el driver de
   SQL Server no lo admite, pero los otros dos motores SÍ lo necesitan.
8. LOS MENSAJES AL USUARIO NO SE TOCAN: conflictos.php se queda como está.
   Solo se agrega un traductor más que decide cuál frase corresponde.
9. Sigue 8_tasks.md FASE POR FASE. En cada fase: explicas en 3-5 líneas,
   entregas los archivos DE A UNO con ruta y contenido completo, esperas mi
   "listo", y al cerrar me das el comando de verificación.
   NOTA: la estructura de carpetas y los archivos vacíos YA EXISTEN.
10. Ojo con lo que cambia en SQL Server, que está en la tabla de 3_plan.md
   §4.2: no existe LIMIT (es OFFSET/FETCH, y EXIGE ORDER BY), la llave
   generada se lee con OUTPUT INSERTED, el usuario es `sa` con una clave de
   política distinta, y la fecha trae siete decimales que hay que recortar.
11. Todo en español, PHP 8.3 con declare(strict_types=1).
12. Yo trabajo en Windows con VS Code (PowerShell) y Docker Desktop.
13. En mi máquina TAMBIÉN corre el proyecto del curso. MI proyecto publica
   los puertos con +100: front "8188:8088", API "8190:8090", phpMyAdmin
   "8204:80", MariaDB "13429:3306", PostgreSQL "15565:5432" y SQL Server
   "11574:1433". Y el docker-compose.yml empieza con
   `name: mi_v4_tres_motores`. Usa localhost:8188 y localhost:8190.

Al final, la versión 4 está TERMINADA solo cuando pasan los 13 criterios,
INCLUIDO el 13 — que se comprueba con un diff, no leyendo.

Empieza: resume en máximo 10 líneas qué vamos a construir y qué NO se puede
tocar. Luego arranca con la Fase 0.
```

### 4.1 El método de la conversación

1. **Vigile las seis tentaciones de §0.** Las tres primeras vienen de la v3;
   las tres últimas son de este motor y son más traicioneras, porque las tres
   aparecen como respuesta a un error real que usted le va a pegar.
2. **Cuando aparezca `Invalid precision value`, no acepte la primera
   solución.** Es el momento exacto en que una IA propone cambiar los
   procedimientos. La respuesta correcta está en el plan.
3. **Abra los tres repositorios de una misma entidad, lado a lado.** Deben
   cumplir la misma interfaz y diferir solo en lo que la tabla de §4.2 dice.
4. **Al terminar, corra el `diff`** y compárelo con el criterio 13.b.

---

## 5. Camino B — El prompt para el agente

### 5.1 Preparación

Copie a su carpeta los 9 documentos, los tres scripts de base, el `init.sh`,
los dos de Bootstrap **y el código completo de la v3**.

### 5.2 El prompt

```
Construye la VERSIÓN 4 de este proyecto, partiendo de la versión 3 que ya
está en esta carpeta y funciona contra MariaDB y PostgreSQL.

Primero lee, bajo docs/spec_kit/: 1_constitution.md y, de
versiones/v4_sqlserver/, los archivos 2_spec, 3_plan, 4_research,
5_data_model, 6_contracts, 7_quickstart y 8_tasks. Lee también
versiones/v3_segundo_motor/2_spec.md. Después resume en máximo 10 líneas qué
vas a construir Y QUÉ NO PUEDES TOCAR, y espera mi confirmación.

docs/spec_kit/ es solo lectura.

REGLAS (no negociables):

1. Esta versión NO agrega funcionalidad: agrega un motor.
2. EL CRITERIO 13 MANDA: al terminar, un diff contra la v3 no puede mostrar
   ningún controlador, ningún servicio ni ninguna interfaz modificados.
   Antes de tocar cualquier archivo que no sea un repositorio nuevo, el
   ensamblador, el compose, los Dockerfile o la plantilla del front,
   pregúntame.
3. Una clase por motor. Prohibido `if ($motor === …)` en los repositorios
   existentes.
4. Las interfaces no se tocan.
5. Los procedimientos almacenados no se tocan para acomodar el driver. Ante
   `SQLSTATE[HY104]`, la solución está en 3_plan.md §4.3.
6. El driver es pdo_sqlsrv (Microsoft), no pdo_dblib.
7. ATTR_EMULATE_PREPARES se quita solo del repositorio nuevo.
8. conflictos.php no se toca: las frases del usuario son las mismas.
9. Sigue 8_tasks.md fase por fase; ejecuta cada verificación y muéstrame el
   resultado real.
10. Todo en español, PHP 8.3 con declare(strict_types=1).
11. Al final: corre `python pruebas_humo/humo_los_tres_motores.py` y muéstrame
   los tres en verde; corre `docker compose down -v && docker compose up -d
   --build` y muéstrame que el sistema arranca desde cero de una sola vez; y
   corre el diff contra la v3 con su lista de archivos.
```

### 5.3 El método de supervisión

1. **Revise cada diff preguntándose si el agente tenía derecho a tocar ese
   archivo.**
2. **Exija la evidencia**, sobre todo la del arranque desde cero: es la que
   se salta con más facilidad y la que más se rompe.
3. **El recorrido a mano lo hace usted** ([7_quickstart.md](7_quickstart.md)
   §6): usar el sistema con los tres motores y comprobar que se siente igual.

---

## 6. Por qué funciona, y qué queda cuando la ruta termina

Cuatro versiones, cuatro guías, y el método fue siempre el mismo: una
constitución, una spec, un plan, unas tareas, unos criterios verificables.

Lo que cambió fue **qué clase de cosa dice cada especificación**:

| | La spec decía… |
|---|---|
| v1 | qué construir, desde cero |
| v2 | qué construir, **sin romper lo anterior** |
| v3 | qué construir, y **qué no se puede tocar** |
| v4 | lo mismo que la v3 — **y que siga siendo cierto con un motor más** |

Esa progresión no es de PHP ni de bases de datos. Es lo que hace que un
sistema aguante que lo sigan tocando. Y es lo único de este curso que va a
servir igual dentro de diez años, cuando el lenguaje sea otro.
