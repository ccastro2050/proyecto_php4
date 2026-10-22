# Plan de la versión 3 — el segundo motor, y ni una funcionalidad nueva

> **Qué es este documento.** Qué agrega la v3 —**nada que el usuario note**— y
> por qué ésa es justamente la versión que más dice sobre si la arquitectura
> de las dos anteriores servía.
>
> El requisito que manda está en
> [`2_spec.md`](../spec_kit/versiones/v3_segundo_motor/2_spec.md).
>
> Versión 1.0 · 22 de octubre de 2026.

---

## 0. Lo que queda al terminar la v3

**El mismo sistema, contra PostgreSQL**, y la elección es **una palabra en
una variable de entorno**.

| | v2 | v3 |
|---|---|---|
| Motores | MariaDB | **MariaDB o PostgreSQL** |
| Endpoints nuevos | — | **ninguno** |
| Pantallas nuevas | — | **ninguna** |
| Repositorios | 6 | **12** — seis por motor |
| Lo que cambió en controladores y vistas | — | **nada** |

> **Es la segunda versión seguida que no agrega una sola funcionalidad**, y
> hay que decirlo sin rodeos porque desconcierta: lo que entrega esta versión
> no es algo que el usuario pueda ver, es **una propiedad del sistema**.

---

## 1. La decisión que define la versión: **la fábrica**

Hay dos formas de escoger el motor, y una de ellas envenena el proyecto:

| | Qué sería | Qué pasa |
|---|---|---|
| **Un `if` en cada repositorio** | Cada clase pregunta qué motor hay y arma su SQL | Cada repositorio conoce los dos motores. El tercero obliga a tocar los doce |
| **Una fábrica** ✅ | Un `match` que decide **qué clase se instancia**, y el resto del sistema no se entera | El tercer motor es **una rama más** en un solo archivo |

```php
$repositorio = match (motorActivo()) {
    'postgres' => new RepositorioProductoPostgres($dsn, $usuario, $clave),
    default    => new RepositorioProductoMariaDB($dsn, $usuario, $clave),
};
```

**Eso es el principio abierto/cerrado dejando de ser una frase**: el sistema
queda abierto a un motor nuevo y cerrado a modificaciones en lo que ya
funciona.

---

## 2. El examen que esta versión le hace a las dos anteriores

La v2 cerró diciendo que si las capas estaban bien cortadas, agregar un motor
no tocaría nada de arriba. La v3 lo cobra, y **se comprueba con un `diff`**:

| Qué debió cambiar | Qué debió quedarse igual |
|---|---|
| Seis repositorios nuevos | Los seis controladores |
| El traductor de errores de PostgreSQL | Los seis servicios |
| El `match` del ensamblador | Las pantallas |
| El `docker-compose.yml` | El contrato: las mismas rutas y los mismos códigos |

> **Si algo de la derecha hubiera tenido que cambiar, la falla no sería de la
> v3: sería de la v1.** Por eso esta versión es el examen, y llega tarde —
> cuando ya hay dos versiones construidas sobre la decisión.

---

## 3. Lo que de verdad cambia entre los dos motores

Y es menos de lo que parece, pero no es cero:

| | MariaDB | PostgreSQL |
|---|---|---|
| Llave autogenerada | `AUTO_INCREMENT` | `SERIAL` |
| Recuperar el id creado | `lastInsertId()` | `RETURNING id` |
| Rechazar desde SQL | `SIGNAL SQLSTATE '45000'` | `RAISE EXCEPTION` |
| Llave duplicada | **1062** | **23505** |
| Referencia rota | **1452** | **23503** |
| Parámetro de salida de un procedimiento | variable de sesión `@salida` | la fila que devuelve el `CALL` |

**Y el requisito que amarra todo esto:** los tres conflictos de integridad
tienen que responder **el mismo texto** en los dos motores. Si el mensaje
cambiara según el motor, el front estaría viendo la diferencia — y la promesa
de la versión sería falsa.

---

## 4. El orden en que se construyó

| # | Paso | Verificar |
|---|---|---|
| 1 | El servicio de PostgreSQL en el compose, con su script | Que levante y siembre igual |
| 2 | El traductor de errores de PostgreSQL | Que 23505 y 23503 den los **mismos** mensajes que 1062 y 1452 |
| 3 | Los seis repositorios de PostgreSQL | Uno por uno, con su dialecto |
| 4 | El `match` en el ensamblador y `motorActivo()` | Que `MOTOR` decida |
| 5 | El motor en el diagnóstico y en el pie de la pantalla | Que se pueda comprobar sin mirar el compose |
| 6 | **El `diff`** | Que controladores, servicios y vistas no hayan cambiado |

---

## 5. Los tropiezos de esta versión

| | Qué pasa | Cómo se nota |
|---|---|---|
| **El dialecto que se cuela** | Un `LIMIT` o un `AUTO_INCREMENT` copiado al repositorio de PostgreSQL | Falla al primer intento — éste es de los buenos: **falla fuerte** |
| **El mensaje distinto** | Cada traductor redacta su propia frase | El criterio se incumple **sin que nada falle**: las dos respuestas son 409 |
| **El motor que no se lee** | `getenv('MOTOR')` con un valor que no está en el `match` | Cae en el `default` y usa MariaDB **sin avisar** |
| **La conexión que se abre dos veces** | Cada repositorio abre la suya | Nada falla, pero una petición que toca dos recursos abre dos conexiones |

---

## 6. Cómo se verificó

```powershell
# El mismo sistema, dos veces
$env:MOTOR = "mariadb";  docker compose up -d
$env:MOTOR = "postgres"; docker compose up -d

# Y lo que tiene que responder igual
Invoke-RestMethod http://localhost:8090/          # cambia SOLO el campo "motor"
```

| Criterio | Resultado |
|---|---|
| Los seis recursos responden igual contra los dos | ✅ |
| Los tres conflictos dicen el mismo texto | ✅ |
| Controladores, servicios y vistas sin cambios | ✅ — comprobado con `git diff` |

---

## 7. Lo que queda para la v4

El tercer motor —SQL Server—, y la pregunta más interesante: **si la v3 dejó
el sitio preparado, la lista de cambios de la v4 tiene que ser más corta que
la de la v3.** No porque se haga menos, sino porque ya no hay que inventar
dónde va cada cosa.

> Y además, lo que la v4 enciende de verdad: el **control de acceso**, que
> lleva en la base de datos desde la v1 sin que ningún código lo nombre. Ver
> [`PLAN_V4.md`](PLAN_V4.md).
