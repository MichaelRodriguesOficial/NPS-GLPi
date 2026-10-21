/**
 * Construtor de pesquisas — reordenação de perguntas por
 * "arrastar e soltar" (seção 10 do escopo).
 *
 * Implementado com a API nativa de Drag and Drop do HTML5, sem
 * dependência de bibliotecas externas.
 */
(function () {
    'use strict';

    // Marcador de carregamento. A interface simplificada
    // (autoatendimento) não inclui os mesmos assets que a interface
    // padrão, e sem este arquivo a pesquisa aparecia sempre em página
    // única, ignorando o formato "uma pergunta por etapa". As telas de
    // resposta carregam este script sob demanda e usam esta marca para
    // não carregá-lo duas vezes.
    window.NPSPHERE_BUILDER_LOADED = true;

    /**
     * Descobre o caminho base de "ajax/" e "front/" do plugin a partir
     * de uma URL já gerada pelo servidor (evita ter que hardcodear ou
     * adivinhar o prefixo real -- em algumas instalações do GLPI os
     * plugins ficam sob "/plugins/", em outras sob "/marketplace/",
     * dependendo de configuração/proxy).
     */
    function guessPluginBasePath() {
        var withReorder = document.querySelector('[data-reorder-url]');
        if (withReorder) {
            return withReorder.dataset.reorderUrl.replace(/ajax\/[^/]+$/, '');
        }
        var withList = document.querySelector('[data-list-url]');
        if (withList) {
            return withList.dataset.listUrl.replace(/ajax\/[^/]+(\?.*)?$/, '');
        }
        var answerForm = document.querySelector('form[action*="/front/answer.form.php"]');
        if (answerForm) {
            return answerForm.getAttribute('action').replace(/front\/answer\.form\.php$/, '');
        }
        var questionForm = document.querySelector('form[action*="/front/question.form.php"]');
        if (questionForm) {
            return questionForm.getAttribute('action').replace(/front\/question\.form\.php$/, '');
        }
        return null;
    }

    /**
     * Busca um token CSRF novo e atualiza TODOS os lugares da página
     * atual que guardam um token (campos ocultos "_glpi_csrf_token" e
     * atributos "data-csrf-token" usados pelas chamadas AJAX do
     * builder) -- inclusive os que forem inseridos depois via AJAX
     * (reordenação, troca de aba, etc.), já que a busca no DOM é feita
     * de novo a cada renovação.
     */
    function refreshCsrfTokens() {
        var base = guessPluginBasePath();
        if (!base) {
            return;
        }
        // Na página pública (visitante sem login) o endpoint de token
        // exige autenticação e responderia 401 -- não faz sentido tentar.
        if (document.querySelector('.npsphere-public-page')) {
            return;
        }

        fetch(base + 'ajax/csrf.token.php', { method: 'GET' })
            .then(function (r) { return r.ok ? r.text() : null; })
            .then(function (token) {
                if (!token) {
                    return;
                }
                token = token.trim();
                if (!token) {
                    return;
                }
                document.querySelectorAll('input[name="_glpi_csrf_token"]').forEach(function (input) {
                    input.value = token;
                });
                document.querySelectorAll('[data-csrf-token]').forEach(function (el) {
                    el.dataset.csrfToken = token;
                });
            })
            .catch(function () { /* mantém o token anterior em caso de falha de rede */ });
    }

    function initBuilder(container) {
        var tbody = container.querySelector('#npsphere-question-sortable');
        if (!tbody) {
            return;
        }

        var draggedRow = null;

        tbody.querySelectorAll('.npsphere-question-row[draggable="true"]').forEach(function (row) {
            row.addEventListener('dragstart', function () {
                draggedRow = row;
                row.classList.add('npsphere-dragging');
            });

            row.addEventListener('dragend', function () {
                row.classList.remove('npsphere-dragging');
                draggedRow = null;
                persistOrder(container, tbody);
            });

            row.addEventListener('dragover', function (event) {
                event.preventDefault();
                if (!draggedRow || draggedRow === row) {
                    return;
                }
                var rect = row.getBoundingClientRect();
                var after = (event.clientY - rect.top) > (rect.height / 2);
                tbody.insertBefore(draggedRow, after ? row.nextSibling : row);
            });
        });
    }

    function persistOrder(container, tbody) {
        var order = [];
        tbody.querySelectorAll('.npsphere-question-row').forEach(function (row) {
            order.push(row.dataset.id);
        });

        function send(token) {
            var params = new URLSearchParams();
            params.append('_glpi_csrf_token', token || container.dataset.csrfToken);
            params.append('surveys_id', container.dataset.surveysId);
            order.forEach(function (id) {
                params.append('order[]', id);
            });

            fetch(container.dataset.reorderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString(),
            }).then(function (response) {
                if (!response.ok) {
                    // Em caso de falha, recarrega para refletir o estado real salvo no banco.
                    window.location.reload();
                }
            }).catch(function () {
                window.location.reload();
            });
        }

        // Busca um token fresco IMEDIATAMENTE antes de reordenar, em vez
        // de confiar só no token capturado quando a lista carregou (que
        // pode já estar velho se o usuário ficou um tempo na tela antes
        // de arrastar).
        fetchFreshCsrfToken().then(send);
    }

    /**
     * Alterna entre a lista de perguntas e o formulário de
     * criação/edição, tudo dentro da mesma aba "Pergunta" (sem navegar
     * para outra página). O formulário e a lista são carregados via
     * fetch() a partir das mesmas URLs usadas no modo de página cheia
     * (front/question.form.php e ajax/question.list.php), apenas
     * pedindo o fragmento HTML com "ajax=1".
     */
    function initManager(manager) {
        if (manager.dataset.npsphereManagerInit === '1') {
            return;
        }
        manager.dataset.npsphereManagerInit = '1';

        var listPanel = manager.querySelector('#npsphere-question-list-panel');
        var formPanel = manager.querySelector('#npsphere-question-form-panel');
        var listUrl = manager.dataset.listUrl;

        function showList() {
            formPanel.style.display = 'none';
            formPanel.innerHTML = '';
            listPanel.style.display = '';
        }

        function showForm() {
            listPanel.style.display = 'none';
            formPanel.style.display = '';
        }

        function reloadList() {
            fetch(listUrl, { method: 'GET' })
                .then(function (r) { return r.ok ? r.text() : null; })
                .then(function (html) {
                    if (html === null) {
                        window.location.reload();
                        return;
                    }
                    listPanel.innerHTML = html;
                    listPanel.querySelectorAll('.npsphere-question-builder').forEach(initBuilder);
                    showList();
                })
                .catch(function () { window.location.reload(); });
        }

        function loadForm(url) {
            var separator = url.indexOf('?') === -1 ? '?' : '&';
            fetch(url + separator + 'ajax=1', { method: 'GET' })
                .then(function (r) { return r.ok ? r.text() : null; })
                .then(function (html) {
                    if (html === null) {
                        window.location.href = url;
                        return;
                    }
                    formPanel.innerHTML = html;
                    executeInjectedScripts(formPanel);
                    showForm();
                })
                .catch(function () { window.location.href = url; });
        }

        manager.addEventListener('click', function (event) {
            var openLink = event.target.closest('.npsphere-open-form');
            if (openLink) {
                event.preventDefault();
                loadForm(openLink.dataset.url || openLink.getAttribute('href'));
                return;
            }
            var cancelBtn = event.target.closest('.npsphere-cancel-form');
            if (cancelBtn) {
                event.preventDefault();
                showList();
            }
        });

        manager.addEventListener('submit', function (event) {
            var form = event.target;
            if (form.id !== 'npsphere-question-form') {
                return; // "Duplicar"/"Excluir" continuam como POST normal
            }
            event.preventDefault();

            var editor = form.closest('.npsphere-question-editor');
            var errorsBox = editor ? editor.querySelector('#npsphere-form-errors') : null;
            var submitBtn = event.submitter;

            if (submitBtn) {
                submitBtn.disabled = true;
            }

            function send() {
                var formData = new FormData(form);
                if (submitBtn && submitBtn.name) {
                    formData.append(submitBtn.name, submitBtn.value || '1');
                }
                formData.append('ajax', '1');

                fetch(form.action, { method: 'POST', body: formData })
                    .then(function (r) { return r.json(); })
                    .catch(function () { return null; })
                    .then(function (data) {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                        }
                        if (data && data.success) {
                            reloadList();
                            return;
                        }
                        if (errorsBox) {
                            var messages = (data && data.errors && data.errors.length)
                                ? data.errors
                                : ['Não foi possível salvar. Verifique os campos e tente novamente.'];
                            errorsBox.style.display = '';
                            errorsBox.innerHTML = '<div class="alert alert-danger"><ul class="mb-0">' +
                                messages.map(function (m) { return '<li>' + m + '</li>'; }).join('') +
                                '</ul></div>';
                        }
                    });
            }

            // Busca um token fresco imediatamente antes de salvar -- o
            // formulário pode ter ficado aberto um tempo (editando
            // texto, ajustando opções) antes do clique em "Salvar".
            fetchFreshCsrfToken().then(function (token) {
                applyCsrfToken(form, token);
                send();
            });
        });
    }

    /**
     * innerHTML não executa tags <script> incluídas na string -- é
     * preciso recriá-las manualmente para que o JS do formulário
     * injetado (pré-visualização, opções dinâmicas, etc.) rode.
     */
    function executeInjectedScripts(container) {
        container.querySelectorAll('script').forEach(function (oldScript) {
            var newScript = document.createElement('script');
            Array.prototype.forEach.call(oldScript.attributes, function (attr) {
                newScript.setAttribute(attr.name, attr.value);
            });
            newScript.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    /**
     * Mantém abas dinâmicas sempre atualizadas ("Visualizar" e
     * "Resultados"): o GLPI guarda o
     * conteúdo já carregado das abas no DOM, então, ao voltar para ela,
     * o usuário via exatamente o estado em que a deixou (parada no
     * "Obrigado pela sua avaliação!", ou no meio de um preenchimento
     * antigo). Aqui, sempre que a aba volta a ficar visível, o conteúdo
     * é recarregado do servidor -- começando do zero, como esperado.
     */
    function initPreviewTabRefresh(tab) {
        if (!tab || tab.dataset.npspherePreviewInit === '1') {
            return;
        }
        tab.dataset.npspherePreviewInit = '1';

        var refreshUrl = tab.dataset.refreshUrl;
        if (!refreshUrl) {
            return;
        }

        // Detecta visibilidade por sondagem simples (offsetParent é null
        // quando o elemento ou qualquer ancestral está com display:none
        // -- que é como o GLPI esconde as abas inativas). Isso é mais
        // confiável aqui do que IntersectionObserver, que não dispara de
        // forma útil para elementos escondidos com display:none.
        function isVisible() {
            return tab.offsetParent !== null;
        }

        var wasVisible = isVisible();
        var seenOnce = wasVisible;

        var timer = setInterval(function () {
            // Se a aba foi substituída/removida do documento, para de sondar.
            if (!tab.isConnected) {
                clearInterval(timer);
                return;
            }

            var visible = isVisible();
            if (visible === wasVisible) {
                return;
            }
            wasVisible = visible;

            if (!visible) {
                return;
            }
            if (!seenOnce) {
                // Primeira exibição: o conteúdo acabou de vir do servidor.
                seenOnce = true;
                return;
            }

            fetch(refreshUrl, { method: 'GET' })
                .then(function (r) { return r.ok ? r.text() : null; })
                .then(function (html) {
                    if (!html) {
                        return;
                    }
                    var wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    var fresh = wrapper.querySelector('.npsphere-refreshable');
                    if (fresh && tab.parentNode) {
                        clearInterval(timer);
                        tab.parentNode.replaceChild(fresh, tab);
                        initAll(fresh);
                    }
                })
                .catch(function () { /* mantém o conteúdo atual em caso de falha */ });
        }, 400);
    }


    /**
     * Regras condicionais (Fase 5): mostra/esconde perguntas em tempo
     * real conforme o respondente preenche as anteriores.
     *
     * Espelha exatamente a lógica de ConditionEvaluator::compare() do
     * PHP -- e o servidor revalida tudo no envio, então isto aqui é
     * apenas conforto visual, nunca a única barreira.
     */
    function compareCondition(answer, operator, expected) {
        if (Array.isArray(answer)) {
            return answer.some(function (v) { return compareCondition(v, operator, expected); });
        }
        var a = String(answer === null || answer === undefined ? '' : answer).trim();
        var b = String(expected === null || expected === undefined ? '' : expected).trim();

        if (operator === 'eq') { return a === b; }
        if (operator === 'ne') { return a !== b; }

        // Comparações numéricas só valem quando os dois lados são números.
        if (a === '' || b === '' || isNaN(Number(a)) || isNaN(Number(b))) {
            return false;
        }
        var na = Number(a), nb = Number(b);
        if (operator === 'lt') { return na < nb; }
        if (operator === 'lte') { return na <= nb; }
        if (operator === 'gt') { return na > nb; }
        if (operator === 'gte') { return na >= nb; }
        return false;
    }

    function readAnswer(block) {
        var checked = block.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked');
        if (checked.length > 0) {
            return Array.prototype.map.call(checked, function (i) { return i.value; });
        }
        var text = block.querySelector('input[type="text"], textarea');
        return text ? text.value : null;
    }

    function initConditionalQuestions(form) {
        if (!form || form.dataset.npsphereCondInit === '1') {
            return;
        }
        var blocks = Array.prototype.slice.call(form.querySelectorAll('[data-question-id]'));
        var hasAnyCondition = blocks.some(function (b) {
            return b.dataset.condTrigger && b.dataset.condTrigger !== '0';
        });
        if (!hasAnyCondition) {
            return;
        }
        form.dataset.npsphereCondInit = '1';

        function apply() {
            var answers = {};
            blocks.forEach(function (b) {
                answers[b.dataset.questionId] = readAnswer(b);
            });

            blocks.forEach(function (b) {
                var trigger = b.dataset.condTrigger;
                if (!trigger || trigger === '0') {
                    return;
                }
                var visible = compareCondition(
                    answers[trigger],
                    b.dataset.condOperator,
                    b.dataset.condValue
                );
                // Marca como condicionalmente oculta; a exibição real
                // considera também o passo atual (modo "uma pergunta por
                // etapa"), tratado em initStepByStep.
                b.dataset.condHidden = visible ? '0' : '1';
                if (!visible) {
                    b.style.display = 'none';
                } else if (form.dataset.displayMode !== 'step_by_step') {
                    b.style.display = '';
                }
            });
        }

        form.addEventListener('change', apply);
        form.addEventListener('input', apply);
        apply();
    }


    /**
     * Filtros da tabela "Quem respondeu" (aba Resultados).
     *
     * Filtragem no NAVEGADOR, de propósito: a lista já veio inteira do
     * servidor, então filtrar aqui é instantâneo e não gera uma
     * requisição a cada tecla digitada. Se um dia a lista crescer a
     * ponto de não caber na página, aí sim vale paginar no servidor.
     */
    function initRespondentsFilter(container) {
        if (!container || container.dataset.npsphereFilterInit === '1') {
            return;
        }
        container.dataset.npsphereFilterInit = '1';

        var search = container.querySelector('#npsphere-resp-search');
        var from = container.querySelector('#npsphere-resp-from');
        var to = container.querySelector('#npsphere-resp-to');
        var clear = container.querySelector('#npsphere-resp-clear');
        var body = container.querySelector('#npsphere-resp-body');
        var count = container.querySelector('#npsphere-resp-count');
        if (!body) {
            return;
        }

        var rows = Array.prototype.slice.call(body.querySelectorAll('tr'));

        var scoreEl = container.querySelector('#npsphere-resp-score');

        // Seletor de entidades: componente à parte, aqui só lemos o que
        // está marcado nele. (A variável antiga apontava para um
        // <select> que deixou de existir -- e por isso o filtro parou
        // de ser aplicado nesta aba.)
        var entityPicker = container.querySelector('.npsphere-picker[data-escopo="answers"]');
        var perPageEl = container.querySelector('#npsphere-resp-perpage');
        var prevBtn = container.querySelector('#npsphere-resp-prev');
        var nextBtn = container.querySelector('#npsphere-resp-next');
        var pageInfo = container.querySelector('#npsphere-resp-page');
        var page = 1;

        function perPage() {
            return perPageEl ? parseInt(perPageEl.value, 10) : 25;
        }

        // O seletor de entidades avisa esta função quando algo muda
        // nele -- ele é um componente à parte e não conhece a lista.
        if (entityPicker) {
            entityPicker.aoMudar = function () { apply(); };
        }

        function apply(resetPage) {
            if (resetPage !== false) {
                page = 1;
            }
            var term = (search ? search.value : '').trim().toLowerCase();
            var dFrom = from ? from.value : '';
            var dTo = to ? to.value : '';
            var matched = [];

            rows.forEach(function (row) {
                // O número do chamado entra na busca: quem investiga uma
                // avaliação costuma partir do chamado, não do nome.
                var haystack = (row.dataset.name || '') + ' ' +
                               (row.dataset.email || '') + ' ' +
                               (row.dataset.phone || '') + ' ' +
                               (row.dataset.entityName || '') + ' ' +
                               (row.dataset.ticket && row.dataset.ticket !== '0'
                                   ? row.dataset.ticket : '');
                haystack = haystack.toLowerCase();
                var matchTerm = term === '' || haystack.indexOf(term) !== -1;

                // Datas em ISO (YYYY-MM-DD) podem ser comparadas como
                // texto -- a ordem lexicográfica coincide com a cronológica.
                var date = row.dataset.date || '';
                var matchFrom = dFrom === '' || date >= dFrom;
                var matchTo = dTo === '' || date <= dTo;

                // Filtro por faixa de nota (promotor/neutro/detrator)
                var wantClass = scoreEl ? scoreEl.value : '';
                var matchScore = wantClass === '' || (row.dataset.class || '') === wantClass;

                // Entidades: seleção múltipla e EXATA -- marcar a
                // entidade-pai não traz as filhas junto.
                var matchEntity = true;
                if (entityPicker) {
                    var escolhidas = Array.prototype.slice
                        .call(entityPicker.querySelectorAll('.npsphere-picker-entity:checked'))
                        .map(function (i) { return i.value; });

                    // Nenhuma marcada significa TODAS -- não filtra.
                    if (escolhidas.length > 0) {
                        matchEntity = escolhidas.indexOf(row.dataset.entity) !== -1;
                    }
                }

                if (matchTerm && matchFrom && matchTo && matchScore && matchEntity) {
                    matched.push(row);
                } else {
                    row.style.display = 'none';
                }
            });

            // Paginação sobre o resultado JÁ filtrado -- filtrar e depois
            // paginar (e não o contrário) é o que faz a contagem bater
            // com o que a pessoa vê.
            var size = perPage();
            var pages = Math.max(1, Math.ceil(matched.length / size));
            if (page > pages) { page = pages; }
            var start = (page - 1) * size;
            var end = start + size;

            matched.forEach(function (row, i) {
                row.style.display = (i >= start && i < end) ? '' : 'none';
            });

            if (count) {
                var total = rows.length;
                count.textContent = (matched.length === total)
                    ? (total + (total === 1 ? ' resposta' : ' respostas'))
                    : ('Filtrando ' + matched.length + ' de ' + total);
            }
            if (pageInfo) {
                pageInfo.textContent = page + ' / ' + pages;
            }
            if (prevBtn) { prevBtn.disabled = (page <= 1); }
            if (nextBtn) { nextBtn.disabled = (page >= pages); }

            // Sem paginação visível quando tudo cabe numa página só.
            var pager = container.querySelector('.npsphere-pager');
            if (pager) {
                pager.style.visibility = (matched.length <= size && pages === 1) ? 'hidden' : '';
            }
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () { page--; apply(false); });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () { page++; apply(false); });
        }
        if (perPageEl) {
            perPageEl.addEventListener('change', function () { apply(true); });
        }

        [search, from, to, scoreEl].forEach(function (el) {
            if (el) {
                el.addEventListener('input', apply);
                el.addEventListener('change', apply);
            }
        });

        if (clear) {
            clear.addEventListener('click', function () {
                // Limpa também as entidades marcadas no seletor.
                if (entityPicker) {
                    entityPicker.querySelectorAll('.npsphere-picker-entity').forEach(function (i) {
                        i.checked = false;
                    });
                    var todas = entityPicker.querySelector('[data-all]');
                    if (todas) { todas.checked = true; }
                    var rotulo = entityPicker.querySelector('.npsphere-picker-label');
                    if (rotulo) { rotulo.textContent = 'Todas as entidades'; }
                    entityPicker.classList.remove('npsphere-picker-ativo');
                }

                if (search) { search.value = ''; }
                if (from) { from.value = ''; }
                if (to) { to.value = ''; }
                apply();
            });
        }

        // Ordenação por coluna
        container.querySelectorAll('.npsphere-sortable').forEach(function (th) {
            th.addEventListener('click', function () {
                var key = th.dataset.sort;
                var asc = th.dataset.dir !== 'asc';
                container.querySelectorAll('.npsphere-sortable').forEach(function (o) {
                    delete o.dataset.dir;
                    o.classList.remove('npsphere-sorted-asc', 'npsphere-sorted-desc');
                });
                th.dataset.dir = asc ? 'asc' : 'desc';
                th.classList.add(asc ? 'npsphere-sorted-asc' : 'npsphere-sorted-desc');

                rows.sort(function (a, b) {
                    var va = a.dataset[key] || '';
                    var vb = b.dataset[key] || '';

                    // Nota é número: comparar como texto colocaria "10"
                    // antes de "9". Linhas sem nota vão para o fim.
                    // Entidade ordena pelo NOME exibido; o id não tem
                    // relação com a ordem alfabética que o usuário vê.
                    if (key === 'entity') {
                        va = a.dataset.entityName || '';
                        vb = b.dataset.entityName || '';
                    }

                    if (key === 'ticket' || key === 'score') {
                        var na = va === '' ? null : parseFloat(va);
                        var nb = vb === '' ? null : parseFloat(vb);
                        if (na === null && nb === null) { return 0; }
                        if (na === null) { return 1; }
                        if (nb === null) { return -1; }
                        return asc ? na - nb : nb - na;
                    }

                    return asc ? va.localeCompare(vb) : vb.localeCompare(va);
                });
                rows.forEach(function (r) { body.appendChild(r); });
            });
        });

        apply();
    }


    /**
     * Sub-abas da aba "Resultados" (Visão geral / Respostas).
     * Com muitas respostas, ter indicadores e lista bruta na mesma
     * rolagem tornava a tela impraticável.
     */
    function initResultTabs(root) {
        if (!root || root.dataset.npsphereSubtabsInit === '1') {
            return;
        }
        var tabs = root.querySelectorAll('.npsphere-subtab');
        if (!tabs.length) {
            return;
        }
        root.dataset.npsphereSubtabsInit = '1';

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.dataset.panel;
                tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
                root.querySelectorAll('.npsphere-panel').forEach(function (panel) {
                    panel.style.display = (panel.dataset.panel === target) ? '' : 'none';
                });

            });
        });
    }

    /**
     * Botão "Ver": carrega as respostas de uma pessoa num painel abaixo
     * da lista, sem sair da tela nem abrir outra aba.
     */
    function initAnswerDetail(container) {
        if (!container || container.dataset.npsphereDetailInit === '1') {
            return;
        }
        container.dataset.npsphereDetailInit = '1';

        var detailUrl = container.dataset.detailUrl;
        var panel = document.getElementById('npsphere-detail-panel');
        if (!detailUrl || !panel) {
            return;
        }

        container.addEventListener('click', function (event) {
            // Botão de imprimir uma resposta: garante que o detalhe
            // daquela pessoa esteja carregado ANTES de mandar imprimir
            // (imprimir um painel vazio geraria uma folha em branco).
            var printOne = event.target.closest('.npsphere-print-answer');
            if (printOne) {
                event.preventDefault();
                loadDetail(printOne.dataset.answerId, function () {
                    printScoped('answer');
                });
                return;
            }

            var btn = event.target.closest('.npsphere-view-answer');
            if (!btn) {
                return;
            }
            event.preventDefault();

            container.querySelectorAll('.npsphere-view-answer').forEach(function (b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');
            loadDetail(btn.dataset.answerId, function () {
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });

        // Link "Ver esta resposta no GLPI" do relatório diário
        // (&npsphere_resposta=ID): abre a sub-aba Respostas já com o
        // detalhe daquela resposta. Uma vez só por página.
        var alvo = new URLSearchParams(window.location.search).get('npsphere_resposta');
        if (alvo && /^[0-9]+$/.test(alvo) && !window.npsphereRespostaAberta) {
            window.npsphereRespostaAberta = true;
            // Depois da inicialização das sub-abas (initResultTabs roda
            // em seguida a esta função).
            setTimeout(function () {
                var abaRespostas = document.querySelector('.npsphere-subtab[data-panel="answers"]');
                if (abaRespostas) {
                    abaRespostas.click();
                }
                loadDetail(alvo, function () {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }, 0);
        }

        function loadDetail(answerId, done) {
            panel.style.display = '';
            panel.innerHTML = '<p class="text-muted mb-0">Carregando…</p>';

            fetch(detailUrl + '?answers_id=' + encodeURIComponent(answerId), { method: 'GET' })
                .then(function (r) { return r.ok ? r.text() : null; })
                .then(function (html) {
                    panel.innerHTML = html || '<p class="text-danger mb-0">Não foi possível carregar as respostas.</p>';

                    // Desabilita de fato os campos exibidos: o
                    // pointer-events do CSS impede o clique, mas não a
                    // navegação por teclado -- e ninguém deve conseguir
                    // "alterar" uma resposta já enviada por aqui.
                    panel.querySelectorAll('.npsphere-readonly input, .npsphere-readonly select, .npsphere-readonly textarea')
                        .forEach(function (el) {
                            el.disabled = true;
                            el.setAttribute('tabindex', '-1');
                        });

                    if (typeof done === 'function') {
                        done();
                    }
                })
                .catch(function () {
                    panel.innerHTML = '<p class="text-danger mb-0">Não foi possível carregar as respostas.</p>';
                });
        }

        // Fechar o painel de detalhe
        panel.addEventListener('click', function (event) {
            if (event.target.closest('.npsphere-detail-close')) {
                panel.style.display = 'none';
                panel.innerHTML = '';
                container.querySelectorAll('.npsphere-view-answer').forEach(function (b) {
                    b.classList.remove('active');
                });
            }
        });
    }


    /**
     * Menu "Exportar" (CSV e impressão/PDF).
     *
     * Nota sobre PDF: o menu de exportação nativo do GLPI pertence ao
     * motor de busca dele e opera sobre listagens padrão -- a aba
     * Resultados é uma tela própria, então não há como herdá-lo. O PDF
     * aqui é gerado pela função de impressão do navegador, com uma
     * folha de estilo @media print que remove menus, filtros e
     * paginação e imprime todas as respostas.
     */
    function initExport(root) {
        if (!root || root.dataset.npsphereExportInit === '1') {
            return;
        }
        root.dataset.npsphereExportInit = '1';

        root.addEventListener('click', function (event) {
            var toggle = event.target.closest('.npsphere-export-toggle');
            if (toggle) {
                var menu = toggle.parentElement.querySelector('.npsphere-export-menu');
                var willOpen = menu.hidden;
                // Fecha qualquer outro menu aberto antes de abrir este
                root.querySelectorAll('.npsphere-export-menu').forEach(function (m) { m.hidden = true; });
                menu.hidden = !willOpen;
                return;
            }

            var csvBtn = event.target.closest('[data-export-url]');
            if (csvBtn) {
                window.location.href = csvBtn.dataset.exportUrl;
                root.querySelectorAll('.npsphere-export-menu').forEach(function (m) { m.hidden = true; });
                return;
            }

            var printBtn = event.target.closest('[data-export-print]');
            if (printBtn) {
                root.querySelectorAll('.npsphere-export-menu').forEach(function (m) { m.hidden = true; });
                printScoped(printBtn.dataset.exportPrint);
            }
        });

        // Clique fora fecha o menu
        document.addEventListener('click', function (event) {
            if (!event.target.closest('.npsphere-export')) {
                root.querySelectorAll('.npsphere-export-menu').forEach(function (m) { m.hidden = true; });
            }
        });
    }


    /**
     * Imprime APENAS uma parte da tela.
     *
     * O escopo é marcado com uma classe no <body>, que a folha de estilo
     * @media print usa para esconder todo o resto -- inclusive a outra
     * sub-aba. Sem isso, "imprimir a visão geral" levaria junto a lista
     * inteira de respostas.
     *
     * A classe é removida logo após a impressão (evento afterprint, com
     * um tempo de segurança para navegadores que não o disparam), para
     * a tela não ficar num estado esquisito depois.
     */
    function printScoped(scope) {
        var source;
        if (scope === 'dashboard') {
            // Painel consolidado: filtros e botões ficam de fora pelo
            // CSS de impressão -- no papel eles não servem para nada.
            source = document.querySelector('.npsphere-dashboard');
        } else if (scope === 'answer') {
            source = document.getElementById('npsphere-detail-panel');
        } else {
            source = document.querySelector('.npsphere-panel[data-panel="overview"]');
        }

        if (!source) {
            window.print();
            return;
        }

        // Clona o conteúdo para um container próprio, filho direto do
        // <body>. Antes eu apenas escondia o resto com visibility:hidden
        // -- mas elementos assim escondidos CONTINUAM ocupando espaço no
        // layout, e era isso que empurrava o conteúdo para baixo,
        // desperdiçando quase meia página em branco no topo.
        var previous = document.getElementById('npsphere-print-root');
        if (previous) {
            previous.remove();
        }

        var root = document.createElement('div');
        root.id = 'npsphere-print-root';

        // Cabeçalho do documento: nome da pesquisa, descrição e data de
        // geração. Uma folha impressa que não se identifica não serve
        // para arquivar nem para enviar a alguém.
        var titulo = document.querySelector('.npsphere-print-title');
        if (titulo) {
            root.appendChild(titulo.cloneNode(true));
        }

        var corpo = document.createElement('div');
        corpo.innerHTML = source.innerHTML;
        root.appendChild(corpo);

        // O botão "Fechar" do detalhe não faz sentido no papel
        root.querySelectorAll('.npsphere-detail-close, .npsphere-view-answer, .npsphere-print-answer')
            .forEach(function (el) { el.remove(); });

        document.body.appendChild(root);
        document.body.classList.add('npsphere-printing');

        function cleanup() {
            document.body.classList.remove('npsphere-printing');
            var el = document.getElementById('npsphere-print-root');
            if (el) {
                el.remove();
            }
            window.removeEventListener('afterprint', cleanup);
        }
        window.addEventListener('afterprint', cleanup);
        setTimeout(cleanup, 2000);

        window.print();
    }


    /**
     * Mesma validação de e-mail feita no servidor
     * (RespondentIdentity::isValidEmail).
     *
     * O type="email" nativo do HTML aceita "fulano@q" -- domínio sem
     * ponto é válido pela especificação, mas num formulário público é
     * quase sempre erro de digitação, e esse e-mail nunca receberia
     * resposta. As duas validações precisam concordar: se o navegador
     * aceitasse e o servidor recusasse, a pessoa só descobriria o
     * problema depois de preencher a pesquisa inteira.
     */
    function isValidEmail(email) {
        email = (email || '').trim();
        var basic = /^[^\s@]+@[^\s@]+$/;
        if (!basic.test(email)) {
            return false;
        }

        var domain = email.slice(email.lastIndexOf('@') + 1);
        if (domain.indexOf('.') === -1) { return false; }
        if (domain.indexOf('..') !== -1) { return false; }
        if (domain[0] === '.' || domain[domain.length - 1] === '.') { return false; }

        var tld = domain.slice(domain.lastIndexOf('.') + 1);
        return /^[a-zA-Z]{2,}$/.test(tld);
    }


    /**
     * Seletor de entidades com caixas de seleção.
     *
     * Mesmo componente nas duas sub-abas, com destinos diferentes:
     *
     *  - "answers"  filtra a lista na hora, no navegador;
     *  - "overview" recarrega a aba pelo servidor, porque NPS, nota
     *    média e distribuição por pergunta são calculados no banco.
     *
     * Não há botão "Aplicar": marcar já aplica, como nos demais filtros
     * da aba de respostas. Na visão geral a recarga é adiada alguns
     * instantes, para quem marcar três entidades seguidas provocar UMA
     * recarga, e não três.
     */
    function initEntityPicker(container) {
        container.querySelectorAll('.npsphere-picker').forEach(function (picker) {
            if (picker.dataset.init === '1') {
                return;
            }
            picker.dataset.init = '1';

            var toggle = picker.querySelector('.npsphere-picker-toggle');
            var menu = picker.querySelector('.npsphere-picker-menu');
            var rotulo = picker.querySelector('.npsphere-picker-label');
            var todas = picker.querySelector('[data-all]');
            var itens = Array.prototype.slice.call(
                picker.querySelectorAll('.npsphere-picker-entity')
            );

            if (!toggle || !menu || !todas) {
                return;
            }

            function selecionadas() {
                return itens.filter(function (i) { return i.checked; });
            }

            function atualizarRotulo() {
                var marcadas = selecionadas();

                // O mesmo componente filtra entidades, pesquisas,
                // técnicos, categorias e requerentes. Os textos vêm do
                // próprio seletor (data-singular/plural/todas); sem
                // eles, cai no texto das entidades dos Resultados.
                var singular = picker.dataset.singular || 'entidade';
                var plural = picker.dataset.plural || 'entidades';
                var todasTexto = picker.dataset.todas || 'Todas as entidades';

                if (marcadas.length === 0) {
                    rotulo.textContent = todasTexto;
                } else if (marcadas.length === 1) {
                    var texto = marcadas[0].parentElement.querySelector('span');
                    rotulo.textContent = texto ? texto.textContent.trim() : '1 ' + singular;
                } else {
                    rotulo.textContent = marcadas.length + ' ' + plural;
                }

                // "Todas" fica marcada quando nenhuma entidade está --
                // são estados equivalentes, e mostrar os dois marcados
                // (ou nenhum) confundiria.
                todas.checked = marcadas.length === 0;
                picker.classList.toggle('npsphere-picker-ativo', marcadas.length > 0);
            }

            var timer = null;
            function aplicar() {
                atualizarRotulo();

                var valores = selecionadas().map(function (i) { return i.value; });

                // Painel consolidado: o filtro é um formulário comum
                // (GET), então basta enviá-lo. O adiamento curto junta
                // várias marcações seguidas num único recarregamento.
                if (picker.dataset.escopo === 'dashboard') {
                    var form = picker.closest('form');
                    if (timer) {
                        clearTimeout(timer);
                    }
                    timer = setTimeout(function () {
                        if (form) {
                            form.submit();
                        }
                    }, 450);
                    return;
                }

                // MESMO comportamento nas duas abas, e efeito nas duas.
                //
                // Antes cada aba aplicava do seu jeito e sobre os seus
                // dados: dava para ter a visão geral de uma entidade e a
                // lista de outra, olhando a mesma tela. Agora o filtro é
                // um só.
                //
                // 1) A lista é filtrada na hora -- é o que dá a sensação
                //    de resposta imediata que você prefere;
                sincronizarPickers(picker, valores);
                filtrarListaDeRespostas();

                // 2) e os indicadores, que só o banco sabe calcular, são
                //    recarregados logo atrás. O adiamento curto junta
                //    várias marcações seguidas numa recarga só.
                // Recarrega SEMPRE, em qualquer aba.
                //
                // Uma versão anterior adiava a recarga enquanto a
                // pessoa estava na lista, para evitar o piscar da tela.
                // A intenção era boa, mas o resultado foi pior: o
                // filtro parecia aplicado e os números não mudavam.
                // Entre uma tela que pisca e uma que mostra dados
                // desatualizados, a que pisca é melhor.
                if (timer) {
                    clearTimeout(timer);
                }
                timer = setTimeout(function () {
                    recarregarVisaoGeral(picker, valores);
                }, 400);
            }

            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                menu.hidden = !menu.hidden;
            });

            menu.addEventListener('click', function (event) {
                event.stopPropagation();
            });

            document.addEventListener('click', function () {
                menu.hidden = true;
            });

            todas.addEventListener('change', function () {
                // Marcar "Todas" limpa a seleção; desmarcar não faz
                // nada, porque "nenhuma entidade" já significa todas.
                if (todas.checked) {
                    itens.forEach(function (i) { i.checked = false; });
                    aplicar();
                } else {
                    todas.checked = true;
                }
            });

            itens.forEach(function (item) {
                item.addEventListener('change', aplicar);
            });

            atualizarRotulo();
        });
    }

    /**
     * Espelha a seleção nos demais seletores da tela.
     *
     * Existem dois (um por sub-aba) porque cada um precisa ficar perto
     * do conteúdo que filtra -- mas representam UM único filtro.
     */
    function sincronizarPickers(origem, valores) {
        var raiz = origem.closest('.npsphere-results') || document;

        raiz.querySelectorAll('.npsphere-picker').forEach(function (outro) {
            if (outro === origem) {
                return;
            }

            outro.querySelectorAll('.npsphere-picker-entity').forEach(function (item) {
                item.checked = valores.indexOf(item.value) !== -1;
            });

            var todas = outro.querySelector('[data-all]');
            if (todas) {
                todas.checked = valores.length === 0;
            }

            var rotulo = outro.querySelector('.npsphere-picker-label');
            if (rotulo) {
                if (valores.length === 0) {
                    rotulo.textContent = 'Todas as entidades';
                } else if (valores.length === 1) {
                    var marcado = outro.querySelector('.npsphere-picker-entity:checked');
                    var texto = marcado ? marcado.parentElement.querySelector('span') : null;
                    rotulo.textContent = texto ? texto.textContent.trim() : '1 entidade';
                } else {
                    rotulo.textContent = valores.length + ' entidades';
                }
            }

            outro.classList.toggle('npsphere-picker-ativo', valores.length > 0);
        });
    }

    /**
     * Pede à lista de respostas que reavalie os filtros.
     */
    function filtrarListaDeRespostas() {
        var alvo = document.querySelector('.npsphere-picker[data-escopo="answers"]');
        if (alvo && typeof alvo.aoMudar === 'function') {
            alvo.aoMudar();
        }
    }

    /**
     * Reabre a sub-aba que estava ativa antes de uma recarga.
     *
     * Reproduz o que o clique na aba faz, em vez de chamá-lo: disparar
     * um clique sintético executaria também qualquer efeito colateral
     * que o manipulador venha a ganhar no futuro.
     */
    function restaurarSubaba(root, panel) {
        if (!root || !panel || panel === 'overview') {
            return; // "overview" já é o estado inicial do fragmento
        }

        root.querySelectorAll('.npsphere-subtab').forEach(function (tab) {
            tab.classList.toggle('is-active', tab.dataset.panel === panel);
        });

        root.querySelectorAll('.npsphere-panel').forEach(function (p) {
            p.style.display = (p.dataset.panel === panel) ? '' : 'none';
        });
    }

    function recarregarVisaoGeral(picker, entidades) {
        var baseUrl = picker.dataset.refreshUrl;
        if (!baseUrl) {
            return;
        }

        var url = baseUrl;
        entidades.forEach(function (e) {
            url += '&npsphere_entities[]=' + encodeURIComponent(e);
        });

        var alvo = picker.closest('.npsphere-refreshable');
        if (!alvo) {
            return;
        }

        // Guarda a sub-aba ATIVA antes de trocar o conteúdo.
        //
        // A recarga substitui o bloco inteiro, e o fragmento novo vem
        // sempre com "Visão geral" aberta. Sem isto, mexer no filtro
        // estando na aba Respostas jogava a pessoa para a outra aba --
        // e o filtro serve justamente para examinar a lista.
        var abaAtiva = 'overview';
        var ativa = alvo.querySelector('.npsphere-subtab.is-active');
        if (ativa && ativa.dataset.panel) {
            abaAtiva = ativa.dataset.panel;
        }

        picker.classList.add('npsphere-picker-carregando');

        fetch(url, { method: 'GET' })
            .then(function (r) { return r.ok ? r.text() : null; })
            .then(function (html) {
                if (!html) {
                    return;
                }

                var wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                var fresco = wrapper.querySelector('.npsphere-refreshable');

                // Nunca substituir por um fragmento vazio: manter o que
                // está na tela é melhor do que esvaziá-la.
                if (!fresco || fresco.children.length === 0 || !alvo.parentNode) {
                    picker.classList.remove('npsphere-picker-carregando');
                    return;
                }

                alvo.parentNode.replaceChild(fresco, alvo);
                initAll(fresco);
                restaurarSubaba(fresco, abaAtiva);
            })
            .catch(function () {
                picker.classList.remove('npsphere-picker-carregando');
            });
    }


    /**
     * Datas do painel aplicam ao mudar, sem botão -- o mesmo
     * comportamento do seletor de pesquisas ao lado delas.
     */
    function initDashboardDates(root) {
        root.querySelectorAll('.npsphere-dash-auto').forEach(function (campo) {
            if (campo.dataset.init === '1') {
                return;
            }
            campo.dataset.init = '1';

            campo.addEventListener('change', function () {
                var form = campo.closest('form');
                if (form && campo.value) {
                    form.submit();
                }
            });
        });
    }


    /**
     * Clicar numa linha de recorte filtra o painel por ela.
     *
     * Marca o item no seletor correspondente -- e APENAS ele, nesse
     * seletor -- e envia o formulário. Os outros filtros permanecem:
     * clicar num técnico com uma categoria já filtrada mostra aquele
     * técnico naquela categoria.
     */
    function initDashboardLinhas(root) {
        root.querySelectorAll('.npsphere-dash-linha').forEach(function (linha) {
            if (linha.dataset.init === '1') {
                return;
            }
            linha.dataset.init = '1';

            linha.addEventListener('click', function (event) {
                // O link "abrir resultados" dentro da linha tem destino
                // próprio e não pode disparar o filtro.
                if (event.target.closest('[data-no-filter]')) {
                    return;
                }

                var campo = linha.dataset.filtroCampo;
                var id = linha.dataset.filtroId;
                var form = root.querySelector('#npsphere-dash-form');
                if (!campo || !id || !form) {
                    return;
                }

                var caixas = form.querySelectorAll('input[name="' + campo + '[]"]');
                if (!caixas.length) {
                    return;
                }

                caixas.forEach(function (c) {
                    c.checked = (c.value === id);
                });

                form.submit();
            });
        });
    }

    function initAll(root) {
        var scope = root || document;
        if (scope.matches) {
            if (scope.matches('.npsphere-question-manager')) { initManager(scope); }
            if (scope.matches('.npsphere-question-builder')) { initBuilder(scope); }
            if (scope.matches('.npsphere-question-steps')) { initStepByStep(scope); }
            if (scope.matches('.npsphere-embedded-answer-form')) { initEmbeddedAnswerForm(scope); }
            if (scope.matches('.npsphere-question-steps')) { initConditionalQuestions(scope); }
            if (scope.matches('.npsphere-refreshable')) { initPreviewTabRefresh(scope); }
            if (scope.matches('.npsphere-respondents')) { initRespondentsFilter(scope); initAnswerDetail(scope); }
            if (scope.matches('.npsphere-results')) { initResultTabs(scope); initExport(scope); initEntityPicker(scope); }
            if (scope.matches('.npsphere-dashboard')) { initEntityPicker(scope); initDashboardDates(scope); initExport(scope); initDashboardLinhas(scope); }
        }
        if (scope.querySelectorAll) {
            scope.querySelectorAll('.npsphere-question-manager').forEach(initManager);
            scope.querySelectorAll('.npsphere-question-builder').forEach(initBuilder);
            scope.querySelectorAll('.npsphere-question-steps').forEach(initStepByStep);
            scope.querySelectorAll('.npsphere-embedded-answer-form').forEach(initEmbeddedAnswerForm);
            scope.querySelectorAll('.npsphere-question-steps').forEach(initConditionalQuestions);
            scope.querySelectorAll('.npsphere-refreshable').forEach(initPreviewTabRefresh);
            scope.querySelectorAll('.npsphere-respondents').forEach(function (el) { initRespondentsFilter(el); initAnswerDetail(el); });
            scope.querySelectorAll('.npsphere-results').forEach(function (el) { initResultTabs(el); initExport(el); initEntityPicker(el); });
            // Painel consolidado: reaproveita o mesmo seletor dos
            // Resultados, com destino diferente (envia o formulário).
            scope.querySelectorAll('.npsphere-dashboard').forEach(function (el) { initEntityPicker(el); initDashboardDates(el); initExport(el); initDashboardLinhas(el); });
        }
    }

    // Exposto de propósito: quem injeta conteúdo do plugin DEPOIS do
    // carregamento da página (o modal pós-login) chama esta função
    // diretamente, em vez de depender do evento de carregamento ou do
    // observador de DOM -- que só enxerga o que é inserido depois dele.
    window.NPSPHERE_INIT = initAll;

    function bootstrap() {
        initAll(document);

        // O GLPI carrega o conteúdo das abas dinamicamente (via AJAX),
        // mas o nome exato do evento disparado varia entre versões e
        // não é uma API pública documentada para plugins. Em vez de
        // depender de adivinhar esse evento, observamos diretamente o
        // DOM: sempre que um elemento do plugin for inserido em
        // qualquer lugar da página -- seja pelo carregamento de aba do
        // GLPI, seja pelas próprias trocas de tela deste plugin -- ele
        // é inicializado automaticamente.
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1) {
                        initAll(node);
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });

        // Renova o token CSRF de tudo periodicamente -- corrige a causa
        // raiz de "só funciona na segunda tentativa" (reordenar
        // perguntas, enviar uma avaliação longa): o token expira por
        // tempo decorrido, não só por uso, então qualquer ação demorada
        // o suficiente falhava na primeira tentativa.
        refreshCsrfTokens();
        setInterval(refreshCsrfTokens, 60000);
    }

    // Este arquivo pode ser carregado DEPOIS de a página estar pronta
    // (o modal o injeta sob demanda). Nesse caso DOMContentLoaded já
    // passou e nunca dispararia.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }

    /**
     * Formato de exibição "Uma pergunta por etapa" (seção 22 do escopo):
     * mostra uma pergunta de cada vez, com botões Anterior/Próxima e um
     * indicador "Pergunta X de Y". Se o formato for "Página única" (ou
     * houver 0-1 pergunta), não faz nada -- todas ficam visíveis.
     */
    function initStepByStep(form) {
        if (!form || form.dataset.stepByStepInit === '1') {
            return;
        }
        if (form.dataset.displayMode !== 'step_by_step') {
            return;
        }

        var steps = Array.prototype.slice.call(form.querySelectorAll('.npsphere-step'));
        if (steps.length <= 1) {
            return;
        }

        form.dataset.stepByStepInit = '1';

        var submitBtn = form.querySelector('.npsphere-submit-btn');
        var submitWrapper = submitBtn ? submitBtn.closest('.d-flex') : null;
        var current = 0;

        // Barra de navegação com 3 áreas de largura fixa (Anterior |
        // progresso | Próxima-ou-Enviar), para o texto "Pergunta X de Y"
        // nunca se deslocar dependendo de qual botão está visível do
        // lado direito -- ambos ocupam a MESMA área, um por vez.
        var nav = document.createElement('div');
        nav.className = 'npsphere-step-nav';

        var prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'btn btn-outline-secondary';
        prevBtn.innerHTML = '<i class="ti ti-chevron-left"></i> ' + 'Anterior';

        var prevSlot = document.createElement('div');
        prevSlot.className = 'npsphere-step-prev-slot';
        prevSlot.appendChild(prevBtn);

        var progress = document.createElement('span');
        progress.className = 'npsphere-step-progress';

        var nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'btn btn-primary npsphere-step-next';
        nextBtn.innerHTML = 'Próxima' + ' <i class="ti ti-chevron-right"></i>';

        var error = document.createElement('div');
        error.className = 'npsphere-step-error alert alert-danger';
        error.style.display = 'none';

        var nextSlot = document.createElement('div');
        nextSlot.className = 'npsphere-step-next-slot';
        nextSlot.appendChild(nextBtn);
        if (submitBtn) {
            // O botão "Enviar avaliação" já existe no formulário (é o
            // botão real de submit); movê-lo para dentro da barra de
            // navegação faz ele ocupar visualmente o lugar de "Próxima"
            // na última pergunta, alinhado com "Anterior".
            nextSlot.appendChild(submitBtn);
        }

        function isStepFilled(step) {
            // Etapa de identificação (nome/e-mail/telefone): TODOS os
            // campos obrigatórios precisam estar preenchidos, e o e-mail
            // precisa ser válido -- diferente de uma pergunta comum,
            // onde basta haver uma resposta.
            if (step.dataset.requireAll === '1') {
                var required = step.querySelectorAll('input[required], select[required], textarea[required]');
                for (var i = 0; i < required.length; i++) {
                    if (required[i].value.trim() === '') {
                        return false;
                    }
                    if (required[i].type === 'email' && !isValidEmail(required[i].value)) {
                        return false;
                    }
                }
                return true;
            }

            var radios = step.querySelectorAll('input[type="radio"]');
            if (radios.length > 0) {
                return Array.prototype.some.call(radios, function (r) { return r.checked; });
            }
            var checkboxes = step.querySelectorAll('input[type="checkbox"]');
            if (checkboxes.length > 0) {
                return Array.prototype.some.call(checkboxes, function (c) { return c.checked; });
            }
            var texts = step.querySelectorAll('input[type="text"], textarea');
            if (texts.length > 0) {
                return Array.prototype.some.call(texts, function (t) { return t.value.trim() !== ''; });
            }
            return true; // tipo de campo desconhecido: não bloqueia a navegação
        }

        // Etapas escondidas por uma regra condicional não contam para a
        // navegação: são puladas ao avançar/voltar e não entram no
        // "Pergunta X de Y" (senão o respondente veria telas em branco
        // e uma contagem que não bate com o que ele realmente vê).
        function isSkipped(step) {
            return step.dataset.condHidden === '1';
        }
        function activeSteps() {
            return steps.filter(function (s) { return !isSkipped(s); });
        }

        function render() {
            var visibleSteps = activeSteps();
            if (visibleSteps.length === 0) {
                return;
            }
            // Se a etapa atual acabou de ser ocultada por uma condição,
            // move para a etapa visível mais próxima.
            if (isSkipped(steps[current])) {
                var fallback = visibleSteps[0];
                current = steps.indexOf(fallback);
            }

            steps.forEach(function (step, i) {
                step.style.display = (i === current && !isSkipped(step)) ? '' : 'none';
            });

            var position = visibleSteps.indexOf(steps[current]) + 1;
            // A etapa de identificação não é uma pergunta: fica fora da
            // contagem, senão "Pergunta 1 de 4" apareceria numa tela que
            // pede nome e e-mail, não uma pergunta.
            var identitySteps = visibleSteps.filter(function (st) {
                return st.dataset.requireAll === '1';
            }).length;
            if (steps[current].dataset.requireAll === '1') {
                progress.textContent = 'Seus dados';
            } else {
                progress.textContent = 'Pergunta ' + (position - identitySteps)
                    + ' de ' + (visibleSteps.length - identitySteps);
            }
            // visibility (não display) preserva o espaço no layout -- é
            // isso que mantém "Pergunta X de Y" sempre centralizado,
            // mesmo quando "Anterior" ou "Próxima" ficam ocultos.
            prevBtn.style.visibility = (position <= 1) ? 'hidden' : 'visible';

            var isLast = position >= visibleSteps.length;
            nextBtn.style.display = isLast ? 'none' : '';
            if (submitBtn) {
                submitBtn.style.display = isLast ? '' : 'none';
            }

            error.style.display = 'none';
        }

        prevBtn.addEventListener('click', function () {
            for (var i = current - 1; i >= 0; i--) {
                if (!isSkipped(steps[i])) {
                    current = i;
                    render();
                    return;
                }
            }
        });
        function goToNextStep() {
            for (var i = current + 1; i < steps.length; i++) {
                if (!isSkipped(steps[i])) {
                    current = i;
                    render();
                    return;
                }
            }
        }

        nextBtn.addEventListener('click', function () {
            // Valida a obrigatoriedade da pergunta atual antes de avançar
            // -- antes, isso só era checado no envio final.
            if (steps[current].dataset.required === '1'
                && !isSkipped(steps[current])
                && !isStepFilled(steps[current])) {
                error.textContent = (steps[current].dataset.requireAll === '1')
                    ? 'Preencha nome, e-mail e telefone para continuar.'
                    : 'Esta pergunta é obrigatória.';
                error.style.display = '';
                return;
            }

            // Etapa de identificação: avisa AQUI se essa pessoa já
            // respondeu, em vez de deixá-la preencher a pesquisa inteira
            // para só então descobrir que não vale.
            var checkUrl = form.dataset.identityCheckUrl;
            if (steps[current].dataset.requireAll === '1' && checkUrl) {
                nextBtn.disabled = true;

                // GET com os dados na querystring: leitura pura, sem
                // token CSRF envolvido (POST para certos caminhos de
                // plugin retorna 403 nesta instalação).
                var params = new URLSearchParams();
                var fd = new FormData(form);
                ['token', 'respondent_email', 'respondent_phone', 'respondent_phone_country', 'respondent_name']
                    .forEach(function (key) {
                        var v = fd.get(key);
                        if (v !== null) {
                            params.append(key, v);
                        }
                    });

                fetch(checkUrl + '?' + params.toString(), { method: 'GET' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (res) {
                        nextBtn.disabled = false;
                        if (res && res.already_answered) {
                            error.textContent = 'Já existe uma resposta com este e-mail ou telefone. '
                                + 'Esta pesquisa aceita apenas uma resposta por pessoa.';
                            error.style.display = '';
                            return;
                        }
                        goToNextStep();
                    })
                    .catch(function () {
                        // Falha na verificação não deve travar a pessoa:
                        // o envio final revalida de qualquer forma.
                        nextBtn.disabled = false;
                        goToNextStep();
                    });
                return;
            }
            goToNextStep();
        });

        // Ao clicar em "Enviar avaliação" na última pergunta, também
        // valida a obrigatoriedade antes de deixar o formulário seguir
        // para a submissão de verdade.
        if (submitBtn) {
            submitBtn.addEventListener('click', function (event) {
                if (steps[current].dataset.required === '1' && !isStepFilled(steps[current])) {
                    event.preventDefault();
                    error.textContent = 'Esta pergunta é obrigatória.';
                    error.style.display = '';
                }
            });
        }

        // Quando o respondente muda uma resposta, uma etapa condicional
        // pode aparecer/sumir -- é preciso recalcular a navegação.
        form.addEventListener('change', function () { render(); });

        nav.appendChild(prevSlot);
        nav.appendChild(progress);
        nav.appendChild(nextSlot);
        steps[steps.length - 1].insertAdjacentElement('afterend', nav);
        nav.insertAdjacentElement('afterend', error);

        if (submitWrapper && submitWrapper.parentNode) {
            // O wrapper original (que só existia para alinhar o botão à
            // direita no modo "página única") fica vazio agora -- remove
            // para não deixar um espaço em branco no layout.
            submitWrapper.parentNode.removeChild(submitWrapper);
        }

        render();
    }
    /**
     * Busca um token CSRF novo e devolve uma Promise com o valor (ou
     * null em caso de falha). Usado imediatamente antes de QUALQUER
     * tentativa de envio da resposta -- nunca reaproveita o token que a
     * tentativa anterior já usou (mesmo que aquela tentativa tenha
     * falhado por outro motivo, o token pode já ter sido consumido).
     */
    function fetchFreshCsrfToken() {
        var base = guessPluginBasePath();
        if (!base) {
            return Promise.resolve(null);
        }
        return fetch(base + 'ajax/csrf.token.php', { method: 'GET' })
            .then(function (r) { return r.ok ? r.text() : null; })
            .then(function (t) { return t ? t.trim() : null; })
            .catch(function () { return null; });
    }

    function applyCsrfToken(form, token) {
        if (!token) {
            return;
        }
        form.querySelectorAll('input[name="_glpi_csrf_token"]').forEach(function (input) {
            input.value = token;
        });
    }

    /**
     * Formulário de resposta exibido dentro da aba "Visualizar" da
     * pesquisa: intercepta o envio para nunca navegar para outra tela
     * (nem em caso de sucesso, nem de erro) -- tudo acontece dentro do
     * mesmo container.
     */
    function initEmbeddedAnswerForm(form) {
        if (!form || form.dataset.npsphereEmbeddedInit === '1') {
            return;
        }
        form.dataset.npsphereEmbeddedInit = '1';

        var container = form.closest('#npsphere-answer-container') || form.parentElement;

        function showErrors(messages) {
            var errorBox = form.querySelector('.npsphere-embedded-answer-errors');
            if (!errorBox) {
                errorBox = document.createElement('div');
                errorBox.className = 'npsphere-embedded-answer-errors alert alert-danger';
                form.insertAdjacentElement('afterbegin', errorBox);
            }
            errorBox.innerHTML = '<ul class="mb-0">' +
                messages.map(function (m) { return '<li>' + m + '</li>'; }).join('') +
                '</ul>';
        }

        function attemptSubmit(submitBtn, isRetry) {
            var formData = new FormData(form);
            formData.append('ajax', '1');

            // Envia para o endpoint em ajax/ (data-submit-url), não para
            // o action do formulário (front/answer.form.php) -- o action
            // fica reservado para o fallback de navegação de página
            // cheia, usado só se tudo isso falhar.
            var submitUrl = form.dataset.submitUrl || form.action;

            fetch(submitUrl, { method: 'POST', body: formData })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('http_' + r.status);
                    }
                    return r.json();
                })
                .then(function (data) {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    if (data && data.success) {
                        // Na aba "Visualizar" o servidor devolve
                        // 'preview': a tela é a mesma que o respondente
                        // veria, mas dizendo com todas as letras que
                        // nada foi gravado -- senão o administrador
                        // ficaria em dúvida se acabou de sujar os
                        // próprios números.
                        var registrada = data.preview
                            ? 'Esta é uma pré-visualização: <strong>nenhuma resposta foi gravada</strong>.'
                            : 'Sua resposta foi registrada com sucesso.';

                        container.innerHTML =
                            '<div class="text-center">' +
                            '<i class="ti ' + (data.preview ? 'ti-eye-check text-primary' : 'ti-circle-check text-success') +
                            '" style="font-size: 3rem;"></i>' +
                            '<h2>Obrigado pela sua avaliação!</h2>' +
                            '<p class="text-muted">' + registrada + '</p>' +
                            '</div>';
                        return;
                    }
                    showErrors((data && data.errors && data.errors.length)
                        ? data.errors
                        : ['Não foi possível registrar sua resposta. Tente novamente.']);
                })
                .catch(function () {
                    if (isRetry) {
                        // Já tentamos uma vez com token renovado e ainda
                        // assim falhou -- desiste do modo embutido e faz
                        // a navegação de página normal como último
                        // recurso (o mesmo caminho que já funcionava
                        // antes de existir o modo embutido).
                        form.submit();
                        return;
                    }
                    // Busca um token GARANTIDAMENTE novo antes de tentar
                    // de novo -- a tentativa anterior pode ter consumido
                    // o token mesmo tendo falhado.
                    fetchFreshCsrfToken().then(function (token) {
                        applyCsrfToken(form, token);
                        attemptSubmit(submitBtn, true);
                    });
                });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var submitBtn = form.querySelector('.npsphere-submit-btn');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            // Busca um token fresco IMEDIATAMENTE antes do envio de
            // verdade, em vez de confiar só na renovação periódica de
            // 60 segundos -- garante que o token usado nunca é mais
            // velho que o tempo de uma requisição.
            fetchFreshCsrfToken().then(function (token) {
                applyCsrfToken(form, token);
                attemptSubmit(submitBtn, false);
            });
        });
    }
})();
