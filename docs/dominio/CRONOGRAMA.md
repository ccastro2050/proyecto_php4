# Cronograma — la ruta de PHP, versión por versión

> **Qué es este documento.** Cuándo se construyó cada versión, qué tomó cada
> una y **qué se aprendió del ritmo real** — no del que se planeó.
>
> Las fechas salen del historial de los cuatro repositorios, no de la
> memoria: `git log --reverse --format=%ad`.
>
> Versión 1.0 · 22 de octubre de 2026.

---

## 1. Lo que pasó de verdad

| Versión | Repositorio | Primer commit | Último | Commits |
|---|---|---|---|---|
| **v1** — producto contra MariaDB, con su front | `proyecto_php1` | 7 de agosto | 17 de octubre | **92** |
| **v2** — seis recursos, llaves foráneas, maestro-detalle | `proyecto_php2` | 4 de septiembre | 17 de octubre | 35 |
| **v3** — el segundo motor, PostgreSQL | `proyecto_php3` | 4 de septiembre | 17 de octubre | 34 |
| **v4** — tercer motor, control de acceso y tablero | `proyecto_php4` | 4 de septiembre | **21 de octubre** | 46 |

---

## 2. Lo que esas fechas enseñan

**La v1 tomó casi un mes sola, y las otras tres arrancaron el mismo día.**
Eso no es un error del cronograma: es lo que pasa cuando la primera versión
tiene que resolver, además del CRUD, **todo lo que no es el CRUD** — el
compose, la imagen de PHP, el enrutador, las capas, el front, la convención
de los sobres y la política de errores.

| | |
|---|---|
| **La v1** | 92 commits para **un** recurso. Ahí se decidió la forma de todo lo demás |
| **La v2** | 35 commits para **cinco** recursos más la facturación. Fueron más baratos porque el molde ya existía |
| **La v3** | 34 commits y **ni una funcionalidad nueva**: solo el segundo motor. Es la versión que comprueba si la v1 cortó bien las capas |
| **La v4** | 46 commits: el tercer motor, el control de acceso completo, `usuario-con-roles`, las diez consultas y el tablero |

> **La v3 es la que más dice de la arquitectura**, y es la que menos se ve.
> Si las capas estaban bien cortadas, agregar PostgreSQL no toca ni un
> controlador ni una pantalla. Si no, se nota ahí — y ya es tarde.

**Los últimos commits de las cuatro son del 17 y el 21 de octubre**, y eso
tiene una explicación que vale la pena: una versión cerrada **no se congela**.
Se le corrigen documentos, se le actualizan dependencias —el parche de PHP
del 21 de octubre tocó la v4— y se le arreglan los errores que aparecen
cuando una versión posterior los destapa.

---

## 3. El ritmo, y por qué importa

| | |
|---|---|
| Duración total de la ruta | **7 de agosto → 21 de octubre**, unas once semanas |
| Commits en total | **207** |
| Promedio | unos 19 commits por semana |

> **El número que importa no es el total: es que no haya huecos largos.** Un
> repositorio con 200 commits en tres días y nada el resto del semestre
> cuenta una historia distinta —y peor— que uno con 20 commits por semana.
> Por eso la sustentación individual mira **los commits de cada quien**, no
> solo el resultado.

---

## 4. Lo que cada versión dejó listo para la siguiente

```
v1 ──▶ v2 ──▶ v3 ──▶ v4
```

| De | Para | Qué le dejó |
|---|---|---|
| v1 → v2 | Las capas, el sobre de las respuestas, la política de errores y el front | Agregar cinco recursos fue repetir un molde |
| v2 → v3 | Las interfaces de repositorio | Entró un motor nuevo **sin tocar** controladores ni pantallas |
| v3 → v4 | La fábrica con su `match` | El tercer motor fue una rama más en el mismo sitio |
| v4 → — | El aplicativo completo | Aquí cierra la ruta: no hay v5 |

**Y las versiones son acumulativas:** la v4 contiene a las otras tres. Por eso
cada entrega exige **regresión** — que lo de antes siga pasando.

---

## 5. Cómo repetir estas mediciones

```powershell
# Cuándo empezó y cuándo terminó cada repositorio
git log --reverse --format="%ad" --date=short | Select-Object -First 1
git log -1 --format="%ad" --date=short

# Cuántos commits
git rev-list --count HEAD

# El ritmo, semana por semana
git log --format="%ad" --date=format:"%Y-%m" | Group-Object | Sort-Object Name
```
