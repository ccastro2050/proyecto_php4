# Cómo construir la versión 3 con IA — por chat o con un IDE agéntico

> Guía para trabajar la versión en curso con ayuda de IA por **cualquiera de
> los dos caminos**: un chat web o un IDE agéntico. La clave del método es la
> misma en ambos: la IA no inventa — **sigue el spec kit**.

---

## 0. Esta versión se dirige distinto, y conviene saberlo antes de empezar

Las versiones anteriores se le pedían a una IA diciendo **qué construir**.
Ésta hay que pedirla diciendo también **qué NO tocar**, y ahí está toda la
dificultad.

Una IA a la que se le dice «haz que este sistema funcione también con
PostgreSQL» va a hacer, con toda naturalidad, alguna de estas tres cosas:

1. **Meter `if ($motor === 'postgres')` en los repositorios que ya existen.**
   Funciona. Y destruye la versión: ya no habría forma de mostrar con un
   `diff` qué se tocó, porque se habría tocado todo.
2. **Cambiar una interfaz** para acomodar alguna diferencia de PostgreSQL. Si
   una interfaz tiene que cambiar, estaba describiendo a MariaDB y no al
   negocio — y el sitio para arreglarlo es la v2, no ésta.
3. **Escribir un ensamblador «inteligente»** que arme el nombre de la clase
   con texto: `"Repositorio{$recurso}{$motor}"`. Doce clases en cuatro
   líneas, y el error sale en producción.

Las tres parecen buenas ideas. Las reglas del prompt las prohíben por su
nombre, y usted tiene que estar atento a que aparezcan igual.

**El criterio de aceptación 13 es el que manda en esta versión**, y no se
juzga leyendo: se corre un `diff` contra la v2.

## 1. Los dos caminos, en una tabla

| | **Camino A: chat web** | **Camino B: IDE agéntico** |
|---|---|---|
| ¿Cómo conoce la spec? | Usted le **sube los 9 archivos** | El agente **lee `docs/spec_kit/`** |
| ¿Quién crea la estructura? | **USTED**, con los comandos de §3 | El agente |
| ¿Quién ejecuta los comandos? | Usted, y pega la salida | El agente (pidiendo permiso) |
| Riesgo típico de ESTA versión | La IA propone el `if` del punto 1 y usted lo pega sin verlo | El agente refactoriza «de paso» archivos que no debía tocar |

## 2. Qué subirle

| # | Archivo | Papel |
|---|---|---|
| 1 | `docs/spec_kit/1_constitution.md` | Las reglas permanentes |
| 2 | `.../v3_segundo_motor/2_spec.md` | QUÉ construir y los 13 criterios — **incluido el 13** |
| 3 | `.../v3_segundo_motor/3_plan.md` | La fábrica y **la tabla de las doce diferencias** |
| 4 | `.../v3_segundo_motor/4_research.md` | Las decisiones y sus alternativas descartadas |
| 5 | `.../v3_segundo_motor/5_data_model.md` | La misma base en dos dialectos |
| 6 | `.../v3_segundo_motor/6_contracts.md` | Los contratos — que **no cambian** |
| 7 | `.../v3_segundo_motor/7_quickstart.md` | El smoke test, con los dos motores |
| 8 | `.../v3_segundo_motor/8_tasks.md` | Las fases, en orden |
| 9 | `.../v2_mas_tablas/2_spec.md` | La spec de la v2, para saber qué NO romper |

Y los **artefactos que no se suben y la IA no genera**: los dos `init.sql`
(uno por motor) y los dos archivos de Bootstrap. Se copian.

---

## 3. Prepare SU proyecto (ANTES de abrir el chat)

**Ojo: NO se construye dentro de la carpeta clonada.**

### 3.1 La carpeta y las subcarpetas

```powershell
mkdir docs\spec_kit\versiones\v3_segundo_motor, db\mariadb, db\postgres, api_facturas, api_facturas\controladores, api_facturas\excepciones, api_facturas\modelos, api_facturas\pruebas, api_facturas\repositorios, api_facturas\servicios, front_php, front_php\publico, front_php\vistas, pruebas_humo
```

### 3.2 Los archivos VACÍOS

