# Cómo construir la versión 2 con IA — por chat o con un IDE agéntico

> Guía para trabajar la versión en curso con ayuda de IA por **cualquiera de
> los dos caminos**: un chat web (Gemini, DeepSeek, ChatGPT…) o un IDE
> agéntico (Antigravity, Cursor, Claude Code, Copilot en VS Code…).
> La clave del método es la misma en ambos: la IA no inventa — **sigue el
> spec kit**. Usted verifica; la IA propone (chat) o ejecuta bajo su
> supervisión (IDE).

---

## 0. Lo que cambia respecto de la guía de la v1

La v1 se construía **desde cero**. La v2 **parte de la v1 funcionando**, y eso
cambia tres cosas del método:

1. **El punto de partida es código que ya existe**, no una carpeta vacía. La
   primera fase de `8_tasks.md` es traer la v1 y cambiarle los puertos.
2. **Hay que vigilar que lo viejo no se rompa.** El criterio 2 dice que los
   siete endpoints de `producto` deben seguir respondiendo igual. Es normal
   que una IA "mejore" de paso algo que funcionaba: eso es una regresión, y
   hay que devolverla.
3. **Aparece una tentación nueva**: como ahora hay cinco recursos que se
   parecen, la IA va a proponer un enrutador genérico o una función que arme
   nombres de clase con texto. El Artículo 10 de la constitución lo prohíbe,
   y las reglas del prompt lo dicen explícito.

## 1. Los dos caminos, en una tabla

| | **Camino A: chat web** | **Camino B: IDE agéntico** |
|---|---|---|
| Herramientas | Gemini, DeepSeek, ChatGPT, Claude (web) | Antigravity, Cursor, Claude Code, Copilot agente |
| ¿Cómo conoce la spec? | Usted le **sube los 9 archivos** | El agente **lee `docs/spec_kit/` de su proyecto** |
| ¿Quién crea la estructura? | **USTED**, con los comandos de §3 | El agente |
| ¿Quién escribe los archivos? | Usted copia/pega lo que la IA propone | El agente |
| ¿Quién ejecuta los comandos? | Usted, y pega la salida | El agente (pidiendo permiso) |
| Su papel | Operador | Supervisor |
| Riesgo típico | La IA olvida el contexto en chats largos | El agente avanza rápido y agrega lo que no se pidió |

En ambos casos «terminado» significa lo mismo: **los 14 criterios de
aceptación de `2_spec.md` en verde**, verificados con `7_quickstart.md`,
corrido por usted.

---

## 2. Qué subirle (los 9 archivos de la v2)

| # | Archivo | Papel |
|---|---|---|
| 1 | `docs/spec_kit/1_constitution.md` | Las reglas permanentes (incluidos los Artículos 10 y 11) |
| 2 | `.../v2_mas_tablas/2_spec.md` | QUÉ construir y los 14 criterios |
| 3 | `.../v2_mas_tablas/3_plan.md` | CÓMO: estructura, capas y las piezas nuevas |
| 4 | `.../v2_mas_tablas/4_research.md` | Las decisiones y sus alternativas descartadas |
| 5 | `.../v2_mas_tablas/5_data_model.md` | Las seis tablas, sus relaciones y **quién escribe cada columna** |
| 6 | `.../v2_mas_tablas/6_contracts.md` | Los endpoints y las pantallas, exactos |
| 7 | `.../v2_mas_tablas/7_quickstart.md` | El smoke test de validación |
| 8 | `.../v2_mas_tablas/8_tasks.md` | Las fases, en orden |
| 9 | `.../v1_producto_mariadb/2_spec.md` | **La spec de la v1**, para que la IA sepa qué NO debe romper |

El noveno es el que no estaba en la guía anterior, y es importante: sin él, la
IA no tiene forma de saber qué prometía la versión de la que parte.

**No suba el mapa de versiones**: le revelaría lo que viene, y la regla es que
una versión no anticipa a la siguiente.

Además de los documentos, la versión trae **artefactos que NO se suben al chat
ni los genera la IA**: `db/init.sql` (la base completa) y los dos archivos de
Bootstrap. Se **copian tal cual** (paso 4).

---

## 3. Prepare SU proyecto (ANTES de abrir el chat)

**Ojo: NO se construye dentro de la carpeta clonada.** El repositorio clonado
es el **material de referencia**; su trabajo va en una **carpeta nueva y
vacía**, fuera de él.

### 3.1 La carpeta y las subcarpetas

Cree la carpeta de su proyecto, ábrala en VS Code (*File → Open Folder*) y, en
la terminal integrada (*Terminal → New Terminal*, PowerShell), parado en ella:

