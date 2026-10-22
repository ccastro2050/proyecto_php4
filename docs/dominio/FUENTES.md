# Las fuentes — de dónde salió cada cosa, y qué manda sobre qué

> **Qué es este documento.** De dónde viene el material de este repositorio
> —qué se heredó, qué se escribió aquí y qué es inventado— y, cuando dos
> documentos se contradigan, **cuál gana**.
>
> Un proyecto sin este documento obliga a adivinar si un `CREATE TABLE` se
> puede cambiar o no.
>
> Versión 1.0 · 22 de octubre de 2026.

---

## 1. La base de datos tiene DOS orígenes, no uno

| Qué | Cuánto | De dónde viene | ¿Se puede cambiar? |
|---|---|---|---|
| **Las 12 tablas**, con sus llaves y restricciones | 12 `CREATE TABLE` | **Del modelo del curso de bases de datos** | **No** sin una razón escrita: es el modelo que se enseña |
| **Los 12 disparadores** | subtotales, stock y total | De este proyecto | Sí, con su criterio |
| **Los 16 procedimientos** | facturación, usuarios con roles, `verificar_acceso_ruta` | De este proyecto | Sí |
| **Los datos sembrados** | 12 bloques de `INSERT` | Inventados, con procedencia en [`DATOS_DE_PRUEBA.md`](DATOS_DE_PRUEBA.md) | Sí |

El script de MariaDB tiene **1.627 líneas**, de las cuales **486 (29 %) son
comentarios**: es material de clase y se lee, no solo se ejecuta. Los otros
dos motores: PostgreSQL 1.113 líneas, SQL Server 1.539.

> **Por qué el modelo se escribe UNA vez y no cambia en cada versión.** Un
> modelo que se mueve en cada entrega obliga a migrar datos, rehacer
> disparadores y reescribir procedimientos — y nada de eso es lo que este
> curso enseña. Lo que cambia entre versiones es **qué tablas entran en
> juego**, no cómo están hechas.

> **Y por qué ninguna IA lo regenera.** Porque ya está hecho y tiene autores.
> Si una IA propone un `CREATE TABLE`, está rehaciendo a ciegas un trabajo que
> no vio nacer.

---

## 2. Lo normativo: qué manda sobre qué

En orden de autoridad. Si dos se contradicen, **gana el de más arriba**:

| # | Fuente | Qué fija | Dónde |
|---|---|---|---|
| **1** | La **constitución** | Las reglas de **todas** las versiones: PHP puro, capas, español, secretos por variable de entorno | `docs/spec_kit/1_constitution.md` |
| **2** | El **mapa de versiones** | Qué entra en cada versión y qué no | `docs/spec_kit/versiones/0_mapa_versiones.md` |
| **3** | El **spec kit de la versión** | Qué construir, con sus criterios de aceptación | `docs/spec_kit/versiones/vN/` |
| **4** | Los documentos de **dominio** | Las reglas, los errores, el modelo | esta carpeta |
| **5** | El **código** | Lo que el sistema hace de verdad | `api_facturas/` y `front_php/` |

> **Y la regla que hace esto útil:** si el código y el documento se
> contradicen, **primero se averigua cuál de los dos está mal**. No siempre
> gana el documento: el documento se escribió antes, y a veces el código
> aprendió algo que el documento no sabía. Lo que no se vale es dejarlos
> distintos.
>
> Esta versión tiene dos casos de eso, y los dos están contados: la
> explicación del `CAST` de T-SQL que era falsa y las transacciones que
> faltaban en MariaDB — los dos en
> `docs/spec_kit/versiones/v4_aplicativo/4_research.md`.

---

## 3. Qué se heredó de las versiones anteriores

Las versiones son **acumulativas**, así que la v4 contiene el trabajo de las
otras tres:

| De dónde | Qué llegó a la v4 |
|---|---|
| **v1** | Las capas, el sobre de las respuestas, la política de errores, el front y el CRUD de `producto` |
| **v2** | Los cinco recursos con llaves foráneas, el maestro-detalle de la factura y el 409 |
| **v3** | La fábrica —el `match` del motor— y el segundo juego de repositorios |
| **v4** | El tercer motor, el control de acceso, `usuario-con-roles`, las diez consultas y el tablero |

---

## 4. Qué es inventado, y se dice

| | |
|---|---|
| **La empresa** | «Comercial Los Andes S.A.» no existe. Ver [`MANUAL_DE_MARCA.md`](MANUAL_DE_MARCA.md) |
| **Las personas, los correos y los teléfonos** | Inventados. No hay datos de personas reales |
| **Los productos y sus precios** | Equipos reales con precios de orden correcto, no la lista de precios de nadie |
| **La elicitación** | Este repositorio **no tiene** carpeta `elicitacion/`: el dominio de la facturación viene dado por el curso, no se levantó preguntando. El ejercicio de elicitar es del proyecto de aula, no del ejemplo |

---

## 5. Lo externo: qué se usa y de dónde viene

| Qué | Versión | De dónde | Para qué |
|---|---|---|---|
| **PHP** | 8.3.36 | imagen oficial `php:8.3-cli` | La API y el front |
| **MariaDB** | 11.8.9 | `mariadb:11` | Motor por defecto |
| **PostgreSQL** | 16.15 | `postgres:16-alpine` | Segundo motor |
| **SQL Server** | 2022 | `mcr.microsoft.com/mssql/server` | Tercer motor |
| **phpMyAdmin** | la última | `phpmyadmin` | Mirar MariaDB desde el navegador |
| **Bootstrap** | 5 | **copiado en `front_php/publico/`** | El aspecto del front |

> **Bootstrap está copiado en el repositorio y no se carga de internet**, a
> propósito: el salón puede quedarse sin red y la pantalla se sigue viendo
> igual.
>
> **Y no hay Composer.** Ni una dependencia de PHP: el JWT, el enrutador y el
> cliente HTTP están escritos a mano. Eso cuesta código y compra una cosa —
> que todo lo que pasa se pueda leer—, y tiene su precio escrito en
> `3_plan.md` de la v4.

---

## 6. Lo que este repositorio NO tomó de ningún lado

| | Por qué |
|---|---|
| Un framework (Laravel, Symfony) | La constitución pide **PHP puro**: lo que se ve es el lenguaje, no la magia del framework |
| Un ORM | El SQL va a la vista: quien lea el repositorio ve exactamente lo que la base de datos va a ejecutar |
| Una librería de JWT | Son sesenta líneas, y escribirlas enseña base64url, HMAC y el ataque de tiempo |
| JavaScript para el front | Cero archivos `.js` propios. Lo único que se carga es Bootstrap, y solo para cerrar los avisos |

---

## 7. Dónde seguir

| Qué | Dónde |
|---|---|
| Qué trae la base de datos al levantarse | [`DATOS_DE_PRUEBA.md`](DATOS_DE_PRUEBA.md) |
| El modelo, tabla por tabla | [`DISENO_BD.md`](DISENO_BD.md) |
| Las decisiones y los tropiezos de la v4 | `docs/spec_kit/versiones/v4_aplicativo/4_research.md` |
| Lo que falta | [`PENDIENTES.md`](PENDIENTES.md) |
