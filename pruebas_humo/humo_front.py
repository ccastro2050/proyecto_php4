# -*- coding: utf-8 -*-
"""Prueba de humo del FRONT de la v4 (PHP, puerto 8088).

QUÉ COMPRUEBA, Y POR QUÉ AQUÍ SE PUEDE COMPROBAR TODO
=====================================================

Este front está hecho de formularios HTML corrientes: cada botón manda un POST
que un guion puede enviar igual que lo manda el navegador. Por eso esta prueba
llega hasta el final, incluida la factura completa.

Lo que se comprueba, y en negrita lo que la v1 no tenía:

  1. cada pantalla responde por su dirección propia, y las hojas de estilo
     llegan de verdad —que no es lo mismo—;
  2. el menú lleva a las seis secciones;
  3. lo que la pantalla muestra es lo que la API devolvió;
  4. la pantalla no le habla al usuario en jerga — y el nombre del motor,
     que la v3 sí muestra, aparece **una sola vez y en el pie**;
  5. el recorrido de una ficha: agregar, los dos botones de guardar, eliminar;
  6. **la integridad**: eliminar una persona que ya es cliente, y crear un
     cliente apuntando a una persona que no existe. Las dos veces la pantalla
     tiene que explicar el motivo en español, sin números de estado;
  7. **maestro-detalle**: crear una factura con dos renglones y comprobar que
     el total lo calculó la base y que el stock bajó;
  8. **anular**: la factura sigue existiendo y el stock vuelve;
  9. y la prueba de los dos procesos: con la API apagada la pantalla sigue en
     pie, con su aviso y sin un solo dato.

LO QUE LA v4 AGREGA
===================

Nada. Ni una línea de comprobación nueva respecto de la v3 — solo que ahora
hay un motor más contra el que correrlo:

    python pruebas_humo/humo_front.py                    contra el motor activo
    python pruebas_humo/humo_los_tres_motores.py         contra los tres

**Que este archivo casi no haya cambiado en dos versiones es el resultado.**
El guion no sabe nada de motores: llena formularios, oprime botones y lee la
pantalla. Si tuviera que saber contra cuál está corriendo para funcionar,
querría decir que el motor se filtró hasta la pantalla.

Uso:  python pruebas_humo/humo_front.py     (parado en la raíz del proyecto)
"""
import html
import http.cookiejar
import json
import os
import random
import re
import string
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

# La consola de Windows no siempre usa UTF-8, y entonces imprimir una flecha
# «→» o una comilla angular revienta el guion con un UnicodeEncodeError — un
# error que no tiene NADA que ver con lo que se está probando, y que hace
# perder media hora buscando dónde está la falla del sistema.
#
# `errors="replace"` es lo importante: si un carácter no cabe en la
# codificación de la consola, sale un signo raro y el guion sigue. Nunca se
# cae una prueba por no poder dibujar una flecha.
sys.stdout.reconfigure(encoding="utf-8", errors="replace")

FRONT = "http://localhost:8088"
API = "http://localhost:8090"
fallos = []

# Un sufijo distinto en cada corrida: si una corrida se interrumpe a la mitad
# deja fichas puestas, y la siguiente fallaría al crearlas por llave duplicada
# — un rojo que no tiene nada que ver con lo que se está probando.
SUFIJO = "".join(random.choices(string.digits, k=4))

# Contra qué motor se ESPERA estar corriendo. Lo pone `correr_contra_los_dos`
# más abajo; cuando el guion se corre a mano queda vacío y la comprobación 10
# solo verifica que la API sepa decir cuál es.
MOTOR_ESPERADO = os.environ.get("MOTOR_ESPERADO", "")

# Cómo se llama cada motor en la pantalla. El pie muestra el nombre bonito,
# no el valor de la variable de entorno: al usuario «sqlserver» no le dice
# nada y «SQL Server» sí.
ETIQUETAS_MOTOR = {
    "mariadb": "MariaDB",
    "postgres": "PostgreSQL",
    "sqlserver": "SQL Server",
}