```powershell
mkdir docs\spec_kit\versiones\v2_mas_tablas, db, api_facturas, api_facturas\controladores, api_facturas\excepciones, api_facturas\modelos, api_facturas\pruebas, api_facturas\repositorios, api_facturas\servicios, front_php, front_php\publico, front_php\vistas, pruebas_humo
```

### 3.2 Los archivos VACÍOS

**Usted los va llenando** uno a uno, pegando en cada uno el código que la IA le
entregue. Que nazcan vacíos y con su nombre puesto es lo que le da forma al
trabajo: se ve de una vez cuántas piezas son y dónde va cada una.

```powershell
New-Item .gitattributes, .gitignore, api_facturas\Dockerfile, api_facturas\controladores\ControladorCliente.php, api_facturas\controladores\ControladorEmpresa.php, api_facturas\controladores\ControladorFactura.php, api_facturas\controladores\ControladorPersona.php, api_facturas\controladores\ControladorProducto.php, api_facturas\controladores\ControladorVendedor.php, api_facturas\excepciones\ConflictoDeIntegridadExcepcion.php, api_facturas\excepciones\NoEncontradoExcepcion.php, api_facturas\index.php, api_facturas\modelos\Cliente.php, api_facturas\modelos\Empresa.php, api_facturas\modelos\Factura.php, api_facturas\modelos\LineaFactura.php, api_facturas\modelos\Persona.php, api_facturas\modelos\Producto.php, api_facturas\modelos\Vendedor.php, api_facturas\pruebas\prueba_capas.php, api_facturas\repositorios\IRepositorioCliente.php, api_facturas\repositorios\IRepositorioEmpresa.php, api_facturas\repositorios\IRepositorioFactura.php, api_facturas\repositorios\IRepositorioPersona.php, api_facturas\repositorios\IRepositorioProducto.php, api_facturas\repositorios\IRepositorioVendedor.php, api_facturas\repositorios\RepositorioClienteMariaDB.php, api_facturas\repositorios\RepositorioEmpresaMariaDB.php, api_facturas\repositorios\RepositorioFacturaMariaDB.php, api_facturas\repositorios\RepositorioPersonaMariaDB.php, api_facturas\repositorios\RepositorioProductoMariaDB.php, api_facturas\repositorios\RepositorioVendedorMariaDB.php, api_facturas\repositorios\errores_de_integridad.php, api_facturas\servicios\IServicioCliente.php, api_facturas\servicios\IServicioEmpresa.php, api_facturas\servicios\IServicioFactura.php, api_facturas\servicios\IServicioPersona.php, api_facturas\servicios\IServicioProducto.php, api_facturas\servicios\IServicioVendedor.php, api_facturas\servicios\ServicioCliente.php, api_facturas\servicios\ServicioEmpresa.php, api_facturas\servicios\ServicioFactura.php, api_facturas\servicios\ServicioPersona.php, api_facturas\servicios\ServicioProducto.php, api_facturas\servicios\ServicioVendedor.php, api_facturas\servicios\ensamblador.php, docker-compose.yml, front_php\Dockerfile, front_php\cliente_api.php, front_php\index.php, front_php\publico\estilos.css, front_php\vistas\clientes_formulario.php, front_php\vistas\clientes_lista.php, front_php\vistas\empresas_formulario.php, front_php\vistas\empresas_lista.php, front_php\vistas\facturas_detalle.php, front_php\vistas\facturas_formulario.php, front_php\vistas\facturas_lista.php, front_php\vistas\inicio.php, front_php\vistas\no_encontrada.php, front_php\vistas\personas_formulario.php, front_php\vistas\personas_lista.php, front_php\vistas\plantilla.php, front_php\vistas\productos_formulario.php, front_php\vistas\productos_lista.php, front_php\vistas\vendedores_formulario.php, front_php\vistas\vendedores_lista.php, pruebas_humo\humo_front.py
```

