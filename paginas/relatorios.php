<?php
if (!isset($pdo)) {
    http_response_code(403);
    exit('Acesse pelo menu do sistema.');
}

function escaparRelatorio($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function dataValidaRelatorio($valor)
{
    if (!is_string($valor)) {
        return false;
    }

    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

    return $data
        && $data->format('Y-m-d') === $valor
        && (int) $data->format('Y') >= 1000;
}

function dataBrasileira($valor)
{
    return DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $valor
    )->format('d/m/Y');
}

// Evita que textos sejam interpretados como fórmulas no Excel.
function textoCsv($valor)
{
    $texto = (string) $valor;

    if (preg_match('/^[\s]*[=+\-@]/u', $texto)) {
        return "'" . $texto;
    }

    return $texto;
}

date_default_timezone_set('America/Sao_Paulo');

$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fim = $_GET['fim'] ?? date('Y-m-d');

$erros = [];
$fretes = [];

if (!dataValidaRelatorio($inicio)
    || !dataValidaRelatorio($fim)) {
    $erros[] = 'Informe datas válidas.';
} elseif ($inicio > $fim) {
    $erros[] = 'A data inicial não pode ser posterior à final.';
}

if (!$erros) {
    $consulta = $pdo->prepare(
        'SELECT
            f.id,
            f.data_frete,
            t.razao_social AS transportador,
            o.identificacao AS origem,
            o.cidade AS origem_cidade,
            o.uf AS origem_uf,
            d.identificacao AS destino,
            d.cidade AS destino_cidade,
            d.uf AS destino_uf,
            p.nome AS produto,
            p.unidade,
            fp.quantidade
         FROM fretes f
         JOIN transportadores t ON t.id = f.transportador_id
         JOIN enderecos o ON o.id = f.origem_id
         JOIN enderecos d ON d.id = f.destino_id
         JOIN frete_produtos fp ON fp.frete_id = f.id
         JOIN produtos p ON p.id = fp.produto_id
         WHERE f.data_frete BETWEEN ? AND ?
         ORDER BY f.data_frete DESC, f.id DESC, fp.id'
    );

    $consulta->execute([$inicio, $fim]);

    // Agrupa os produtos para mostrar uma linha por frete.
    while ($linha = $consulta->fetch()) {
        $id = $linha['id'];

        if (!isset($fretes[$id])) {
            $fretes[$id] = [
                'id' => $id,
                'data' => $linha['data_frete'],
                'transportador' => $linha['transportador'],
                'origem' => $linha['origem'] . ' — '
                    . $linha['origem_cidade'] . '/'
                    . $linha['origem_uf'],
                'destino' => $linha['destino'] . ' — '
                    . $linha['destino_cidade'] . '/'
                    . $linha['destino_uf'],
                'produtos' => []
            ];
        }

        $fretes[$id]['produtos'][] = $linha['produto']
            . ' × ' . $linha['quantidade']
            . ' ' . $linha['unidade'];
    }
}

// O download usa exatamente o mesmo período da consulta.
if (($_GET['baixar'] ?? '') === 'csv' && !$erros) {
    header('Content-Type: text/csv; charset=UTF-8');
    header(
        'Content-Disposition: attachment; filename="fretes_'
        . $inicio . '_a_' . $fim . '.csv"'
    );
    header('Cache-Control: no-store');

    $arquivo = fopen('php://output', 'w');

    // Ajuda o Excel a reconhecer acentos em UTF-8.
    fwrite($arquivo, "\xEF\xBB\xBF");

    fputcsv(
        $arquivo,
        ['ID', 'Data', 'Transportador', 'Origem', 'Destino', 'Produtos'],
        ';',
        '"',
        ''
    );

    foreach ($fretes as $frete) {
        $linha = [
            $frete['id'],
            dataBrasileira($frete['data']),
            $frete['transportador'],
            $frete['origem'],
            $frete['destino'],
            implode(' | ', $frete['produtos'])
        ];

        fputcsv(
            $arquivo,
            array_map('textoCsv', $linha),
            ';',
            '"',
            ''
        );
    }

    fclose($arquivo);
    exit;
}

$linkDownload = 'index.php?' . http_build_query([
    'pagina' => 'relatorios',
    'inicio' => is_string($inicio) ? $inicio : '',
    'fim' => is_string($fim) ? $fim : '',
    'baixar' => 'csv'
]);
?>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-danger" role="alert">
        <?= escaparRelatorio($erro) ?>
    </div>
<?php endforeach; ?>

<div class="card mb-4">
    <div class="card-body p-4">
        <h3 class="h5 mb-4">Filtrar por período</h3>

        <form method="get" action="index.php" class="row g-3 align-items-end">
            <input type="hidden" name="pagina" value="relatorios">

            <div class="col-md-4">
                <label for="inicio" class="form-label">Data inicial</label>
                <input type="date" id="inicio" name="inicio"
                       class="form-control" required
                       min="1000-01-01" max="9999-12-31"
                       value="<?= escaparRelatorio(
                           is_string($inicio) ? $inicio : ''
                       ) ?>">
            </div>

            <div class="col-md-4">
                <label for="fim" class="form-label">Data final</label>
                <input type="date" id="fim" name="fim"
                       class="form-control" required
                       min="1000-01-01" max="9999-12-31"
                       value="<?= escaparRelatorio(
                           is_string($fim) ? $fim : ''
                       ) ?>">
            </div>

            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                    Filtrar
                </button>

                <a href="index.php?pagina=relatorios"
                   class="btn btn-outline-secondary">
                    Limpar
                </a>
            </div>
        </form>

        <p class="small text-secondary mt-3 mb-0">
            Para consultar um único dia, use a mesma data nos dois campos.
        </p>
    </div>
</div>

<div class="card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <h3 class="h5 mb-0">Fretes do período</h3>

            <?php if (!$erros): ?>
                <a href="<?= escaparRelatorio($linkDownload) ?>"
                   class="btn btn-outline-primary">
                    Baixar CSV
                </a>
            <?php endif; ?>
        </div>

        <p class="text-secondary small">
            <?= count($fretes) ?> frete(s) encontrado(s).
        </p>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Data</th>
                        <th scope="col">Transportador</th>
                        <th scope="col">Origem</th>
                        <th scope="col">Destino</th>
                        <th scope="col">Produtos / quantidade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fretes as $frete): ?>
                        <tr>
                            <td><?= (int) $frete['id'] ?></td>
                            <td>
                                <?= dataBrasileira($frete['data']) ?>
                            </td>
                            <td>
                                <?= escaparRelatorio($frete['transportador']) ?>
                            </td>
                            <td>
                                <?= escaparRelatorio($frete['origem']) ?>
                            </td>
                            <td>
                                <?= escaparRelatorio($frete['destino']) ?>
                            </td>
                            <td>
                                <?php foreach ($frete['produtos'] as $produto): ?>
                                    <div><?= escaparRelatorio($produto) ?></div>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$fretes): ?>
                        <tr>
                            <td colspan="6">
                                <?= $erros
                                    ? 'Corrija o período para consultar.'
                                    : 'Nenhum frete encontrado neste período.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>