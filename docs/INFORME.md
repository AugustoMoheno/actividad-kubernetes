# Informe: aplicación en contenedores y despliegue en Kubernetes

Instrucciones para pasar este texto al PDF. No forman parte del informe: borrádselas al pegarlo en el editor.

- Fuente Calibri, tamaño 11.
- Interlineado 1,5.
- Máximo 10 páginas. Los YAML del repositorio no se copian enteros aquí.
- Sustituid cada campo entre corchetes y pegad una captura en cada `[CAPTURA]`.
- El usuario de Docker Hub es el mismo que quedó en `k8s/04-db-deployment.yaml` y `k8s/06-web-deployment.yaml`.

## Portada

| Campo | Completar |
|-------|-----------|
| Asignatura | Contenedores |
| Actividad | Construcción de aplicativo en contenedores y despliegue en un clúster de Kubernetes |
| Fecha | [DD/MM/AAAA] |
| Usuario de Docker Hub | [USUARIO_DOCKERHUB] |
| Repositorio | [URL del repositorio en GitHub] |

Integrantes:

| Nombre y apellidos | Tarea en el equipo |
|--------------------|--------------------|
| [Nombre Apellidos] | [Imágenes / manifiestos / despliegue / informe] |
| [Nombre Apellidos] | [Tarea] |
| [Nombre Apellidos] | [Tarea] |
| [Nombre Apellidos] | [Tarea] |

Borrad las filas que sobren.

## 1. Objetivo

El equipo ha preparado una aplicación con dos contenedores y la ha desplegado en Kubernetes mediante ficheros YAML.

La aplicación es una página de notas. No es uno de los ejemplos de un solo contenedor vistos en clase. PHP muestra y guarda el texto. MariaDB lo almacena. La web se publica fuera del clúster, con dos réplicas. La base de datos tiene una réplica, contraseña en un Secret y un disco propio.

## 2. Arquitectura

| Pieza | Imagen | Réplicas | Acceso |
|-------|--------|----------|--------|
| Web PHP | `[USUARIO_DOCKERHUB]/notas-web:1.0` | 2 | Service `LoadBalancer` y puerto de nodo 30080 |
| MariaDB | `[USUARIO_DOCKERHUB]/notas-db:1.0` | 1 | Service `ClusterIP`, solo dentro del namespace `actividad-k8s` |

La web puede tener dos réplicas porque no escribe en su disco: cualquier copia atiende cualquier visita. El nombre del Pod aparece al final de la página. Al recargar, la petición puede llegar a la otra réplica.

MariaDB tiene una réplica. Su Deployment usa la estrategia `Recreate` para que el disco de 1 GiB no se monte en dos Pods a la vez. El Service de la base de datos no se publica en internet. La web llega a ella por el nombre `notas-db`, definido en el ConfigMap.

`[CAPTURA]` Salida de `kubectl -n actividad-k8s get deploy,svc,pvc`.

## 3. Imágenes y Docker Hub

Las dos imágenes se construyen con un Dockerfile y se publican en Docker Hub, en repositorios públicos, para que el clúster las descargue.

La imagen web parte de `php:8.3-apache`, instala la extensión `mysqli` y copia `index.php`. La imagen de base de datos parte de `mariadb:11.4` y copia `init.sql`, que crea la tabla `notas`. El usuario y la contraseña no van en el Dockerfile: MariaDB los recibe al arrancar, desde el Secret.

```bash
docker build -t [USUARIO_DOCKERHUB]/notas-web:1.0 ./app
docker build -t [USUARIO_DOCKERHUB]/notas-db:1.0 ./db
docker push [USUARIO_DOCKERHUB]/notas-web:1.0
docker push [USUARIO_DOCKERHUB]/notas-db:1.0
```

`[CAPTURA]` Construcción y `docker push` de las dos imágenes.

`[CAPTURA]` Las dos imágenes visibles en Docker Hub.

