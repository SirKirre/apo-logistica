<?php
if (!isset($pdo)) {
    http_response_code(403);
    exit('Acesse pelo menu do sistema.');
}

function escaparFrete($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function inteiroFrete($valor)
{
    if (!is_string($valor) && !is_int($valor)) {
        return false;
    }

    return filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
    );
}

$transportadores = $pdo->query(
    'SELECT id, razao_social FROM transportadores ORDER BY razao_social'
)->fetchAll(PDO::FETCH_UNIQUE);

$enderecos = $pdo->query(
    'SELECT id, identificacao, cidade, uf
     FROM enderecos ORDER BY identificacao'
)->fetchAll(PDO::FETCH_UNIQUE);

$produtos = $pdo->query(
    'SELECT id, nome, unidade FROM produtos ORDER BY nome'
)->fetchAll(PDO::FETCH_UNIQUE);

date_default_timezone_set('America/Sao_Paulo');

$dados = [
    'data_frete' => date('Y-m-d'),
    'transportador_id' => '',
    'origem_id' => '',
    'destino_id' => ''
];

$itens = [['produto_id' => '', 'quantidade' => 1]];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token)
        || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Formulário expirado. Recarregue a página.');
    }

    foreach ($dados as $campo => $padrao) {
        $valor = $_POST[$campo] ?? '';
        $dados[$campo] = is_string($valor) ? trim($valor) : '';
    }

    $data = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $dados['data_frete']
    );

    if (!$data || $data->format('Y-m-d') !== $dados['data_frete']
        || (int) $data->format('Y') < 1000) {
        $erros[] = 'Informe uma data válida.';
    }

    $transportadorId = inteiroFrete($dados['transportador_id']);
    $origemId = inteiroFrete($dados['origem_id']);
    $destinoId = inteiroFrete($dados['destino_id']);

    if (!$transportadorId || !isset($transportadores[$transportadorId])) {
        $erros[] = 'Selecione um transportador cadastrado.';
    }

    if (!$origemId || !isset($enderecos[$origemId])) {
        $erros[] = 'Selecione um endereço de origem.';
    }

    if (!$destinoId || !isset($enderecos[$destinoId])) {
        $erros[] = 'Selecione um endereço de destino.';
    }

    if ($origemId && $origemId === $destinoId) {
        $erros[] = 'Origem e destino devem ser diferentes.';
    }

    $recebidos = $_POST['itens'] ?? [];
    $itens = [];
    $selecionados = [];

    if (!is_array($recebidos)
        || count($recebidos) < 1
        || count($recebidos) > 100) {
        $erros[] = 'Inclua entre 1 e 100 produtos.';
    } else {
        foreach ($recebidos as $item) {
            if (!is_array($item)) {
                $erros[] = 'Produto inválido.';
                continue;
            }

            $produtoId = inteiroFrete($item['produto_id'] ?? '');
            $quantidade = inteiroFrete($item['quantidade'] ?? '');

            $itens[] = [
                'produto_id' => $produtoId ?: '',
                'quantidade' => $quantidade ?: ''
            ];

            if (!$produtoId || !isset($produtos[$produtoId])) {
                $erros[] = 'Selecione um produto cadastrado em cada linha.';
                continue;
            }

            if (!$quantidade) {
                $erros[] = 'A quantidade deve ser um número inteiro positivo.';
            }

            if (isset($selecionados[$produtoId])) {
                $erros[] = 'Produto repetido: use uma única linha e ajuste a quantidade.';
            }

            $selecionados[$produtoId] = true;
        }
    }

    if (!$erros) {
        try {
            // Grava o frete e todos os produtos juntos.
            $pdo->beginTransaction();

            $comando = $pdo->prepare(
                'INSERT INTO fretes
                 (data_frete, transportador_id, origem_id, destino_id)
                 VALUES (?, ?, ?, ?)'
            );

            $comando->execute([
                $dados['data_frete'],
                $transportadorId,
                $origemId,
                $destinoId
            ]);

            $freteId = $pdo->lastInsertId();

            $comandoItem = $pdo->prepare(
                'INSERT INTO frete_produtos
                 (frete_id, produto_id, quantidade)
                 VALUES (?, ?, ?)'
            );

            foreach ($itens as $item) {
                $comandoItem->execute([
                    $freteId,
                    $item['produto_id'],
                    $item['quantidade']
                ]);
            }

            $pdo->commit();

            $_SESSION['frete_salvo'] = $freteId;
            header('Location: index.php?pagina=frete');
            exit;
        } catch (PDOException $erro) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($erro->getMessage());
            $erros[] = 'Não foi possível salvar. Confira os cadastros e tente novamente.';
        }
    }

    if (!$itens) {
        $itens = [['produto_id' => '', 'quantidade' => 1]];
    }
}

