/* global document, fetch */
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
    const btnExpSel   = $('#btn-exportar-sel');
    const checkTodos  = $('#check-todos');
    const toast       = $('#toast');

    const LIMITE = 50;
    let offset = 0;
    let total  = 0;
    let filtros = {};

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

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function idsSelecionados() {
        return Array.from(document.querySelectorAll('.check-linha:checked'))
            .map((c) => c.value);
    }

    // Eventos
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
    btnExpSel.addEventListener('click', () => {
        const ids = idsSelecionados();
        if (ids.length === 0) {
            mostrarToast('Selecione ao menos um livro.', 'erro');
            return;
        }
        window.location.href = '../api/exportar.php?ids=' + encodeURIComponent(ids.join(','));
    });

    // Enter nos campos de filtro dispara busca
    ['campo-busca','campo-editora','campo-ano'].forEach((id) => {
        $('#' + id).addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') { ev.preventDefault(); btnFiltrar.click(); }
        });
    });

    carregar();
})();
