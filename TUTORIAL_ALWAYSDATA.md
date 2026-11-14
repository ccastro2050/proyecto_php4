# Tutorial — Publicar SU proyecto en internet (PHP + MariaDB)

**Este tutorial es para que usted publique el proyecto de su equipo**, no el
del profesor. Todo lo que aquí aparece —las direcciones, los nombres de las
carpetas, la base de datos, las contraseñas— **es de la corrida de ejemplo**,
hecha con el proyecto de facturación del curso para poder tomar las capturas.
**Donde vea esos valores, ponga los suyos.**

| La pieza | Dónde va a quedar |
|---|---|
| **La base de datos** — MariaDB | **alwaysdata** |
| **La API** — PHP | **alwaysdata**, un sitio |
| **El front** — PHP | **alwaysdata**, otro sitio |

> **Todo en un solo proveedor, gratis y sin Docker.** No porque Docker
> estorbe, sino porque aquí no hace falta: alwaysdata **ya tiene PHP
> instalado**. El `docker-compose.yml` del repositorio sigue siendo para su
> máquina.

### Antes de empezar: la tabla de SUS datos

**Llene esta tabla antes del paso 1 y téngala al lado.** Cada vez que el
tutorial muestre un valor del ejemplo, usted usa el de su fila.

| Qué es | En el ejemplo | **El suyo** |
|---|---|---|
| Nombre de la cuenta (y subdominio) | `phpfacturas` → `phpfacturas.alwaysdata.net` | |
| Contraseña de la cuenta (FTP, MySQL y SSH) | *(la que puso al crearla)* | |
| Base de datos | `phpfacturas_bdfacturas` | |
| Servidor de la base de datos | `mysql-phpfacturas.alwaysdata.net` | |
| Script de la base de datos | `db/mariadb/init_hospedaje.sql` | |
| Repositorio en GitHub | `github.com/ccastro2050/proyecto_php4` | |
| Carpeta de la API dentro del repositorio | `api_facturas` | |
| Carpeta del front dentro del repositorio | `front_php` | |

### Lo que su proyecto necesita tener para que esto funcione

| | Por qué |
|---|---|
| **Estar en GitHub**, y de preferencia público | el servidor lo clona de ahí. Si es privado, tendrá que autenticarse |
| **Leer la conexión del entorno** (`getenv`), no escrita en el código | en el servidor los datos se ponen en el panel. Si su proyecto tiene la contraseña escrita adentro, **arréglelo antes de publicar** |
| **Un `index.php` que enrute** en cada aplicación | es lo que hace que el `.htaccess` del paso 7 funcione |

> **Si su proyecto es una sola aplicación** —sin API y front separados—, haga
> únicamente el sitio del paso 5.1 y sáltese el 5.2: **todo lo demás es igual.**

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

### Cómo debe quedar SU `.sql` — con ejemplos de lo que falla

**Abra su script y busque estas cinco cosas.** Cada una tumba la importación,
y el mensaje de error casi nunca dice cuál es la causa de fondo.

#### 1 · `CREATE DATABASE` — quitarlo

```sql
-- ✗ MAL: su usuario de hospedaje NO puede crear bases de datos
CREATE DATABASE IF NOT EXISTS bdfacturas_mariadb_local
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

> **El error que sale:** `#1044 - Access denied for user ... to database`.
> La base de datos ya existe: usted la creó en el paso 2.

#### 2 · `USE` — quitarlo

```sql
-- ✗ MAL: esa base de datos no existe en el servidor
USE bdfacturas_mariadb_local;
```

> **El nombre real lleva el prefijo de su cuenta** (`phpfacturas_bdfacturas`),
> y no lo escribe el script: lo escoge usted al hacer clic en la base de datos
> antes de importar.

#### 3 · El nombre de la base de datos pegado a las tablas — quitarlo

```sql
-- ✗ MAL                                   -- ✓ BIEN
INSERT INTO bdfacturas.producto ...         INSERT INTO producto ...
SELECT * FROM `bdfacturas`.`factura` ...    SELECT * FROM factura ...
```

> **Esto es lo que más se cuela** cuando el script salió de un export, y es
> fácil de arreglar: busque el nombre de su base de datos local dentro del
> archivo y bórrelo, dejando solo el nombre de la tabla.

#### 4 · `DEFINER` — quitarlo

```sql
-- ✗ MAL: phpMyAdmin y Workbench lo agregan solos al exportar
CREATE DEFINER=`root`@`localhost` PROCEDURE sp_insertar_factura ...

-- ✓ BIEN
CREATE PROCEDURE sp_insertar_factura ...
```

