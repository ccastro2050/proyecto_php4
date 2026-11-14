# Tutorial — Publicar el sistema en internet (PHP + MariaDB)

**Hasta aquí el proyecto corrió en su máquina**, con un solo `docker compose up`:
seis contenedores que nacen juntos. **Aquí sale a internet** — y con una
condición que no es menor: **sin cambiar de motor**. Lo que se publica es la
misma MariaDB que usted entrega.

| La pieza | Dónde va a quedar |
|---|---|
| **La base de datos** — MariaDB | **alwaysdata** |
| **La API** — PHP | **alwaysdata**, un sitio |
| **El front** — PHP | **alwaysdata**, otro sitio |

> **Todo en un solo proveedor, y sin Docker.** No porque Docker estorbe, sino
> porque aquí no hace falta: alwaysdata **ya tiene PHP instalado**. Se suben
> los archivos y corren. El `docker-compose.yml` del repositorio sigue siendo
> para su máquina.

---

## Por qué alwaysdata, y no los otros

**Esta parte vale más que el despliegue**, porque es el trabajo que un
ingeniero hace antes de escoger dónde pone un sistema. Las opciones se
revisaron el **13 de noviembre de 2026**, una por una.

**El problema no era PHP: era la base de datos.** Este proyecto pone lógica
*dentro* de MariaDB —seis disparadores que calculan subtotales, totales y
stock, y dieciséis procedimientos—, y eso es justo lo que casi ningún
hospedaje gratuito permite.

| Hospedaje | Qué pasó |
|---|---|
| **InfinityFree** | Corre PHP y MySQL, pero su plan gratuito **desactiva llaves foráneas, procedimientos y disparadores**. Y hay algo peor para nosotros: su sistema anti-robots **responde una página de JavaScript a cualquier programa** que llame al sitio — o sea que **una API alojada ahí deja de ser una API** |
| **TiDB Cloud** | Gratis y compatible con MySQL, pero **no tiene disparadores ni procedimientos** |
| **Aiven** | Tenía MySQL gratuito. Lo está retirando: desde el **6 de noviembre de 2026** no deja crear nuevos y el **21 de diciembre** apaga los que quedan |
| **PlanetScale** | Cerró su plan gratuito en 2024 |
| **Render** | Corre cualquier `Dockerfile`, pero **no ofrece MySQL**: habría que irse a PostgreSQL, y entonces ya no sería lo que los estudiantes entregan |
| **alwaysdata** | **1 GB gratis, de por vida, sin tarjeta. MariaDB administrada con `GRANT ALL` sobre sus bases de datos — y hospedaje PHP en la misma cuenta** |

> **La lección, que es del oficio y no del tutorial:** PostgreSQL tiene varios
> hospedajes gratuitos que duran porque hay empresas compitiendo ahí. **En
> MySQL esa competencia se apagó** — PlanetScale cobró, Aiven cerró—, y lo que
> queda gratis casi siempre viene recortado: sin rutinas, sin llaves foráneas,
> o sin dejar que nadie llame a su servidor.
>
> **Quien ponga la lógica dentro de la base de datos tiene que averiguar esto
> ANTES de elegir dónde publicar.** No después.

---

## Lo que cuesta: nada

| | El plan `Free` de alwaysdata |
|---|---|
| **Disco** | 1 GB, de por vida |
| **Memoria** | 256 MB · ¼ de CPU |
| **Bases de datos y sitios** | sin límite de cantidad |
| **Tarjeta de crédito** | **ninguna** |
| **La letra menuda** | es para **uso personal**, no comercial, y el espacio de las bases de datos es una décima parte del total |

---

## Los pasos

| | Qué se hace | Dónde |
|---|---|---|
| **1** | La cuenta | alwaysdata |
| **2** | La base de datos MariaDB | el panel |
| **3** | Preparar el `.sql` — **lo único que hay que cambiarle** | su editor |
| **4** | Importar el esquema y comprobar las rutinas | phpMyAdmin |
| **5** | Subir la API y el front | FTP |
| **6** | La configuración de la conexión | el servidor |
| **7** | La prueba del sistema completo | el navegador |