```powershell
New-Item .gitattributes, .gitignore, api_facturas\Dockerfile, api_facturas\controladores\ControladorCliente.php, api_facturas\controladores\ControladorEmpresa.php, api_facturas\controladores\ControladorFactura.php, api_facturas\controladores\ControladorPersona.php, api_facturas\controladores\ControladorProducto.php, api_facturas\controladores\ControladorVendedor.php, api_facturas\excepciones\ConflictoDeIntegridadExcepcion.php, api_facturas\excepciones\NoEncontradoExcepcion.php, api_facturas\index.php, api_facturas\modelos\Cliente.php, api_facturas\modelos\Empresa.php, api_facturas\modelos\Factura.php, api_facturas\modelos\LineaFactura.php, api_facturas\modelos\Persona.php, api_facturas\modelos\Producto.php, api_facturas\modelos\Vendedor.php, api_facturas\pruebas\prueba_capas.php, api_facturas\repositorios\IRepositorioCliente.php, api_facturas\repositorios\IRepositorioEmpresa.php, api_facturas\repositorios\IRepositorioFactura.php, api_facturas\repositorios\IRepositorioPersona.php, api_facturas\repositorios\IRepositorioProducto.php, api_facturas\repositorios\IRepositorioVendedor.php, api_facturas\repositorios\RepositorioClienteMariaDB.php, api_facturas\repositorios\RepositorioClientePostgres.php, api_facturas\repositorios\RepositorioEmpresaMariaDB.php, api_facturas\repositorios\RepositorioEmpresaPostgres.php, api_facturas\repositorios\RepositorioFacturaMariaDB.php, api_facturas\repositorios\RepositorioFacturaPostgres.php, api_facturas\repositorios\RepositorioPersonaMariaDB.php, api_facturas\repositorios\RepositorioPersonaPostgres.php, api_facturas\repositorios\RepositorioProductoMariaDB.php, api_facturas\repositorios\RepositorioProductoPostgres.php, api_facturas\repositorios\RepositorioVendedorMariaDB.php, api_facturas\repositorios\RepositorioVendedorPostgres.php, api_facturas\repositorios\conflictos.php, api_facturas\repositorios\errores_de_integridad_mariadb.php, api_facturas\repositorios\errores_de_integridad_postgres.php, api_facturas\servicios\IServicioCliente.php, api_facturas\servicios\IServicioEmpresa.php, api_facturas\servicios\IServicioFactura.php, api_facturas\servicios\IServicioPersona.php, api_facturas\servicios\IServicioProducto.php, api_facturas\servicios\IServicioVendedor.php, api_facturas\servicios\ServicioCliente.php, api_facturas\servicios\ServicioEmpresa.php, api_facturas\servicios\ServicioFactura.php, api_facturas\servicios\ServicioPersona.php, api_facturas\servicios\ServicioProducto.php, api_facturas\servicios\ServicioVendedor.php, api_facturas\servicios\ensamblador.php, docker-compose.yml, front_php\Dockerfile, front_php\cliente_api.php, front_php\index.php, front_php\publico\estilos.css, front_php\vistas\clientes_formulario.php, front_php\vistas\clientes_lista.php, front_php\vistas\empresas_formulario.php, front_php\vistas\empresas_lista.php, front_php\vistas\facturas_detalle.php, front_php\vistas\facturas_formulario.php, front_php\vistas\facturas_lista.php, front_php\vistas\inicio.php, front_php\vistas\no_encontrada.php, front_php\vistas\personas_formulario.php, front_php\vistas\personas_lista.php, front_php\vistas\plantilla.php, front_php\vistas\productos_formulario.php, front_php\vistas\productos_lista.php, front_php\vistas\vendedores_formulario.php, front_php\vistas\vendedores_lista.php, pruebas_humo\humo_front.py, pruebas_humo\humo_los_dos_motores.py
```

> **Cuente los repositorios: son doce**, seis por motor, más los tres
> archivos de errores. Esa duplicación aparente es la versión entera — y si
> alguien propone reducirla a seis con un `if` adentro, lea otra vez §0.

### 3.3 Los archivos que vienen DADOS

| Del clon del curso | A su proyecto |
|---|---|
| `db\mariadb\init.sql` | `db\mariadb\` |
| `db\mariadb\init_phpmyadmin.sql` | `db\mariadb\` |
| `db\postgres\init.sql` | `db\postgres\` |
| `front_php\publico\bootstrap.min.css` | `front_php\publico\` |
| `front_php\publico\bootstrap.bundle.min.js` | `front_php\publico\` |
| `docs\spec_kit\1_constitution.md` | `docs\spec_kit\` |
| Los `.md` de `docs\spec_kit\versiones\v3_segundo_motor\` | la misma ruta |

### 3.4 Compruebe antes de empezar

- [ ] `db\mariadb\init.sql` y `db\postgres\init.sql` tienen contenido.
- [ ] `docs\spec_kit\versiones\v3_segundo_motor\` tiene **8 archivos**.
- [ ] `front_php\publico\` tiene los dos de Bootstrap.
- [ ] **Y tiene a mano el código de la v2**, porque el criterio 13 se
      comprueba comparando contra él.

---

## 4. Camino A — El prompt del chat (cópielo tal cual)

```
Actúa como mi asistente de programación para construir la VERSIÓN 3 de un
proyecto universitario. Te adjunto 9 documentos.