> **El error que sale:** `#1227 - Access denied; you need SUPER privilege`.
> Usted no es `root` en el hospedaje, y **nadie puede crear objetos a nombre
> de otro usuario.** Si su script tiene veinte procedimientos, son veinte
> `DEFINER` que hay que borrar — en VS Code, `Ctrl+H` y reemplazar
> ``DEFINER=`root`@`localhost` `` por nada.

#### 5 · `CREATE USER` y `GRANT` — quitarlos

```sql
-- ✗ MAL: los usuarios de base de datos se crean en el panel, no en el script
CREATE USER 'paradigmas'@'%' IDENTIFIED BY 'paradigmas123';
GRANT ALL PRIVILEGES ON bdfacturas.* TO 'paradigmas'@'%';
```

#### Y dos que NO hay que tocar

| | |
|---|---|
| **`DELIMITER $$`** | déjelo. phpMyAdmin lo entiende, y sin él los procedimientos se parten en pedazos |
| **Los disparadores y procedimientos** | **déjelos todos.** Son justamente lo que este hospedaje sí permite y otros no |

**Guarde el resultado con otro nombre** —en este proyecto es
`db/mariadb/init_hospedaje.sql`— y **no borre el original**: el de su máquina
sigue necesitando el `CREATE DATABASE`.

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

## Paso 5 — Los dos sitios

**El proyecto son dos programas**, así que en alwaysdata son **dos sitios**:

| El sitio | Dirección | Carpeta en el servidor |
|---|---|---|
| **El front** | `phpfacturas.alwaysdata.net` | `www/front/` |
| **La API** | `phpfacturas.alwaysdata.net/api` | `www/api/` |

> **Y fíjese en lo que NO hizo falta: un segundo dominio.** La API vive en la
> misma dirección, bajo la ruta `/api`. Eso funciona porque **todas las rutas
> de esta API empiezan por `/api/`** —`/api/producto`, `/api/sesion`,
> `/api/factura`—, así que lo que el navegador pide y lo que el enrutador
> espera coinciden exactamente.
>
> **Si su API respondiera en `/producto` a secas**, este montaje no serviría y
> tocaría pedir un subdominio aparte. Mire sus rutas antes de copiar esto.

### 5.1 · El sitio del front

**`Web` → `Sitios`.** Ya existe uno, creado con la cuenta:

![La lista con un solo sitio](docs/tutorial_publicar/img/13_sitios_uno.png)

**Ábralo con el engranaje ⚙** y cambie **una sola casilla**:

![El sitio del front, con su raíz](docs/tutorial_publicar/img/14_sitio_front_raiz.png)

| La casilla | **Lo que se escribe** | Para qué |
|---|---|---|
| `directorio raíz` | **`www/front/`** | viene en `www/`. Como vamos a tener dos aplicaciones, cada una necesita su carpeta |

### 5.2 · El sitio de la API

**`+ Agregar un sitio`:**

![El sitio nuevo, el de la API](docs/tutorial_publicar/img/15_sitio_api_nuevo.png)

| La casilla | **Lo que se escribe** |
|---|---|
| `Direcciones` | **`phpfacturas.alwaysdata.net/api`** ← con `/api` al final |
| `Amable` (tipo) | `PHP` |
| `directorio raíz` | **`www/api/`** |

**Y antes de guardar**, en la misma página, **`Variables ambientales`**:

![Las variables de entorno de la API](docs/tutorial_publicar/img/16_variables_api.png)

```
MOTOR=mariadb
DB_DSN_MARIADB=mysql:host=mysql-phpfacturas.alwaysdata.net;port=3306;dbname=phpfacturas_bdfacturas;charset=utf8mb4
DB_USUARIO=phpfacturas
DB_CLAVE=la-contraseña-de-su-cuenta
```

| La variable | Qué es |
|---|---|
| `MOTOR` | el interruptor de la v4: con cuál de los tres motores habla la API |
| `DB_DSN_MARIADB` | **dónde está la base de datos.** El host ya no es `mariadb` —el nombre del contenedor— sino el servidor de alwaysdata |
| `DB_USUARIO` · `DB_CLAVE` | las credenciales |

> **Y aquí está la diferencia que vale la clase:** esas credenciales quedaron
> **en el panel del hospedaje, no en un archivo del repositorio**. El código
> las lee con `getenv()`, igual que leía las del `docker-compose.yml`. **Por
> eso el repositorio puede ser público sin exponer nada.**

