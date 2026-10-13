# Lista de chequeo de requisitos — Versión 4

> **La compuerta 3** del método (ver [SDD_SPECKIT](../../../SDD_SPECKIT.md)). Esta lista
> revisa **la ESPECIFICACIÓN, no el código**: se pasa cuando los documentos
> 2 a 8 de esta versión están escritos y ANTES de programar la primera
> línea. Es el equivalente a mano de `checklists/requirements.md`, que en
> Spec Kit genera `/speckit.checklist`.

## Cómo se usa

- **Las casillas las marca una persona.** Una IA puede ayudar a evaluar y a
  señalar dudas, pero **no puede auto-aprobarse**: quien firma es quien
  responde por la versión.
- Se marca `[x]` solo cuando el criterio se cumple **hoy, en el documento**
  — no "cuando lo arregle".
- **Con una sola casilla en rojo no se escribe código.** Se vuelve al
  documento que la causó, se corrige, y se pasa la lista otra vez.
- Trabaja bien en pareja: un estudiante revisa la spec del otro. Las
  ambigüedades que uno no ve, el otro las tropieza de una.

---

## A. Claridad — ¿dice UNA sola cosa?

- [ ] Ningún requisito usa palabras sin definir: *rápido, amigable,
      eficiente, correcto, adecuado, robusto*.
- [ ] No queda ningún marcador `[NECESITA ACLARACIÓN: …]` sin resolver en
      la sección de **Clarificaciones** de [2_spec.md](2_spec.md).
- [ ] Cada RF explica UNA cosa. Si uno necesita un "y" para entenderse, se
      partió en dos.
- [ ] Los RF no mencionan tecnología ni nombres de clase: el QUÉ está
      separado del CÓMO, que vive en [3_plan.md](3_plan.md).

## B. Medible — ¿se puede verificar?

- [ ] Cada criterio de aceptación de [2_spec.md](2_spec.md) dice un **valor
      concreto**: un número, un código de estado o un texto exacto.
- [ ] Cada criterio se puede comprobar con **un comando** de
      [7_quickstart.md](7_quickstart.md). Si no hay comando posible, no es
      criterio.
- [ ] Los códigos de error están dichos por su número (400, 404, 422,
      500), no como "responde con un error".

## C. Completitud — ¿falta algo?

- [ ] Los RF cubren todo lo que promete el propósito: nada del alcance
      quedó sin requisito.
- [ ] [2_spec.md](2_spec.md) tiene su **NO incluye** explícito.
- [ ] Cada entrada de [6_contracts.md](6_contracts.md) documenta sus
      desenlaces de **ERROR**, no solo el camino feliz.
- [ ] [6_contracts.md](6_contracts.md) dice el nombre EXACTO de cada campo
      **en el JSON**, no solo el de la columna. Se parecen, pero no siempre
      son iguales — y quien reconstruye el sistema copia del contrato.
- [ ] Cada rechazo de integridad que la especificación promete tiene su
      **mensaje en español** escrito, no solo su código.
- [ ] [5_data_model.md](5_data_model.md) trae los **datos exactos** de los
      que dependen los comandos del smoke test.
- [ ] **La versión tiene su front especificado**, no solo su API: hay
      requisitos de lo que la persona ve y hace, y [6_contracts.md](6_contracts.md)
      dice qué pantalla existe y qué le pide a la API (Artículo 1.1 de la
      [constitución](../../1_constitution.md)).
- [ ] Está dicho **qué hace la pantalla cuando la API no responde** — no
      basta con el camino feliz.
- [ ] Está dicho **qué hace la pantalla cuando no hay filas**, y que eso no
      es un error.
- [ ] Cada decisión de [4_research.md](4_research.md) tiene al menos una
      alternativa descartada con su razón.

## D. Coherencia — ¿los documentos dicen lo mismo?

- [ ] Todo RF de [2_spec.md](2_spec.md) aparece en
      [6_contracts.md](6_contracts.md).
- [ ] Todo lo que promete [6_contracts.md](6_contracts.md) tiene una tarea
      que lo construye en [8_tasks.md](8_tasks.md).
- [ ] Todo criterio de aceptación tiene su comando en
      [7_quickstart.md](7_quickstart.md), **con el mismo número**.