El proyecto es PHP 8.3 puro (sin frameworks ni Composer). Ya tiene seis
recursos funcionando contra MariaDB, con su front. Esta versión NO agrega
ninguna funcionalidad: agrega PostgreSQL como segundo motor.

LO PRIMERO QUE TIENES QUE ENTENDER, PORQUE CAMBIA TODO:

El criterio de aceptación más importante de esta versión (el 13 de 2_spec.md)
no dice qué construir: dice QUÉ NO SE PUEDE HABER TOCADO. Al terminar, un
`diff` contra la versión 2 no puede mostrar NINGÚN controlador ni NINGUNA
vista modificados. Si para hacer funcionar PostgreSQL hay que tocar uno, la
versión está mal hecha aunque funcione.

REGLAS DE TRABAJO (no negociables):

1. La especificación manda. No agregues NADA que los documentos no pidan.
   Esta versión no tiene recursos nuevos, ni endpoints nuevos, ni pantallas
   nuevas. Si te dan ganas de "mejorar" algo de paso, pregúntame antes.
2. UNA CLASE POR MOTOR. Cada repositorio nuevo se llama
   Repositorio{Recurso}Postgres y cumple LA MISMA interfaz que el de
   MariaDB. PROHIBIDO meter `if ($motor === 'postgres')` dentro de los
   repositorios que ya existen: eso destruye el criterio 13.
3. LAS INTERFACES NO SE TOCAN. Si crees que una interfaz tiene que cambiar
   para que PostgreSQL entre, PARA y dímelo: significa que esa interfaz
   estaba describiendo a MariaDB y no al negocio, y eso se arregla en otra
   versión, no en ésta.
4. NADA GENÉRICO (Artículo 10 de la constitución). El ensamblador tiene
   doce combinaciones escritas a mano. PROHIBIDO armar nombres de clase con
   texto ("Repositorio{$recurso}{$motor}") ni resolver esto con un switch
   sobre una lista: con las clases escritas, PHP verifica los tipos; con
   nombres armados, el error sale en producción.
5. LOS MENSAJES AL USUARIO SE ESCRIBEN UNA SOLA VEZ, en conflictos.php. Los
   traductores por motor solo deciden CUÁL frase corresponde. Si cada motor
   redacta la suya, la misma petición dirá cosas distintas según el motor, y
   eso rompe el contrato.
6. Sigue 8_tasks.md FASE POR FASE, en orden. En cada fase: explicas en 3-5
   líneas qué vamos a hacer, me entregas los archivos DE A UNO (ruta exacta y
   contenido completo, con los comentarios didácticos en español), esperas mi
   "listo", y al cerrar me dices el comando de verificación.
   NOTA: la estructura de carpetas y los archivos vacíos YA EXISTEN.
7. La API no calcula el total, los subtotales ni el stock: los mueve un
   trigger, en LOS DOS motores.
8. Ojo con las diferencias entre motores: están en la tabla de 3_plan.md
   §4.3 y son doce. Las que más se olvidan: PostgreSQL usa el MISMO SQLSTATE
   (23503) para "apunta a algo que no existe" y para "hay filas que dependen
   de esto" —hay que mirar el mensaje—, y devuelve la fecha con
   microsegundos, que hay que recortar para que el contrato sea uniforme.
9. Todo en español, PHP 8.3 con declare(strict_types=1).
10. Yo trabajo en Windows con VS Code (PowerShell) y Docker Desktop.
11. En mi máquina TAMBIÉN corre el proyecto del curso. MI proyecto publica
   los puertos con +100: front "8184:8084", API "8186:8086", phpMyAdmin
   "8203:80", MariaDB "13428:3306" y PostgreSQL "15564:5432" (adentro de los
   contenedores todo igual). Y el docker-compose.yml empieza con
   `name: mi_v3_motores`. Usa localhost:8184 y localhost:8186 en las pruebas.

