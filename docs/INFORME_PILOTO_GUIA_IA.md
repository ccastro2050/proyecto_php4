# Informe — Prueba piloto de la Guía de IA (Camino A: chat web)

**Fecha:** 8 de agosto de 2026
**Quién:** el profesor del curso, actuando como estudiante
**Herramienta:** DeepSeek (chat web) con **Pensamiento Profundo** activado y
búsqueda web apagada
**Qué se probó:** construir la **v1 completa** (API de facturación en PHP
puro + MariaDB) desde cero, en una carpeta propia, siguiendo
[GUIA_IA1.md](spec_kit/versiones/v1_producto_mariadb/GUIA_IA1.md) al pie de la letra — con el spec kit como única
fuente de la IA.

---

## 1. Resultado global

**La guía funciona.** El chat construyó los 12 archivos del proyecto
(compose, Dockerfile, front controller, modelo, controlador, servicio,
repositorio, interfaces, excepción y prueba de capas) siguiendo las fases de
`8_tasks.md`, sin frameworks, sin librerías, sin generar el SQL de la BD, y
con los comentarios didácticos en español. El código quedó fiel a la
arquitectura del curso; queda pendiente el smoke test final.

Lo más valioso: **las tres trampas de MariaDB documentadas en el plan
llegaron al código** (`ATTR_EMULATE_PREPARES=false`,
`MYSQL_ATTR_FOUND_ROWS=true` con su porqué comentado, y el `LIMIT` con
`PARAM_INT`). La información viajó del documento a la implementación — que
es exactamente la tesis del spec kit.

## 2. Comportamientos observados del chat

**Positivos:**

- Resumió el alcance en ≤10 líneas y esperó confirmación antes de empezar.
- Entregó los archivos de a uno y esperó el "listo" (regla 2b).
- Respetó que la BD viene dada (jamás intentó escribir un CREATE TABLE).
- Reconoció que las carpetas y archivos vacíos ya existían (no dictó mkdir).
- Cuando se le corrigió, aceptó sin discutir y reenvió el archivo completo.
- Adaptó todos los comandos y URLs a los puertos personalizados (+100).

**Negativos (los que hay que enseñar a cazar):**

1. **Metió cosas de su cosecha** en la Fase 0: un `container_name` que
   ningún documento pedía, una clave de root distinta a la del plan, y
   variables `MYSQL_*` en vez de las `MARIADB_*` del plan.
2. **Afirmó cumplimiento falso**: dijo "healthcheck tal como se describe en
   el plan" cuando el plan pide `healthcheck.sh --connect
   --innodb_initialized` y él había puesto `mysqladmin ping`. No es solo
   estética: `ping` puede reportar la BD "sana" mientras el `init.sql`
   todavía corre. **Se corrigió al señalárselo.**
3. **Entregó el mismo archivo dos veces** en un par de ocasiones (pérdida de
   hilo en conversaciones largas — el "riesgo típico" de la tabla de la
   guía). Regla práctica: si los dos son idénticos, no pasa nada; si
   difieren, vale el último.
4. **Suavizó tipos en la validación**: usó `is_numeric` donde el contrato
   exige entero (`is_int`) — un `{"stock": 7.5}` pasa y trunca en vez de
   responder 422 — y un `codigo` numérico revienta en 500 en vez de 422.
   Detectado en revisión de código; se verificará en el smoke test.

## 3. Mejoras que esta prueba le dejó a la guía

Cada tropiezo real del piloto se convirtió en un cambio de la guía:

| Tropiezo en la prueba | Cambio en la guía |
|---|---|
| El chat no puede crear carpetas ni archivos | A.2 pasos 3-4: dos comandos (uno crea las carpetas, otro los archivos vacíos) |
| El paso de copiar specs + init.sql se pasó por alto | A.2 paso 5 como tabla de copiar/pegar + verificación explícita antes de abrir el chat |
| La carpeta de specs no seguía la estructura del curso | El proyecto del estudiante replica `docs\spec_kit\` por versiones, idéntico al repo |
| Los modos del chat no estaban documentados | A.3: activar razonamiento (Pensamiento Profundo), apagar búsqueda web, verificar los 8 adjuntos |
| Quedarse varado en un error de fase mata el avance | Método nuevo: "pegue primero, ejecute cuando quiera"; las verificaciones de fase son opcionales; el punto de control real es el smoke test final |
| Choque de puertos entre el clon y el proyecto propio | Regla del prompt: el proyecto del estudiante publica los puertos con **+100** (API `8122:8022`, MariaDB `13426:3306`) y ambos conviven |
| Texto del chat pegado en la terminal (lluvia de errores "no se reconoce como cmdlet") | Advertencia en A.2: al chat texto, a la terminal SOLO comandos |
| Compose confundió el proyecto propio con el clon (misma carpeta `proyecto_php1` → mismos contenedores: se los "secuestró" al clon y reutilizó su volumen) | Regla del prompt: el compose del estudiante declara `name: mi_v1_producto` — proyecto Compose propio, con contenedores y volúmenes propios |

## 4. Estado y pendientes

- [x] Fases 0 a 6: los 12 archivos construidos y pegados.
- [ ] **Smoke test final** (`7_quickstart.md` con puertos +100): arranque
      con un solo comando, los criterios de aceptación, y el contraste
      PUT=422 / PATCH=200.

> **Nota posterior:** este piloto se corrió cuando la v1 era **solo la API**
> y el front estaba planeado para una v5. La v1 ahora incluye su pantalla
> (Artículo 1.1 de la constitución), así que son 10 criterios y no 6, y el
> andamiaje de la guía crea también `front_php\`. Lo que el piloto encontró
> —los choques de puertos, el texto pegado en la terminal, el compose que
> confundió los dos proyectos— sigue valiendo igual, y por eso el informe se
> conserva tal como se escribió.
- [ ] **Pruebas trampa de la revisión de código**: `POST` con
      `{"stock": 7.5}` y con `codigo` numérico — el contrato exige 422; si
      el código del chat responde otra cosa, se le pide la corrección
      (material didáctico de por qué el contrato y el smoke test importan).

## 5. Conclusión para el curso

El experimento valida el método: **un chat web gratuito, alimentado SOLO con
el spec kit, reconstruye la versión completa** — pero necesita un estudiante
que supervise: que lo frene cuando agrega cosas, que verifique sus
afirmaciones contra los documentos, y que corra el smoke test sin creerle.
Ese criterio de supervisión ES el aprendizaje que el curso busca: la IA
escribe rápido; el contrato lo hace cumplir el humano.
