const lista = document.getElementById('itens-frete');
const adicionar = document.getElementById('adicionar-produto');
const aviso = document.getElementById('aviso-itens');

// Guarda uma linha limpa para criar novos campos.
const modelo = lista.querySelector('.item-frete').cloneNode(true);
let proximoIndice = lista.children.length;

adicionar.addEventListener('click', function () {
    if (lista.children.length >= 100) {
        aviso.textContent = 'Limite de 100 produtos por frete.';
        return;
    }

    const linha = modelo.cloneNode(true);

    linha.querySelectorAll('[name]').forEach(function (campo) {
        campo.name = campo.name.replace(
            /itens\[\d+\]/,
            'itens[' + proximoIndice + ']'
        );

        if (campo.tagName === 'SELECT') {
            campo.value = '';
        } else {
            campo.value = '1';
        }
    });

    proximoIndice++;
    lista.appendChild(linha);
    aviso.textContent = '';
    linha.querySelector('select').focus();
});

lista.addEventListener('click', function (evento) {
    const botao = evento.target.closest('.remover-item');

    if (!botao) {
        return;
    }

    if (lista.children.length === 1) {
        aviso.textContent = 'O frete precisa ter pelo menos um produto.';
        return;
    }

    botao.closest('.item-frete').remove();
    aviso.textContent = '';
});s