> **Fíjese en la lista y cuente.** Cinco recursos tienen exactamente las
> mismas piezas con distinto nombre; la factura tiene **dos modelos** en vez
> de uno y **una vista de más** (`facturas_detalle.php`). Esa asimetría no es
> un descuido de la lista: es lo que la versión viene a enseñar.
>
> **Tiene** los archivos de `front_php\` porque **la versión incluye su
> pantalla** (Artículo 1.1). Son la mitad del trabajo.
>
> **No tiene** `db\init.sql` ni los dos de Bootstrap: ésos no nacen vacíos.

### 3.3 Los archivos que vienen DADOS: cópielos del repositorio del curso

Con el explorador de Windows (Ctrl+C, Ctrl+V), cada uno a la misma ruta:

| Del clon del curso | A su proyecto |
|---|---|
| `db\init.sql` | `db\` |
| `db\init_phpmyadmin.sql` | `db\` |
| `front_php\publico\bootstrap.min.css` | `front_php\publico\` |
| `front_php\publico\bootstrap.bundle.min.js` | `front_php\publico\` |
| `docs\spec_kit\1_constitution.md` | `docs\spec_kit\` |
| Los `.md` de `docs\spec_kit\versiones\v2_mas_tablas\` | la misma ruta |

Estos vienen dados y **la IA no los genera**: los documentos se le SUBEN al
chat, `db/init.sql` es la base ya escrita, y Bootstrap es la hoja de estilos
que el proyecto **guarda en vez de traer de internet**, para que la pantalla
se vea igual en un salón sin red.

### 3.4 Compruebe antes de empezar

- [ ] `docs\spec_kit\1_constitution.md` existe y tiene contenido.
- [ ] `docs\spec_kit\versiones\v2_mas_tablas\` tiene **8 archivos**.
- [ ] `db\init.sql` tiene contenido (~1.300 líneas), no está vacío.
- [ ] `front_php\publico\` tiene los dos de Bootstrap con contenido
      (unos 230 KB y 80 KB).
- [ ] `front_php\` existe con sus carpetas, aunque los archivos estén
      vacíos: si no está, la versión va a nacer sin la mitad que se ve.

Si algo está vacío o falta, es el paso 3.3.

> **La estructura queda lista ANTES de hablar con la IA**, y es la que describe
> `3_plan.md` §2. Así el chat entrega código para archivos que ya existen, en
> vez de proponerle a usted dónde ponerlos.

### 3.5 ¿Desde qué carpeta se corre cada comando?

| Comando | Se corre desde |
|---|---|
| `docker compose ...` | La **raíz** de su proyecto |
| `php -l archivo.php` · `php pruebas\prueba_capas.php` | `api_facturas\` |
| `python pruebas_humo\humo_front.py` | La **raíz** de su proyecto |

**A la terminal SOLO se le pegan COMANDOS** — lo que viene en las cajitas de
código. Si pega el texto del mensaje, la terminal intentará ejecutar cada
palabra y llenará la pantalla de errores tipo `'Te' no se reconoce como
nombre de un cmdlet`. Al chat, texto; a la terminal, comandos.

---

## 4. Camino A — El prompt del chat (cópielo tal cual)

**Antes de enviar el primer mensaje, tres chequeos en el chat:**

1. **Los 9 adjuntos**: verifique que aparecen todos.
2. **Active el modo de razonamiento** si el chat lo tiene (en DeepSeek se
   llama «Pensamiento Profundo»): sigue mucho mejor las reglas estrictas.
3. **Apague la búsqueda web**: no se necesita y puede traer código de internet
   por fuera de la spec.

```
Actúa como mi asistente de programación para construir la VERSIÓN 2 de un
proyecto universitario. Te adjunto 9 documentos: una constitución (reglas
permanentes), el spec kit de la versión 2, y la spec de la versión 1 para que
sepas qué ya existe y no se puede romper.

El proyecto es PHP 8.3 puro (sin frameworks ni Composer) + MariaDB. Son DOS
programas que se construyen a la vez: la API (api_facturas) y su front
(front_php), que habla con ella por HTTP. Si en tu respuesta aparece OTRO
lenguaje o framework, significa que no leíste los adjuntos: detente y dímelo.

Esta versión NO parte de cero: parte de la versión 1, que ya tiene el recurso
`producto` funcionando de punta a punta. Yo ya tengo ese código.

REGLAS DE TRABAJO (no negociables):

1. La especificación manda. No agregues NADA que los documentos no pidan: ni
   frameworks, ni Composer, ni librerías, ni tablas extra, ni motores extra,
   ni fábricas "por si acaso", ni mejoras de tu cosecha. Si crees que falta
   algo, pregúntame antes.
2. NO ROMPAS LA v1. Los siete endpoints de `producto` deben seguir
   respondiendo exactamente igual (es el criterio 2 de 2_spec.md). Si vas a
   tocar un archivo de la v1, dime primero por qué.
