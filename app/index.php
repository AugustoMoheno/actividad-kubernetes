<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'notas-db';
$port = (int) (getenv('DB_PORT') ?: '3306');
$database = getenv('DB_NAME') ?: 'notas';
$user = getenv('DB_USER') ?: 'notasapp';
$password = getenv('DB_PASSWORD') ?: '';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function paginaError(): void
{
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="es"><meta charset="utf-8"><title>Notas</title>';
    echo '<p>MariaDB aún está arrancando. Espera unos segundos y recarga la página.</p></html>';
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = new mysqli($host, $user, $password, $database, $port);
    $db->set_charset('utf8mb4');
    $db->query(
        'CREATE TABLE IF NOT EXISTS notas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            texto VARCHAR(255) NOT NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
} catch (Throwable $e) {
    paginaError();
    exit;
}

$error = '';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $texto = trim((string) ($_POST['texto'] ?? ''));
        if ($texto === '') {
            $error = 'Escribe un texto antes de guardar.';
        } elseif (strlen($texto) > 255) {
            $error = 'El texto admite como máximo 255 caracteres.';
        } else {
            $stmt = $db->prepare('INSERT INTO notas (texto) VALUES (?)');
            $stmt->bind_param('s', $texto);
            $stmt->execute();
            $stmt->close();
            header('Location: /');
            exit;
        }
    }

    $result = $db->query('SELECT id, texto, creado_en FROM notas ORDER BY id DESC');
    $notas = $result->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    paginaError();
    exit;
}

$pod = gethostname() ?: 'desconocido';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notas</title>
  <style>
    body { margin: 0; font-family: Calibri, "Segoe UI", sans-serif; background: #eef2f5; color: #1c2830; }
    main { max-width: 40rem; margin: 2.5rem auto; padding: 0 1rem 2rem; }
    h1 { font-size: 1.8rem; margin-bottom: 0.25rem; }
    p.lead { margin-top: 0; color: #52616b; }
    form, article, .vacio { background: #fff; border-radius: 8px; padding: 1rem 1.1rem; margin-bottom: 0.8rem; }
    textarea { width: 100%; min-height: 5rem; box-sizing: border-box; font: inherit; padding: 0.5rem; }
    button { margin-top: 0.6rem; font: inherit; padding: 0.45rem 0.9rem; background: #1f6f8b; color: #fff; border: 0; border-radius: 6px; }
    .error { color: #9b2335; }
    time { display: block; color: #6b7780; font-size: 0.9rem; margin-top: 0.35rem; }
    footer { color: #6b7780; font-size: 0.9rem; }
  </style>
</head>
<body>
  <main>
    <h1>Notas</h1>
    <p class="lead">Escribe una nota y se guarda en MariaDB. Hay dos copias de esta página. El nombre del pod, al final, indica cuál ha respondido.</p>

    <form method="post" action="/">
      <label for="texto">Texto de la nota</label>
      <textarea id="texto" name="texto" maxlength="255" required></textarea>
      <?php if ($error !== ''): ?>
        <p class="error"><?= h($error) ?></p>
      <?php endif; ?>
      <button type="submit">Guardar nota</button>
    </form>

    <?php if ($notas === []): ?>
      <p class="vacio">Aún no hay notas. Escribe la primera en el formulario.</p>
    <?php endif; ?>

    <?php foreach ($notas as $nota): ?>
      <article>
        <p><?= h((string) $nota['texto']) ?></p>
        <time><?= h((string) $nota['creado_en']) ?></time>
      </article>
    <?php endforeach; ?>

    <footer>Pod que ha respondido: <strong><?= h($pod) ?></strong>. Si al recargar cambia el nombre, ha respondido la otra réplica.</footer>
  </main>
</body>
</html>
