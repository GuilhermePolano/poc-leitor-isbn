<?php
declare(strict_types=1);
/**
 * Tela 1 — Consulta por bipagem.
 * Front-end estático que dispara as APIs em api/*.php.
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Livros por ISBN</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="topo">
        <h1>📚 Consulta de Livros por ISBN</h1>
        <nav>
            <a href="lista.php" class="btn-link">Ver cadastrados</a>
        </nav>
    </header>

    <main class="container">
        <section class="card card-bipagem">
            <label for="campo-isbn"><strong>Bipe o código de barras do livro</strong> (ou digite + Enter)</label>
            <input
                type="text"
                id="campo-isbn"
                autocomplete="off"
                autofocus
                placeholder="Aguardando leitura..."
                inputmode="numeric"
            >
            <div id="status-consulta" class="status">Aguardando leitura...</div>
        </section>

        <section id="painel-livro" class="card hidden">
            <div class="livro-topo">
                <div class="capa">
                    <img id="img-capa" src="" alt="Capa do livro">
                </div>
                <div class="dados">
                    <h2 id="livro-titulo">—</h2>
                    <p class="subtitulo" id="livro-subtitulo"></p>
                    <table class="tabela-dados">
                        <tr><th>Autor(es)</th><td id="livro-autores">—</td></tr>
                        <tr><th>Editora</th><td id="livro-editora">—</td></tr>
                        <tr><th>Ano</th><td id="livro-ano">—</td></tr>
                        <tr><th>ISBN-13</th><td id="livro-isbn13">—</td></tr>
                        <tr><th>ISBN-10</th><td id="livro-isbn10">—</td></tr>
                        <tr><th>Idioma</th><td id="livro-idioma">—</td></tr>
                        <tr><th>Formato</th><td id="livro-formato">—</td></tr>
                        <tr><th>Páginas</th><td id="livro-paginas">—</td></tr>
                        <tr><th>Dimensões</th><td id="livro-dimensoes">—</td></tr>
                        <tr><th>Preço sugerido</th><td id="livro-preco">—</td></tr>
                        <tr><th>Local</th><td id="livro-local">—</td></tr>
                    </table>
                </div>
            </div>

            <div class="meta-info">
                <p><strong>Assuntos:</strong> <span id="livro-assuntos">—</span></p>
                <p><strong>Categorias:</strong> <span id="livro-categorias">—</span></p>
                <p><strong>Sinopse:</strong></p>
                <div class="sinopse" id="livro-sinopse">—</div>
                <p class="fonte" id="livro-fonte"></p>
            </div>
        </section>

        <section id="painel-hyb" class="card hidden">
            <h3>📋 Campos Complementares (HYB) <small>— todos opcionais</small></h3>
            <p class="dica">Pré-preenchidos com defaults do <code>.env</code> e dados da API.
            Edite ou deixe em branco. Campos vazios vão em branco para o XLSX.</p>

            <form id="form-hyb" class="grid-hyb">
                <label>Bem/Produto <small>(só para edição no HYB)</small>
                    <input type="text" name="bem_produto" maxlength="50">
                </label>

                <label>Unidade
                    <input type="text" name="unidade" maxlength="20">
                </label>

                <label>Categoria
                    <input type="text" name="categoria" maxlength="255">
                </label>

                <label>NCM
                    <input type="text" name="ncm" maxlength="20" placeholder="9999.99.99">
                </label>

                <label>Preço de Venda
                    <input type="text" name="preco_venda" inputmode="decimal">
                </label>

                <label>Estoque Mínimo
                    <input type="text" name="estoque_minimo" inputmode="numeric">
                </label>

                <label>Referência
                    <input type="text" name="referencia" maxlength="100">
                </label>

                <label>Patrimônio
                    <select name="patrimonio">
                        <option value="">(em branco)</option>
                        <option value="S">Sim</option>
                        <option value="N">Não</option>
                    </select>
                </label>

                <label>Depreciação (%)
                    <input type="text" name="depreciacao_pct" inputmode="decimal">
                </label>

                <label>Tipo
                    <select name="tipo">
                        <option value="">(em branco)</option>
                        <option value="Desconhecido">Desconhecido</option>
                        <option value="Móvel">Móvel</option>
                        <option value="Imóvel">Imóvel</option>
                    </select>
                </label>

                <label>Estoque Inicial — Quantidade
                    <input type="text" name="estoque_ini_qtd" inputmode="decimal">
                </label>

                <label>Estoque Inicial — Custo Unitário
                    <input type="text" name="estoque_ini_custo" inputmode="decimal">
                </label>

                <label class="span-2">Descrição (auto-gerada, editável)
                    <textarea name="descricao" rows="3"></textarea>
                </label>
            </form>

            <div class="acoes">
                <button id="btn-salvar" type="button" class="btn btn-primario">💾 Salvar no banco <span class="atalho">F8</span></button>
                <button id="btn-copiar" type="button" class="btn">📋 Copiar JSON <span class="atalho">F2</span></button>
                <button id="btn-novo" type="button" class="btn btn-secundario">↺ Nova consulta <span class="atalho">ESC</span></button>
            </div>
        </section>

        <section id="painel-erro" class="card alerta hidden">
            <h3>⚠️ <span id="erro-titulo">Erro</span></h3>
            <p id="erro-detalhe"></p>
        </section>
    </main>

    <div id="toast" class="toast hidden"></div>

    <audio id="beep-ok" preload="auto"
        src="data:audio/wav;base64,UklGRkQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YSAAAACAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIA=">
    </audio>

    <script src="assets/js/app.js"></script>
</body>
</html>