$freteSalvo = $_SESSION['frete_salvo'] ?? null;
unset($_SESSION['frete_salvo']);

$podeCadastrar = count($transportadores) > 0
    && count($enderecos) >= 2
    && count($produtos) > 0;
?>

<?php if ($freteSalvo): ?>
    <div class="alert alert-success" role="status">
        Frete nº <?= (int) $freteSalvo ?> salvo com sucesso!
    </div>
<?php endif; ?>

<?php foreach (array_unique($erros) as $erro): ?>
    <div class="alert alert-danger" role="alert">
        <?= escaparFrete($erro) ?>
    </div>
<?php endforeach; ?>

<?php if (!$podeCadastrar): ?>
    <div class="alert alert-warning">
        Antes de criar um frete, cadastre pelo menos um produto,
        um transportador e dois endereços diferentes.
    </div>
<?php endif; ?>

<form method="post" action="index.php?pagina=frete">
    <input type="hidden" name="csrf"
           value="<?= escaparFrete($_SESSION['csrf']) ?>">

    <div class="card mb-4">
        <div class="card-body p-4">
            <h3 class="h5 mb-4">Dados do frete</h3>

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="data_frete" class="form-label">
                        Data do frete *
                    </label>
                    <input type="date" id="data_frete" name="data_frete"
                           class="form-control" required
                           min="1000-01-01" max="9999-12-31"
                           value="<?= escaparFrete($dados['data_frete']) ?>">
                </div>

                <div class="col-md-8">
                    <label for="transportador_id" class="form-label">
                        Transportador *
                    </label>
                    <select id="transportador_id" name="transportador_id"
                            class="form-select" required>
                        <option value="">Selecione</option>
                        <?php foreach ($transportadores as $id => $item): ?>
                            <option value="<?= (int) $id ?>"
                                <?= (string) $dados['transportador_id'] === (string) $id
                                    ? 'selected' : '' ?>>
                                <?= escaparFrete($item['razao_social']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php foreach (['origem_id' => 'Origem', 'destino_id' => 'Destino'] as $campo => $rotulo): ?>
                    <div class="col-md-6">
                        <label for="<?= $campo ?>" class="form-label">
                            <?= $rotulo ?> *
                        </label>
                        <select id="<?= $campo ?>" name="<?= $campo ?>"
                                class="form-select" required>
                            <option value="">Selecione</option>
                            <?php foreach ($enderecos as $id => $item): ?>
                                <option value="<?= (int) $id ?>"
                                    <?= (string) $dados[$campo] === (string) $id
                                        ? 'selected' : '' ?>>
                                    <?= escaparFrete(
                                        $item['identificacao'] . ' — ' .
                                        $item['cidade'] . '/' . $item['uf']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body p-4">
            <h3 class="h5 mb-3">Produtos do frete</h3>

            <div id="itens-frete">
                <?php foreach ($itens as $indice => $item): ?>
                    <div class="row g-3 mb-3 item-frete">
                        <div class="col-md-7">
                            <label class="form-label">
                                Produto *
                                <select name="itens[<?= $indice ?>][produto_id]"
                                        class="form-select" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($produtos as $id => $produto): ?>
                                        <option value="<?= (int) $id ?>"
                                            <?= (string) $item['produto_id'] === (string) $id
                                                ? 'selected' : '' ?>>
                                            <?= escaparFrete(
                                                $produto['nome'] .
                                                ' (' . $produto['unidade'] . ')'
                                            ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">
                                Quantidade *
                                <input type="number" class="form-control"
                                       name="itens[<?= $indice ?>][quantidade]"
                                       min="1" max="2147483647" step="1" required
                                       value="<?= escaparFrete($item['quantidade']) ?>">
                            </label>
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button"
                                    class="btn btn-outline-danger mb-2 remover-item">
                                Remover
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" id="adicionar-produto"
                    class="btn btn-outline-primary">
                Adicionar produto
            </button>

            <p id="aviso-itens" class="small text-secondary mt-2"
               role="status"></p>
        </div>
    </div>

    <button type="submit" class="btn btn-primary"
            <?= !$podeCadastrar ? 'disabled' : '' ?>>
        Salvar frete
    </button>

    <a href="index.php?pagina=frete" class="btn btn-outline-secondary">
        Limpar formulário
    </a>
</form>

<script src="assets/js/frete.js" defer></script>