3. Vamos a seguir 8_tasks.md FASE POR FASE, en orden. En cada fase:
   a. Me explicas en 3-5 líneas qué vamos a hacer y por qué.
   b. Me entregas los archivos DE A UNO: primero la ruta exacta y el
      contenido COMPLETO de UN solo archivo, listo para copiar y pegar, con
      los comentarios didácticos en español que exige la constitución.
      Esperas mi "listo" y solo entonces sigues.
   c. Al cerrar la fase me dices su comando de verificación y qué salida
      esperar.
   NOTA: la estructura de carpetas y los archivos vacíos YA EXISTEN — no me
   des comandos para crearlos; tu trabajo es dictarme el CONTENIDO.
4. NADA GENÉRICO. Es el Artículo 10 de la constitución y aquí hay cinco
   recursos que se parecen, así que la tentación es grande:
   - una ruta por recurso, escrita con su nombre (`/api/cliente`), nunca
     `/api/{tabla}`;
   - una función por operación en el ensamblador y en el cliente del front,
     nunca un `switch` ni nombres de clase armados con texto
     ("Repositorio{$recurso}MariaDB" está prohibido);
   - código repetitivo-pero-legible antes que compacto-pero-mágico.
5. LA FACTURA NO ES UN CRUD, y no la fuerces a serlo:
   - no tiene PATCH (el detalle se reemplaza entero);
   - tiene una operación `/anular` que no es eliminar;
   - su repositorio llama PROCEDIMIENTOS ALMACENADOS, no escribe SELECT.
6. LA INTEGRIDAD LA DEFIENDE LA BASE. No escribas comprobaciones previas del
   tipo "¿existe esa persona?" antes de insertar: se intenta, y se traduce el
   rechazo del motor a un 409 con mensaje en español, en un solo archivo
   (errores_de_integridad.php). El porqué está en 4_research.md D2.
7. LA API NO CALCULA lo que la base calcula: ni el total de la factura, ni
   los subtotales, ni el stock. Los mueve un trigger. Si escribes una
   multiplicación para el total, es que no leíste 5_data_model.md.
8. El código debe cumplir 6_contracts.md al pie de la letra: mismos verbos,
   rutas, códigos de estado y formatos, incluidos el 409 y el 405.
9. Todo en español: nombres, comentarios y mensajes. PHP 8.3 con
   declare(strict_types=1) en cada archivo.
10. Ninguna pantalla le habla al usuario en jerga: nada de verbos HTTP,
   códigos de estado, /api/, PDO, MariaDB ni nombres de columna como
   fkcodpersona en el texto que se ve.
11. Bootstrap ya está en front_php/publico/: enlaza esos archivos locales
   (/publico/bootstrap.min.css), NUNCA un CDN, y no me dictes su contenido.
12. Los errores NO nos frenan. Si te pego un error, lo diagnosticas y me das
   el archivo completo corregido; si no sale rápido, seguimos y lo retomamos
   al final.
