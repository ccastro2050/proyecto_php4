# Plan de trabajo — cómo se reparte este proyecto en un equipo

> **Qué es este documento.** Cómo se organizaría un equipo de tres para
> construir este sistema: quién integra, cómo se reparte el código, quién
> escribe cada documento del spec kit y qué pasa cuando algo sale mal.
>
> ⚠️ **Y lo primero, para no engañar a nadie: este repositorio NO lo
> construyó un equipo.** Lo escribió una sola persona —el profesor— y el
> historial lo dice sin que haya que creerle a este documento:
>
> ```
> git shortlog -sne
> #   21  Carlos Arturo
> #   15  Carlos Arturo Castro Castro
> #   10  ccastro2050
> #    1  Carlos Castro
> ```
>
> **47 commits, cuatro nombres de la misma persona, una sola rama (`main`).**
> Así que lo que sigue no es el acta de una reunión que ocurrió: es **el
> reparto que su equipo sí tiene que acordar**, con el ejemplo de este
> repositorio como material.
>
> Versión 1.0 · 22 de octubre de 2026.

---

## 0. Por qué esto se acuerda ANTES de escribir código

Porque **un equipo que no acuerda quién hace qué lo descubre fusionando** — y
para entonces ya hay dos formas de hacer lo mismo en el mismo proyecto y una
tarde perdida.

> **Y porque la nota es individual.** La rúbrica dice que cada estudiante
> responde por **su** rama: qué hizo, por qué, y sus commits lo respaldan. Si
> el reparto no está escrito antes, al final nadie puede demostrar qué parte
> era suya.

---

## 1. Los tres papeles

| Papel | Cuántos | Qué hace |
|---|---|---|
| **Integrador** | 1 | **El único que fusiona a `main`.** Revisa cada PR: ¿compila? ¿cumple la spec? ¿los criterios anteriores siguen pasando? Y además su parte de código |
| **Desarrollador** | los demás | Su parte del código, en **su** rama, y su PR |

> **Ser integrador no es un privilegio: es trabajo extra.** Quien integra
> revisa el trabajo de los otros y responde por lo que entra a `main`. Por eso
> también tiene su propia rama y su propia parte: no es un cargo, es un turno.

---

## 2. El reparto del código: por ARCHIVOS, no por tareas

**Ésta es la decisión que evita más conflictos**, y conviene entender por qué.

| Repartir por… | Qué pasa |
|---|---|
| **Tareas** («tú el CRUD, yo el login») | Los tres acaban tocando `index.php`, el ensamblador y la plantilla. Tres ramas, el mismo archivo, conflicto garantizado |
| **Archivos** («estos recursos son tuyos, de punta a punta») | Cada quien toca sus archivos. Los compartidos se tocan **de a uno y avisando** |

Un reparto posible para este sistema, con los números reales de este
repositorio:

| | Quién | Qué archivos |
|---|---|---|
| **A** | | `producto`, `empresa`, `persona` — sus modelos, sus 9 repositorios, sus servicios, sus controladores y sus vistas |
| **B** | | `cliente`, `vendedor`, `factura` — lo mismo, **y la factura es la pesada**: maestro-detalle, procedimientos y disparadores |
| **C** | | El control de acceso: `rol`, `ruta`, `usuario`, los dos puentes, `autorizacion/` y el tablero |

**Los archivos compartidos**, que nadie «tiene» y todos necesitan:

| Archivo | Regla |
|---|---|
| `api_facturas/index.php` | Se toca **de a uno**: se avisa, se hace el cambio y se integra antes de que otro lo toque |
| `api_facturas/servicios/ensamblador.php` | Igual: cada recurso agrega su `match` |
| `front_php/vistas/plantilla.php` | Igual: cada recurso agrega su entrada al menú |
| `db/*/init.sql` | **El más delicado.** Un conflicto aquí no es de texto: es que dos personas definieron la misma tabla distinto |

