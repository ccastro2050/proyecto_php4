#!/bin/bash
# ==============================================================
# Inicializador de SQL Server para el Proyecto PHP v4.
# Crea la base de datos bdfacturas_sqlserver_local y ejecuta el
# script SOLO la primera vez (si la BD no existe todavia).
#
# POR QUE SQL SERVER NECESITA ESTO Y LOS OTROS DOS NO:
# las imagenes de MariaDB y PostgreSQL ejecutan solas cualquier .sql que
# encuentren en /docker-entrypoint-initdb.d/. La de SQL Server no tiene ese
# mecanismo, asi que hace falta un contenedor de un solo uso que espere a que
# el servidor este listo, cree la base y corra el script.
#
# Es la primera diferencia de la v4, y aparece antes de escribir una linea
# de PHP: no todos los motores se dejan levantar igual.
# ==============================================================
set -e

SQLCMD=/opt/mssql-tools18/bin/sqlcmd
SERVER=sqlserver
DB=bdfacturas_sqlserver_local

echo "[init] Verificando si la base de datos $DB existe..."
EXISTE=$($SQLCMD -S $SERVER -U sa -P "$MSSQL_SA_PASSWORD" -C -h -1 -W -Q "SET NOCOUNT ON; SELECT COUNT(*) FROM sys.databases WHERE name = '$DB'")

if [ "$EXISTE" = "1" ]; then
    echo "[init] La base de datos $DB ya existe. No se hace nada."
    exit 0
fi

echo "[init] Creando base de datos $DB..."
$SQLCMD -S $SERVER -U sa -P "$MSSQL_SA_PASSWORD" -C -Q "CREATE DATABASE $DB"

echo "[init] Ejecutando script bdfacturas.sql..."
$SQLCMD -S $SERVER -U sa -P "$MSSQL_SA_PASSWORD" -C -d $DB -i /scripts/bdfacturas.sql

echo "[init] SQL Server inicializado correctamente."
