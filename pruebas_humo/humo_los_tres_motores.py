# -*- coding: utf-8 -*-
"""LA PRUEBA DE LA VERSIÓN 4: el mismo guion, contra los TRES motores.

    python pruebas_humo/humo_los_tres_motores.py

Es el de la v3 con un motor más en la lista, y esa frase es todo el resultado
de esta versión: **agregar SQL Server no obligó a repensar cómo se prueba.**

Qué hace, en tres pasos que se repiten tres veces:

  1. reinicia la API con `MOTOR=mariadb`, `postgres` o `sqlserver`, y espera a
     que responda **y confirme que está en el motor que se pidió**;
  2. corre `humo_front.py` entero, sin cambiarle una línea;
  3. compara.

`humo_front.py` no sabe nada de motores. Llena formularios, oprime botones y
lee lo que sale en la pantalla. Que dé el mismo resultado tres veces es la
demostración de que el motor no se filtra hacia arriba — porque si se
filtrara, la pantalla se comportaría distinto y el guion lo notaría.

**Y hay algo que la v3 no podía comprobar.** Entre MariaDB y PostgreSQL el
SQL de las consultas era idéntico, así que quedaba la duda razonable de si
las clases separadas por motor eran ceremonia. Con SQL Server el SQL SÍ
cambia —no existe `LIMIT`, la llave generada se lee con `OUTPUT INSERTED`—, y
aun así estas tres corridas dan lo mismo. Eso ya no se puede atribuir a la
suerte.

Al final quedan las tres bases con los datos que la prueba fue dejando. Para
volver al punto de partida:

    docker compose down -v && docker compose up -d --build

Ojo: con `down -v` SQL Server vuelve a crearse desde cero, y eso tarda más
que los otros dos — hay que darle su minuto.
"""
import json
import os
import subprocess
import sys
import time
import urllib.request

# La consola de Windows no siempre usa UTF-8; sin esto, imprimir una flecha
# revienta el guion con un error que no tiene nada que ver con lo que se prueba.
sys.stdout.reconfigure(encoding="utf-8", errors="replace")

API = "http://localhost:8090"
MOTORES = ["mariadb", "postgres", "sqlserver"]
NOMBRES = {"mariadb": "MariaDB", "postgres": "PostgreSQL", "sqlserver": "SQL Server"}


def esperar_api(motor, segundos=240):
    """Espera a que la API responda Y esté en el motor que se pidió.

    Las dos condiciones hacen falta. Esperar solo a que responda no alcanza:
    el contenedor viejo puede seguir contestando unos segundos mientras el
    nuevo arranca, y entonces la prueba correría contra el motor anterior sin
    que nadie se enterara — que es justamente el error que este guion existe
    para no cometer.
    """
    for _ in range(segundos // 2):
        try:
            with urllib.request.urlopen(API + "/", timeout=5) as r:
                if json.loads(r.read()).get("motor") == motor:
                    return True
        except Exception:
            pass
        time.sleep(2)
    return False


resultados = {}

for motor in MOTORES:
    print()
    print("=" * 70)
    print("  ARRANCANDO LA API CONTRA " + NOMBRES[motor].upper())
    print("=" * 70)

    # `--no-deps` para no reiniciar las bases: los datos se conservan entre
    # las corridas, que es lo realista. Cada corrida usa un sufijo nuevo para
    # sus fichas, así que no chocan.
    subprocess.run(
        ["docker", "compose", "up", "-d", "--no-deps", "--force-recreate",
         "api-facturas"],
        env={**os.environ, "MOTOR": motor},
        capture_output=True, text=True,
    )

    if not esperar_api(motor):
        print("  La API no llegó a responder en " + motor + ".")
        resultados[motor] = "no arrancó"
        continue

    print("  Lista. Corriendo la prueba de humo completa…")
    print()

    proceso = subprocess.run(
        [sys.executable, "pruebas_humo/humo_front.py"],
        env={**os.environ, "MOTOR_ESPERADO": motor},
    )
    resultados[motor] = "VERDE" if proceso.returncode == 0 else "ROJO"

print()
print("=" * 70)
print("  RESULTADO CONTRA LOS TRES MOTORES")
print("=" * 70)
for motor in MOTORES:
    print("  " + NOMBRES[motor].ljust(14) + resultados.get(motor, "?"))

if all(r == "VERDE" for r in resultados.values()):
    print()
    print("  Los tres en verde: el mismo guion, llenando los mismos")
    print("  formularios, obtuvo el mismo resultado contra tres motores")
    print("  que no se parecen en casi nada por dentro.")
    print()
    print("  Y fíjese en lo que NO hubo que hacer para agregar el tercero:")
    print("  tocar un controlador, un servicio o una pantalla. Seis")
    print("  repositorios más, un traductor de errores más, y una rama más")
    print("  en el ensamblador.")
    raise SystemExit(0)

print()
print("  Alguno falló. Si unos están en verde y otro en rojo, el problema")
print("  no es de las pruebas: es que algo de ESE motor se filtró hacia")
print("  arriba. Busque el SQL, el nombre de procedimiento o el código de")
print("  error que quedó fuera de una clase con apellido de motor.")
raise SystemExit(1)
