<?php
if (!isset($pdo)) {
    http_response_code(403);
    exit('Acesse pelo menu do sistema.');
}

function escaparTransportador($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$campos = [
    'razao_social' => ['Razão social', 150],
    'cnpj' => ['CNPJ', 18],
    'responsavel' => ['Responsável', 100],
    'email' => ['E-mail', 150],
    'telefone' => ['Telefone', 20]
];

$transportador = array_fill_keys(array_keys($campos), '');
$transportador['id'] = '';
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token)
        || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Formulário expirado. Recarregue a página.');
    }

    foreach ($transportador as $campo => $padrao) {
        $valor = $_POST[$campo] ?? '';
        $transportador[$campo] = is_string($valor)
            ? trim($valor) : '';
    }

    foreach ($campos as $campo => [$rotulo, $limite]) {
        if ($transportador[$campo] === '') {
            $erros[] = "Preencha o campo $rotulo.";
        }

        if (mb_strlen($transportador[$campo]) > $limite) {
            $erros[] = "$rotulo permite até $limite caracteres.";
        }
    }

    if (!filter_var($transportador['email'], FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido.';
    }

    // Confere apenas o formato, sem consultar a autenticidade.
    if (!preg_match(
        '/^(?:\d{14}|\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})$/',
        $transportador['cnpj']
    )) {
        $erros[] = 'Informe o CNPJ com 14 números, com ou sem pontuação.';
    }

    if ($transportador['id'] !== '') {
        $id = filter_var(
            $transportador['id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            $erros[] = 'Identificador inválido.';
        } else {
            $consulta = $pdo->prepare(
                'SELECT id FROM transportadores WHERE id = ?'
            );
            $consulta->execute([$id]);

            if (!$consulta->fetch()) {
                $erros[] = 'Transportador não encontrado.';
            }
        }
    }

    if (!$erros) {
        $dados = [];

        foreach ($campos as $campo => $configuracao) {
            $dados[] = $transportador[$campo];
        }

        if ($transportador['id'] === '') {
            $sql = 'INSERT INTO transportadores
                (razao_social, cnpj, responsavel, email, telefone)
                VALUES (?, ?, ?, ?, ?)';
        } else {
            $sql = 'UPDATE transportadores SET
                razao_social = ?, cnpj = ?, responsavel = ?,
                email = ?, telefone = ? WHERE id = ?';

            $dados[] = $transportador['id'];
        }

        $comando = $pdo->prepare($sql);
        $comando->execute($dados);

        header('Location: index.php?pagina=transportadores&salvo=1');
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
            'SELECT * FROM transportadores WHERE id = ?'
        );
        $consulta->execute([$id]);
        $registro = $consulta->fetch();

        if ($registro) {
            $transportador = $registro;
        } else {
            $erros[] = 'Transportador não encontrado.';
        }
    }
}

$transportadores = $pdo->query(
    'SELECT * FROM transportadores ORDER BY id DESC'
)->fetchAll();
?>

<?php if (isset($_GET['salvo'])): ?>
    <div class="alert alert-success" role="status">
        Transportador salvo com sucesso.
    </div>
<?php endif; ?>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-danger" role="alert">
        <?= escaparTransportador($erro) ?>
    </div>
<?php endforeach; ?>

<div class="card mb-4">
    <div class="card-body p-4">
        <h3 class="h5 mb-4">
            <?= $transportador['id']
                ? 'Editar transportador' : 'Cadastrar transportador' ?>
        </h3>

        <form method="post" action="index.php?pagina=transportadores">
            <input type="hidden" name="csrf"
                   value="<?= escaparTransportador($_SESSION['csrf']) ?>">

            <input type="hidden" name="id"
                   value="<?= escaparTransportador($transportador['id']) ?>">

            <div class="mb-3">
                <label for="transportador-id" class="form-label">ID</label>
                <input id="transportador-id" class="form-control"
                       value="<?= escaparTransportador($transportador['id']) ?>"
                       placeholder="Gerado ao salvar" disabled>
            </div>

            <div class="row g-3">
                <?php foreach ($campos as $campo => [$rotulo, $limite]): ?>
                    <div class="col-md-6">
                        <label for="<?= $campo ?>" class="form-label">
                            <?= $rotulo ?> *
                        </label>

                        <input
                            id="<?= $campo ?>"
                            name="<?= $campo ?>"
                            type="<?= $campo === 'email' ? 'email' : 'text' ?>"
                            class="form-control"
                            maxlength="<?= $limite ?>"
                            value="<?= escaparTransportador($transportador[$campo]) ?>"
                            required
                        >
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary mt-4">
                Salvar transportador
            </button>

            <a href="index.php?pagina=transportadores"
               class="btn btn-outline-secondary mt-4">
                Novo / Cancelar
            </a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-4">
        <h3 class="h5 mb-3">Transportadores cadastrados</h3>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Transportador</th>
                        <th scope="col">CNPJ</th>
                        <th scope="col">E-mail</th>
                        <th scope="col">Telefone</th>
                        <th scope="col">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transportadores as $item): ?>
                        <tr>
                            <td><?= (int) $item['id'] ?></td>
                            <td>
                                <?= escaparTransportador($item['razao_social']) ?>
                            </td>
                            <td>
                                <?= escaparTransportador($item['cnpj']) ?>
                            </td>
                            <td>
                                <?= escaparTransportador($item['email']) ?>
                            </td>
                            <td>
                                <?= escaparTransportador($item['telefone']) ?>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="index.php?pagina=transportadores&editar=<?= (int) $item['id'] ?>">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$transportadores): ?>
                        <tr>
                            <td colspan="6">
                                Nenhum transportador cadastrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>