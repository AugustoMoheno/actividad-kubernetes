# Imagen de MariaDB.
# docker build -t USUARIO_DOCKERHUB/notas-db:1.0 ./db
# El usuario y la contraseña no van aquí: llegan del Secret al arrancar el Pod.
FROM mariadb:11.4

COPY init.sql /docker-entrypoint-initdb.d/01-init.sql

EXPOSE 3306
