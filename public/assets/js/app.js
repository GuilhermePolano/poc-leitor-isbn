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
    let categoriasCarregadas = false;

    // Estado do leitor de câmera (Ajustes 3 + 4).
    // Estratégia:
    //   1. BarcodeDetector nativo se disponível (Edge Windows / Chrome macOS+Android) — mais rápido
    //   2. Quagga2 como fallback (Chrome Windows/Linux, Firefox) — feito para webcam
    let cameraAtiva = false;
    let cameraStream = null;
    let cameraRafId = null;
    let quaggaIniciado = false;
    let cameraDicasTimer = null;
    let cameraDicasTimer2 = null;
    let timerRelaxar = null;
    // M5: ERRO_MAX começa rígido (0.15) e, se 15s sem aceitar, relaxa para 0.22.
    let erroMaxAtual = 0.15;
    // M2: rate-limit do log do Quagga.
    let contadorLog = 0;
    let ultimoErroLogado = -1;
    let primeiraRejeicaoLogada = false;
    const QUAGGA_CDN_URL = 'https://cdn.jsdelivr.net/npm/@ericblade/quagga2@1.8.4/dist/quagga.min.js';
    let quaggaPromise = null;

    const HYB_DEFAULTS = window.HYB_DEFAULTS || {};

    async function carregarCategorias() {
        const select = $('#campo-categoria');
        if (!select) return;
        try {
            const res = await fetch('../api/categorias.php', { method: 'GET' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const lista = await res.json();
            if (!Array.isArray(lista)) throw new Error('Resposta inesperada de /api/categorias.php');

            // Decisão #4: dropdown linear ordenado por indice.
            // O backend já retorna ordenado, mas garantimos aqui também.
            lista.sort((a, b) => String(a.indice || '').localeCompare(String(b.indice || ''), 'pt-BR', { numeric: true }));

            // Limpa qualquer opção residual (preserva o placeholder).
            const placeholder = select.querySelector('option[value=""]');
            select.innerHTML = '';
            if (placeholder) select.appendChild(placeholder);

            lista.forEach((c) => {
                const opt = document.createElement('option');
                opt.value = String(c.id);
                opt.textContent = `${c.indice || ''} — ${c.descricao || ''}`.trim();
                select.appendChild(opt);
            });

            categoriasCarregadas = true;

            // Pré-seleção: se nenhum livro foi consultado ainda, aplica o default.
            if (!ultimaConsulta && HYB_DEFAULTS.categoria_id) {
                const id = String(HYB_DEFAULTS.categoria_id);
                if (select.querySelector(`option[value="${id}"]`)) {
                    select.value = id;
                }
            }
        } catch (err) {
            console.error('Falha ao carregar categorias:', err);
            mostrarToast('Não foi possível carregar a lista de categorias.', 'erro');
        }
    }

    function focarInput() {
        if (cameraAtiva) return; // não rouba foco enquanto a câmera está aberta
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

        // Trata categoria_id de forma especial: mapeia para o <select>.
        const selectCategoria = $('#campo-categoria');
        if (selectCategoria && Object.prototype.hasOwnProperty.call(valores, 'categoria_id')) {
            const cid = valores.categoria_id;
            const cidStr = (cid === null || cid === undefined || cid === '') ? '' : String(cid);
            if (cidStr === '') {
                selectCategoria.value = '';
            } else if (selectCategoria.querySelector(`option[value="${cidStr}"]`)) {
                selectCategoria.value = cidStr;
            } else if (valores.categoria) {
                // Legado: id não consta no dropdown — cria option dinâmica preservando o texto antigo.
                const opt = document.createElement('option');
                opt.value = cidStr;
                opt.textContent = String(valores.categoria) + ' (legado)';
                selectCategoria.appendChild(opt);
                selectCategoria.value = cidStr;
            }
        }

        // Trata quantidade (#5): reaproveita o input editável; default 1.
        const inputQtd = $('#campo-quantidade');
        if (inputQtd && Object.prototype.hasOwnProperty.call(valores, 'quantidade')) {
            const q = parseInt(valores.quantidade, 10);
            inputQtd.value = (Number.isFinite(q) && q >= 1) ? String(q) : '1';
        }

        Object.keys(valores).forEach((campo) => {
            if (campo === 'categoria_id' || campo === 'quantidade') return;
            const input = formHyb.elements.namedItem(campo);
            if (!input) return;
            const v = valores[campo];
            input.value = (v === null || v === undefined) ? '' : String(v);
        });
    }

    function coletarHyb() {
        const dados = {};
        const fields = ['bem_produto','unidade','ncm','preco_venda',
            'estoque_minimo','referencia','patrimonio','depreciacao_pct',
            'tipo','estoque_ini_custo','descricao'];
        fields.forEach((c) => {
            const el = formHyb.elements.namedItem(c);
            dados[c] = el ? el.value.trim() : '';
        });

        // Categoria: envia categoria_id (int) quando selecionado;
        // só envia 'categoria' (string) como fallback legado se não houver id.
        const selectCategoria = $('#campo-categoria');
        const catVal = selectCategoria ? selectCategoria.value.trim() : '';
        if (catVal !== '') {
            dados.categoria_id = parseInt(catVal, 10);
        } else {
            const legado = (formHyb.elements.namedItem('categoria') || {}).value;
            if (legado && String(legado).trim() !== '') {
                dados.categoria = String(legado).trim();
            }
        }

        // Quantidade (#5): int, default 1.
        const inputQtd = $('#campo-quantidade');
        const qRaw = inputQtd ? parseInt(inputQtd.value, 10) : NaN;
        dados.quantidade = (Number.isFinite(qRaw) && qRaw >= 1) ? qRaw : 1;

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

    /**
     * Núcleo da persistência do livro.
     * Retorna { ok: true, id, criado } em sucesso ou { ok: false, erro } em falha.
     * Não exibe toast em caso de sucesso silencioso quando silencioso=true
     * (usado pelo atalho "Salvar e baixar XLSX" para evitar dois toasts).
     */
    async function salvarLivroCore(silencioso) {
        if (!ultimaConsulta) {
            return { ok: false, erro: 'Nenhum livro consultado.' };
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
                ultimaConsulta.cadastrado = true;
                ultimaConsulta.livro_id = json.id;
                if (!silencioso) {
                    mostrarToast(json.criado ? 'Livro cadastrado!' : 'Livro atualizado!', 'ok');
                }
                return { ok: true, id: json.id, criado: !!json.criado };
            }
            return { ok: false, erro: json.erro || 'erro desconhecido' };
        } catch (err) {
            console.error(err);
            return { ok: false, erro: 'Erro de rede ao salvar.' };
        }
    }

    async function salvarLivro() {
        const r = await salvarLivroCore(false);
        if (!r.ok) {
            mostrarToast('Falha ao salvar: ' + r.erro, 'erro');
        }
    }

    /**
     * Atalho pós-cadastro (decisão #2):
     * 1) Salva o livro (reaproveita salvarLivroCore).
     * 2) Em caso de sucesso, dispara POST /api/exportar.php para gerar e baixar
     *    o XLSX deste único livro (origem='atalho_bipagem', decisão #13).
     * 3) Limpa o formulário e devolve o foco ao campo de bipagem.
     *
     * Se o save falhar, NÃO tenta exportar.
     * Se o save passar mas o export falhar, o livro continua salvo e exibimos
     * mensagem clara ao operador (o XLSX poderá ser baixado depois pela tela /lista).
     */
    async function salvarEBaixarXlsx() {
        if (!ultimaConsulta) {
            mostrarToast('Nenhum livro consultado.', 'erro');
            return;
        }

        // (a)+(b)+(c) Salva e aguarda o livro_id.
        const r = await salvarLivroCore(true);
        if (!r.ok) {
            mostrarToast('Falha ao salvar: ' + r.erro + ' (XLSX não gerado).', 'erro');
            return;
        }
        const livroId = r.id;

        // (d) Solicita o XLSX só deste livro.
        let respExport;
        try {
            respExport = await fetch('../api/exportar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: [livroId], origem: 'atalho_bipagem' })
            });
        } catch (err) {
            console.error(err);
            mostrarToast('Livro salvo, mas erro de rede ao gerar XLSX. Use a tela /lista para baixar depois.', 'erro');
            return;
        }

        if (!respExport.ok) {
            // Tenta extrair mensagem do corpo (JSON ou texto).
            let detalhe = 'HTTP ' + respExport.status;
            try {
                const ctype = respExport.headers.get('Content-Type') || '';
                if (ctype.includes('application/json')) {
                    const errJson = await respExport.json();
                    detalhe = errJson.erro || detalhe;
                } else {
                    const txt = await respExport.text();
                    if (txt) detalhe = txt.slice(0, 200);
                }
            } catch (_) { /* ignora */ }
            mostrarToast('Livro salvo, mas falha ao gerar XLSX: ' + detalhe, 'erro');
            return;
        }

        // (e) Dispara download via blob + anchor.
        try {
            const blob = await respExport.blob();
            const cd = respExport.headers.get('Content-Disposition') || '';
            let filename = 'exportacao.xlsx';
            const m = cd.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
            if (m && m[1]) {
                try { filename = decodeURIComponent(m[1]); } catch (_) { filename = m[1]; }
            }
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (err) {
            console.error(err);
            mostrarToast('Livro salvo, mas falha ao iniciar o download do XLSX.', 'erro');
            return;
        }

        // (f) Feedback + reset (mesmo fluxo do botão Salvar/Nova consulta).
        mostrarToast(r.criado ? 'Livro cadastrado e XLSX baixado!' : 'Livro atualizado e XLSX baixado!', 'ok');
        novaConsulta();
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
            if (cameraAtiva) return; // câmera tem precedência sobre o input
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
    const btnSalvarBaixar = $('#btn-salvar-e-baixar');
    if (btnSalvarBaixar) btnSalvarBaixar.addEventListener('click', salvarEBaixarXlsx);
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
        } else if (ev.key === 'F9') {
            ev.preventDefault();
            salvarEBaixarXlsx();
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

    // Carrega categorias e aplica defaults básicos quando a página estiver pronta.
    function aplicarDefaultsIniciais() {
        // Quantidade default já vem por HTML (value="1"); reforça via JS se vier diferente do .env.
        const inputQtd = $('#campo-quantidade');
        if (inputQtd && HYB_DEFAULTS.quantidade) {
            const q = parseInt(HYB_DEFAULTS.quantidade, 10);
            if (Number.isFinite(q) && q >= 1) inputQtd.value = String(q);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            aplicarDefaultsIniciais();
            carregarCategorias();
            inicializarCamera();
        });
    } else {
        aplicarDefaultsIniciais();
        carregarCategorias();
        inicializarCamera();
    }

    // ============================================================
    // Leitor de código de barras via câmera
    // Ajustes 3 + 4. Decisões: #6 (decoder cross-browser), #7 (não bloquear
    // EAN sem prefixo 978/979 — apenas valida dígito verificador).
    //
    // Estratégia:
    //   1. BarcodeDetector nativo se disponível (Edge Windows, Chrome macOS/Android)
    //   2. Fallback Quagga2 (lib JS pura) para Chrome Windows/Linux e Firefox
    //   Quagga é carregado lazy via CDN só quando precisa.
    // ============================================================

    /**
     * Valida o dígito verificador de um EAN-13/ISBN-13 (mod 10, pesos 1,3,1,3,...).
     * Decisão #7: NÃO checa prefixo 978/979 aqui — qualquer EAN-13 válido segue
     * para a consulta (que pode falhar adiante e o operador decide).
     */
    function validarIsbn13(s) {
        if (typeof s !== 'string') return false;
        if (!/^\d{13}$/.test(s)) return false;
        let soma = 0;
        for (let i = 0; i < 12; i++) {
            const d = s.charCodeAt(i) - 48;
            soma += (i % 2 === 0) ? d : d * 3;
        }
        const dv = (10 - (soma % 10)) % 10;
        return dv === (s.charCodeAt(12) - 48);
    }

    /**
     * Carrega Quagga2 dinamicamente (uma única vez por sessão).
     * Retorna Promise que resolve com window.Quagga pronto para uso.
     * Quagga é feito para webcams reais (foco fixo, baixa res) e funciona muito
     * melhor em webcams nesses cenários (foco fixo, baixa res, JPEG artifacts).
     */
    function carregarQuagga() {
        if (window.Quagga) return Promise.resolve(window.Quagga);
        if (quaggaPromise) return quaggaPromise;
        quaggaPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = QUAGGA_CDN_URL;
            script.async = true;
            script.onload = () => {
                if (window.Quagga) resolve(window.Quagga);
                else reject(new Error('Quagga carregou mas window.Quagga está undefined'));
            };
            script.onerror = () => reject(new Error('Falha ao carregar Quagga2 do CDN'));
            document.head.appendChild(script);
        });
        return quaggaPromise;
    }

    /**
     * Processa um EAN detectado (por qualquer decoder).
     * Centraliza validação + side effects (beep, fechar câmera, consultar).
     */
    function processarEanDetectado(ean) {
        if (!validarIsbn13(ean)) return false; // EAN-8 ou checksum inválido — segue varrendo
        try { beepOk.currentTime = 0; beepOk.play().catch(() => {}); } catch (_) {}
        fecharCamera();
        const input = document.getElementById('campo-isbn');
        if (input) input.value = ean;
        consultarIsbn(ean);
        return true;
    }

    async function iniciarCameraEDecodificar() {
        if (cameraAtiva) return; // já aberta

        const overlay = document.getElementById('overlay-camera');
        overlay.hidden = false;
        cameraAtiva = true;

        // B2: dicas em 2 estágios.
        //   - 4s: atualiza a .camera-msg pra reforçar o posicionamento (não mostra ainda o bloco #camera-dicas)
        //   - 12s: mostra #camera-dicas com texto que aponta o botão "Digitar ISBN manualmente"
        const dicas = document.getElementById('camera-dicas');
        if (dicas) dicas.hidden = true;
        cameraDicasTimer = setTimeout(() => {
            const m = document.querySelector('#overlay-camera .camera-msg');
            if (cameraAtiva && m) {
                m.textContent = 'Mantenha o código estável na linha vermelha';
            }
        }, 4000);
        cameraDicasTimer2 = setTimeout(() => {
            if (cameraAtiva && dicas) {
                dicas.innerHTML = "Está difícil de ler. Tente ajustar distância OU clique em <strong>'Digitar ISBN manualmente'</strong> abaixo.";
                dicas.hidden = false;
            }
        }, 12000);

        // Escolhe o decoder: BarcodeDetector nativo (Edge Win / Chrome mac) é mais rápido;
        // Quagga2 é fallback robusto para Chrome Windows/Linux e Firefox.
        if ('BarcodeDetector' in window) {
            console.log('[camera] Usando BarcodeDetector nativo');
            await iniciarComStreamProprio();
        } else {
            console.log('[camera] Usando Quagga2 (fallback)');
            await iniciarComQuagga();
        }
    }

    /**
     * Caminho nativo: abrimos o stream nós mesmos e passamos pro BarcodeDetector.
     */
    async function iniciarComStreamProprio() {
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment',
                    width:  { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            });
        } catch (e) {
            console.warn('getUserMedia falhou:', e);
            mostrarToast('Permissão de câmera negada', 'erro');
            fecharCamera();
            return;
        }
        const video = document.getElementById('video-camera');
        video.srcObject = cameraStream;
        await new Promise((resolve) => {
            if (video.readyState >= 2 && video.videoWidth > 0) return resolve();
            const onReady = () => { video.removeEventListener('loadedmetadata', onReady); resolve(); };
            video.addEventListener('loadedmetadata', onReady);
        });
        console.log('[camera] Stream ativo:', video.videoWidth + 'x' + video.videoHeight);
        iniciarLoopNativo(video);
    }

    /**
     * Caminho Quagga2: a lib gerencia stream + decodificação internamente.
     * É otimizada pra webcam de notebook (foco fixo, baixa res, jpeg artifacts).
     */
    async function iniciarComQuagga() {
        const msg = document.querySelector('#overlay-camera .camera-msg');
        if (msg) msg.textContent = 'Carregando leitor de código de barras…';

        let Quagga;
        try {
            Quagga = await carregarQuagga();
        } catch (e) {
            console.warn('Falha ao carregar Quagga:', e);
            mostrarToast('Não foi possível carregar o leitor. Verifique sua conexão.', 'erro');
            fecharCamera();
            return;
        }
        if (!cameraAtiva) return; // usuário cancelou enquanto carregava

        if (msg) {
            msg.textContent = 'Procurando código de barras…';
            msg.classList.add('camera-msg-procurando');
        }

        // O viewport precisa estar vazio antes do init — Quagga injeta video+canvas dentro.
        // Remove o <video> placeholder usado pelo caminho nativo.
        const viewport = document.getElementById('container-camera-viewport');
        const videoPlaceholder = document.getElementById('video-camera');
        if (videoPlaceholder && videoPlaceholder.parentNode === viewport) {
            videoPlaceholder.remove();
        }

        Quagga.init({
            inputStream: {
                name: 'Live',
                type: 'LiveStream',
                target: viewport,
                constraints: {
                    width:  { ideal: 1280 },
                    height: { ideal: 720 },
                    facingMode: 'environment'
                },
                area: { // ROI: faixa central da imagem (mais barata de processar)
                    top:    '30%',
                    right:  '5%',
                    bottom: '30%',
                    left:   '5%'
                }
            },
            locator: {
                patchSize: 'medium',
                halfSample: true
            },
            // M4: 2 workers e 15fps — reduz CPU sem prejudicar a janela de votação.
            numOfWorkers: Math.min(2, navigator.hardwareConcurrency || 2),
            frequency: 15,
            decoder: {
                // SOMENTE EAN-13: livros usam EAN-13 (978/979 para ISBN, 977
                // para periódicos, demais prefixos comerciais permitidos pela
                // decisão #7). Code 128, ITF, EAN-8, UPC etc. introduzem
                // falsos positivos ao decodificar FRAGMENTOS do EAN-13.
                readers: ['ean_reader'],
                multiple: false
            },
            locate: true
        }, (err) => {
            // M1: usuário pode ter cancelado durante o init async — abortar.
            if (!cameraAtiva) {
                try { Quagga.stop(); } catch (_) {}
                return;
            }
            if (err) {
                console.error('[camera] Quagga init falhou:', err);
                const nome = err && err.name ? err.name : '';
                if (nome === 'NotAllowedError' || nome === 'PermissionDeniedError') {
                    mostrarToast('Permissão de câmera negada', 'erro');
                } else {
                    mostrarToast('Erro ao iniciar a câmera: ' + (err.message || err.name || err), 'erro');
                }
                fecharCamera();
                return;
            }
            console.log('[camera] Quagga inicializou, começando o stream…');
            Quagga.start();
            quaggaIniciado = true;
            // Captura o stream para podermos parar depois
            const liveVideo = viewport.querySelector('video');
            if (liveVideo) {
                cameraStream = liveVideo.srcObject;
                if (liveVideo.videoWidth) {
                    console.log('[camera] Stream Quagga:', liveVideo.videoWidth + 'x' + liveVideo.videoHeight);
                }
            }
            // M5: se em 15s não aceitamos nada, relaxar o teto de erro de 0.15 → 0.22.
            //     Webcams com foco fixo raramente entregam erro < 0.15.
            timerRelaxar = setTimeout(() => {
                if (cameraAtiva && erroMaxAtual === 0.15) {
                    erroMaxAtual = 0.22;
                    console.log('[camera] Relaxando ERRO_MAX para', erroMaxAtual, '(15s sem aceitação)');
                    mostrarToast('Aproxime ou afaste o livro até a imagem ficar nítida');
                }
            }, 15000);
        });

        Quagga.onDetected(onQuaggaDetectado);
    }

    /**
     * Erro médio das amostras de dígitos do EAN. Quagga2 retorna
     * data.codeResult.decodedCodes — array {error, code} por dígito/segmento.
     * - < 0.10 => leitura limpa
     * - > 0.20 => provavelmente errado
     * Usamos 0.15 como teto (rejeitar amostras acima).
     */
    function calcularErroMedio(decodedCodes) {
        const errs = (decodedCodes || []).filter((c) => typeof c.error === 'number').map((c) => c.error);
        if (errs.length === 0) return 1.0;
        return errs.reduce((s, e) => s + e, 0) / errs.length;
    }

    // Janela deslizante de 10 amostras de QUALIDADE (já filtradas por erro
    // médio <= 0.15). Aceita o EAN só quando 5 dessas 10 amostras concordam
    // — majority voting forte para webcam ruidosa.
    const JANELA_TAM = 10;
    const VOTOS_MIN = 5;
    const ERRO_MAX = 0.15;
    const amostrasJanela = [];
    // C2: janela do caminho nativo (BarcodeDetector é mais confiável → margem menor).
    const JANELA_NATIVO = 5;
    const VOTOS_NATIVO = 3;
    const amostrasJanelaNativo = [];

    /**
     * Callback Quagga: chamado a cada decodificação bem-sucedida.
     * Estratégia anti-falso-positivo:
     *   1. Rejeita amostra com erroMedio > 0.15 (leitura suja).
     *   2. Adiciona à janela deslizante de 10 amostras de qualidade.
     *   3. Aceita só quando algum EAN aparece >= 5 vezes nas últimas 10.
     *   4. validarIsbn13() em processarEanDetectado() é o filtro final (checksum).
     */
    function onQuaggaDetectado(data) {
        if (!cameraAtiva) return;
        if (!data || !data.codeResult || !data.codeResult.code) return;
        const ean = String(data.codeResult.code);
        const erroMedio = calcularErroMedio(data.codeResult.decodedCodes);

        // (1) filtro de confiança — M5: usa erroMaxAtual, que pode relaxar em 15s.
        if (erroMedio > erroMaxAtual) {
            // M2: log de rejeição só na primeira ou se o erro mudou notavelmente.
            if (!primeiraRejeicaoLogada || Math.abs(erroMedio - ultimoErroLogado) > 0.05) {
                console.log('[camera] Quagga REJEITADO erro=', erroMedio.toFixed(3),
                            '(teto=', erroMaxAtual.toFixed(2), ')');
                primeiraRejeicaoLogada = true;
                ultimoErroLogado = erroMedio;
            }
            return;
        }

        // (2) janela deslizante de amostras de qualidade
        amostrasJanela.push(ean);
        if (amostrasJanela.length > JANELA_TAM) amostrasJanela.shift();

        // M2: rate-limit do log (1 a cada 10 amostras) em vez de a cada frame.
        contadorLog++;
        if (contadorLog % 10 === 0) {
            console.log('[camera] Quagga amostras=', amostrasJanela.length,
                        'último=', ean, 'erro=', erroMedio.toFixed(3));
        }

        // (3) majority voting: precisa de >= VOTOS_MIN ocorrências do MESMO ean
        const contagem = {};
        for (const c of amostrasJanela) contagem[c] = (contagem[c] || 0) + 1;
        let vencedor = null;
        let maxVotos = 0;
        for (const c in contagem) {
            if (contagem[c] > maxVotos) { maxVotos = contagem[c]; vencedor = c; }
        }

        // M3: feedback visual dinâmico — "Lendo… (X/5 confirmações)".
        const msgEl = document.querySelector('#overlay-camera .camera-msg');
        if (msgEl && cameraAtiva && maxVotos > 0) {
            msgEl.textContent = 'Lendo… (' + maxVotos + '/' + VOTOS_MIN + ' confirmações)';
        }

        if (vencedor && maxVotos >= VOTOS_MIN) {
            console.log('[camera] Quagga ACEITO', vencedor,
                        'com', maxVotos, '/', amostrasJanela.length, 'votos');
            // B1: só limpa a janela se foi realmente aceito (checksum OK).
            const aceito = processarEanDetectado(vencedor);
            if (aceito) amostrasJanela.length = 0;
        }
    }

    /**
     * Caminho nativo (Edge Windows, Chrome macOS/Android).
     * Polling via requestAnimationFrame chamando BarcodeDetector.detect().
     */
    function iniciarLoopNativo(video) {
        const msg = document.querySelector('#overlay-camera .camera-msg');
        if (msg) {
            msg.textContent = 'Procurando código de barras…';
            msg.classList.add('camera-msg-procurando');
        }

        let detector;
        try {
            // C1: SOMENTE EAN-13 — livros usam EAN-13. Outros formatos (Code 128,
            // ITF, EAN-8, UPC) leem fragmentos do EAN-13 e geram falsos positivos.
            detector = new BarcodeDetector({ formats: ['ean_13'] });
        } catch (e) {
            console.warn('BarcodeDetector não suporta os formatos pedidos:', e);
            mostrarToast('Formato de código não suportado pelo navegador.', 'erro');
            fecharCamera();
            return;
        }

        async function loop() {
            if (!cameraAtiva) return;
            try {
                const codes = await detector.detect(video);
                if (codes && codes.length && codes[0].rawValue) {
                    const ean = String(codes[0].rawValue);
                    // C2: votação 3 em 5 (BarcodeDetector é mais confiável que Quagga,
                    // margem menor é suficiente).
                    amostrasJanelaNativo.push(ean);
                    if (amostrasJanelaNativo.length > JANELA_NATIVO) amostrasJanelaNativo.shift();

                    const contagem = {};
                    for (const c of amostrasJanelaNativo) contagem[c] = (contagem[c] || 0) + 1;
                    let vencedor = null;
                    let maxVotos = 0;
                    for (const c in contagem) {
                        if (contagem[c] > maxVotos) { maxVotos = contagem[c]; vencedor = c; }
                    }

                    // M3: feedback visual dinâmico no caminho nativo também.
                    const msgEl = document.querySelector('#overlay-camera .camera-msg');
                    if (msgEl && cameraAtiva && maxVotos > 0) {
                        msgEl.textContent = 'Lendo… (' + maxVotos + '/' + VOTOS_NATIVO + ' confirmações)';
                    }

                    if (vencedor && maxVotos >= VOTOS_NATIVO) {
                        console.log('[camera] BarcodeDetector ACEITO', vencedor,
                                    'com', maxVotos, '/', amostrasJanelaNativo.length, 'votos');
                        const aceito = processarEanDetectado(vencedor);
                        if (aceito) { amostrasJanelaNativo.length = 0; return; }
                    }
                }
            } catch (e) {
                console.warn('detect erro', e);
            }
            cameraRafId = requestAnimationFrame(loop);
        }
        cameraRafId = requestAnimationFrame(loop);
    }

    // Caminho fallback antigo (ZXing) removido — substituído por Quagga2 em
    // iniciarComQuagga(), que tem decode muito mais robusto em webcam.

    function fecharCamera() {
        cameraAtiva = false;
        if (cameraRafId) {
            cancelAnimationFrame(cameraRafId);
            cameraRafId = null;
        }
        if (cameraDicasTimer)  { clearTimeout(cameraDicasTimer);  cameraDicasTimer  = null; }
        if (cameraDicasTimer2) { clearTimeout(cameraDicasTimer2); cameraDicasTimer2 = null; }
        if (timerRelaxar)      { clearTimeout(timerRelaxar);      timerRelaxar      = null; }
        // Reset das janelas de votação + telemetria para evitar carry-over entre sessões.
        amostrasJanela.length = 0;
        amostrasJanelaNativo.length = 0;
        erroMaxAtual = 0.15;
        contadorLog = 0;
        ultimoErroLogado = -1;
        primeiraRejeicaoLogada = false;
        // Parar Quagga se estiver rodando — ele controla seu próprio stream e canvas.
        if (quaggaIniciado && window.Quagga) {
            try { window.Quagga.offDetected(onQuaggaDetectado); } catch (_) {}
            try { window.Quagga.stop(); } catch (_) {}
            quaggaIniciado = false;
        }
        // Parar o stream do caminho nativo (caso aplicável)
        if (cameraStream) {
            try { cameraStream.getTracks().forEach((t) => t.stop()); } catch (_) {}
            cameraStream = null;
        }
        const overlay = document.getElementById('overlay-camera');
        if (overlay) overlay.hidden = true;
        const viewport = document.getElementById('container-camera-viewport');
        if (viewport) {
            // Remove qualquer <video>/<canvas> que Quagga ou o caminho nativo deixaram
            viewport.querySelectorAll('video, canvas').forEach((el) => el.remove());
            // Reinjeta o placeholder <video> usado pelo caminho nativo na próxima abertura
            if (!document.getElementById('video-camera')) {
                const v = document.createElement('video');
                v.id = 'video-camera';
                v.autoplay = true;
                v.playsInline = true;
                v.muted = true;
                viewport.insertBefore(v, viewport.firstChild);
            }
        }
        const msg = document.querySelector('#overlay-camera .camera-msg');
        if (msg) {
            msg.classList.remove('camera-msg-procurando');
            msg.textContent = 'Aponte o código de barras para a linha vermelha';
        }
        const dicas = document.getElementById('camera-dicas');
        if (dicas) dicas.hidden = true;
        // Devolve foco ao input agora que a câmera fechou.
        focarInput();
    }

    /**
     * Quando o usuário desiste do leitor visual, fechar a câmera e dar foco ao input.
     */
    function digitarManualmente() {
        fecharCamera();
        const input = document.getElementById('campo-isbn');
        if (input) {
            input.value = '';
            input.focus();
            mostrarToast('Digite o ISBN manualmente e pressione Enter');
        }
    }

    function inicializarCamera() {
        const btnAbrir  = document.getElementById('btn-camera');
        const btnFechar = document.getElementById('btn-fechar-camera');
        const btnManual = document.getElementById('btn-digitar-manualmente');
        if (btnAbrir)  btnAbrir.addEventListener('click', iniciarCameraEDecodificar);
        if (btnFechar) btnFechar.addEventListener('click', fecharCamera);
        if (btnManual) btnManual.addEventListener('click', digitarManualmente);

        // ESC global já existe e chama novaConsulta(); aqui fechamos a câmera
        // antes de qualquer reset, para liberar tracks.
        document.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape' && cameraAtiva) {
                ev.preventDefault();
                ev.stopPropagation();
                fecharCamera();
            }
        }, true); // capture: roda antes do listener de novaConsulta
    }
})();
