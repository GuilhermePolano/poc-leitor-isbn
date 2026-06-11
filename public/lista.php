<?php
declare(strict_types=1);
/**
 * Tela 2 — Livros cadastrados.
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livros cadastrados — Consulta ISBN</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="topo">
        <h1>📚 Livros Cadastrados <small id="total-livros"></small></h1>
        <nav>
            <a href="index.php" class="btn-link">← Voltar à bipagem</a>
        </nav>
    </header>

    <main class="container">
        <section class="card">
            <div class="barra-filtros">
                <input type="search" id="campo-busca" placeholder="Buscar por título, autor ou ISBN…">
                <input type="text" id="campo-editora" placeholder="Filtrar por editora">
                <input type="text" id="campo-ano" placeholder="Ano" inputmode="numeric">
                <button id="btn-filtrar" class="btn btn-primario" type="button">Filtrar</button>
                <button id="btn-limpar" class="btn" type="button">Limpar</button>
            </div>

            <div class="barra-acoes">
                <button id="btn-abrir-modal-export" class="btn btn-primario" type="button">📥 Exportar para HYB</button>
            </div>

            <div class="tabela-wrap">
                <table class="tabela-livros">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="check-todos"></th>
                            <th>Capa</th>
                            <th>Título</th>
                            <th>Autor(es)</th>
                            <th>Editora</th>
                            <th>Ano</th>
                            <th>ISBN-13</th>
                            <th>Exportado?</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-livros">
                        <tr><td colspan="8" class="vazio">Carregando…</td></tr>
                    </tbody>
                </table>
            </div>

            <nav class="paginacao">
                <button id="btn-anterior" class="btn" type="button" disabled>‹ anterior</button>
                <span id="info-pagina">Página 1</span>
                <button id="btn-proxima" class="btn" type="button" disabled>próxima ›</button>
            </nav>
        </section>
    </main>

    <dialog id="modal-export" hidden>
        <header class="modal-header">
            <h2>📥 Exportar para HYB</h2>
            <button type="button" class="modal-fechar" id="btn-fechar-modal" aria-label="Fechar">×</button>
        </header>

        <div class="modal-toolbar">
            <input type="search" id="modal-busca" placeholder="Buscar por título, autor ou ISBN…">
            <select id="modal-filtro-baixa">
                <option value="todos">Mostrar todos</option>
                <option value="nao_baixados" selected>Não baixados</option>
                <option value="ja_baixados">Já baixados</option>
            </select>
            <label class="modal-check-todos">
                <input type="checkbox" id="modal-check-todos"> Selecionar todos
            </label>
        </div>

        <div class="modal-tabela-wrap">
            <table id="tabela-modal-export">
                <thead>
                    <tr>
                        <th style="width: 32px;">✓</th>
                        <th style="width: 52px;">Capa</th>
                        <th>Título</th>
                        <th>Autores</th>
                        <th>ISBN-13</th>
                        <th>Última baixa</th>
                        <th style="width: 80px;">Qtd</th>
                    </tr>
                </thead>
                <tbody id="modal-tbody">
                    <tr><td colspan="7" class="vazio">Carregando…</td></tr>
                </tbody>
            </table>
        </div>

        <footer class="modal-footer">
            <span id="modal-contador">0 selecionados de 0</span>
            <button type="button" id="btn-gerar-xlsx" class="btn btn-primario" disabled>
                Gerar XLSX (<span id="modal-contador-botao">0</span> livros)
            </button>
        </footer>
    </dialog>

    <div id="toast" class="toast hidden"></div>
    <script src="assets/js/lista.js"></script>
</body>
</html>