![Los dos sitios](docs/tutorial_publicar/img/17_dos_sitios.png)

---

## Paso 6 — Subir el código: el servidor clona el repositorio

**En alwaysdata hay FTP, pero no lo vamos a usar.** Hay algo mejor: **SSH**, y
con SSH el servidor se baja el código **directamente de GitHub**.

> **Por qué es mejor que arrastrar archivos:** subir por FTP son miles de
> archivos y media hora, y al día siguiente nadie se acuerda de cuáles cambió.
> Con `git`, **actualizar es un comando**, y lo que está publicado es
> exactamente lo que está en el repositorio. Ni un archivo de más.

### 6.1 · Entrar

**`Acceso remoto` → `SSH/SFTP`:**

![El panel de SSH](docs/tutorial_publicar/img/18_ssh.png)

**Y no hace falta instalar nada:** el recuadro amarillo trae el enlace
**`a través de la web`**, que abre una terminal en el navegador.

Usuario **`phpfacturas`** y la contraseña de la cuenta. **No se ve mientras la
escribe** —ni puntos ni asteriscos—: es así a propósito.

### 6.2 · Clonar

```bash
git clone https://github.com/ccastro2050/proyecto_php4.git ~/repo
```

![El repositorio clonado](docs/tutorial_publicar/img/19_git_clone.png)

```
Cloning into '/home/phpfacturas/repo'...
Receiving objects: 100% (632/632), 14.93 MiB | 25.19 MiB/s, done.
Updating files: 100% (287/287), done.
```

> **El estudiante pone aquí la dirección de SU repositorio.** Y si es privado,
> tendrá que autenticarse; lo más simple para un curso es que sea público, que
> es lo que hacemos aquí — **justamente porque las contraseñas no están
> adentro.**

### 6.3 · Los dos atajos

**El código quedó en `~/repo`, pero los sitios buscan en `~/www/api` y
`~/www/front`.** Esas dos carpetas todavía no existen:

```bash
ln -s ~/repo/api_facturas ~/www/api
ln -s ~/repo/front_php ~/www/front
ls -l ~/www
```

![Los dos enlaces creados](docs/tutorial_publicar/img/20_enlaces.png)

```
api   -> /home/phpfacturas/repo/api_facturas
front -> /home/phpfacturas/repo/front_php
```

| | |
|---|---|
| **`ln -s` crea un atajo**, como el acceso directo de Windows | **no copia nada**: apunta |
| Por eso el código vive **en un solo sitio** | y mañana, `cd ~/repo && git pull` actualiza **las dos aplicaciones de una vez** |

---

## Paso 7 — El `.htaccess`, y por qué sin él no funciona nada

**Con el código subido, la API respondía esto:**

```
404 Not Found — The requested URL was not found on this server.
```

**Y no era culpa de la API: nunca llegó a ejecutarse.** En el registro de
Apache (`~/admin/logs/apache/`) estaba escrito:

```
AH00128: File does not exist: /home/phpfacturas/www/api/sesion
```

### Qué está pasando

| En su máquina | En el hospedaje |
|---|---|
| `php -S 0.0.0.0:8090 index.php` | **Apache** |
| Ese último argumento convierte a `index.php` en **el router**: *todas* las peticiones entran por ahí | Apache lee la URL **como una ruta de archivos**: para `/api/sesion` busca una carpeta `api` con un archivo `sesion` adentro |

> **Es el tropiezo número uno al publicar una API de PHP**, y es
> desconcertante porque **el código está bien**. La aplicación no se equivocó:
> no la llamaron.

### Dónde va exactamente el `.htaccess`

**Uno por aplicación, en la MISMA carpeta donde está el `index.php` que
enruta** — es decir, en la carpeta que el sitio tiene como `directorio raíz`.

```
su-proyecto/                     ← el repositorio
   api_facturas/                 ← lo que apunta www/api
      index.php
      .htaccess        ← AQUÍ UNO
      controladores/
      servicios/
   front_php/                    ← lo que apunta www/front
      index.php
      .htaccess        ← AQUÍ EL OTRO
      vistas/
      publico/
```

| Dónde NO va | Por qué |
|---|---|
| En la raíz del repositorio | Apache **no la mira**: el sitio empieza en `www/api`, no más arriba |
| En `~/www/` | tampoco: `www/api` y `www/front` son enlaces a otras carpetas |
| Uno solo para los dos | son **dos sitios distintos**, y cada uno lee el suyo |