- [ ] Cada pantalla de [6_contracts.md](6_contracts.md) §9 usa un endpoint
      que existe en las secciones de arriba del mismo documento — y ninguna
      inventa uno.
- [ ] Los ejemplos de [6_contracts.md](6_contracts.md) usan datos que
      existen en [5_data_model.md](5_data_model.md).
- [ ] [3_plan.md](3_plan.md) no nombra ningún archivo que ninguna tarea
      construya, y ninguna tarea construye un archivo que el plan no liste.
- [ ] El **Chequeo de constitución** de [3_plan.md](3_plan.md) está
      completo: artículo por artículo, sin saltarse ninguno.


## E. Alcance — ¿no se está anticipando?

- [ ] Ningún documento nombra entidades, motores o pantallas fuera del
      alcance declarado en [2_spec.md](2_spec.md).
- [ ] **Ninguna ruta es genérica**: no aparece `/api/{tabla}` ni nada que
      reciba el nombre del recurso como parámetro, ni en la API ni en el
      cliente del front (Artículo 10 de la
      [constitución](../../1_constitution.md)).
- [ ] **Ningún recurso tiene operaciones que no le corresponden.** Si la
      especificación le da a un recurso un verbo que su negocio no admite
      —un PATCH de factura, por ejemplo— se está describiendo la comodidad
      de quien programa, no lo que la cosa hace.
- [ ] **El dialecto del motor no aparece fuera de las clases con apellido
      de motor.** Ni un `SELECT`, ni un nombre de procedimiento, ni un
      código de error en un servicio, un controlador o una vista.
- [ ] **La especificación dice qué NO se puede haber tocado**, y de una
      forma que se pueda comprobar con un comando. Un criterio de
      aceptación que solo se puede juzgar leyendo el código no es un
      criterio: es una opinión.
- [ ] **Las excepciones al criterio anterior están escritas con su motivo.**
      Si un archivo que «no debía cambiar» cambió, el documento lo dice y
      explica por qué. Un criterio redactado para que dé verde no sirve de
      nada.
- [ ] **Lo que el usuario lee es igual con TODOS los motores.** No solo los
      códigos de estado: las frases. Si los mensajes se redactan por
      separado en cada motor, tarde o temprano se separan.
- [ ] **Las concesiones que un motor obliga a hacer están declaradas.** Si
      uno exige un usuario distinto, una clave con otra forma o una opción
      de seguridad relajada, eso va escrito en la constitución y en el
      compose — no escondido en una variable. Una convención con una
      excepción explicada sigue siendo una convención.
- [ ] **El sistema arranca DOS VECES seguidas.** Levantarlo, bajarlo y
      volverlo a levantar no puede romper nada ni duplicar datos. Un
      inicializador que solo funciona con el volumen vacío es una trampa.
- [ ] **Está dicho quién escribe cada columna.** Para cada campo: lo escribe
      el cliente, lo genera la base, o lo calcula un trigger. Un campo cuyo
      dueño no está declarado acaba escrito por dos.
- [ ] Ningún documento anticipa una versión futura (Artículo 1 de la
      [constitución](../../1_constitution.md): lo que no pide esta versión, no se
      escribe).
- [ ] Las dependencias que nombra [3_plan.md](3_plan.md) son exactamente
      las que permite la constitución.

---

## Resultado

| | |
|---|---|
| **Revisada por** | *(nombre de quien firma)* |
| **Fecha** | |
| **Casillas en rojo** | |
| **Veredicto** | ⬜ En verde: puede empezar el código · ⬜ En rojo: vuelve a la spec |

> Si el veredicto es rojo, anote aquí qué documento hay que corregir y por
> qué. Esa nota es la que evita repetir el mismo error en la versión
> siguiente.


---

# ANEXO A LA v4 — LA REVISIÓN DE LOS REQUISITOS QUE SE AGREGARON

Los requisitos del aplicativo pasaron por la misma revisión que los del
tercer motor, y conviene dejar escritas las **tres** correcciones que salieron
de ella, porque las tres son del tipo que uno no ve en su propio texto.

## A. Claridad — ¿dice UNA sola cosa?

