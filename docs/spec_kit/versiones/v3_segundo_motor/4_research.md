# Investigación y decisiones — Versión 3

> **Versión 3** · **Lectura opcional** (el porqué de las decisiones del plan,
> con las alternativas que se evaluaron y descartaron).

---

## D1 — Un solo servicio de API que escoge, y no uno por motor

**Alternativa descartada:** dos servicios en el compose —`api-mariadb` y
`api-postgres`— cada uno con su puerto y su configuración fija.
**Decisión:** un solo servicio, una sola imagen, y la variable `MOTOR`.
**Por qué:** con dos servicios, la versión habría «funcionado» sin demostrar
nada. Lo que hay que hacer visible es que **es el mismo programa**, y dos
contenedores distintos corriendo a la vez lo esconden — cualquiera podría
pensar que cada uno tiene su código.

Con uno solo, cambiar de motor es una palabra y un reinicio, y el front
—que sigue apuntando al mismo puerto— no se entera. Eso es lo que se quería
enseñar.

**Precio asumido:** la imagen carga los dos drivers de PDO aunque solo use
uno. Son unos megabytes y no se notan.

## D2 — Una clase por motor, y no una sola con `if ($motor === …)`

**Alternativa descartada:** un `RepositorioCliente` único que preguntara el
motor en cada método. A primera vista parece menos código.
**Decisión:** `RepositorioClienteMariaDB` y `RepositorioClientePostgres`, cada
una cumpliendo la misma interfaz.
**Por qué:** porque las diferencias entre los dos motores **no son una línea
por método**. La tabla de [3_plan.md](3_plan.md) §4.3 tiene doce, y están
repartidas: en las opciones de la conexión, en cómo se lee una llave
generada, en cómo se llama un procedimiento, en cómo se reconoce un error, en
cómo llega una fecha.

Un solo archivo con esas doce diferencias adentro sería ilegible a los tres
meses. Y, sobre todo, haría imposible el criterio 13: no habría forma de
mostrar con un `diff` qué se tocó al agregar el motor, porque se habría
tocado todo.

**El hallazgo que no esperábamos:** el `SELECT` resultó **idéntico** en los dos
motores. Esta base usa SQL estándar, así que las consultas se copiaron tal
cual. Eso podría leerse como que la separación no hacía falta — y es al
revés: **la clase separada no existe porque el SQL fuera a cambiar, sino
porque todo lo de alrededor sí cambia**. Vale la pena dejarlo escrito, porque
es la objeción que va a hacer cualquiera que compare los dos archivos.

## D3 — Los mensajes del usuario se escriben una vez, en un archivo aparte

**Alternativa descartada:** que cada traductor de errores redactara sus
propios mensajes (que es como quedó la v2, con un solo motor).
**Decisión:** `conflictos.php` con las tres frases, y dos traductores que solo
deciden **cuál** corresponde.
**Por qué:** si cada motor redactara, la MISMA petición contra dos motores le
diría cosas distintas al usuario. El contrato de la API promete un texto, no
un código de error — y un contrato que cambia según lo que haya detrás no es
un contrato.

**Y hay una lección de diseño más general escondida aquí.** El archivo de la
v2 hacía dos cosas: reconocer y redactar. Nadie lo notó mientras hubo un
motor, porque no había forma de notarlo. El segundo motor no *creó* el
problema: lo **reveló**. Esa es la razón real por la que vale la pena hacer
una versión que no agrega funcionalidad.

## D4 — El motor activo se publica, y el front lo muestra

**Alternativas descartadas:** no publicarlo (y comprobar el motor mirando el
compose o los logs), o mostrarlo grande en la cabecera.
**Decisión:** un campo `motor` en `GET /`, y una etiqueta pequeña en el pie de
las pantallas.
**Por qué no ocultarlo:** la versión promete que todo funciona igual contra
los dos motores. Una promesa así hay que poder comprobarla, y «vaya a leer el
archivo de configuración» no es una comprobación: es un acto de fe. Con el
dato publicado, la prueba automática puede verificar **que está corriendo
contra el motor que se pidió** — sin eso, las dos corridas podrían estar
dando contra la misma base sin que nadie lo notara.

**Por qué en el pie y en letra pequeña:** al usuario no le importa, y puede
usar el sistema entero sin mirarlo. Ponerlo grande habría sugerido que es un
dato del negocio, y no lo es.

**Lo que costó, y es interesante:** al mostrar el nombre del motor, la prueba
de humo empezó a fallar — la regla de la v2 decía que ninguna pantalla puede
nombrar un motor. Chocaron una regla vieja y una necesidad nueva.

Se resolvió **haciendo la regla más precisa, no quitándola**: el nombre del
motor tiene ahora exactamente un sitio permitido, y la prueba comprueba que
aparezca **una sola vez y en el pie**. La comprobación quedó más estricta que
antes, no más floja. Cuando una regla estorba, casi siempre es que estaba
redactada de forma más gruesa de lo que hacía falta.

## D5 — Cambiar de motor exige reiniciar la API

**Alternativa descartada:** leer `MOTOR` en cada petición, para poder cambiar
en caliente.
**Decisión:** se lee al arrancar.
**Por qué:** con la lectura por petición, dos peticiones simultáneas podrían
usar motores distintos y dejar la mitad de los datos en cada base. Sería un
error rarísimo de reproducir y catastrófico de encontrar.

Y además, «cambiar de motor sin reiniciar» no es un requisito de nadie: en un
sistema real el motor se escoge al desplegar y no se toca más.

## D6 — Las dos bases son independientes y NO se sincronizan

**Alternativa descartada:** replicar los datos de una en otra, o migrar.
**Decisión:** dos bases, cada una con los mismos datos de ejemplo, sin
relación entre sí.
**Por qué:** lo que la versión demuestra es que **el programa** funciona
contra las dos, no que las dos sean la misma. Sincronizarlas habría metido un
problema entero —replicación— que no es contenido de este curso y que habría
tapado lo que sí lo es.

**Está declarado en el alcance para que nadie se confunda:** cree un cliente
con `MOTOR=mariadb`, cambie a `postgres`, y no lo va a ver. No es un error: es
otra base.

## D7 — Se aprovechó para unificar un nombre de método

**Qué pasó:** al escribir cada repositorio por segunda vez se hizo evidente
que `producto` llamaba `obtenerPorCodigo` a su método de lectura, mientras los
cinco recursos de la v2 lo llamaban `obtenerPorClave`. Dos nombres para lo
mismo, en seis interfaces, sin ninguna razón.

**Decisión:** unificar en `obtenerPorClave`.
**Por qué ese nombre y no el otro:** dos de los seis recursos tienen una llave
numérica (`id`), y llamarla «código» habría sido mentir.

**Por qué se hizo en esta versión y no se dejó pasar:** porque es exactamente
el tipo de cosa que el segundo motor saca a la luz. Escribir algo una vez
esconde las inconsistencias; escribirlo dos veces las pone al lado. Dejarla
habría significado que cada estudiante que llegue a la v4 tenga que aprenderse
la excepción.

**El riesgo, y por qué es aceptable:** toca un servicio, y el criterio 13 dice
que ningún servicio debería cambiar. Se declara **explícitamente** en ese
criterio en vez de esconderlo — un criterio redactado para dar verde no sirve
de nada. Es un cambio de nombre interno: el contrato HTTP no se movió.