navegador = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def ver(url):
    """Un GET, como el que hace el navegador al escribir la dirección."""
    try:
        with navegador.open(url, timeout=25) as r:
            return r.status, r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")
    except Exception as e:
        return 0, str(e)


def enviar(ruta, campos):
    """Un POST de formulario: exactamente lo que manda el botón.

    `campos` acepta listas para los renglones repetidos del detalle
    (`detalle_codigo[]`), que es como los manda un formulario de verdad.
    """
    pares = []
    for clave, valor in campos.items():
        if isinstance(valor, list):
            pares.extend((clave, v) for v in valor)
        else:
            pares.append((clave, valor))

    datos = urllib.parse.urlencode(pares).encode()
    peticion = urllib.request.Request(FRONT + ruta, data=datos)
    try:
        with navegador.open(peticion, timeout=25) as r:
            return r.status, r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")
    except Exception as e:
        return 0, str(e)


def visible(pagina):
    """El texto que el usuario VE: sin etiquetas y con las tildes de verdad.

    Comprobar sobre el HTML crudo da falsos positivos de los dos lados: un
    «500» puede estar dentro de un precio, y el aviso «no está disponible»
    llega escrito `est&#xE1;`, así que buscar la «á» literal no lo encuentra.
    """
    sin_script = re.sub(r"(?is)<(script|style)[^>]*>.*?</\1>", " ", pagina)
    sin_etiquetas = re.sub(r"<[^>]*>", " ", sin_script)
    return re.sub(r"\s+", " ", html.unescape(sin_etiquetas))


def revisar(nombre, condicion, detalle=""):
    marca = "[OK]    " if condicion else "[FALLO] "
    print(marca + nombre + " " + detalle[:150])
    if not condicion:
        fallos.append(nombre)


def api(ruta):
    """Una lectura directa a la API, para contrastar contra la pantalla."""
    codigo, texto = ver(API + ruta)
    if codigo not in (200, 204):
        return None
    return json.loads(texto) if texto else None