13. Yo trabajo en Windows con VS Code (terminal PowerShell) y Docker Desktop.
14. En mi máquina TAMBIÉN corre el proyecto clonado del curso. Para que ambos
   convivan, MI proyecto publica los puertos del host con +100: el front va
   "8124:8024", la API "8126:8026", phpMyAdmin "8202:80" y MariaDB
   "13427:3306" (adentro de los contenedores todo queda igual, incluido
   URL_API, que sigue apuntando a http://api-facturas:8026). Y el
   docker-compose.yml empieza con la línea `name: mi_v2_facturas` antes de
   `services:`, para que Docker lo trate como un proyecto distinto.
   Cuando me des URLs de prueba, usa localhost:8124 (pantalla),
   localhost:8126 (API) y localhost:13427 (BD).

Al final, la versión 2 está TERMINADA solo cuando pasan los 14 criterios de
aceptación de 2_spec.md, verificados con el smoke test de 7_quickstart.md.

Empieza: resume en máximo 10 líneas qué vamos a construir y en qué se
diferencia de la v1, y luego arranca con la Fase 0.
```

### 4.1 El método de la conversación

1. **Pegue primero, ejecute cuando quiera.** Lo obligatorio es pegar cada
   archivo en su ruta y responder "listo".
2. **No se quede varado en un error.** Anótelo, siga con las fases y retómelo
   al final.
3. **El punto de control real es el smoke test final.** Péguele CADA error tal
   cual salga, completo.
4. **Vigile las tres tentaciones de esta versión**, que son nuevas:
   - un enrutador o un ensamblador genérico (regla 4);
   - un `if` que comprueba llaves foráneas antes de insertar (regla 6);
   - una multiplicación para calcular el total (regla 7).

   Las tres «funcionan» y las tres están mal por razones que los documentos
   explican. Si aparecen, cite la regla y pida el archivo corregido.
5. **Si la IA "mejora" algo de la v1**, devuélvalo: es una regresión.
6. **Si el chat pierde el contexto**, abra uno nuevo, vuelva a subir los 9
   documentos y agregue: «Ya tengo construidas las fases 0 a N; te pego el
   código actual. Continuemos en la fase N+1».

---

## 5. Camino B — El prompt para el agente (cópielo tal cual)

### 5.1 Preparación

1. Cree su carpeta y copie dentro los 9 documentos (misma estructura que en
   §3), `db/init.sql` y los dos de Bootstrap.
2. Copie también **el código de la v1**: el agente parte de ahí.
3. Abra SU carpeta en el IDE y tenga Docker Desktop corriendo.
4. Active el modo agente.

### 5.2 El prompt

```
Construye la VERSIÓN 2 de este proyecto, partiendo de la versión 1 que ya
está en esta carpeta y funciona.

Primero lee, en este orden, los documentos que están bajo docs/spec_kit/:
1_constitution.md, y de versiones/v2_mas_tablas/ los archivos 2_spec,
3_plan, 4_research, 5_data_model, 6_contracts, 7_quickstart y 8_tasks. Lee
también versiones/v1_producto_mariadb/2_spec.md, para saber qué NO puedes
romper. Después resume en máximo 10 líneas qué vas a construir y espera mi
confirmación antes de tocar nada.

docs/spec_kit/ es solo lectura: no la modifiques.

REGLAS (no negociables):

1. La especificación manda. No agregues NADA que los documentos no pidan.
2. NO ROMPAS LA v1: los siete endpoints de `producto` deben seguir
   respondiendo igual (criterio 2 de 2_spec.md).
3. Sigue 8_tasks.md FASE POR FASE. Al terminar cada fase, EJECUTA su
   verificación, muéstrame el resultado real, y espera mi OK.
4. NADA GENÉRICO (Artículo 10): una ruta y una función por recurso, con su
   nombre escrito. Prohibido armar nombres de clase con texto.
5. La factura no es un CRUD: sin PATCH, con /anular, y su repositorio llama
   procedimientos almacenados.
6. La integridad la defiende la base: no comprobaciones previas; se traduce
   el rechazo a 409 en errores_de_integridad.php.
7. La API no calcula el total, los subtotales ni el stock: los mueve un
   trigger.
8. Todo en español, PHP 8.3 con declare(strict_types=1), con los comentarios
   didácticos que exige la constitución.
9. Esta versión incluye SU FRONT. El front no toca la base: nada de new PDO
   ahí, y NO le hagas require de ningún archivo de api_facturas aunque los
   dos estén en PHP y funcionaría. En el compose, el servicio del front no
   lleva credenciales de base de datos ni depends_on de mariadb.
10. Bootstrap ya está en front_php/publico/: enlaza esos archivos locales,
   nunca un CDN.
11. Al final, corre el smoke test completo de 7_quickstart.md —incluida la
   prueba de apagar la API con la base encendida— y muéstrame la evidencia
   de los 14 criterios. La versión no está terminada hasta que los 14 estén
   en verde.
```

### 5.3 El método de supervisión

1. **Revise cada diff antes de aceptar.** Si un archivo no está en la
   estructura de `3_plan.md` §2, pregunte por qué existe.
2. **Exija la evidencia, no el relato.** «Ya pasa la fase 3» no vale: pida la
   salida real del comando.
3. **Vigile el alcance.** Si aparece un `composer.json`, una tabla `usuario` o
   una fábrica multi-motor, el agente se salió de la v2.
4. **Vigile las tres tentaciones** de §4.1 punto 4. Son las que un agente
   comete con más naturalidad, porque las tres parecen buenas ideas.
5. **El cierre lo corre usted**, y el recorrido a mano de `7_quickstart.md`
   §3.2 **no lo puede hacer el agente**: juzga si la pantalla se entiende, y
   eso no lo mide ningún guion.

---

## 6. Por qué funciona (la lección del curso)

Esto ES spec-driven development ([SDD_SPECKIT.md](../../../SDD_SPECKIT.md)):
la misma IA que con «hazme una API de facturas en PHP» produce cualquier cosa,
con una constitución + spec + plan + tareas produce EL sistema especificado.

Y la v2 agrega una lección que la v1 no podía dar: **la especificación también
sirve para decir que NO.** Las tres tentaciones de §4.1 —el enrutador
genérico, la comprobación previa de llaves foráneas, el total calculado en
PHP— son cosas que una IA propone con naturalidad porque funcionan. Lo que
las descarta no es el gusto de nadie: es un documento escrito antes, con su
razón al lado. Sin él, la discusión sería de opiniones.