| Requisito como se escribió primero | Qué le faltaba | Como quedó |
|---|---|---|
| «la API valida que el usuario tenga permiso» | *Validar* no dice qué responde, y aquí hay **dos** respuestas distintas | «sin token: 401; con token y sin permiso: 403» |
| «el front oculta lo que el usuario no puede ver» | Suena a control de acceso y **no lo es** | «el front no ofrece lo que la API va a rechazar; la API niega aunque el front ofrezca» |
| «crear un usuario con sus roles en una operación» | No decía qué pasa si falla a mitad | «…y es atómica: si un rol no existe, no queda el usuario creado sin roles» |

El segundo es el importante. Escrito como estaba, un estudiante podría
«cumplirlo» escondiendo botones y dejando la API abierta. **Un requisito que
se puede cumplir mal está mal escrito.**

## B. Medible — ¿se puede verificar?

| Criterio | Cómo se mide, exactamente |
|---|---|
| El permiso no está en el token | Quitar un rol con el token ya emitido y repetir **la misma** petición: 200 → 403, sin reentrar |
| Los permisos de los roles se suman | `vendedor1` tiene dos roles; quitarle uno **no** le cierra `/factura`, que el otro también da |
| Las diez consultas valen en los tres motores | `prueba_consultas_tres_motores.php`, que compara **columnas** y **valores**, no códigos de respuesta |
| El `ticket_promedio` no se trunca | La misma prueba lo recalcula como `total / facturas` y lo compara con 2 decimales |
| La operación es atómica | Mandar un rol inexistente y **mirar la base de datos**, no la respuesta |

Los dos últimos son los que de verdad justifican el anexo: **cuatro de los
seis tropiezos de esta versión no fallaban.** Un criterio que solo mira el
código de respuesta los habría dado por buenos.

## C. Completitud — ¿falta algo?

Lo que la revisión encontró que faltaba, y entró:

- qué pasa con una ruta **no declarada** en la tabla `ruta` → fallar cerrado;
- qué pasa con un usuario **sin ningún rol** → entra (la contraseña es
  correcta) y no alcanza nada: 401 no, 403 en todo;
- qué devuelve una consulta **sin filas** → `total: 0`, y no es un error;
- y qué **no** se hace: la lista negra de tokens, el bloqueo por intentos
  fallidos y el registro de auditoría. Están en
  `docs/dominio/PENDIENTES.md`, con su razón cada uno. **Lo que no se hace
  también se escribe**: un pendiente anotado es una decisión; uno no anotado
  es un olvido que alguien encuentra en la sustentación.

## D. Coherencia — ¿los documentos dicen lo mismo?

Aquí salió la corrección más grande, y no era de esta versión: el mapa de
versiones decía que el control de acceso **no** entraba en la ruta de PHP
—«seis tablas que ningún código nombra»— y el repositorio de consultas de
SQL Server explicaba el `CAST` con una afirmación falsa que llevaba cuatro
repos copiándose.

Las dos están corregidas, y **las dos quedan contadas** en lugar de
reescritas en silencio: `0_mapa_versiones.md` dice qué decía antes y por qué
cambió, y `4_research.md` dice cómo se cayó la explicación del CAST. Un
estudiante que lea las dos versiones aprende más que uno que lea solo la
segunda.

## E. Alcance — ¿no se está anticipando?

Lo que **no** entró, teniéndolo a mano:

- **refrescar el token automáticamente** desde el front: hay
  `POST /api/sesion/renovar` y el front no lo llama. Cuando el token vence,
  la siguiente petición responde 401 y el front manda a `/entrar`. Es
  honesto y es suficiente para un proyecto de aula;
- **paginar las consultas**: un reporte se devuelve entero o no sirve para
  sumar;
- **permisos por verbo** (que un rol pueda leer y no escribir): la tabla
  `ruta` guarda la ruta, no el método. Cambiarlo es cambiar el modelo, y eso
  es una decisión mayor que no se toma en un anexo.

## Resultado

Los requisitos del aplicativo quedan **aprobados con las tres correcciones de
la sección A aplicadas**, y con los cinco criterios de la sección B escritos
de forma que se puedan volver a correr. Lo que no se hizo está anotado donde
se anota.