**Y el nombre importa:** se llama `.htaccess`, con el punto adelante y sin
extensión. En Windows, el Explorador se resiste a crear archivos que empiezan
por punto — **créelo desde VS Code**, que no tiene ese problema.

### Lo que lleva adentro — copie y pegue

**El mismo contenido sirve para los dos** (el repositorio ya los trae, en
`api_facturas/.htaccess` y `front_php/.htaccess`):

```apache
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L,QSA]
```

| El renglón | Qué hace |
|---|---|
| `RewriteEngine On` | enciende la reescritura de direcciones |
| `!-f` y `!-d` | **solo** reescribir si lo pedido **no** es un archivo ni una carpeta que existan. Así el CSS y las imágenes se sirven directo, sin pasar por PHP |
| `RewriteRule ^ index.php [L,QSA]` | todo lo demás, a `index.php`. `[L]` = última regla; `[QSA]` = conserva lo que venga después del `?` |

### Y el segundo tropiezo, que costó otro rato

**La primera versión de ese archivo llevaba los comentarios al final de la
línea**, y el sitio entero empezó a responder **500**:

```
/home/phpfacturas/www/api/.htaccess: RewriteCond: bad flag delimiters
```

> **Apache no admite comentarios en la misma línea de una directiva.** Esto
> está mal:
>
> ```apache
> RewriteCond %{REQUEST_FILENAME} !-f    # no es un archivo
> ```
>
> El servidor intenta leer ese texto como parte de la condición. **El
> comentario va en su propio renglón**, siempre.
>
> **Y la lección de fondo no es el `.htaccess`: es dónde se mira.** Las dos
> veces, la respuesta estaba escrita en `~/admin/logs/apache/` y en ningún
> otro lado:
>
> ```bash
> tail -n 15 ~/admin/logs/apache/*
> ```

### Traer el arreglo al servidor

```bash
cd ~/repo && git pull
```

**Y ahí sí, la API publicada contesta:**

```powershell
Invoke-RestMethod -Method Post -Uri https://phpfacturas.alwaysdata.net/api/sesion `
  -ContentType "application/json" `
  -Body '{"email":"admin@correo.com","contrasena":"admin123"}'
```

```
token   : eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
email   : admin@correo.com
roles   : {Administrador}
expira  : 2026-11-14T04:03:34+01:00
```

> **Ese token prueba el camino completo:** Apache recibió la petición, se la
> pasó a `index.php`, el enrutador llamó al controlador de sesión, el
> repositorio consultó la tabla `usuario` **en la MariaDB de alwaysdata**, y
> volvió. Si cualquiera de los pasos anteriores estuviera mal, aquí no habría
> token.

---

## Paso 8 — Conectar el front con la API, y la prueba

**Con el código arriba, el front abría pero no servía:**

> **No se pudo completar la operación: El servicio no está disponible.
> ¿Está arriba la API?**

**Y la API sí estaba arriba.** Lo que pasaba es que el front la buscaba donde
dice su valor por defecto —`http://localhost:8090`—, y **dentro del servidor
`localhost` es el servidor mismo**: ahí no hay ninguna API escuchando.

Es el mismo malentendido que en Docker se resuelve usando el nombre del
servicio (`api-facturas`) en vez de `localhost`. Aquí se resuelve con la
dirección pública.

**`Web` → `Sitios`** → engranaje ⚙ del sitio **`phpfacturas.alwaysdata.net`**
→ `Variables ambientales`:

| KEY=VALUE | Para qué |
|---|---|
| **`URL_API=https://phpfacturas.alwaysdata.net`** | a qué dirección le habla el front. **Sin `/api` al final**: eso lo agrega el código al armar cada ruta (`/api/sesion`, `/api/producto`) |

> **Ni una línea de PHP cambió entre su máquina y el servidor.** Lo único que
> cambió fueron cuatro variables en un panel. Eso es lo que significa leer la
> configuración del entorno en vez de escribirla en el código.

### La prueba

```
https://phpfacturas.alwaysdata.net/entrar
```

Entre con `admin@correo.com` / `admin123`:

![El sistema publicado y funcionando](docs/tutorial_publicar/img/21_sistema_publicado.png)

**Está dentro como `admin@correo.com` · Administrador · alcanza 15 rutas de las
15 declaradas.**

### Lo que recorrió esa pantalla

