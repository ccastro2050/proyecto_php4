# Datos de prueba — de dónde salió cada fila

> **Qué es este documento.** Qué trae la base de datos cuando se levanta por
> primera vez, **de dónde salió cada dato** y cuáles están puestos a propósito
> para que algo se pueda comprobar.
>
> **La regla que rige aquí:** un dato sembrado o viene de una fuente, o se
> anuncia como inventado. Lo que no se vale es que el estudiante no sepa
> cuál es cuál.
>
> Versión 1.0 · 22 de octubre de 2026.

---

## 1. Lo que siembra el script

Contado de `db/mariadb/init.sql` —los otros dos motores siembran lo mismo—:

| Tabla | Filas | De dónde salen |
|---|---|---|
| `empresa` | 3 | Inventadas, con nombres verosímiles de comercio colombiano |
| `persona` | 6 | Inventadas: nombre, correo y teléfono con formato colombiano |
| `producto` | 8 | Inventados: equipos de cómputo con precios **en pesos y realistas** |
| `cliente` | 4 | Derivadas de `persona`, con su cupo de crédito |
| `vendedor` | 3 | Derivadas de `persona`, con carné y dirección |
| `factura` | 6 | Inventadas, con su detalle |
| `productosporfactura` | 12 | Los renglones de esas seis facturas |
| `rol` | 5 | Administrador · Vendedor · Cajero · Contador · Cliente |
| `ruta` | 15 | Las interfaces del sistema: `/home`, `/factura`, `/usuario`… |
| `usuario` | 8 | Ocho cuentas, **con contraseña conocida** (§3) |
| `rol_usuario` | 21 | Qué roles tiene cada cuenta |
| `rutarol` | 25 | Qué rutas alcanza cada rol |

---

## 2. Las filas que están puestas para que algo se pueda comprobar

Éstas no son relleno: cada una existe porque **sin ella un criterio no se
puede verificar**.

| Lo sembrado | Para qué está |
|---|---|
| Un `cliente` con `fkcodempresa` en **`null`** | Para que el formulario tenga que ofrecer «(ninguna)» y la consulta 10 use `LEFT JOIN`. Con `INNER JOIN` ese cliente desaparecería del reporte sin que nadie lo notara |
| **Un hueco en los id de `cliente`**: 1, 2, 3 y 5 | Para que nadie suponga que el id es consecutivo ni que coincide con la posición en la lista |
| Una misma `persona` que es **cliente y vendedor** | Para que se vea por qué el nombre vive en `persona` y no en las otras dos tablas |
| Stock **bajo** en algunos productos | Para poder provocar el rechazo por stock insuficiente sin tener que facturar miles de unidades |
| Tres cuentas con alcance **distinto** | Para comprobar el 403 sin tocar la base de datos (§3) |
| `AUTO_INCREMENT` adelantado en `cliente`, `vendedor` y `factura` | Para que la primera fila que cree el estudiante **no choque** con las sembradas |

---

## 3. Las ocho cuentas, con su contraseña

**Y las contraseñas están escritas aquí a propósito.** De un hash no se puede
volver a la clave —eso es lo que lo hace un hash— y sin la clave no se
comprueba un solo criterio del control de acceso.

| Correo | Contraseña | Roles |
|---|---|---|
| `admin@correo.com` | `admin123` | Administrador |
| `vendedor1@correo.com` | `vendedor123` | Vendedor · Cajero |
| `jefe@correo.com` | `jefe123` | Administrador · Cajero · Contador |
| `cliente1@correo.com` | `cliente123` | Cliente |
| `test_encript@correo.com` | `test123` | Administrador |
| `nuevo@correo.com` | `nuevo123` | Administrador · Vendedor · Cajero |
| `carlos.castro@usbmed.edu.co` | `carlos123` | los cinco roles |
| `carloscastro5033@correo.itm.edu.co` | `carlos123` | los cinco roles |

**Las tres que importan para probar el control de acceso:**

| Cuenta | Alcanza |
|---|---|
| `admin@correo.com` | las **15** rutas |
| `vendedor1@correo.com` | solo `/home`, `/factura` y `/cliente` |
| `cliente1@correo.com` | solo `/home` y `/producto` |

Con esas tres se comprueba el 403 sin tocar nada: entre como `vendedor1`,
pida `/api/usuario` y tiene que responder **403**, no 401.

> **Dos cosas del hash que se ven en estos datos:**
>
> 1. Las dos cuentas de `carlos.castro` comparten contraseña y **sus hash no
>    se parecen**: bcrypt lleva *salt* adentro. Sin él, dos hash iguales
>    delatarían que esas dos personas usan la misma clave.
> 2. El hash empieza por `$2y$12$`: `2y` es la variante de PHP y **12 es el
>    costo**. Cada punto de costo duplica el tiempo de cálculo.

> **Una nota de historia, porque explica un comentario del código:** hasta
> octubre de 2026 dos de estas filas tenían la contraseña **en texto plano** y
> de otras cinco nadie sabía la clave. El único que podía entrar era `admin`.
> `password_verify` devuelve `false` ante un hash malformado **sin lanzar
> nada**, así que la API respondía «no coincide» en vez de caerse — y ese
> comportamiento se dejó: un dato malo en la base de datos no puede tumbar la
> API.

---

## 4. Lo que es inventado, dicho sin rodeos

| | |
|---|---|
| **Inventado** | Los nombres de personas y empresas, los correos, los teléfonos, las direcciones, los carnés y los cupos de crédito |
| **Verosímil pero inventado** | Los productos y sus precios: son equipos reales con precios de orden correcto, no una lista de precios de nadie |
| **Real** | La estructura: las once tablas, sus llaves y sus restricciones vienen del modelo del curso |

**No hay datos de personas reales.** Las dos cuentas con correo institucional
son del profesor, y están para poder entrar con el correo que uno recuerda.

---

## 5. La base de datos que usted tiene hoy ≠ la que siembra el script

Y conviene saberlo antes de confundirse:

| Tabla | Siembra el script | Hay hoy (22 de octubre) |
|---|---|---|
| `producto` | 8 | **9** |
| `cliente` | 4 | **13** |
| `vendedor` | 3 | **11** |
| `factura` | 6 | **11** |
| `ruta` | 15 | **16** |

La diferencia son las filas creadas **usando el sistema**: pruebas de clase,
peticiones de Postman, y tres que se crearon a propósito para que las
consultas «de lo que no está» tuvieran algo que mostrar —un producto que
nadie compró, una factura anulada y una ruta sin rol—.

**Para volver exactamente a los datos sembrados** hay que recrear el volumen,
y eso **borra** lo que haya:

```powershell
docker compose down -v      # ⚠️ borra los volúmenes: se pierde todo lo creado
docker compose up -d
```

> **`-v` es la bandera peligrosa.** `docker compose down` a secas apaga los
> contenedores y **deja los datos**; con `-v` se lleva los volúmenes. Si lo
> que quiere es apagar, nunca le ponga `-v`.

---

## 6. Dónde está cada cosa

| Qué | Dónde |
|---|---|
| El script con la siembra | `db/mariadb/init.sql` · `db/postgres/init.sql` · `db/sqlserver/bdfacturas.sql` |
| Qué tabla es cada una | [`DISENO_BD.md`](DISENO_BD.md) |
| Qué reglas protegen estos datos | [`REGLAS_DE_NEGOCIO.md`](REGLAS_DE_NEGOCIO.md) |
| Las peticiones que los usan | `postman/coleccion_v4.postman_collection.json` |