## 4. Manifiestos

Todos los recursos están en el namespace `actividad-k8s`.

| Fichero | Para qué sirve |
|---------|----------------|
| `00-namespace.yaml` | Separa esta práctica del resto del clúster |
| `01-secret.yaml` | Base de datos `notas`, usuario `notasapp` y contraseñas |
| `02-configmap.yaml` | Host `notas-db` y puerto `3306` |
| `03-pvc.yaml` | Disco de 1 GiB, modo `ReadWriteOnce`, montado en `/var/lib/mysql` |
| `04-db-deployment.yaml` | MariaDB, una réplica |
| `05-db-service.yaml` | Acceso interno a MariaDB |
| `06-web-deployment.yaml` | Web con `replicas: 2` |
| `07-web-service.yaml` | Publicación exterior: `LoadBalancer` y `nodePort: 30080` |

El Secret está declarado con `stringData`, en texto legible dentro del YAML. Kubernetes lo guarda codificado en base64. Esa codificación no cifra el valor: sirve para no meter la contraseña en la imagen ni en el Deployment. Quien pueda leer Secrets en el namespace puede verla.

La web incluye un `initContainer` que espera a que el puerto 3306 de `notas-db` acepte conexiones. Así el Apache no arranca antes de que la base de datos esté escuchando.

La sonda de readiness de la web pide la página `/`. Si MariaDB no responde, la página devuelve 503 y ese Pod deja de recibir visitas. La sonda de vida solo comprueba que el puerto 80 está abierto, para no reiniciar la web por un fallo breve de la base de datos.

`[CAPTURA]` `kubectl -n actividad-k8s get pods` con `notas-db` en `Running` y dos Pods `notas-web` en `Running`.

## 5. Despliegue y acceso desde fuera

```bash
kubectl apply -f k8s/00-namespace.yaml
kubectl apply -f k8s/
kubectl -n actividad-k8s get svc notas-web
```

La dirección usada por el equipo ha sido una de estas:

- Balanceador del clúster: `http://[EXTERNAL-IP]`
- Docker Desktop: http://localhost
- Minikube: `minikube tunnel` o `minikube service notas-web -n actividad-k8s`
- Puerto de nodo: `http://[IP-DEL-NODO]:30080`

Dejad escrita la opción que hayáis usado y tachad las demás al pasar el texto al PDF, para que el informe coincida con el clúster real.

El fichero `k8s/opcional/08-ingress.yaml` publica la misma web mediante un Ingress de clase `nginx`. Se aplica solo cuando el clúster ya tiene ese controlador.

`[CAPTURA]` Navegador con una nota guardada y el nombre del Pod al final de la página.

`[CAPTURA]` La misma página servida por el otro Pod, o `kubectl -n actividad-k8s get endpoints notas-web` con dos direcciones.

## 6. Prueba

1. Abrir la dirección pública.
2. Guardar una nota.
3. Recargar y comprobar que la nota sigue en la lista.
4. Borrar los Pods de la web. El Deployment los vuelve a crear y la nota sigue, porque está en MariaDB:

```bash
kubectl -n actividad-k8s delete pod -l app=notas-web
```

`[CAPTURA]` La nota sigue visible después de recrear los Pods de la web.

## 7. Conclusión

El equipo ha separado la construcción de las imágenes de la descripción del clúster. Los Dockerfile y Docker Hub definen qué se ejecuta. Los YAML definen cuántas copias hay, dónde está la contraseña, dónde está el disco y cómo se entra desde fuera.

La web es intercambiable, así que tiene dos réplicas. El estado está en MariaDB, con una réplica, un Secret y un volumen. El Service `LoadBalancer` y el puerto 30080 cumplen el acceso desde fuera del clúster.

La página no identifica usuarios. Cualquier persona que alcance la dirección puede escribir notas. Es suficiente para la práctica y se dejaría así solo en un entorno de laboratorio.
