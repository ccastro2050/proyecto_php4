# Requisitos no funcionales — Facturación (`bdfacturas`), en PHP

> **Qué es este documento.** No lo que el sistema hace, sino **cómo tiene que
> hacerlo**: con qué rapidez, con qué seguridad, qué tan portable es y qué tan
> fácil de sostener.
>
> **Cada requisito trae su medición**, no un adjetivo. «Rápido» no es un
> requisito; «responde en menos de 300 ms» sí, porque se puede comprobar y se
> puede incumplir.
>
> Versión 1.0 · 21 de octubre de 2026.

---

## 1. Rendimiento

| Id | Requisito | Medido el 21 de octubre de 2026 |
|---|---|---|
| **RNF-01** | Una consulta de lista responde en **menos de 300 ms** con los datos sembrados | `GET /api/factura` → **19 ms** · `GET /api/producto` → 423 ms la primera (la conexión se abre) y ~20 ms las siguientes |
| **RNF-02** | Una de las **diez consultas** —cuatro o más tablas— responde en **menos de 500 ms** | `ventas-por-producto` → **26 ms** · `alcance-de-usuarios`, que cruza cinco tablas → **29 ms** |
| **RNF-03** | Una pantalla del front responde en **menos de 500 ms** | inicio → **16 ms** · `/facturas` → 142 ms · `/tablero/ventas-por-producto` → **30 ms** |

> **La primera petición siempre es la más lenta**, y conviene saber por qué:
> el repositorio abre la conexión PDO **de forma perezosa**, en la primera
> consulta que la necesita. Las 400 ms de la primera son la conexión, no la
> consulta. Si eso molestara en producción, la respuesta es un *pool* de
> conexiones — que es justamente lo que este proyecto **no** usa, para que se
> vea el costo.

---

## 2. Seguridad

| Id | Requisito | Cómo está resuelto |
|---|---|---|
| **RNF-04** | Ninguna contraseña se guarda legible | `password_hash` con **bcrypt costo 12** en el repositorio |
| **RNF-05** | Ninguna contraseña sale en una respuesta | los `SELECT` no piden esa columna |
| **RNF-06** | Ningún secreto en el código ni en el repositorio | las cadenas de conexión y la clave de firma llegan por **variables de entorno** del compose |
| **RNF-07** | Toda consulta con datos de fuera usa **sentencias preparadas** | **168 `prepare`** en los repositorios del CRUD; las diez consultas usan `query` porque no reciben ni un parámetro |
| **RNF-08** | La firma del token se compara en **tiempo constante** | `hash_equals`, no `===` — si no, el tiempo delata cuántos bytes se acertaron |
| **RNF-09** | El token **no lleva el permiso**: se consulta en cada petición | `verificar_acceso_ruta` en la base de datos |
| **RNF-10** | Lo que no está declarado **no pasa** | lista blanca de dos rutas en `index.php`; lo demás exige sesión |
| **RNF-11** | Las versiones de PHP y de los motores se mantienen **parcheadas** | ver el aviso del `README.md`: 8.3.33 → **8.3.36** el 21 de octubre de 2026 |

> **RNF-11 no estaba en la primera versión de este documento**, y entró
> después de un caso real: el 22 de octubre de 2026 PHP publicó parches para
> dos vulnerabilidades críticas de ejecución remota, y este proyecto corría
> una versión vulnerable. **Elegir `php:8.3-cli` no fue una decisión que se
> toma una vez: es una suscripción.**

---

## 3. Portabilidad

| Id | Requisito | Cómo se comprueba |
|---|---|---|
| **RNF-12** | El sistema corre contra **tres motores** sin tocar código | `MOTOR=mariadb` · `postgres` · `sqlserver` |
| **RNF-13** | Lo que cambia entre motores **no sube** de la capa de repositorio | `PDO` no aparece en servicios ni controladores: las tres menciones que hay son comentarios |
| **RNF-14** | Todo se levanta con **un solo comando** | `docker compose up -d` |
| **RNF-15** | No se instala nada en la máquina del estudiante | ni PHP, ni Composer, ni un motor: todo va en contenedores |