def esperar_api(segundos=180):
    """Acepta 200 y 204: un 204 es la API diciendo que la tabla está vacía."""
    for _ in range(segundos // 3):
        if ver(API + "/api/producto?limite=1")[0] in (200, 204):
            return True
        time.sleep(3)
    return False


if not esperar_api():
    print("La API no respondió. ¿Está levantado el sistema?")
    print("   docker compose up -d --build")
    raise SystemExit(1)

# ======================================================================
print("=== 1. Las pantallas responden, cada una por su dirección ===")
# ======================================================================
PANTALLAS = [
    ("/", "Sistema de facturas"),
    ("/facturas", "Facturas"),
    ("/productos", "Productos"),
    ("/clientes", "Clientes"),
    ("/vendedores", "Vendedores"),
    ("/personas", "Personas"),
    ("/empresas", "Empresas"),
    ("/facturas/nueva", "Nueva factura"),
    ("/productos/nuevo", "Agregar el producto"),
]
for ruta, titulo in PANTALLAS:
    c, t = ver(FRONT + ruta)
    revisar(ruta.ljust(20) + " dice «" + titulo + "»",
            c == 200 and titulo in visible(t))

c, t = ver(FRONT + "/pantalla-que-no-existe")
revisar("una dirección inventada da 404, no una página en blanco",
        c == 404 and "no existe" in visible(t))

print()
print("=== 1.b Los archivos de estilo LLEGAN ===")
# Esta sección existe porque una vez el guion estuvo en verde con la pantalla
# completamente sin estilos: comprobar el TEXTO de una página no dice si la
# página se ve. Un 200 que devuelve HTML disfrazado de CSS es el fallo exacto.
for archivo, tipo in [("/publico/bootstrap.min.css", "text/css"),
                      ("/publico/estilos.css", "text/css"),
                      ("/publico/bootstrap.bundle.min.js", "javascript")]:
    try:
        with navegador.open(FRONT + archivo, timeout=20) as r:
            codigo, contenido = r.status, r.headers.get("Content-Type", "")
    except Exception as e:
        codigo, contenido = 0, str(e)
    revisar(archivo.ljust(32) + " llega como " + tipo,
            codigo == 200 and tipo in contenido, str(contenido))

# ======================================================================
print()
print("=== 2. El menú lleva a las seis secciones ===")
# ======================================================================
c, inicio = ver(FRONT + "/")
for seccion in ("facturas", "productos", "clientes", "vendedores",
                "personas", "empresas"):
    revisar("el menú tiene /" + seccion, 'href="/' + seccion + '"' in inicio)

revisar("y NINGUNA dirección tiene el nombre de la tabla como parámetro",
        "{tabla}" not in inicio and "?tabla=" not in inicio)

# ======================================================================
print()
print("=== 3. Las pantallas traen los datos que dio la API ===")
# ======================================================================
COLUMNAS = {
    "/productos":  ("Código", "Nombre", "Stock", "Valor unitario"),
    "/clientes":   ("Id", "Cupo de crédito", "Persona", "Empresa"),
    "/vendedores": ("Id", "Carné", "Dirección", "Persona"),
    "/personas":   ("Código", "Nombre", "Correo", "Teléfono"),
    "/empresas":   ("Código", "Nombre"),
    "/facturas":   ("Número", "Fecha", "Cliente", "Total", "Estado"),
}
for ruta, etiquetas in COLUMNAS.items():
    c, t = ver(FRONT + ruta)
    faltan = [e for e in etiquetas if e not in visible(t)]
    revisar(ruta.ljust(20) + " trae sus columnas", not faltan, str(faltan))

# El contraste que importa: lo que dice la API es lo que se ve en pantalla.
sobre = api("/api/empresa?limite=3")
codigos = [d["codigo"] for d in sobre["datos"]] if sobre else []
c, t = ver(FRONT + "/empresas")
revisar("los códigos que devolvió la API se ven en la pantalla",
        all(x in visible(t) for x in codigos), str(codigos))

# ======================================================================
print()
print("=== 4. Lo que la pantalla NO debe decirle al usuario ===")
# ======================================================================
# La jerga se busca como TOKEN TÉCNICO, no como palabra suelta: «producto» y
# «factura» son nombres de tabla Y palabras que el usuario dice todos los
# días. Jerga de verdad es la ruta de la API, los verbos y los nombres de
# columna.
#
# FÍJESE EN QUE LOS NOMBRES DE LOS MOTORES NO ESTÁN EN ESTA LISTA, y no es
# porque la regla se hubiera aflojado. Es que la v3 les dio UN sitio
# permitido: la etiqueta del pie de página, que existe para poder comprobar
# contra qué motor está corriendo el sistema. Con tres motores la regla no
# cambió: sigue siendo una sola vez, y sigue siendo en el pie.
#
# Cuando una regla y una necesidad nueva chocan, hay dos salidas: quitar la
# regla, o hacerla más precisa. Aquí se hizo lo segundo — la comprobación de
# abajo es más estricta que la de antes, no más floja: exige que el nombre del
# motor aparezca **una sola vez y en el pie**.
JERGA = ["PUT", "PATCH", "DELETE", "/api/", "PDO", "SELECT",
         "endpoint", "localhost:", "fkcod", "fkid"]
for ruta, _ in PANTALLAS:
    c, t = ver(FRONT + ruta)
    visto = [j for j in JERGA if j in visible(t)]
    revisar(ruta.ljust(20) + " sin jerga", not visto, str(visto))

print()
print("=== 4.b El nombre del motor tiene UN solo sitio permitido ===")
MOTORES_VISIBLES = ("MariaDB", "PostgreSQL", "SQL Server")
for ruta, _ in PANTALLAS:
    c, t = ver(FRONT + ruta)
    texto = visible(t)
    veces = sum(texto.count(m) for m in MOTORES_VISIBLES)
    revisar(ruta.ljust(20) + " lo nombra exactamente una vez",
            veces == 1, str(veces) + " vez/veces")

    # Y esa vez tiene que estar DESPUÉS del contenido, en el pie. Si
    # apareciera en medio de la pantalla —en un aviso de error, por ejemplo—
    # el usuario estaría leyendo el nombre de un motor de base de datos, que
    # es lo que la regla prohíbe.
    pie = texto[-260:]
    revisar("  y es en el pie de página",
            any(m in pie for m in MOTORES_VISIBLES))

# ======================================================================
print()
print("=== 5. El recorrido de una ficha, botón por botón ===")
# ======================================================================
COD_EMPRESA = "EH" + SUFIJO

c, t = enviar("/empresas/nuevo", {"codigo": COD_EMPRESA, "nombre": "Empresa " + SUFIJO})
revisar("agregar la empresa " + COD_EMPRESA, "Se agregó" in visible(t))
revisar("  y ya aparece en el listado", COD_EMPRESA in visible(t))

c, t = enviar("/empresas/" + COD_EMPRESA + "/editar",
              {"verbo": "completa", "nombre": "Renombrada " + SUFIJO})
revisar("«Guardar la ficha completa» guarda", "Se guardaron" in visible(t))
revisar("  y el nombre nuevo se ve", "Renombrada " + SUFIJO in visible(t))

c, t = enviar("/empresas/" + COD_EMPRESA + "/editar",
              {"verbo": "completa", "nombre": ""})
revisar("la ficha completa con el nombre en blanco se RECHAZA",
        "Se guardaron" not in visible(t))
revisar("  y el aviso lo dice sin números de estado ni jerga",
        not any(j in visible(t) for j in ("422", "PUT", "/api/")))

# ======================================================================
print()
print("=== 6. LA INTEGRIDAD, vista desde la pantalla ===")
# ======================================================================
# Aquí está lo que la v2 viene a enseñar. Las dos veces la base dice que no,
# y la pantalla tiene que explicar por qué en español.

# 6.a — apuntar a algo que no existe
# El código va CORTO a propósito. La primera versión de esta prueba mandaba
# "NOEXISTE" + el sufijo: trece caracteres, que la validación del controlador
# rechaza con un 422 antes de que la petición llegue a la base. La prueba
# salía en rojo, pero no por lo que creía estar probando — comprobaba la
# validación de forma, no la llave foránea. Con un código de longitud válida
# la petición sí llega a la base, y ahí es donde la integridad dice que no.
c, t = enviar("/clientes/nuevo", {
    "credito": "1000", "fkcodpersona": "NX" + SUFIJO, "fkcodempresa": ""})
texto = visible(t)
revisar("crear un cliente que apunta a una persona inexistente: se rechaza",
        "Se agregó" not in texto)
revisar("  y el motivo se explica en español",
        "no existe" in texto.lower())
revisar("  sin número de estado ni jerga",
        not any(j in texto for j in ("409", "1452", "SQLSTATE", "foreign")))

# 6.b — borrar algo de lo que otros dependen
personas = api("/api/persona?limite=50")
clientes = api("/api/cliente?limite=50")
usada = clientes["datos"][0]["fkcodpersona"] if clientes else None
if usada:
    c, t = enviar("/personas/" + usada + "/eliminar", {})
    texto = visible(t)
    revisar("eliminar la persona " + usada + ", que ya es cliente: se rechaza",
            "Se eliminó" not in texto)
    revisar("  y el aviso dice qué hacer",
            "dependen" in texto.lower() or "elimine primero" in texto.lower())
    revisar("  y la persona SIGUE ahí",
            api("/api/persona/" + usada) is not None)
else:
    print("[--]    no hay clientes: no se puede probar el borrado con dependencias")

# La empresa de prueba sí se puede borrar: nadie la usa.
c, t = enviar("/empresas/" + COD_EMPRESA + "/eliminar", {})
revisar("y una empresa que NO usa nadie sí se elimina", "Se eliminó" in visible(t))
c, t = ver(FRONT + "/empresas")
revisar("  el aviso se muestra UNA vez y no se repite", "Se eliminó" not in visible(t))
revisar("  y la ficha ya no está", COD_EMPRESA not in visible(t))

# ======================================================================
print()
print("=== 7. MAESTRO-DETALLE: una factura de dos renglones ===")
# ======================================================================
productos = api("/api/producto?limite=50")["datos"]
p1, p2 = productos[0], productos[1]
stock1_antes, stock2_antes = int(p1["stock"]), int(p2["stock"])

id_cliente = clientes["datos"][0]["id"]
id_vendedor = api("/api/vendedor?limite=5")["datos"][0]["id"]

c, t = enviar("/facturas/nueva", {
    "fkidcliente": str(id_cliente),
    "fkidvendedor": str(id_vendedor),
    # Cinco casillas, como las manda el formulario: dos con producto y tres
    # vacías. Las vacías se ignoran, y eso también se está probando.
    "detalle_codigo[]":   [p1["codigo"], p2["codigo"], "", "", ""],
    "detalle_cantidad[]": ["2", "3", "", "", ""],
})
texto = visible(t)
revisar("crear la factura", "Se creó la factura" in texto)

numero = None
m = re.search(r"Se creó la factura (\d+)", texto)
if m:
    numero = int(m.group(1))
revisar("  y la pantalla lleva a su detalle", numero is not None, str(numero))

if numero:
    factura = api("/api/factura/%d" % numero)
    esperado = (2 * float(p1["valorunitario"])) + (3 * float(p2["valorunitario"]))
    revisar("  el total lo calculó la base de datos",
            abs(float(factura["total"]) - esperado) < 0.01,
            "total=%s esperado=%s" % (factura["total"], esperado))
    revisar("  y tiene exactamente los DOS renglones (las casillas vacías se ignoraron)",
            len(factura["detalle"]) == 2, str(len(factura["detalle"])))

    ahora1 = int(api("/api/producto/" + p1["codigo"])["stock"])
    ahora2 = int(api("/api/producto/" + p2["codigo"])["stock"])
    revisar("  el stock del primer producto bajó 2",
            ahora1 == stock1_antes - 2, "%d → %d" % (stock1_antes, ahora1))
    revisar("  el del segundo bajó 3",
            ahora2 == stock2_antes - 3, "%d → %d" % (stock2_antes, ahora2))

    c, t = ver(FRONT + "/facturas/%d" % numero)
    texto = visible(t)
    revisar("  la pantalla del detalle muestra los dos productos",
            p1["nombre"][:12] in texto and p2["nombre"][:12] in texto)
    revisar("  y muestra el total", "Total" in texto)

    # --- Una factura sin ningún renglón: la base se niega ---
    c, t = enviar("/facturas/nueva", {
        "fkidcliente": str(id_cliente), "fkidvendedor": str(id_vendedor),
        "detalle_codigo[]": ["", "", "", "", ""],
        "detalle_cantidad[]": ["", "", "", "", ""],
    })
    texto = visible(t)
    revisar("una factura sin renglones se rechaza", "Se creó la factura" not in texto)
    revisar("  y el motivo se explica en español",
            "al menos un producto" in texto.lower())

    # Vale la pena saber QUIÉN dijo que no, porque hay dos que podrían:
    #
    #   · el CONTROLADOR de la API, que valida la forma del body y responde
    #     422 «El detalle debe traer al menos un producto»;
    #   · y el PROCEDIMIENTO ALMACENADO, que también se niega («la factura
    #     requiere minimo 1 producto») y responde 409.
    #
    # Gana el controlador, porque está antes: la petición ni siquiera llega a
    # la base. La regla del procedimiento no sobra por eso — es la que
    # protege a la base de cualquier otro programa que le escriba sin pasar
    # por esta API. Dos defensas a distinta altura, y la de afuera responde
    # más rápido y con mejor mensaje.
    revisar("  y lo rechazó la API antes de llegar a la base",
            "requiere minimo" not in texto.lower())

    # ==================================================================
    print()
    print("=== 8. ANULAR no es eliminar ===")
    # ==================================================================
    c, t = enviar("/facturas/%d/anular" % numero, {})
    texto = visible(t)
    revisar("anular la factura %d" % numero, "Se anuló" in texto)

    factura = api("/api/factura/%d" % numero)
    revisar("  la factura SIGUE existiendo", factura is not None)
    revisar("  y quedó marcada como anulada",
            factura and factura["estado"] == "anulada", str(factura and factura["estado"]))

    vuelto1 = int(api("/api/producto/" + p1["codigo"])["stock"])
    revisar("  el stock volvió al producto",
            vuelto1 == stock1_antes, "%d → %d" % (ahora1, vuelto1))

    c, t = ver(FRONT + "/facturas/%d" % numero)
    revisar("  y la pantalla lo dice, sin ofrecer editarla",
            "anulada" in visible(t) and 'href="/facturas/%d/editar"' % numero not in t)

    c, t = enviar("/facturas/%d/anular" % numero, {})
    revisar("anularla otra vez se rechaza con su motivo",
            "ya está anulada" in visible(t) or "ya esta anulada" in visible(t))

    # Se limpia lo que esta corrida creó.
    enviar("/facturas/%d/eliminar" % numero, {})

# ======================================================================
print()
print("=== 9. LA PRUEBA DE LOS DOS PROCESOS: se apaga la API ===")
print("    (esto tarda unos segundos)")
# ======================================================================
subprocess.run(["docker", "compose", "stop", "api-facturas"],
               capture_output=True, text=True)
time.sleep(3)

c, t = ver(FRONT + "/facturas")
texto = visible(t)
revisar("la pantalla SIGUE respondiendo con la API apagada", c == 200)
revisar("  y muestra el aviso dentro de la aplicación",
        "no está disponible" in texto)
revisar("  con su menú y su marco intactos",
        "Productos" in texto and "Clientes" in texto and "Inicio" in texto)
# Ésta es LA comprobación de la constitución: MariaDB sigue encendida y con
# los datos ahí. Si el front pudiera llegar a la base por su cuenta, la tabla
# se seguiría viendo. No se ve.
revisar("  y SIN un solo dato: el front no puede llegar a la base solo",
        "Ana Torres" not in texto)

subprocess.run(["docker", "compose", "start", "api-facturas"],
               capture_output=True, text=True)
print("    API encendida otra vez; esperando a que responda…")
esperar_api(90)
c, t = ver(FRONT + "/facturas")
revisar("y al volver la API, la pantalla vuelve a traer los datos",
        "Ana Torres" in visible(t))

# ======================================================================
print()
print("=== 10. EL MOTOR ACTIVO, y que sea el que se pidió ===")
# ======================================================================
# Sin esta comprobación, las dos corridas de `--ambos` podrían estar dando
# contra la misma base sin que nadie lo notara, y el guion diría que todo
# está bien cuando en realidad no habría probado nada nuevo.
diag = api("/")
motor_real = (diag or {}).get("motor")
revisar("la API dice contra qué motor está corriendo",
        motor_real in ETIQUETAS_MOTOR, str(motor_real))

if MOTOR_ESPERADO:
    revisar("  y es el que se pidió (" + MOTOR_ESPERADO + ")",
            motor_real == MOTOR_ESPERADO, str(motor_real))

c, t = ver(FRONT + "/")
etiqueta = ETIQUETAS_MOTOR.get(motor_real, "MariaDB")
revisar("  la pantalla lo muestra en el pie", etiqueta in visible(t))

print()
if fallos:
    print("=== RESULTADO: " + str(len(fallos)) + " FALLO(S) ===")
    for f in fallos:
        print("   -", f)
    raise SystemExit(1)

print("=== RESULTADO: TODO EN VERDE contra " + etiqueta + " ===")
print()
print("Se recorrieron los seis recursos desde la PANTALLA, con los mismos")
print("POST que manda el navegador — incluidas la factura de dos renglones y")
print("las dos formas en que la base dice que no. Queda para una persona lo")
print("que un guion no ve: que se entienda. Está en 7_quickstart.md.")