---

## 3. El flujo de ramas

```
rama-ana   ──●──●──●──╮ PR
rama-juan  ──●──●─────┤ PR     (revisa y fusiona: SOLO el integrador)
rama-sara  ──●──●──●──┤ PR
                      ▼
main       ────────●──●──●── tag v1 ──●──●── tag v2 ──…
```

| Regla | Por qué |
|---|---|
| **Nadie trabaja en `main`** | Ni un commit directo. `main` es lo que funciona |
| **Cada quien en su rama**, con su nombre | `rama-ana`, `rama-juan` — y de ahí sale la nota individual |
| **Todo entra por PR** | Y lo fusiona solo el integrador |
| **Las ramas NO se borran** | Se quedan ahí. GitHub ofrece borrarlas al aceptar el PR: no acepten esa opción |
| **La versión se cierra con `tag vN` sobre `main`** | Lo pone el integrador cuando los criterios pasan |

---

## 4. Quién escribe cada documento del spec kit

**No los escribe uno solo**, y no se reparten al azar: cada quien escribe el
documento de lo que va a construir.

| Documento | Lo escribe |
|---|---|
| `2_spec.md` | **Entre los tres.** Es el alcance: si no están los tres de acuerdo, no hay versión |
| `3_plan.md` | El integrador, con lo que cada uno aporte de su parte |
| `4_research.md` | Quien haya tomado la decisión que se documenta |
| `5_data_model.md` | Quien toque la base de datos en esa versión |
| `6_contracts.md` | **Cada quien, la parte de sus recursos.** El integrador revisa que los sobres y los códigos sean los mismos en los tres |
| `7_quickstart.md` | El integrador |
| `8_tasks.md` | **Entre los tres**, porque es el reparto |
| `9_checklist.md` | El integrador, antes de cerrar |
| `GUIA_IAn.md` | **Cada quien su prompt**, con su nombre — es parte de la nota individual |

---

## 5. Qué hacer cuando algo sale mal

| Situación | Qué se hace |
|---|---|
| Dos ramas tocaron el mismo archivo | Lo resuelve **quien abrió el PR después**, no el integrador. Quien conoce su cambio sabe cuál se queda |
| Alguien no entregó lo suyo | Se dice en la reunión, no el día de la entrega. El reparto se ajusta y queda escrito en este documento |
| El spec kit y el código no coinciden | Se para. Se averigua cuál de los dos está mal —ver [`FUENTES.md`](FUENTES.md) §2— y se corrige **uno**, no los dos a medias |
| La IA generó algo que nadie entiende | **No entra.** La rúbrica lo califica hablando, y lo que nadie puede explicar no se puede sostener |

---

## 6. Cómo se comprueba que este plan se cumplió

No con la palabra de nadie: con el historial.

```powershell
# Quién hizo cuánto
git shortlog -sne

# Las ramas, que no se borran
git branch -a

# Los commits de una persona, con sus archivos
git log --author="ana" --stat

# Que main solo tenga fusiones del integrador
git log main --merges --format="%an  %s"
```

> **En este repositorio esa comprobación da lo que da**: un solo autor, una
> sola rama, sin PR. Es el ejemplo del profesor, no una entrega de equipo — y
> por eso este documento se lee como lo que es: **el plan que su equipo tiene
> que escribir, no el acta de uno que ya ocurrió.**

---

## 7. Dónde seguir

| Qué | Dónde |
|---|---|
| Las reglas de Git del curso | `ProyectosDeAula/docs/0_METODOLOGIA.md` §4 |
| Qué entra en cada versión | `docs/spec_kit/versiones/0_mapa_versiones.md` |
| Qué manda sobre qué | [`FUENTES.md`](FUENTES.md) |
| Las preguntas de la sustentación | [`SUSTENTACION_DEL_CODIGO.md`](SUSTENTACION_DEL_CODIGO.md) |
