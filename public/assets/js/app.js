/* global document, fetch */
(function () {
    'use strict';

    const $ = (sel) => document.querySelector(sel);
    const campoIsbn  = $('#campo-isbn');
    const status     = $('#status-consulta');
    const painelLivro = $('#painel-livro');
    const painelHyb   = $('#painel-hyb');
    const painelErro  = $('#painel-erro');
    const formHyb     = $('#form-hyb');
    const toast       = $('#toast');
    const beepOk      = $('#beep-ok');

    let ultimaConsulta = null; // { livro, hyb, hyb_defaults, livro_id }

    function focarInput() {
        campoIsbn.value = '';
        campoIsbn.focus();
    }

    function mostrarToast(mensagem, tipo) {
        toast.textContent = mensagem;
        toast.className = 'toast ' + (tipo || 'info');
        toast.classList.remove('hidden');
        clearTimeout(window._toastTimer);
        window._toastTimer = setTimeout(() => toast.classList.add('hidden'), 3000);
    }

    function setStatus(msg, classe) {
        status.textContent = msg;
        status.className = 'status' + (classe ? ' ' + classe : '');
    }

    function fmt(valor) {
        if (valor === null || valor === undefined || valor === '') return '—';
        if (Array.isArray(valor)) return valor.length ? valor.join(' · ') : '—';
        return String(valor);
    }

    function fmtPreco(p) {
        if (!p || p.valor === null || p.valor === undefined || p.valor === '') return '—';
        const simbolo = p.moeda === 'BRL' ? 'R$' : (p.moeda || '');
        return (simbolo + ' ' + Number(p.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })).trim();
    }

    function fmtDimensoes(d) {
        if (!d) return '—';
        const partes = [];
        if (d.altura_cm != null)    partes.push(Number(d.altura_cm).toLocaleString('pt-BR', { minimumFractionDigits: 1 }));
        if (d.largura_cm != null)   partes.push(Number(d.largura_cm).toLocaleString('pt-BR', { minimumFractionDigits: 1 }));
        if (d.espessura_cm != null) partes.push(Number(d.espessura_cm).toLocaleString('pt-BR', { minimumFractionDigits: 1 }));
        if (partes.length === 0) return '—';
        return partes.join(' × ') + ' cm';
    }

    function renderizarLivro(livro, origem, providerOrigem, tempoMs, cadastrado) {
        painelErro.classList.add('hidden');
        painelLivro.classList.remove('hidden');
        painelHyb.classList.remove('hidden');

        const capa = livro.capa_url || livro.capa_thumbnail || '';
        const img = $('#img-capa');
        if (capa) {
            img.src = capa;
            img.style.display = 'block';
        } else {
            img.removeAttribute('src');
            img.style.display = 'none';
        }

        $('#livro-titulo').textContent    = livro.titulo || '—';
        $('#livro-subtitulo').textContent = livro.subtitulo || '';
        $('#livro-autores').textContent   = fmt(livro.autores);
        $('#livro-editora').textContent   = fmt(livro.editora);
        $('#livro-ano').textContent       = fmt(livro.ano_publicacao);
        $('#livro-isbn13').textContent    = fmt(livro.isbn_13);
        $('#livro-isbn10').textContent    = fmt(livro.isbn_10);
        $('#livro-idioma').textContent    = fmt(livro.idioma);
        $('#livro-formato').textContent   = fmt(livro.formato);
        $('#livro-paginas').textContent   = fmt(livro.paginas);
        $('#livro-dimensoes').textContent = fmtDimensoes(livro.dimensoes);
        $('#livro-preco').textContent     = fmtPreco(livro.preco);
        $('#livro-local').textContent     = fmt(livro.local_publicacao);

        $('#livro-assuntos').textContent   = fmt(livro.assuntos);
        $('#livro-categorias').textContent = fmt(livro.categorias);
        $('#livro-sinopse').textContent    = livro.sinopse || '—';

        const horaAgora = new Date().toLocaleTimeString('pt-BR');
        $('#livro-fonte').textContent =
            `Fonte: ${origem || '—'}` +
            (providerOrigem ? ` · Provedor: ${providerOrigem}` : '') +
            ` · Consultado: ${horaAgora}` +
            (tempoMs ? ` · ${tempoMs} ms` : '') +
            (cadastrado ? ' · Já cadastrado' : '');
    }

    function preencherHyb(valores) {
        if (!valores) return;
        Object.keys(valores).forEach((campo) => {
            const input = formHyb.elements.namedItem(campo);
            if (!input) return;
            const v = valores[campo];
            input.value = (v === null || v === undefined) ? '' : String(v);
        });
    }

    function coletarHyb() {
        const dados = {};
        const fields = ['bem_produto','unidade','categoria','ncm','preco_venda',
            'estoque_minimo','referencia','patrimonio','depreciacao_pct',
            'tipo','estoque_ini_qtd','estoque_ini_custo','descricao'];
        fields.forEach((c) => {
            const el = formHyb.elements.namedItem(c);
            dados[c] = el ? el.value.trim() : '';
        });
        return dados;
    }

    async function consultarIsbn(isbn) {
        setStatus('Consultando "' + isbn + '"…', 'loading');
        painelErro.classList.add('hidden');

        try {
            const res = await fetch('../api/consultar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ isbn })
            });
            const json = await res.json();

            if (!json.sucesso) {
                exibirErro(json.erro || 'ISBN não encontrado', json.isbn || isbn);
                setStatus('Falha na consulta', 'erro');
                return;
            }

            ultimaConsulta = json;
            renderizarLivro(json.livro, json.origem, json.provider_origem, json.tempo_ms, !!json.cadastrado);

            // Pré-preenche os campos HYB:
            //  - Se já existe registro no banco → usa o hyb salvo
            //  - Senão → usa os defaults sugeridos pelo backend
            if (json.cadastrado && json.hyb) {
                preencherHyb(json.hyb);
            } else if (json.hyb_defaults) {
                preencherHyb(json.hyb_defaults);
            }

            setStatus(`Consulta concluída via ${json.origem} em ${json.tempo_ms || 0} ms`, 'ok');
            try { beepOk.currentTime = 0; beepOk.play().catch(() => {}); } catch (e) {}
        } catch (err) {
            exibirErro('Sem conexão com o servidor.', isbn);
            setStatus('Erro de rede', 'erro');
            console.error(err);
        } finally {
            focarInput();
        }
    }

    function exibirErro(mensagem, isbn) {
        painelLivro.classList.add('hidden');
        painelHyb.classList.add('hidden');
        painelErro.classList.remove('hidden');
        $('#erro-titulo').textContent = 'Não encontrado';
        $('#erro-detalhe').textContent = `${mensagem}${isbn ? ' (ISBN ' + isbn + ')' : ''}`;
    }

    async function salvarLivro() {
        if (!ultimaConsulta) {
            mostrarToast('Nenhum livro consultado.', 'erro');
            return;
        }
        try {
            const res = await fetch('../api/livros.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    livro_api: ultimaConsulta.livro,
                    hyb: coletarHyb()
                })
            });
            const json = await res.json();
            if (json.sucesso) {
                mostrarToast(json.criado ? 'Livro cadastrado!' : 'Livro atualizado!', 'ok');
                ultimaConsulta.cadastrado = true;
                ultimaConsulta.livro_id = json.id;
            } else {
                mostrarToast('Falha ao salvar: ' + (json.erro || 'erro desconhecido'), 'erro');
            }
        } catch (err) {
            mostrarToast('Erro de rede ao salvar.', 'erro');
            console.error(err);
        }
    }

    async function copiarJson() {
        if (!ultimaConsulta) {
            mostrarToast('Nenhum livro consultado.', 'erro');
            return;
        }
        const payload = {
            livro_api: ultimaConsulta.livro,
            hyb: coletarHyb()
        };
        try {
            await navigator.clipboard.writeText(JSON.stringify(payload, null, 2));
            mostrarToast('JSON copiado para a área de transferência.', 'ok');
        } catch (err) {
            mostrarToast('Falha ao copiar.', 'erro');
        }
    }

    function novaConsulta() {
        ultimaConsulta = null;
        painelLivro.classList.add('hidden');
        painelHyb.classList.add('hidden');
        painelErro.classList.add('hidden');
        setStatus('Aguardando leitura...');
        focarInput();
    }

    // ----- Listeners ---------------------------------------------------

    campoIsbn.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            const isbn = campoIsbn.value.replace(/[^\dXx]/g, '');
            if (isbn.length >= 10) {
                consultarIsbn(isbn);
            } else {
                setStatus('ISBN muito curto (precisa de 10 ou 13 dígitos).', 'erro');
            }
        }
    });

    $('#btn-salvar').addEventListener('click', salvarLivro);
    $('#btn-copiar').addEventListener('click', copiarJson);
    $('#btn-novo').addEventListener('click', novaConsulta);

    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape') {
            ev.preventDefault();
            novaConsulta();
        } else if (ev.key === 'F2') {
            ev.preventDefault();
            copiarJson();
        } else if (ev.key === 'F8') {
            ev.preventDefault();
            salvarLivro();
        }
    });

    // Devolve foco para o campo quando o usuário clica fora dos inputs
    document.addEventListener('click', (ev) => {
        const tag = (ev.target.tagName || '').toLowerCase();
        if (!['input','textarea','select','button','a'].includes(tag)) {
            focarInput();
        }
    });

    focarInput();
})();
