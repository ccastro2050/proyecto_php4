# ==============================================================
# Front Facturas v1 — PHP puro (puerto 8020)
#
# El mismo servidor embebido que la API, con index.php como router: todas las
# peticiones pasan por el front controller sin configurar Apache ni nginx.
#
# FÍJESE EN LO QUE NO ESTÁ: no se instala `pdo_mysql`. La API sí lo necesita,
# el front NO — y esa ausencia no es un olvido: es la comprobación de que este
# proceso no puede llegar a MariaDB ni queriendo.
# ==============================================================
FROM php:8.3-cli

WORKDIR /app

# El código (en desarrollo queda tapado por el volumen del compose:
# guardar un .php = refrescar, porque PHP reinterpreta en cada petición).
COPY . .

EXPOSE 8088

CMD ["php", "-S", "0.0.0.0:8088", "index.php"]
