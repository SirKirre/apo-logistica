<?php
if (!isset($pdo)) {
    http_response_code(403);
    exit('Acesse pelo menu do sistema.');
}

function escaparEndereco($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// Nome do campo => [rótulo, limite de caracteres].
$campos = [
    'identificacao' => ['Identificação', 150],
    'cep' => ['CEP', 9],
    'uf' => ['UF', 2],
    'logradouro' => ['Logradouro', 200],
    'numero' => ['Número', 20],
    'complemento' => ['Complemento', 150],
    'bairro' => ['Bairro', 100],
    'cidade' => ['Cidade', 100]
];

$endereco = array_fill_keys(array_keys($campos), '');
$endereco['id'] = '';
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token)
        || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Formulário expirado. Recarregue a página.');
    }

    foreach ($endereco as $campo => $padrao) {
        $valor = $_POST[$campo] ?? '';
        $endereco[$campo] = is_string($valor) ? trim($valor) : '';
    }

    $endereco['uf'] = mb_strtoupper($endereco['uf']);

    foreach ($campos as $campo => [$rotulo, $limite]) {
        if ($campo !== 'complemento' && $endereco[$campo] === '') {
            $erros[] = "Preencha o campo $rotulo.";
        }

        if (mb_strlen($endereco[$campo]) > $limite) {
            $erros[] = "$rotulo permite até $limite caracteres.";
        }
    }

    $ufs = explode(
        ' ',
        'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'
    );

    if (!in_array($endereco['uf'], $ufs, true)) {
        $erros[] = 'Informe uma UF válida, como SC.';
    }

    if (!preg_match('/^\d{5}-?\d{3}$/', $endereco['cep'])) {
        $erros[] = 'Informe o CEP com 8 números, com ou sem hífen.';
    }

    if ($endereco['id'] !== '') {
        $id = filter_var(
            $endereco['id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            $erros[] = 'Identificador inválido.';
        } else {
            $consulta = $pdo->prepare(
                'SELECT id FROM enderecos WHERE id = ?'
            );
            $consulta->execute([$id]);

            if (!$consulta->fetch()) {
                $erros[] = 'Endereço não encontrado.';
            }
        }
    }

    if (!$erros) {
        $dados = [];

        foreach ($campos as $campo => $configuracao) {
            $dados[] = $endereco[$campo];
        }

        if ($endereco['id'] === '') {
            $sql = 'INSERT INTO enderecos
                (identificacao, cep, uf, logradouro, numero,
                 complemento, bairro, cidade)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        } else {
            $sql = 'UPDATE enderecos SET
                identificacao = ?, cep = ?, uf = ?,
                logradouro = ?, numero = ?, complemento = ?,
                bairro = ?, cidade = ? WHERE id = ?';

            $dados[] = $endereco['id'];
        }

        $comando = $pdo->prepare($sql);
        $comando->execute($dados);

        header('Location: index.php?pagina=enderecos&salvo=1');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    && isset($_GET['editar'])) {
    $id = filter_var(
        $_GET['editar'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        $erros[] = 'Identificador inválido.';
    } else {
        $consulta = $pdo->prepare(
            'SELECT * FROM enderecos WHERE id = ?'
        );
        $consulta->execute([$id]);
        $registro = $consulta->fetch();

        if ($registro) {
            $endereco = $registro;
        } else {
            $erros[] = 'Endereço não encontrado.';
        }
    }
}

$enderecos = $pdo->query(
    'SELECT * FROM enderecos ORDER BY id DESC'
)->fetchAll();
?>

<?php if (isset($_GET['salvo'])): ?>
    <div class="alert alert-success" role="status">
        Endereço salvo com sucesso.
    </div>
<?php endif; ?>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-danger" role="alert">
        <?= escaparEndereco($erro) ?>
    </div>
<?php endforeach; ?>

<div class="card mb-4">
    <div class="card-body p-4">
        <h3 class="h5 mb-4">
            <?= $endereco['id'] ? 'Editar endereço' : 'Cadastrar endereço' ?>
        </h3>

        <form method="post" action="index.php?pagina=enderecos">
            <input type="hidden" name="csrf"
                   value="<?= escaparEndereco($_SESSION['csrf']) ?>">
            <input type="hidden" name="id"
                   value="<?= escaparEndereco($endereco['id']) ?>">

            <div class="mb-3">
                <label for="endereco-id" class="form-label">ID</label>
                <input id="endereco-id" class="form-control"
                       value="<?= escaparEndereco($endereco['id']) ?>"
                       placeholder="Gerado ao salvar" disabled>
            </div>

            <div class="row g-3">
                <?php foreach ($campos as $campo => [$rotulo, $limite]): ?>
                    <div class="col-md-6">
                        <label for="<?= $campo ?>" class="form-label">
                            <?= $rotulo ?>
                            <?= $campo !== 'complemento' ? '*' : '' ?>
                        </label>

                        <input
                            id="<?= $campo ?>"
                            name="<?= $campo ?>"
                            class="form-control"
                            maxlength="<?= $limite ?>"
                            value="<?= escaparEndereco($endereco[$campo]) ?>"
                            <?= $campo !== 'complemento' ? 'required' : '' ?>
                        >
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary mt-4">
                Salvar endereço
            </button>

            <a href="index.php?pagina=enderecos"
               class="btn btn-outline-secondary mt-4">
                Novo / Cancelar
            </a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-4">
        <h3 class="h5 mb-3">Endereços cadastrados</h3>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Identificação</th>
                        <th scope="col">Endereço</th>
                        <th scope="col">Cidade / UF</th>
                        <th scope="col">CEP</th>
                        <th scope="col">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enderecos as $item): ?>
                        <tr>
                            <td><?= (int) $item['id'] ?></td>
                            <td>
                                <?= escaparEndereco($item['identificacao']) ?>
                            </td>
                            <td>
                                <?= escaparEndereco(
                                    $item['logradouro'] . ', ' .
                                    $item['numero'] . ' — ' .
                                    $item['bairro']
                                ) ?>
                            </td>
                            <td>
                                <?= escaparEndereco(
                                    $item['cidade'] . ' / ' . $item['uf']
                                ) ?>
                            </td>
                            <td><?= escaparEndereco($item['cep']) ?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="index.php?pagina=enderecos&editar=<?= (int) $item['id'] ?>">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$enderecos): ?>
                        <tr>
                            <td colspan="6">Nenhum endereço cadastrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>