> **RNF-13 es el que sostiene a RNF-12.** Si una `PDOException` llegara al
> controlador, cambiar de motor obligaría a tocar el controlador — y entonces
> «sin tocar código» sería falso.

---

## 4. Usabilidad

| Id | Requisito | Cómo está resuelto |
|---|---|---|
| **RNF-16** | La interfaz **no habla en jerga**: ni «PUT», ni «422», ni «FK» en pantalla | los mensajes salen del sobre de la API, en castellano |
| **RNF-17** | Las llaves foráneas **no se digitan**: se escogen de una lista que muestra el nombre | los desplegables se cargan de la API |
| **RNF-18** | Los errores de forma llegan **todos a la vez** | el 422 trae la lista completa, no el primero |
| **RNF-19** | El front funciona **sin JavaScript propio** | **cero archivos `.js` propios**; el único `<script>` del proyecto carga Bootstrap, y solo para cerrar los avisos |

> **RNF-19 tiene consecuencias visibles**, y por eso está escrito: el
> formulario de la factura agrega renglones **recargando la página**, no con
> JavaScript. Es más lento de usar y tiene una ventaja que importa en un curso
> de PHP: **todo lo que pasa se ve en PHP**, no en una capa de magia del
> navegador.

---

## 5. Mantenibilidad

| Id | Requisito | Medido |
|---|---|---|
| **RNF-20** | Tres capas, con la dependencia en una sola dirección | 120 archivos en la API, repartidos en modelos, repositorios, servicios, controladores y autorización |
| **RNF-21** | Cambiar de motor es **un `match`**, no una cascada de `if` | **17 `match (motorActivo())`** en el ensamblador, uno por servicio |
| **RNF-22** | Los servicios se pueden probar **sin base de datos** | `pruebas/prueba_capas.php`, con repositorios falsos |
| **RNF-23** | El código explica **por qué**, no qué | ~70 % de las líneas son comentarios: es material de clase |

---

## 6. Disponibilidad

| Id | Requisito | Cómo está resuelto |
|---|---|---|
| **RNF-24** | Los contenedores vuelven solos tras un reinicio | `restart: unless-stopped` en todos los servicios menos el de inicialización |
| **RNF-25** | El diagnóstico responde **sin credenciales** | `GET /` es una de las dos rutas abiertas: un *healthcheck* que pidiera token no serviría |
| **RNF-26** | Los datos sobreviven a recrear un contenedor | volúmenes nombrados; **`docker compose down -v` es lo único que los borra** |

---

## 7. Lo que este sistema NO promete

Decirlo es parte del documento:

| No promete | Qué pasaría |
|---|---|
| Aguantar carga concurrente | No hay *pool* de conexiones ni pruebas de carga. Es un proyecto de aula |
| Cifrar el tráfico | Corre en `http://localhost`. En un servidor de verdad, HTTPS no es opcional |
| Ocultar el detalle del error | El 500 devuelve el mensaje del motor **a propósito**, para poder leerlo en clase. En producción, eso se registra y no se devuelve |
| Estar disponible fuera de la máquina | Es `localhost`: no existe desde otro computador hasta que se publique |

---

## 8. Cómo repetir estas mediciones

```powershell
# Los tiempos de la API
curl.exe -s -o NUL -w "%{time_total}s`n" http://localhost:8090/api/factura -H "Authorization: Bearer $t"

# Las diez consultas en los tres motores
docker exec -e MOTOR=postgres proyecto_php4-api-facturas-1 php /app/pruebas/prueba_consultas_tres_motores.php

# Las capas, sin base de datos
docker exec proyecto_php4-api-facturas-1 php /app/pruebas/prueba_capas.php

# La versión de PHP que está corriendo de verdad
docker exec proyecto_php4-api-facturas-1 php -v
```

> En PowerShell use `curl.exe` con el `.exe`: `curl` a secas es un alias de
> `Invoke-WebRequest` y no entiende esas banderas.
