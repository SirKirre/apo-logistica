<?php
$paginas = [
    'produtos' => 'Produtos',
    'enderecos' => 'Endereços',
    'transportadores' => 'Transportadores',
    'frete' => 'Novo Frete',
    'relatorios' => 'Relatórios'
];

$pagina = $_GET['pagina'] ?? 'produtos';

if (!is_string($pagina) || !isset($paginas[$pagina])) {
    $pagina = 'produtos';
}

$titulo = $paginas[$pagina];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $titulo ?> | Gestão de fretes</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <aside class="menu-lateral">
        <img
    src="assets/img/logo_vertex.png"
    alt="Vertex Logística"
    class="logo-menu">
        <h1 class="h5">Gestão de fretes</h1>
        <p class="text-secondary small">SISTEMA INTERNO</p>

        <nav class="nav flex-column gap-2 mt-5">
            <?php foreach ($paginas as $chave => $nome): ?>
                <a
                    href="?pagina=<?= $chave ?>"
                    class="nav-link <?= $pagina === $chave ? 'active' : '' ?>"
                    <?= $pagina === $chave ? 'aria-current="page"' : '' ?>
                >
                    <?= $nome ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <p class="aviso small">
            Uso interno<br>
            Dados fictícios para demonstração
        </p>
    </aside>

    <main class="area-principal">
        <header class="barra-superior">
            Sistema interno / <?= $titulo ?>
        </header>

        <section class="p-4 p-lg-5">
            <h2 class="h3 mb-4"><?= $titulo ?></h2>

            <div class="card">
                <div class="card-body">
                    Área de <?= $titulo ?>.
                    Os formulários serão implementados na próxima etapa.
                </div>
            </div>
        </section>
    </main>
</body>
</html>