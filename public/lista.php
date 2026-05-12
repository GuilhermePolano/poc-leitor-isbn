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
                <a href="../api/exportar.php" class="btn btn-primario">📥 Exportar todos para HYB.xlsx</a>
                <a href="../api/exportar.php?apenas_nao_exportados=1" class="btn">📥 Apenas não exportados</a>
                <button id="btn-exportar-sel" class="btn" type="button">📥 Exportar selecionados</button>
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

    <div id="toast" class="toast hidden"></div>
    <script src="assets/js/lista.js"></script>
</body>
</html>
