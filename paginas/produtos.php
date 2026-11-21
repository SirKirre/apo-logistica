<?php
// Impede a execução desta tela fora do index.php.
if (!isset($pdo)) {
    http_response_code(403);
    exit('Acesse pelo menu do sistema.');
}

// Protege os textos exibidos no HTML.
function escapar($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

$produto = [
    'id' => '',
    'nome' => '',
    'unidade' => 'un',
    'peso' => '0',
    'descricao' => ''
];

$erros = [];
$unidades = ['un', 'kg', 'cx', 'pct'];

// Recebe o formulário e cadastra ou atualiza o produto.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token)
        || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Formulário expirado. Recarregue a página.');
    }

    foreach ($produto as $campo => $padrao) {
        $valor = $_POST[$campo] ?? $padrao;
        $produto[$campo] = is_string($valor)
            ? trim($valor)
            : '';
    }

    $produto['peso'] = str_replace(',', '.', $produto['peso']);

    if ($produto['nome'] === ''
        || mb_strlen($produto['nome']) > 150) {
        $erros[] = 'Informe um nome com até 150 caracteres.';
    }

    if (!in_array($produto['unidade'], $unidades, true)) {
        $erros[] = 'Selecione uma unidade válida.';
    }

    if (!is_numeric($produto['peso'])
        || $produto['peso'] < 0
        || $produto['peso'] > 9999999.999) {
        $erros[] = 'Informe um peso válido, maior ou igual a zero.';
    }

    if (strlen($produto['descricao']) > 60000) {
        $erros[] = 'A descrição está muito longa.';
    }

    if ($produto['id'] !== '') {
        $idValido = filter_var(
            $produto['id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($idValido === false) {
            $erros[] = 'Identificador inválido.';
        } else {
            $consulta = $pdo->prepare(
                'SELECT id FROM produtos WHERE id = ?'
            );
            $consulta->execute([$idValido]);

            if (!$consulta->fetch()) {
                $erros[] = 'Produto não encontrado.';
            }
        }
    }

    if (!$erros) {
        $dados = [
            $produto['nome'],
            $produto['unidade'],
            $produto['peso'],
            $produto['descricao']
        ];

        if ($produto['id'] === '') {
            $sql = 'INSERT INTO produtos
                    (nome, unidade, peso, descricao)
                    VALUES (?, ?, ?, ?)';
        } else {
            $sql = 'UPDATE produtos
                    SET nome = ?, unidade = ?,
                        peso = ?, descricao = ?
                    WHERE id = ?';
            $dados[] = $produto['id'];
        }

        $comando = $pdo->prepare($sql);
        $comando->execute($dados);

        // Evita repetir o cadastro ao atualizar o navegador.
        header('Location: index.php?pagina=produtos&salvo=1');
        exit;
    }
}

// Carrega um produto para edição.
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
            'SELECT * FROM produtos WHERE id = ?'
        );
        $consulta->execute([$id]);
        $registro = $consulta->fetch();

        if ($registro) {
            $produto = $registro;
        } else {
            $erros[] = 'Produto não encontrado.';
        }
    }
}

$produtos = $pdo->query(
    'SELECT * FROM produtos ORDER BY id DESC'
)->fetchAll();
?>

<?php if (isset($_GET['salvo'])): ?>
    <div class="alert alert-success" role="status">
        Produto salvo com sucesso.
    </div>
<?php endif; ?>

<?php foreach ($erros as $erro): ?>
    <div class="alert alert-danger" role="alert">
        <?= escapar($erro) ?>
    </div>
<?php endforeach; ?>

<div class="card mb-4">
    <div class="card-body p-4">
        <h3 class="h5 mb-4">
            <?= $produto['id'] ? 'Editar produto' : 'Cadastrar produto' ?>
        </h3>

        <form method="post" action="index.php?pagina=produtos">
            <input type="hidden" name="csrf"
                   value="<?= escapar($_SESSION['csrf']) ?>">

            <input type="hidden" name="id"
                   value="<?= escapar($produto['id']) ?>">

            <div class="row g-3">
                <div class="col-md-2">
                    <label for="produto-id" class="form-label">ID</label>
                    <input id="produto-id" class="form-control"
                           value="<?= escapar($produto['id']) ?>"
                           placeholder="Automático" disabled>
                </div>

                <div class="col-md-5">
                    <label for="nome" class="form-label">Nome *</label>
                    <input id="nome" name="nome" class="form-control"
                           maxlength="150" required
                           value="<?= escapar($produto['nome']) ?>">
                </div>

                <div class="col-md-2">
                    <label for="unidade" class="form-label">Unidade *</label>
                    <select id="unidade" name="unidade"
                            class="form-select" required>
                        <?php foreach ($unidades as $unidade): ?>
                            <option value="<?= $unidade ?>"
                                <?= $produto['unidade'] === $unidade
                                    ? 'selected' : '' ?>>
                                <?= $unidade ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="peso" class="form-label">Peso unitário (kg) *</label>
                    <input id="peso" name="peso" type="number"
                           class="form-control" min="0"
                           max="9999999.999" step="0.001" required
                           value="<?= escapar($produto['peso']) ?>">
                </div>

                <div class="col-12">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea id="descricao" name="descricao"
                              class="form-control" rows="2"
                    ><?= escapar($produto['descricao']) ?></textarea>
                </div>
            </div>

            <button class="btn btn-primary mt-4" type="submit">
                Salvar produto
            </button>

            <a href="index.php?pagina=produtos"
               class="btn btn-outline-secondary mt-4">
                Novo / Cancelar
            </a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-4">
        <h3 class="h5 mb-3">Produtos cadastrados</h3>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Produto</th>
                        <th scope="col">Unidade</th>
                        <th scope="col">Peso unitário</th>
                        <th scope="col">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $item): ?>
                        <tr>
                            <td><?= escapar($item['id']) ?></td>
                            <td><?= escapar($item['nome']) ?></td>
                            <td><?= escapar($item['unidade']) ?></td>
                            <td>
                                <?= number_format(
                                    (float) $item['peso'], 3, ',', '.'
                                ) ?> kg
                            </td>
                            <td>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="index.php?pagina=produtos&editar=<?= (int) $item['id'] ?>">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$produtos): ?>
                        <tr>
                            <td colspan="5">Nenhum produto cadastrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>