---

## Paso 1 — La cuenta

```
https://www.alwaysdata.com/en/offers/
```

![Los tres planes de alwaysdata](docs/tutorial_publicar/img/01_alwaysdata_planes.png)

**La tarjeta de la izquierda: `Free`, 0 €/mes.** No la del medio: ese
*«30 days free»* es la prueba de un plan de pago, y después cobra.

`Subscribe` lleva al registro:

![El registro](docs/tutorial_publicar/img/02_alwaysdata_registro.png)

> **Mire la dirección: termina en `?p=2012`.** Ese número es el plan gratuito,
> y es lo que lo mantiene enganchado mientras usted se registra. Si usted ya
> tiene cuenta, entre por **`I already have an account. Log in`** desde esta
> misma pantalla.

### 1.1 · La selección de oferta

![La selección de oferta, con el plan gratuito](docs/tutorial_publicar/img/03_alwaysdata_plan_gratis.png)

| La casilla | **Lo que se escribe** | Para qué |
|---|---|---|
| **`Producto`** | **`Gratis (1 GB de disco, 256 MB de RAM, 0,25 de CPU)`** | **Viene marcado «Pequeño (50 GB)», que es de pago.** Hay que cambiarlo a mano |
| `Nombre` | **`phpfacturas`** | Define el subdominio: `phpfacturas.alwaysdata.net`. Y también el prefijo de sus bases de datos |
| `Contraseña` | una suya | **Es la misma para FTP, MySQL y SSH.** Anótela |
| Las dos casillas de la derecha | marcadas | |

![La cuenta creada](docs/tutorial_publicar/img/04_alwaysdata_cuenta_creada.png)

![El panel de la cuenta](docs/tutorial_publicar/img/05_alwaysdata_panel.png)

---

## Paso 2 — La base de datos

**Menú de la izquierda: `Bases de datos` → `MySQL`.**

![El panel de MySQL, todavía sin bases de datos](docs/tutorial_publicar/img/06_alwaysdata_mysql_vacio.png)

**Lea el recuadro amarillo, que trae los tres datos que va a necesitar después:**

```
Servidor MySQL: mysql-phpfacturas.alwaysdata.net
Versión: 11.8 (mariadb)
phpMyAdmin
```

| Lo que dice | Por qué importa |
|---|---|
| **`mariadb`, no `mysql`** | es **el mismo motor** del `docker-compose.yml` (`mariadb:11`). No hay que traducir nada |
| **`11.8`** | la misma serie mayor que en su máquina |
| El servidor es **un nombre público** | `mysql-phpfacturas.alwaysdata.net` se resuelve desde internet — eso servirá si algún día la API se va a otro hospedaje |

**`+ Agregar una base de datos`:**

| La casilla | **Lo que se escribe** | Para qué |
|---|---|---|
| `Nombre` | **`phpfacturas_bdfacturas`** | el panel **obliga** al prefijo de la cuenta: no puede llamarse `bdfacturas` a secas |
| `Permisos` | **`todos los derechos`** | sin esto no se pueden crear disparadores ni procedimientos |

![La base de datos creada](docs/tutorial_publicar/img/07_alwaysdata_bd_creada.png)

---

## Paso 3 — Preparar el `.sql`

**Y aquí está lo único que hay que cambiarle al script del repositorio.** Son
dos renglones:

```sql
CREATE DATABASE IF NOT EXISTS bdfacturas_mariadb_local ...;
USE bdfacturas_mariadb_local;
```

| Por qué estorban | |
|---|---|
| **`CREATE DATABASE`** | la base de datos **ya existe** —la creó usted en el paso 2— y además **un usuario de hospedaje compartido no tiene permiso para crear bases de datos** |
| **`USE`** | el nombre real lleva el prefijo de la cuenta: `phpfacturas_bdfacturas`, no el del portátil |