Al final, la versión 3 está TERMINADA solo cuando pasan los 13 criterios de
2_spec.md, INCLUIDO el 13 — que se comprueba con un diff, no leyendo.

Empieza: resume en máximo 10 líneas qué vamos a construir y, sobre todo, qué
NO se puede tocar. Luego arranca con la Fase 0.
```

### 4.1 El método de la conversación

1. **Vigile las tres tentaciones de §0** en cada respuesta. Son las que una
   IA propone con más naturalidad porque las tres funcionan.
2. **Cuando entregue un repositorio de PostgreSQL, ábralo al lado del de
   MariaDB.** Deben cumplir la misma interfaz y diferir solo en lo que la
   tabla de §4.3 del plan dice.
3. **Si le propone cambiar una interfaz, pare.** No es un detalle: es el
   síntoma de que algo estaba mal cortado antes.
4. **Al terminar, corra el `diff`** y compárelo con el criterio 13.b. Si
   aparece un archivo que el criterio no contempla, no lo borre del criterio:
   averigüe por qué cambió.

---

## 5. Camino B — El prompt para el agente

### 5.1 Preparación

Copie a su carpeta los 9 documentos, los dos `init.sql`, los dos de Bootstrap
**y el código completo de la v2**: el agente parte de ahí, y necesita la v2
para poder comparar.

### 5.2 El prompt

```
Construye la VERSIÓN 3 de este proyecto, partiendo de la versión 2 que ya
está en esta carpeta y funciona contra MariaDB.

Primero lee, bajo docs/spec_kit/: 1_constitution.md y, de
versiones/v3_segundo_motor/, los archivos 2_spec, 3_plan, 4_research,
5_data_model, 6_contracts, 7_quickstart y 8_tasks. Lee también
versiones/v2_mas_tablas/2_spec.md. Después resume en máximo 10 líneas qué vas
a construir Y QUÉ NO PUEDES TOCAR, y espera mi confirmación.

docs/spec_kit/ es solo lectura.

REGLAS (no negociables):

1. Esta versión NO agrega funcionalidad: agrega un motor. Ningún recurso,
   endpoint ni pantalla nueva.
2. EL CRITERIO 13 MANDA: al terminar, un diff contra la v2 no puede mostrar
   ningún controlador ni ninguna vista modificados. Antes de tocar cualquier
   archivo que no sea un repositorio, el ensamblador, el compose o los
   Dockerfile, pregúntame.
3. Una clase por motor. Prohibido `if ($motor === …)` dentro de los
   repositorios existentes.
4. Las interfaces no se tocan. Si crees que alguna debe cambiar, para y
   dímelo.
5. Nada genérico: doce combinaciones escritas en el ensamblador, sin armar
   nombres de clase con texto.
6. Los mensajes al usuario, una sola vez, en conflictos.php.
7. Sigue 8_tasks.md fase por fase; al terminar cada una ejecuta su
   verificación, muéstrame el resultado real y espera mi OK.
8. Todo en español, PHP 8.3 con declare(strict_types=1), con los comentarios
   didácticos que exige la constitución.
9. Al final: corre `python pruebas_humo/humo_los_dos_motores.py` y muéstrame
   los dos en verde, Y corre el diff contra la v2 y muéstrame la lista de
   archivos modificados. La versión no está terminada hasta que las dos
   cosas estén bien.
```

### 5.3 El método de supervisión

1. **Revise cada diff antes de aceptar**, y esta vez con un ojo distinto: no
   solo «¿está bien lo que escribió?», sino **«¿tenía derecho a tocar este
   archivo?»**.
2. **Exija la evidencia, no el relato.** Especialmente la del criterio 13:
   pida la salida real del `diff`.
3. **El recorrido a mano lo hace usted** ([7_quickstart.md](7_quickstart.md)
   §6): usar el sistema con un motor, cambiar, y volver a usarlo igual. Que
   «se sienta igual» no lo mide ningún guion.

---

## 6. Por qué funciona (la lección del curso)

Las guías anteriores decían que una especificación sirve para dirigir a una
IA. Ésta muestra algo más incómodo y más útil: **una especificación también
sirve para decirle que no a algo que funciona.**

Las tres tentaciones de §0 producen un sistema que corre contra los dos
motores. Pasarían cualquier prueba funcional. Lo que las descarta no es que
fallen: es un criterio escrito antes, que dice qué no se puede haber tocado,
y que se comprueba con un comando.

Sin ese criterio, la discusión sería de opiniones — y contra una IA que
argumenta bien, las opiniones se pierden.
