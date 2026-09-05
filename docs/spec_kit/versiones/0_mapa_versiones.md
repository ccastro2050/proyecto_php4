# Mapa de versiones — Proyecto PHP

> **Cómo se trabaja este proyecto: por versiones (desarrollo incremental guiado
> por especificaciones).** Así maneja SDD el crecimiento de un sistema: la
> **constitución es permanente** ([../1_constitution.md](../1_constitution.md))
> y cada versión tiene **su propia especificación, plan y tareas** en una
> carpeta `vN_nombre/`. La spec de una versión es EL documento que se le
> entrega a la IA (o al estudiante) para construir ESA versión — ni más ni menos.
>
> Regla de avance: una versión está TERMINADA cuando pasa todos los criterios
> de aceptación de su `2_spec.md`. Solo entonces se escribe la spec de la
> siguiente.

---

## Cada versión vive en su propio repositorio

| Versión | Repositorio | Qué EXISTE al terminarla | Qué concepto nuevo enseña |
|---|---|---|---|
| **v1** | [`proyecto_php1`](https://github.com/ccastro2050/proyecto_php1) | El CRUD de **producto** de punta a punta: API (PHP puro + PDO) contra **MariaDB** **y su front**. Una tabla, un motor. | Arquitectura en capas con `interface` de PHP desde el día 1, y la separación front/API a nivel de sistema |
| **v2** | [`proyecto_php2`](https://github.com/ccastro2050/proyecto_php2) | Seis recursos: producto, **empresa, persona, cliente, vendedor y factura**, con sus pantallas. Sigue siendo solo MariaDB. | **Llaves foráneas e integridad**; **maestro-detalle** con triggers y procedimientos almacenados; el 409 |
| **v3** | [`proyecto_php3`](https://github.com/ccastro2050/proyecto_php3) | Lo mismo, **sin una funcionalidad nueva**, ahora también contra **PostgreSQL**, escogido por configuración | Nace la **fábrica** — abierto/cerrado en acción: cero cambios en controladores ni pantallas, y se comprueba con un `diff` |
| **v4** | **`proyecto_php4`** ← **USTED ESTÁ AQUÍ** | Tercer motor (**SQL Server**), los tres a la vez, compose completo | Que el sistema **siga** abierto: aguantar la segunda extensión, no solo la primera |

**Un repositorio por versión, y no una rama por versión.** La razón es
práctica: así se pueden tener dos versiones **encendidas al mismo tiempo** y
compararlas lado a lado — por eso cada una usa puertos propios. Y así la v1
sigue siendo un ejemplo completo y ejecutable aunque la v2 ya exista.

Cada repositorio **acumula** las carpetas de especificación de las versiones
anteriores, para poder leer cómo se llegó hasta aquí. El **código**, en
cambio, es el de la versión que le da nombre.

## Qué cambia de la v3 a esta versión

| | v3 | v4 |
|---|---|---|
| Recursos, endpoints, pantallas | 6 · 38 · 15 | **los mismos** |
| Motores | MariaDB + PostgreSQL | **+ SQL Server** |
| Repositorios | 12 | **18** (seis por motor) |
| Servicios del compose | 4 | **6** |
| Controladores, servicios, interfaces y vistas | sin cambios | **sin cambios** ← *el punto* |

**La segunda versión seguida que no agrega ni una funcionalidad.** La v3
demostró que el sistema estaba **abierto a un segundo motor**; ésta demuestra
que **sigue abierto**, que es una cosa distinta: muchas arquitecturas aguantan
la primera extensión —a veces por casualidad— y se deforman en la segunda.

Y hay una razón concreta para que **este** motor fuera la prueba de fuego:

> Entre MariaDB y PostgreSQL el SQL de las consultas resultó **idéntico**. Se
> copiaron tal cual. Eso dejaba una duda razonable: ¿de verdad hacían falta
> clases separadas por motor, o era ceremonia?
>
> **Con SQL Server el SQL sí cambia.** No existe `LIMIT`. La llave generada se
> lee con una cláusula en medio del `INSERT`. Su driver ni siquiera viene con
> PHP. Y aun así, ninguna capa de arriba se movió.

### Y algo que solo se puede ver con tres

En la v3 parecía que PostgreSQL era el motor raro por usar un solo código de
error para los dos sentidos de una llave foránea, mientras MariaDB los
separaba en dos. Con SQL Server —que también usa uno solo— se ve que **el raro
era MariaDB**.

Con dos puntos de comparación uno no distingue una regla de una coincidencia.
Con tres empieza a poder.

## Reglas del trabajo por versiones

1. **La constitución no se toca entre versiones.** Si una versión exige cambiar
   una regla, eso es una decisión mayor que se discute aparte. (La v2 le
   *agregó* el Artículo 11, que no contradice ninguno: describe algo que la v1
   no tenía enfrente.)
2. **Cada carpeta de versión es autocontenida**: con la constitución + esa
   carpeta se puede construir la versión desde el estado que dejó la anterior,
   sin leer nada más.
3. **Una versión incluye su front.** No está terminada cuando la API responde:
   está terminada cuando la pantalla de esa versión muestra lo que la API
   devuelve, y sigue en pie —con su aviso— cuando la API no responde
   (Artículo 1.1 de la [constitución](../1_constitution.md)).
4. **El código de una versión no anticipa a la siguiente**: en v2 NO se escribe
   la fábrica multi-motor "por si acaso" — se escribe la interfaz, y la
   fábrica llegará cuando un segundo motor la justifique (v3). **YAGNI con
   dirección**: *You Aren't Gonna Need It* ("no lo vas a necesitar") — no se
   escribe hoy lo que solo hará falta mañana, pero se sabe hacia dónde va.
5. **Cada versión termina en verde**: criterios de aceptación verificables,
   commit (y tag `v1`, `v2`, …) al cerrarla.
6. La spec de la versión siguiente **parte del estado real** dejado por la
   anterior — si el código divergió de la spec, primero se reconcilia
   (la spec siempre refleja el estado actual: deuda de especificación).

## Cómo salió el examen de la v3

La v2 cerraba diciendo que si estaba bien cortada, agregar PostgreSQL no
debería tocar ni un controlador, ni un servicio, ni una pantalla.

**Salió casi perfecto, y el «casi» es lo interesante.**

- Ningún controlador y ninguna vista cambiaron. Ni uno.
- El `ensamblador.php` sí, claro: es el archivo de esta versión.
- Y cambió **un servicio**, `ServicioProducto`, por una razón que no tiene
  nada que ver con el motor: se aprovechó para unificar un nombre de método
  que estaba distinto desde la v1 (`obtenerPorCodigo` contra
  `obtenerPorClave`).

Esa inconsistencia llevaba dos versiones ahí y **nadie la había notado**. Se
notó ahora porque hubo que escribir cada repositorio **por segunda vez**, y
al ponerlos lado a lado saltó a la vista.

Está declarada en el criterio 13.b de la v3, con su motivo. Un criterio
redactado para que dé verde no sirve de nada.

## Cómo salió el examen de la v4

La v3 cerraba diciendo que si estaba bien hecha, agregar SQL Server debería
ser escribir seis repositorios más y un traductor de errores más — y nada más.

**Salió exacto, y esta vez sin «casi».**

- Ningún controlador, ningún servicio, ninguna interfaz y ningún modelo
  cambiaron.
- La vista solo cambió para escribir «SQL Server» en vez de `sqlserver` en el
  pie.
- `conflictos.php` —las frases que lee el usuario— no se tocó.
- La prueba de capas tampoco.

**Compare la lista de cambios permitidos de la v3 con la de la v4.** La de la
v4 es más corta, y no porque se haya hecho menos: es porque la v3 dejó el
sitio preparado. Ésa es la diferencia entre una arquitectura que aguantó una
extensión y una que aguanta las que vengan.

## Y aquí termina la ruta

No hay v5, y es una decisión, no un abandono.

Un cuarto motor no enseñaría nada que la tercera columna no haya mostrado ya.
Y el control de acceso —esas seis tablas que la base trae desde la v1 y que
ningún código nombra— es un tema entero, con sesiones, hashing y permisos por
ruta, que merece su propio curso y no un apéndice.

**Una ruta de versiones también se diseña sabiendo dónde parar.** Estirarla
para que parezca más completa es la forma más común de arruinar un buen
ejemplo.