**El repositorio ya trae la variante lista**, para no editar nada a mano:

```
db/mariadb/init_hospedaje.sql
```

Es **idéntico** a `db/mariadb/init.sql` salvo esos dos renglones. Mismas tablas,
mismas llaves foráneas, mismos seis disparadores, mismos dieciséis
procedimientos y los mismos datos.

### La lista de chequeo, para cuando el script sea el suyo

**Un estudiante que publique su propio proyecto tiene que mirar cuatro cosas
en su `.sql`:**

| Qué buscar | Por qué quitarlo |
|---|---|
| `CREATE DATABASE ...` | el hospedaje ya la creó, y su usuario no puede crear otras |
| `USE ...` | el nombre real lleva prefijo |
| **`DEFINER=`root`@`localhost`**` | si exportó con phpMyAdmin o Workbench, **los procedimientos salen marcados a nombre de `root`** — y su usuario no puede crear objetos a nombre de otro. El script muere ahí |
| `CREATE USER` / `GRANT` | los usuarios se crean desde el panel, no desde el script |

**Todo lo demás se sube tal cual.**

---

## Paso 4 — Importar, y la prueba que decide todo

**En el panel, el enlace `phpMyAdmin` del recuadro amarillo.** Entra con el
usuario **`phpfacturas`** y la contraseña de la cuenta.

![phpMyAdmin, ya adentro](docs/tutorial_publicar/img/08_phpmyadmin_dentro.png)

### 4.1 · El error que todo el mundo comete primero

**Si usted va directo a la pestaña `Import`, el título dice
«Importing into the current server»** — al *servidor*, no a una base de datos.
Y como le quitamos el `USE`, el script no sabe dónde crear nada:

![El error 1046](docs/tutorial_publicar/img/09_error_no_database_selected.png)

```
#1046 - No database selected
```

> **No es un error del script ni del hospedaje: es de dónde estaba parado.**
> Primero se hace clic en **`phpfacturas_bdfacturas`**, en la columna
> izquierda, y se comprueba que la barra gris de arriba diga
> **`Database: phpfacturas_bdfacturas`**. Ahí sí, `Import`.

### 4.2 · La importación

`Choose File` → `init_hospedaje.sql` → **`Import`**.

![97 sentencias ejecutadas](docs/tutorial_publicar/img/10_import_97_sentencias.png)

```
Import has been successfully finished, 97 queries executed. (init_hospedaje.sql)
```

### 4.3 · Comprobar que las rutinas de verdad entraron

**Este es el momento que justifica haber escogido alwaysdata**, así que no se
salte la comprobación.

**Pestaña `Routines`** — dieciséis procedimientos:

![Los procedimientos](docs/tutorial_publicar/img/11_procedimientos.png)

**Pestaña `Triggers`** — seis disparadores sobre `productosporfactura`:

![Los disparadores](docs/tutorial_publicar/img/12_disparadores.png)

```
trg_prodfact_before_insert   BEFORE  INSERT
trg_prodfact_after_insert    AFTER   INSERT
trg_prodfact_before_update   BEFORE  UPDATE
trg_prodfact_after_update    AFTER   UPDATE
trg_prodfact_before_delete   BEFORE  DELETE
trg_prodfact_after_delete    AFTER   DELETE
```

> **Esos seis son los que InfinityFree no habría dejado crear**, y sin ellos
> la facturación no funciona: el subtotal, el total y el descuento de stock
> **los calcula la base de datos**, no el PHP.

> **Y un detalle del script que vale leer:** los disparadores se crean,
> después se **borran** para sembrar los datos de ejemplo —si no, recalcularían
> los subtotales que ya traen— y al final **se vuelven a crear**. Por eso
> aparecen dos veces en el archivo.

---

> **En construcción.** Los pasos 5 a 7 —subir la API y el front por FTP, la
> configuración de la conexión y la prueba del sistema completo— se escriben a
> medida que se ejecutan: este documento no describe nada que no se haya hecho
> de verdad.
