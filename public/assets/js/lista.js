/* global document, fetch, window, URL, Blob */
(function () {
    'use strict';
    const $ = (s) => document.querySelector(s);

    const tbody       = $('#tbody-livros');
    const totalEl     = $('#total-livros');
    const infoPagina  = $('#info-pagina');
    const btnAnterior = $('#btn-anterior');
    const btnProxima  = $('#btn-proxima');
    const btnFiltrar  = $('#btn-filtrar');
    const btnLimpar   = $('#btn-limpar');
    const checkTodos  = $('#check-todos');
    const toast       = $('#toast');

    // --- Modal Exportar ---
    const btnAbrirModal     = $('#btn-abrir-modal-export');
    const modalExport       = $('#modal-export');
    const btnFecharModal    = $('#btn-fechar-modal');
    const modalBusca        = $('#modal-busca');
    const modalFiltroBaixa  = $('#modal-filtro-baixa');
    const modalCheckTodos   = $('#modal-check-todos');
    const modalTbody        = $('#modal-tbody');
    const modalContador     = $('#modal-contador');
    const modalContadorBtn  = $('#modal-contador-botao');
    const btnGerarXlsx      = $('#btn-gerar-xlsx');

    const LIMITE = 50;
    let offset = 0;
    let total  = 0;
    let filtros = {};

    // Cache da listagem completa para filtro client-side dentro do modal
    window.livrosModal = [];

    function mostrarToast(msg, tipo) {
        toast.textContent = msg;
        toast.className = 'toast ' + (tipo || 'info');
        toast.classList.remove('hidden');
        clearTimeout(window._t);
        window._t = setTimeout(() => toast.classList.add('hidden'), 3000);
    }

    function fmtAutores(autores) {
        if (!autores) return '—';
        if (Array.isArray(autores)) return autores.length ? autores.join(', ') : '—';
        return String(autores);
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtData(iso) {
        if (!iso) return '—';
        // espera "YYYY-MM-DD HH:ii:ss" ou ISO; tenta parsear
        const d = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(iso);
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yy = d.getFullYear();
        const hh = String(d.getHours()).padStart(2, '0');
        const mi = String(d.getMinutes()).padStart(2, '0');
        return `${dd}/${mm}/${yy} ${hh}:${mi}`;
    }

    async function carregar() {
        tbody.innerHTML = '<tr><td colspan="8" class="vazio">Carregando…</td></tr>';

        const params = new URLSearchParams({ limite: LIMITE, offset });
        Object.entries(filtros).forEach(([k, v]) => {
            if (v) params.set(k, v);
        });

        try {
            const res = await fetch('../api/livros.php?' + params.toString());
            const json = await res.json();

            total = json.total || 0;
            totalEl.textContent = total > 0 ? `(${total} registros)` : '(nenhum registro)';

            const livros = json.livros || [];
            if (livros.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="vazio">Nenhum livro encontrado.</td></tr>';
            } else {
                tbody.innerHTML = livros.map((l) => {
                    const api = l.livro_api || {};
                    const capa = api.capa_thumbnail || api.capa_url || '';
                    const exportado = l.exportado_em
                        ? '<span class="badge sim">Sim</span>'
                        : '<span class="badge nao">Não</span>';
                    return `
                        <tr data-id="${l.id}">
                            <td><input type="checkbox" class="check-linha" value="${l.id}"></td>
                            <td>${capa ? `<img class="capa-mini" src="${capa}" alt="capa">` : ''}</td>
                            <td>${escapeHtml(api.titulo || '—')}</td>
                            <td>${escapeHtml(fmtAutores(api.autores))}</td>
                            <td>${escapeHtml(api.editora || '—')}</td>
                            <td>${api.ano_publicacao || '—'}</td>
                            <td>${api.isbn_13 || '—'}</td>
                            <td>${exportado}</td>
                        </tr>
                    `;
                }).join('');
            }

            const pagina = Math.floor(offset / LIMITE) + 1;
            const totalPaginas = Math.max(1, Math.ceil(total / LIMITE));
            infoPagina.textContent = `Página ${pagina} de ${totalPaginas}`;
            btnAnterior.disabled = offset <= 0;
            btnProxima.disabled  = (offset + LIMITE) >= total;
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="8" class="vazio">Erro ao carregar.</td></tr>';
            mostrarToast('Erro ao carregar lista.', 'erro');
            console.error(e);
        }
    }

    // ============================================================
    //  Modal Exportar para HYB
    // ============================================================

    function renderizarLinhasModal() {
        const livros = window.livrosModal || [];
        if (livros.length === 0) {
            modalTbody.innerHTML = '<tr><td colspan="7" class="vazio">Nenhum livro disponível.</td></tr>';
            return;
        }
        modalTbody.innerHTML = livros.map((l) => {
            const api = l.livro_api || {};
            const capa = api.capa_thumbnail || api.capa_url || '';
            const naoBaixado = !l.exportado_em;
            const qtd = (l.quantidade && Number(l.quantidade) > 0) ? Number(l.quantidade) : 1;
            const baixaLabel = l.exportado_em ? fmtData(l.exportado_em) : '—';
            const checkedAttr = naoBaixado ? 'checked' : '';
            const tipoLinha = naoBaixado ? 'nao_baixados' : 'ja_baixados';
            return `
                <tr data-id="${l.id}"
                    data-tipo="${tipoLinha}"
                    data-busca="${escapeHtml(((api.titulo || '') + ' ' + fmtAutores(api.autores) + ' ' + (api.isbn_13 || '')).toLowerCase())}">
                    <td><input type="checkbox" class="modal-check-linha" value="${l.id}" ${checkedAttr}></td>
                    <td>${capa ? `<img class="capa-mini" src="${capa}" alt="capa">` : ''}</td>
                    <td>${escapeHtml(api.titulo || '—')}</td>
                    <td>${escapeHtml(fmtAutores(api.autores))}</td>
                    <td>${api.isbn_13 || '—'}</td>
                    <td>${baixaLabel}</td>
                    <td class="qtd"><input type="number" min="1" step="1" value="${qtd}" data-livro-id="${l.id}"></td>
                </tr>
            `;
        }).join('');
    }

    function aplicarFiltros() {
        const termo = (modalBusca.value || '').trim().toLowerCase();
        const tipo  = modalFiltroBaixa.value;
        const linhas = modalTbody.querySelectorAll('tr[data-id]');
        linhas.forEach((tr) => {
            const linhaTipo = tr.getAttribute('data-tipo');
            const buscaLinha = tr.getAttribute('data-busca') || '';
            const passaTipo  = (tipo === 'todos') || (tipo === linhaTipo);
            const passaBusca = (termo === '') || buscaLinha.includes(termo);
            tr.style.display = (passaTipo && passaBusca) ? '' : 'none';
        });
        atualizarContador();
    }

    function linhasVisiveis() {
        return Array.from(modalTbody.querySelectorAll('tr[data-id]'))
            .filter((tr) => tr.style.display !== 'none');
    }

    function atualizarContador() {
        const visiveis = linhasVisiveis();
        const total = visiveis.length;
        const selecionados = visiveis.filter((tr) => {
            const c = tr.querySelector('.modal-check-linha');
            return c && c.checked;
        }).length;
        modalContador.textContent = `${selecionados} selecionados de ${total}`;
        modalContadorBtn.textContent = String(selecionados);
        btnGerarXlsx.disabled = selecionados === 0;

        // estado do "selecionar todos" — reflete o subset visível
        if (total === 0) {
            modalCheckTodos.checked = false;
            modalCheckTodos.indeterminate = false;
        } else if (selecionados === total) {
            modalCheckTodos.checked = true;
            modalCheckTodos.indeterminate = false;
        } else if (selecionados === 0) {
            modalCheckTodos.checked = false;
            modalCheckTodos.indeterminate = false;
        } else {
            modalCheckTodos.checked = false;
            modalCheckTodos.indeterminate = true;
        }
    }

    async function abrirModalExport() {
        modalTbody.innerHTML = '<tr><td colspan="7" class="vazio">Carregando…</td></tr>';
        modalExport.removeAttribute('hidden');
        if (typeof modalExport.showModal === 'function') {
            if (!modalExport.open) modalExport.showModal();
        } else {
            // fallback (browsers sem <dialog>): mostra como overlay simples
            modalExport.style.display = 'flex';
        }

        try {
            const params = new URLSearchParams({ limite: 10000, offset: 0 });
            const res = await fetch('../api/livros.php?' + params.toString());
            const json = await res.json();
            window.livrosModal = json.livros || [];
            renderizarLinhasModal();
            aplicarFiltros();
        } catch (e) {
            modalTbody.innerHTML = '<tr><td colspan="7" class="vazio">Erro ao carregar livros.</td></tr>';
            mostrarToast('Erro ao carregar livros do modal.', 'erro');
            console.error(e);
        }
    }

    function fecharModalExport() {
        if (typeof modalExport.close === 'function' && modalExport.open) {
            modalExport.close();
        } else {
            modalExport.style.display = 'none';
        }
        modalExport.setAttribute('hidden', '');
    }

    async function gerarXlsxDoModal() {
        const linhas = linhasVisiveis().filter((tr) => {
            const c = tr.querySelector('.modal-check-linha');
            return c && c.checked;
        });
        if (linhas.length === 0) {
            mostrarToast('Selecione ao menos um livro.', 'erro');
            return;
        }

        const ids = linhas.map((tr) => Number(tr.getAttribute('data-id'))).filter((n) => n > 0);
        const quantidades = {};
        linhas.forEach((tr) => {
            const id = Number(tr.getAttribute('data-id'));
            const input = tr.querySelector('td.qtd input');
            const q = input ? Number(input.value) : 1;
            if (id > 0 && q > 0) quantidades[String(id)] = q;
        });

        btnGerarXlsx.disabled = true;
        const labelOriginal = btnGerarXlsx.innerHTML;
        btnGerarXlsx.innerHTML = 'Gerando…';

        try {
            const res = await fetch('../api/exportar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids, quantidades, origem: 'lista' }),
            });

            if (!res.ok) {
                let msg = 'Falha ao gerar XLSX.';
                try {
                    const erro = await res.json();
                    if (erro && erro.erro) msg = erro.erro;
                } catch (_) { /* ignore */ }
                throw new Error(msg);
            }

            const blob = await res.blob();
            // Extrai filename do Content-Disposition (se houver), senão default
            let nome = 'hyb_export.xlsx';
            const cd = res.headers.get('Content-Disposition') || '';
            const m = /filename="?([^";]+)"?/i.exec(cd);
            if (m && m[1]) nome = m[1];

            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = nome;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(() => URL.revokeObjectURL(url), 1000);

            mostrarToast(`XLSX gerado: ${ids.length} livro(s) baixado(s).`, 'ok');

            // Recarrega lista principal e modal para refletir a nova "Última baixa"
            await carregar();
            await abrirModalExport();
        } catch (e) {
            mostrarToast('Erro: ' + e.message, 'erro');
            console.error(e);
            btnGerarXlsx.disabled = false;
            btnGerarXlsx.innerHTML = labelOriginal;
        }
    }

    function idsSelecionados() {
        return Array.from(document.querySelectorAll('.check-linha:checked'))
            .map((c) => c.value);
    }

    // ============================================================
    //  Eventos
    // ============================================================
    btnFiltrar.addEventListener('click', () => {
        filtros = {
            busca:   $('#campo-busca').value.trim(),
            editora: $('#campo-editora').value.trim(),
            ano:     $('#campo-ano').value.trim(),
        };
        offset = 0;
        carregar();
    });
    btnLimpar.addEventListener('click', () => {
        $('#campo-busca').value = '';
        $('#campo-editora').value = '';
        $('#campo-ano').value = '';
        filtros = {};
        offset = 0;
        carregar();
    });
    btnAnterior.addEventListener('click', () => {
        if (offset >= LIMITE) { offset -= LIMITE; carregar(); }
    });
    btnProxima.addEventListener('click', () => {
        if (offset + LIMITE < total) { offset += LIMITE; carregar(); }
    });
    checkTodos.addEventListener('change', () => {
        document.querySelectorAll('.check-linha').forEach((c) => c.checked = checkTodos.checked);
    });

    // Enter nos campos de filtro dispara busca
    ['campo-busca','campo-editora','campo-ano'].forEach((id) => {
        $('#' + id).addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') { ev.preventDefault(); btnFiltrar.click(); }
        });
    });

    // --- Modal: bindings ---
    if (btnAbrirModal) {
        btnAbrirModal.addEventListener('click', abrirModalExport);
    }
    if (btnFecharModal) {
        btnFecharModal.addEventListener('click', fecharModalExport);
    }
    if (modalExport) {
        // ESC nativo do <dialog> dispara 'close' — sincroniza atributo hidden
        modalExport.addEventListener('close', () => {
            modalExport.setAttribute('hidden', '');
        });
    }
    if (modalBusca) {
        modalBusca.addEventListener('input', aplicarFiltros);
    }
    if (modalFiltroBaixa) {
        modalFiltroBaixa.addEventListener('change', aplicarFiltros);
    }
    if (modalCheckTodos) {
        modalCheckTodos.addEventListener('change', () => {
            const marcar = modalCheckTodos.checked;
            linhasVisiveis().forEach((tr) => {
                const c = tr.querySelector('.modal-check-linha');
                if (c) c.checked = marcar;
            });
            atualizarContador();
        });
    }
    if (modalTbody) {
        // delegação: checkboxes individuais
        modalTbody.addEventListener('change', (ev) => {
            const t = ev.target;
            if (t && t.classList && t.classList.contains('modal-check-linha')) {
                atualizarContador();
            }
        });
    }
    if (btnGerarXlsx) {
        btnGerarXlsx.addEventListener('click', gerarXlsxDoModal);
    }

    // Expor helpers para depuração (não persistente em produção)
    window.abrirModalExport = abrirModalExport;
    window.idsSelecionados  = idsSelecionados;

    carregar();
})();
