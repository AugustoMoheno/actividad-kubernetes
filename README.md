# Notas en Kubernetes

Web sencilla en PHP y base de datos MariaDB, listas para desplegar en un clúster. Cualquier persona del equipo puede seguir esta guía desde el clon del repositorio.

La web no guarda ficheros. Por eso puede haber dos copias (réplicas). MariaDB guarda las notas en un disco y tiene una sola copia. La contraseña está en un Secret. La página se publica fuera del clúster.

## Lo que hay que tener instalado

En el ordenador de quien vaya a construir o desplegar:

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) en marcha
- `kubectl`
- Un clúster: Kubernetes de Docker Desktop, Minikube, o el clúster de la asignatura
- Una cuenta gratuita en [Docker Hub](https://hub.docker.com/)
- Git

Comprobación:

```bash
docker version
kubectl version --client
kubectl config current-context
kubectl get nodes
```

`kubectl get nodes` tiene que listar al menos un nodo en estado `Ready`. Si falla, el clúster no está arrancado o `kubectl` no apunta a él.

## Valores del proyecto

| Dato | Valor en el repositorio | Qué hacer |
|------|-------------------------|-----------|
| Usuario de Docker Hub | `USUARIO_DOCKERHUB` | Cambiarlo por el usuario real del equipo. Es el único cambio obligatorio |
| Imagen web | `USUARIO_DOCKERHUB/notas-web:1.0` | Queda bien al cambiar el usuario |
| Imagen base de datos | `USUARIO_DOCKERHUB/notas-db:1.0` | Queda bien al cambiar el usuario |
| Namespace | `actividad-k8s` | Dejarlo así |
| Base de datos | `notas` | Dejarlo así |
| Usuario SQL | `notasapp` | Dejarlo así |
| Contraseña de la aplicación | `NotasK8s-2026` | Dejarla, o cambiarla en `k8s/01-secret.yaml` antes del primer despliegue |
| Contraseña root | `RootNotas-2026` | Igual que la anterior |
| Réplicas de la web | 2 | Dejarlas así. La actividad pide al menos dos |
| Réplicas de MariaDB | 1 | Dejarla así |
| Puerto en cada nodo | 30080 | Dejarlo así. La web también escucha ahí |

El equipo usa **un solo usuario de Docker Hub**. Quien tenga la contraseña de esa cuenta publica las imágenes. El resto clona el repositorio, con el usuario ya escrito, y puede desplegar: las imágenes públicas se descargan solas.

## Paso 1. Clonar el repositorio

```bash
git clone URL-DEL-REPOSITORIO
cd NOMBRE-DEL-REPOSITORIO
```

Sustituye la URL por la del repositorio de GitHub del equipo. Todos los comandos siguientes se ejecutan dentro de esa carpeta.

## Paso 2. Poner el usuario de Docker Hub

Hay que reemplazar el texto `USUARIO_DOCKERHUB` por el usuario real, en minúsculas y sin espacios. Aparece en:

- `k8s/04-db-deployment.yaml`
- `k8s/06-web-deployment.yaml`

Ejemplo: si el usuario es `equipo-notas`, la línea de la web queda así:

```yaml
image: equipo-notas/notas-web:1.0
```

Y la de la base de datos:

```yaml
image: equipo-notas/notas-db:1.0
```

Para cambiarlo en los dos ficheros de una vez, desde la raíz del repositorio:

macOS:

```bash
grep -Rl USUARIO_DOCKERHUB k8s | while read -r f; do
  sed -i '' 's/USUARIO_DOCKERHUB/equipo-notas/g' "$f"
done
```

Linux:

```bash
grep -Rl USUARIO_DOCKERHUB k8s | while read -r f; do
  sed -i 's/USUARIO_DOCKERHUB/equipo-notas/g' "$f"
done
```

Cambia `equipo-notas` por el usuario real. Comprueba el resultado:

```bash
grep -n "image:" k8s/04-db-deployment.yaml k8s/06-web-deployment.yaml
```

Ese mismo nombre se usa en `docker build` y `docker push`.

La contraseña de la práctica puede quedarse como está. Si el equipo prefiere otra, editad solo `k8s/01-secret.yaml` **antes** del primer `kubectl apply`. MariaDB guarda la contraseña dentro del disco la primera vez que arranca. Un cambio posterior en el Secret no la actualiza: haría falta borrar el namespace y desplegar otra vez.

## Paso 3. Construir las imágenes

Docker Desktop tiene que estar abierto.

```bash
docker build -t USUARIO_DOCKERHUB/notas-web:1.0 ./app
docker build -t USUARIO_DOCKERHUB/notas-db:1.0 ./db
```

Usa el usuario real, el mismo del paso 2. La primera vez descarga las imágenes base y tarda varios minutos.

`app/Dockerfile` parte de PHP con Apache y añade la extensión para hablar con MariaDB. `db/Dockerfile` parte de MariaDB y copia la tabla `notas`.

## Paso 4. Publicar en Docker Hub

Lo hace una persona del equipo, con la cuenta elegida. En Docker Hub los dos repositorios tienen que ser **públicos** para que el clúster pueda descargarlos.

```bash
docker login
docker push USUARIO_DOCKERHUB/notas-web:1.0
docker push USUARIO_DOCKERHUB/notas-db:1.0
```

`docker login` pide el usuario y un token de acceso (Docker Hub → Account settings → Personal access tokens). La contraseña de la web no forma parte de la imagen.

Comprobación en el navegador:

- `https://hub.docker.com/r/USUARIO_DOCKERHUB/notas-web`
- `https://hub.docker.com/r/USUARIO_DOCKERHUB/notas-db`

## Paso 5. Desplegar en el clúster

```bash
kubectl apply -f k8s/00-namespace.yaml
kubectl apply -f k8s/
kubectl -n actividad-k8s get pods
```

El primer comando crea el namespace. El segundo crea el resto. Los Pods tardan un minuto en pasar a `Running`. Repite `get pods` hasta ver:

- 1 Pod `notas-db` en `Running`
- 2 Pods `notas-web` en `Running`

Qué hace cada fichero de `k8s/`:

| Fichero | Qué crea |
|---------|----------|
| `00-namespace.yaml` | Namespace `actividad-k8s`, separado del resto del clúster |
| `01-secret.yaml` | Usuario, base de datos y contraseñas |
| `02-configmap.yaml` | Dirección interna de MariaDB: el Service `notas-db`, puerto 3306 |
| `03-pvc.yaml` | Disco de 1 GiB para los datos |
| `04-db-deployment.yaml` | MariaDB, una réplica |
| `05-db-service.yaml` | Acceso interno. Desde fuera del clúster no se abre el puerto 3306 |
| `06-web-deployment.yaml` | La web, dos réplicas. Espera a que MariaDB acepte conexiones |
| `07-web-service.yaml` | Publica la web con un balanceador y en el puerto 30080 de cada nodo |

`k8s/opcional/08-ingress.yaml` no se aplica con el comando anterior. Solo hace falta si el profesor pide un Ingress. El clúster tiene que tener un controlador, y el fichero usa la clase `nginx`.

## Paso 6. Abrir la web

```bash
kubectl -n actividad-k8s get svc notas-web
```

Usa la primera opción que encaje con vuestro clúster.

**Clúster en la nube.** En la columna `EXTERNAL-IP` aparece una IP. Abrid `http://ESA-IP`. Si pone `<pending>`, esperad un minuto y volved a lanzar el comando.

**Docker Desktop.** `EXTERNAL-IP` suele ser `localhost`. Abrid http://localhost

**Minikube.** En otra terminal:

```bash
minikube tunnel
```

Dejad esa terminal abierta y consultad otra vez el Service. También podéis abrirla con:

```bash
minikube service notas-web -n actividad-k8s
```

**Cualquier nodo del clúster.** Abrid `http://IP-DEL-NODO:30080`. La IP sale en:

```bash
kubectl get nodes -o wide
```

Guardad una nota. Tiene que seguir en la lista al recargar: la está leyendo MariaDB. Al final de la página aparece el Pod que ha respondido. Recargad varias veces. Si el nombre cambia entre `notas-web-...`, están respondiendo las dos réplicas.

Otra forma de ver las dos copias:

```bash
kubectl -n actividad-k8s get endpoints notas-web
```

Ahí tienen que salir dos direcciones IP.

## Paso 7. Comprobar que los datos permanecen

```bash
kubectl -n actividad-k8s delete pod -l app=notas-web
kubectl -n actividad-k8s get pods
```

El Deployment crea otra vez dos Pods. La nota sigue en la página porque vive en MariaDB, no en la web.

## Si algo falla

**`ImagePullBackOff` o `ErrImagePull`.** El clúster no puede bajar la imagen. Revisad que el usuario del YAML sea el mismo del `docker push`, que el `push` haya terminado y que el repositorio sea público:

```bash
kubectl -n actividad-k8s describe pod -l app=notas-web
```

**El PVC se queda en `Pending`.** El clúster no tiene una clase de almacenamiento por defecto.

```bash
kubectl get pvc -n actividad-k8s
kubectl get storageclass
```

Minikube y Docker Desktop suelen traer una. En un clúster vacío hay que crearla o pedirla en la asignatura antes de desplegar.

**Los Pods `notas-web` se quedan en `Init`.** Aún esperan a MariaDB. Mirad el Pod de la base de datos:

```bash
kubectl -n actividad-k8s get pods
kubectl -n actividad-k8s logs -l app=notas-db
```

**La página dice que MariaDB está arrancando.** Esperad y recargad. El primer arranque de la base de datos tarda un poco.

**`EXTERNAL-IP` sigue en `<pending>`.** Ese clúster no reparte balanceadores. Usad el puerto 30080 o `minikube service`, como en el paso 6.

**La web no entra a la base de datos después de cambiar la contraseña.** El disco ya se inicializó con la contraseña anterior. Borrad el despliegue con el comando del apartado siguiente y volved a aplicar los YAML.

## Quitar el despliegue

Borra el namespace y, con él, los Pods, el Secret y el disco de las notas.

```bash
kubectl delete namespace actividad-k8s
```

## Informe en PDF

La plantilla está en `docs/INFORME.md`.

1. Abrid ese fichero y copiad el contenido a Word o LibreOffice.
2. Fuente Calibri, tamaño 11, interlineado 1,5.
3. Rellenad los nombres del equipo, la fecha, el usuario de Docker Hub y pegad las capturas donde indica `[CAPTURA]`.
4. Exportad a PDF. Máximo 10 páginas. Los YAML completos van en el repositorio, no hace falta pegarlos enteros en el PDF.

## Mapa del repositorio

```text
app/                 Web en PHP y su Dockerfile
db/                  MariaDB, su Dockerfile y la tabla notas
k8s/                 Manifiestos que se aplican en el clúster
k8s/opcional/        Ingress, solo si el clúster ya tiene controlador
docs/INFORME.md      Texto para el PDF de la entrega
```