```
su navegador
   |  https
front PHP        phpfacturas.alwaysdata.net          (Apache + PHP)
   |  https
API PHP          phpfacturas.alwaysdata.net/api      (otro sitio, otro proceso)
   |  TCP
MariaDB 11.8     mysql-phpfacturas.alwaysdata.net    (con sus 6 disparadores)
```

| | |
|---|---|
| **Tres piezas, un solo proveedor** | y **cero pesos** |
| **Dos programas que se hablan por HTTP** | no es una aplicación partida en carpetas: el front puede caerse y la API sigue respondiendo |
| **El motor es el mismo que usted entrega** | MariaDB, con llaves foráneas, procedimientos y disparadores. No hubo que cambiar de motor para poder publicar |

---

## Si algo falla

### Al importar la base de datos

| Lo que ve | Qué pasó |
|---|---|
| **`#1046 - No database selected`** | no hizo clic en la base de datos antes de `Import`: estaba importando *al servidor* (paso 4.1) |
| **`#1044 - Access denied ... to database`** | dejó el `CREATE DATABASE` en el script |
| **`#1227 - Access denied; you need SUPER privilege`** | dejó el `DEFINER=root@localhost` en algún procedimiento o disparador |
| **`#1049 - Unknown database 'loquesea'`** | dejó el `USE`, o el nombre de su base local pegado a alguna tabla (`bdfacturas.producto`) |
| **`#1064 - syntax error`** cerca de un `BEGIN` | borró el `DELIMITER $$`. Déjelo |
| Importa, pero no aparecen los procedimientos | mire la pestaña `Routines`. Si está vacía, el error salió antes: lea el mensaje completo, no solo el final |

### Al abrir los sitios

| Lo que ve | Qué revisar |
|---|---|
| **`404 Not Found`** de Apache en una ruta | falta el `.htaccess`, está en la carpeta equivocada, o no llegó al servidor: `cd ~/repo && git pull` |
| **`500`** en todo el sitio | un error en el `.htaccess` — casi siempre un comentario al final de una directiva. **Mire el registro**: `tail -n 15 ~/admin/logs/apache/*` |
| **`403 Forbidden`** | el `directorio raíz` del sitio apunta a una carpeta que no existe, o el enlace `ln -s` quedó mal. Compruebe con `ls -l ~/www` |
| **Página en blanco**, sin error | PHP se murió sin alcanzar a escribir. `tail -n 20 ~/admin/logs/php/*` |
| **«El servicio no está disponible. ¿Está arriba la API?»** | falta `URL_API` en el sitio del front, o quedó con `/` al final, o sin `https://` |
| El login falla pero la API responde | `DB_CLAVE`, o el nombre de la base de datos en `DB_DSN_MARIADB` |
| La página se ve sin estilos | el CSS no está donde el HTML lo busca. Mire la pestaña `Red` del navegador: va a ver un 404 con la ruta exacta |
| Cambié el código y el sitio sigue igual | el servidor tiene **su propia copia** del repositorio: `cd ~/repo && git pull` |
| Cambié una variable de entorno y no pasó nada | hay que **guardar el sitio** en el panel; el cambio se aplica al guardar |

**Los registros, que es donde está la respuesta:**

```bash
tail -n 20 ~/admin/logs/apache/*      # errores de Apache (404, 500, .htaccess)
tail -n 20 ~/admin/logs/php/*         # errores de PHP
```

---

## Lo que quedó demostrado

| | |
|---|---|
| **Se puede publicar gratis con MySQL, pero hay que buscar** | y la búsqueda es parte del trabajo: cuatro de los cinco hospedajes revisados no servían, **y ninguno lo dice en su portada** |
| **La lógica dentro de la base de datos tiene un costo** | seis disparadores y dieciséis procedimientos **descartaron solos** a InfinityFree y a TiDB. Es una decisión de diseño que limita dónde se puede publicar |
| **Lo que cambia al publicar no es el código** | son cuatro variables de entorno y un `.htaccess` |
| **Dos programas, dos sitios** | y por eso cada uno tiene sus propias variables: el front no sabe la contraseña de la base de datos, **y no tiene por qué saberla** |
| **El repositorio puede ser público** | porque ninguna credencial vive adentro |
| **Cuando algo falla, el registro lo dice** | las dos veces que se cayó hoy, la respuesta estaba en `~/admin/logs/apache/` y en ningún otro sitio |

---

**Versión 1.0** — escrito el 13 de noviembre de 2026, ejecutando cada paso.
Las direcciones, la base de datos y las contraseñas de este documento son las
de esa corrida; **las suyas van a ser otras.**
