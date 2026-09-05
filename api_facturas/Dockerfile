# ==============================================================
# API Facturas v4 — PHP puro (puerto 8090)
# El servidor embebido de PHP con index.php como router: todas
# las peticiones pasan por el front controller sin configurar
# Apache/nginx. En desarrollo, el compose monta el código como
# volumen: guardar un .php = refrescar (PHP reinterpreta).
#
# LO QUE CAMBIA EN LA v4: la imagen trae los controladores de LOS
# TRES motores. Sigue siendo UNA sola imagen — cuál se usa se
# decide al arrancar, con la variable MOTOR.
#
# Y aquí aparece la primera diferencia de SQL Server, antes de
# escribir una línea de PHP: **su driver no viene con PHP.**
# `pdo_mysql` y `pdo_pgsql` son extensiones del propio PHP y se
# compilan con un comando. El de SQL Server lo publica Microsoft
# aparte, hay que traerlo de su repositorio, y necesita un
# controlador ODBC del sistema debajo.
#
# Los tres motores no se instalan igual. Es una molestia real, y
# está aquí a la vista en vez de escondida en un script.
# ==============================================================
FROM php:8.3-cli

# --- 1. Los dos drivers que vienen con PHP -------------------
# El de PostgreSQL necesita sus cabeceras (libpq-dev); el de
# MySQL/MariaDB ya trae lo necesario.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# --- 2. El de SQL Server, que es harina de otro costal --------
# Tres pasos, y ninguno se parece a lo de arriba:
#   a. agregar el repositorio de paquetes de Microsoft;
#   b. instalar el controlador ODBC (msodbcsql18), que es lo que
#      de verdad habla con SQL Server;
#   c. compilar la extensión de PHP (pdo_sqlsrv) con PECL, que es
#      el gestor de extensiones del lenguaje, encima de ese ODBC.
#
# ACCEPT_EULA=Y acepta la licencia del controlador de Microsoft
# sin abrir una pregunta interactiva — sin eso, el build se
# quedaría esperando una respuesta que nadie va a escribir.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl gnupg2 apt-transport-https ca-certificates unixodbc-dev \
    && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
        | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" \
        > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 \
    && pecl install pdo_sqlsrv \
    && docker-php-ext-enable pdo_sqlsrv \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# El código (en desarrollo queda tapado por el volumen del compose)
COPY . .

EXPOSE 8090

CMD ["php", "-S", "0.0.0.0:8090", "index.php"]
