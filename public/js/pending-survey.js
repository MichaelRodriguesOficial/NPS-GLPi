/**
 * Interceptação de pesquisas obrigatórias após o login.
 *
 * Carregado em todas as páginas do GLPI. Se houver uma pesquisa interna
 * obrigatória pendente para o usuário, exibe um aviso sobre a tela:
 *
 *  - até o penúltimo dia, dá para adiar ("Responder depois") e seguir
 *    usando o sistema normalmente;
 *  - no ÚLTIMO dia, o aviso não pode ser fechado: só sai dali
 *    respondendo. É o comportamento pedido para pesquisas obrigatórias.
 *
 * O adiamento vale para a sessão do navegador (sessionStorage), não
 * para sempre: no próximo login o aviso volta a aparecer.
 */
(function () {
    'use strict';

    var DISMISS_KEY = 'npsphere_dismissed_surveys';

    function dismissed() {
        try {
            return JSON.parse(sessionStorage.getItem(DISMISS_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function dismiss(id) {
        try {
            var list = dismissed();
            if (list.indexOf(id) === -1) {
                list.push(id);
                sessionStorage.setItem(DISMISS_KEY, JSON.stringify(list));
            }
        } catch (e) { /* navegação privada pode bloquear: só não lembra */ }
    }

    function basePath() {
        var script = document.querySelector('script[src*="npsphere"][src*="pending-survey"]');
        if (!script) {
            return null;
        }
        // .../npsphere/js/pending-survey.js  =>  .../npsphere/
        return script.getAttribute('src').replace(/js\/pending-survey\.js.*$/, '');
    }

    function render(survey) {
        var overlay = document.createElement('div');
        overlay.className = 'npsphere-gate' + (survey.blocking ? ' npsphere-gate-blocking' : '');

        var deadline = '';
        if (survey.deadline) {
            var d = String(survey.deadline).substring(0, 10).split('-');
            deadline = d[2] + '/' + d[1] + '/' + d[0];
        }

        var html =
            '<div class="npsphere-gate-box">' +
            '<div class="npsphere-gate-icon"><i class="ti ti-clipboard-text"></i></div>' +
            '<h2>' + escapeHtml(survey.name) + '</h2>';

        if (survey.description) {
            html += '<p class="npsphere-gate-desc">' + escapeHtml(survey.description) + '</p>';
        }

        if (survey.blocking) {
            html += '<p class="npsphere-gate-warning">' +
                '<i class="ti ti-alert-triangle"></i> ' +
                'Hoje é o último dia para responder. É necessário responder para continuar.' +
                '</p>';
        } else if (deadline) {
            html += '<p class="npsphere-gate-desc">Você pode responder depois, até ' + deadline + '.</p>';
        }

        // A pesquisa é respondida AQUI mesmo, sem tirar a pessoa da
        // tela em que ela estava.
        html += '<div class="npsphere-gate-body">' +
                '<p class="text-muted">Carregando…</p>' +
                '</div>';

        if (!survey.blocking) {
            html += '<div class="npsphere-gate-actions">' +
                    '<button type="button" class="btn btn-link text-secondary npsphere-gate-later">Responder depois</button>' +
                    '</div>';
        }
        html += '</div>';

        overlay.innerHTML = html;
        document.body.appendChild(overlay);

        var later = overlay.querySelector('.npsphere-gate-later');
        if (later) {
            later.addEventListener('click', function () {
                dismiss(survey.id);
                overlay.remove();
            });
        }

        loadForm(overlay, survey);
    }

    /**
     * Carrega o formulário da pesquisa dentro do modal e intercepta o
     * envio, para tudo acontecer sem recarregar a página.
     */
    function loadForm(overlay, survey) {
        var base = basePath();
        var body = overlay.querySelector('.npsphere-gate-body');
        if (!base || !body) {
            return;
        }

        fetch(base + 'ajax/answer.embed.php?surveys_id=' + encodeURIComponent(survey.id), { method: 'GET' })
            .then(function (r) { return r.ok ? r.text() : null; })
            .then(function (formHtml) {
                if (!formHtml) {
                    body.innerHTML = '<p class="text-danger">Não foi possível carregar a pesquisa. ' +
                        '<a href="' + survey.url + '">Abrir em outra tela</a>.</p>';
                    return;
                }

                body.innerHTML = formHtml;

                var form = body.querySelector('form');
                if (form) {
                    // O formato configurado na pesquisa é RESPEITADO --
                    // antes eu forçava página única aqui, porque o
                    // script que faz a navegação por etapas só era
                    // carregado nas telas do plugin. O resultado era
                    // incoerente: a mesma pesquisa aparecia por etapas
                    // ao abrir pela tela de resposta, e numa página só
                    // no aviso pós-login.
                    bindSubmit(overlay, form, survey);
                    carregarBuilder();
                }
            })
            .catch(function () {
                body.innerHTML = '<p class="text-danger">Não foi possível carregar a pesquisa. ' +
                    '<a href="' + survey.url + '">Abrir em outra tela</a>.</p>';
            });
    }

    /**
     * Carrega sob demanda o script que faz a navegação por etapas, as
     * regras condicionais e os widgets. Ele não vem carregado nas
     * páginas comuns do GLPI, e o marcador evita carregá-lo duas vezes.
     */
    function carregarBuilder() {
        // Já carregado: basta inicializar o conteúdo recém-inserido.
        if (window.NPSPHERE_BUILDER_LOADED) {
            if (typeof window.NPSPHERE_INIT === 'function') {
                window.NPSPHERE_INIT(document);
            }
            return;
        }

        var base = basePath();
        if (!base) {
            return;
        }

        var script = document.createElement('script');
        script.src = base + 'js/survey-builder.js';

        // Inicializa EXPLICITAMENTE ao terminar de carregar.
        //
        // Antes eu dependia do próprio script se inicializar sozinho no
        // momento certo -- e era aí que falhava: o formulário do modal
        // já estava na tela quando o script chegava, então nem o evento
        // de carregamento da página nem o observador de DOM (que só vê
        // o que é inserido DEPOIS) o alcançavam. Resultado: a pesquisa
        // ignorava o formato por etapas e as regras condicionais.
        script.onload = function () {
            if (typeof window.NPSPHERE_INIT === 'function') {
                window.NPSPHERE_INIT(document);
            }
        };

        script.onerror = function () {
            // Sem o script, ao menos o envio continua funcionando --
            // o formulário fica em página única, que é o comportamento
            // antigo, em vez de uma tela quebrada.
        };

        document.body.appendChild(script);
    }

    function bindSubmit(overlay, form, survey) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var btn = form.querySelector('.npsphere-submit-btn');
            var errors = form.querySelector('.npsphere-gate-errors');
            if (btn) {
                btn.disabled = true;
            }

            // Busca um token CSRF NOVO imediatamente antes de enviar.
            // O token embutido no HTML pode já ter expirado -- o
            // formulário fica aberto enquanto a pessoa responde, e
            // esse atraso é justamente o caso mais comum. Foi a mesma
            // correção aplicada ao formulário público (v0.27.0) e ao
            // builder de perguntas (v0.13.0).
            var base = basePath();
            var tokenPromise = base
                ? fetch(base + 'ajax/csrf.token.php', { method: 'GET' })
                    .then(function (r) { return r.ok ? r.text() : null; })
                    .catch(function () { return null; })
                : Promise.resolve(null);

            tokenPromise.then(function (token) {
                if (token && token.trim() !== '') {
                    var field = form.querySelector('input[name="_glpi_csrf_token"]');
                    if (field) {
                        field.value = token.trim();
                    }
                }

                var data = new FormData(form);
                data.append('ajax', '1');
                data.append('submit_answer', '1');

                return fetch(form.action, { method: 'POST', body: data });
            })
                .then(function (r) {
                    if (!r) {
                        throw new Error('sem_resposta');
                    }
                    // 403/500: não é erro de preenchimento, é a
                    // requisição sendo recusada. Mostrar "verifique os
                    // campos" seria enganoso -- melhor abrir a pesquisa
                    // em tela cheia, onde o envio segue outro caminho.
                    if (!r.ok) {
                        throw new Error('http_' + r.status);
                    }
                    return r.json();
                })
                .then(function (res) {
                    if (btn) {
                        btn.disabled = false;
                    }

                    if (res && res.success) {
                        showThanks(overlay);
                        return;
                    }

                    var msgs = (res && res.errors && res.errors.length)
                        ? res.errors
                        : ['Não foi possível registrar sua resposta. Tente novamente.'];
                    if (errors) {
                        errors.style.display = '';
                        errors.innerHTML = '<div class="alert alert-danger"><ul class="mb-0">' +
                            msgs.map(function (m) { return '<li>' + escapeHtml(m) + '</li>'; }).join('') +
                            '</ul></div>';
                    }
                })
                .catch(function () {
                    if (btn) {
                        btn.disabled = false;
                    }
                    // Último recurso: abre a pesquisa em tela cheia, para
                    // a resposta não se perder por um erro de rede.
                    window.location.href = survey.url;
                });
        });
    }

    /**
     * Agradecimento e retorno à página inicial após 3 segundos.
     */
    function showThanks(overlay) {
        var box = overlay.querySelector('.npsphere-gate-box');
        if (!box) {
            return;
        }

        box.innerHTML =
            '<div class="npsphere-gate-icon npsphere-gate-icon-success"><i class="ti ti-circle-check"></i></div>' +
            '<h2>Obrigado pela sua avaliação!</h2>' +
            '<p class="npsphere-gate-desc">Sua resposta foi registrada com sucesso.</p>' +
            '<p class="npsphere-gate-countdown">Redirecionando em <strong>3</strong>…</p>';

        var seconds = 3;
        var label = box.querySelector('.npsphere-gate-countdown strong');

        var timer = setInterval(function () {
            seconds--;
            if (label) {
                label.textContent = String(seconds);
            }
            if (seconds <= 0) {
                clearInterval(timer);
                // Página inicial do GLPI: a raiz da instalação, deduzida
                // do caminho do próprio plugin.
                var base = basePath();
                window.location.href = base ? base.replace(/(marketplace|plugins)\/npsphere\/$/, '') : '/';
            }
        }, 1000);
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Substitui, dentro da pesquisa de satisfação NATIVA do GLPI, o
     * link para este plugin pelo próprio formulário.
     *
     * Como funciona: quando a entidade está configurada como "pesquisa
     * externa" apontando para o plugin, o GLPI mostra no chamado um
     * link "Responder à pesquisa". Em vez de mandar o usuário para
     * outra tela, localizamos esse link e trocamos o bloco dele pela
     * pesquisa, respondida ali mesmo.
     *
     * A troca é feita no DOM, e não por alteração do núcleo do GLPI:
     * o seletor "Pesquisa interna / Pesquisa externa" da entidade é um
     * campo do núcleo, com valores fixos -- não há como um plugin
     * acrescentar uma terceira opção sem modificar o GLPI, e a
     * modificação se perderia na próxima atualização.
     *
     * Se qualquer etapa falhar, o link original permanece intacto: um
     * caminho degradado é melhor do que nenhum caminho.
     */
    function embutirPesquisaDoChamado() {
        // Só no CHAMADO. O link da pesquisa aparece também em outras
        // telas -- a fila de notificação mostra o e-mail do convite, com
        // os links de cada nota -- e ali o formulário não deve entrar no
        // lugar do link.
        if (!/\/front\/ticket\.form\.php$/.test(window.location.pathname)) {
            return;
        }

        var base = basePath();
        if (!base) {
            return;
        }

        var PADRAO = 'npsphere/front/ticket.php';
        var link = null;
        var tickets_id = null;

        // O ID da PESQUISA também é extraído da URL configurada na
        // entidade. Sem ele, a montagem do iframe recriava o endereço
        // apenas com o chamado -- e o parâmetro que identifica QUAL
        // pesquisa usar se perdia, caindo sempre na resolução
        // automática por entidade.
        var surveys_id = null;

        // 1) A URL como LINK (<a href="...">)
        // Links das opções do e-mail (&pergunta=, &valor=) não são o link
        // da pesquisa externa do GLPI: ficam como estão.
        link = Array.prototype.find.call(
            document.querySelectorAll('a[href*="' + PADRAO + '"]'),
            function (a) { return !/[?&](pergunta|valor)=/.test(a.getAttribute('href') || ''); }
        ) || null;
        if (link) {
            var href = link.getAttribute('href') || '';
            var m = href.match(/tickets_id=(\d+)/);
            tickets_id = m ? m[1] : null;

            var ms = href.match(/surveys_id=(\d+)/);
            surveys_id = ms ? ms[1] : null;
        }

        // 2) A URL como TEXTO.
        //
        // Nem sempre o GLPI transforma a URL da pesquisa externa em
        // link -- em alguns casos ela é impressa como texto puro. Era
        // por isso que a substituição não acontecia: o seletor
        // procurava apenas por <a>.
        if (!tickets_id) {
            var caminhador = document.createTreeWalker(
                document.body,
                NodeFilter.SHOW_TEXT,
                {
                    acceptNode: function (node) {
                        return node.nodeValue && node.nodeValue.indexOf(PADRAO) !== -1
                            ? NodeFilter.FILTER_ACCEPT
                            : NodeFilter.FILTER_REJECT;
                    }
                }
            );

            var noTexto = caminhador.nextNode();
            if (noTexto) {
                var m2 = noTexto.nodeValue.match(/tickets_id=(\d+)/);
                if (m2) {
                    tickets_id = m2[1];
                    link = noTexto.parentElement;

                    var ms2 = noTexto.nodeValue.match(/surveys_id=(\d+)/);
                    surveys_id = ms2 ? ms2[1] : null;
                }
            }
        }

        if (!tickets_id || !link) {
            return;
        }

        // Escolha do elemento a substituir.
        //
        // A versão anterior subia até o .card-body mais próximo -- e
        // esse cartão, no chamado, é o do CHAMADO INTEIRO. O resultado
        // foi a pesquisa apagar todo o conteúdo da tela.
        //
        // Agora a subida é limitada por TAMANHO: só sobe enquanto o
        // elemento contiver pouca coisa além da própria URL. Assim
        // alcança o parágrafo e o rótulo que vêm com ela, e nunca um
        // container que abriga o resto do chamado.
        var textoUrl = (link.textContent || '').trim();
        var LIMITE = textoUrl.length + 120;

        var alvo = link;
        var pai = alvo.parentElement;

        while (
            pai
            && pai !== document.body
            && pai.tagName !== 'FORM'
            && (pai.textContent || '').trim().length <= LIMITE
        ) {
            alvo = pai;
            pai = alvo.parentElement;
        }

        // Rótulo "Pesquisa externa": removido apenas quando for um
        // irmão IMEDIATO do alvo e o texto casar exatamente -- nunca
        // varrendo a página, para não apagar títulos legítimos.
        var rotulo = /^(pesquisa externa|external survey|encuesta externa)$/i;
        [alvo.previousElementSibling, alvo.nextElementSibling].forEach(function (irmao) {
            if (irmao && rotulo.test((irmao.textContent || '').trim())) {
                irmao.remove();
            }
        });

        if (alvo.dataset && alvo.dataset.npsphereEmbutido === '1') {
            return;
        }

        // A pesquisa entra num IFRAME, e não injetada no DOM do
        // chamado.
        //
        // Injetando, os campos passavam a conviver com o restante da
        // página do GLPI: IDs colidiam, o observador de DOM
        // reprocessava o formulário a cada alteração e o JavaScript do
        // plugin concorria com o do GLPI. O resultado eram vários
        // cliques para marcar uma opção e a navegação por etapas sem
        // efeito. Dentro do iframe a pesquisa roda isolada -- é a mesma
        // página que funciona em tela cheia.
        var iframe = document.createElement('iframe');
        iframe.src = base + 'front/ticket.php?tickets_id='
            + encodeURIComponent(tickets_id)
            + (surveys_id ? '&surveys_id=' + encodeURIComponent(surveys_id) : '')
            + '&embed=1';
        iframe.className = 'npsphere-ticket-frame';
        iframe.setAttribute('title', 'Pesquisa de satisfação');
        iframe.setAttribute('scrolling', 'no');
        iframe.style.width = '100%';
        iframe.style.border = '0';
        iframe.style.minHeight = '320px';

        alvo.dataset.npsphereEmbutido = '1';
        alvo.innerHTML = '';
        alvo.appendChild(iframe);

        // A altura acompanha o conteúdo: a própria página embutida
        // informa quanto ocupa, inclusive ao trocar de etapa.
        if (!window.npsphereAlturaOuvindo) {
            window.npsphereAlturaOuvindo = true;
            window.addEventListener('message', function (evento) {
                var dado = evento.data;
                if (!dado || dado.npsphere !== 'altura') {
                    return;
                }
                var altura = parseInt(dado.valor, 10);
                if (!altura || altura < 1) {
                    return;
                }

                document.querySelectorAll('.npsphere-ticket-frame').forEach(function (f) {
                    // Altura EXATA, sem folga somada.
                    //
                    // A folga de 20px que eu havia acrescentado
                    // realimentava o cálculo: o iframe ficava maior, o
                    // conteúdo media mais, e a altura crescia sem
                    // parar -- era a barra "crescendo sozinha".
                    var novo = altura + 'px';
                    if (f.style.height !== novo) {
                        f.style.height = novo;
                    }
                });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        embutirPesquisaDoChamado();

        // O GLPI carrega o conteúdo das abas por AJAX, então o link
        // pode aparecer depois do carregamento inicial da página.
        var observador = new MutationObserver(function () {
            embutirPesquisaDoChamado();
        });
        observador.observe(document.body, { childList: true, subtree: true });

        // Nunca mostrar o aviso em cima da própria pesquisa: seria um
        // laço -- a pessoa abriria a pesquisa e o aviso apareceria de
        // novo por cima dela.
        if (/npsphere\/front\/(answer|public)\.form\.php/.test(window.location.pathname)) {
            return;
        }

        var base = basePath();
        if (!base) {
            return;
        }

        fetch(base + 'ajax/pending.php', { method: 'GET' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.surveys || !data.surveys.length) {
                    return;
                }

                var skipped = dismissed();
                for (var i = 0; i < data.surveys.length; i++) {
                    var s = data.surveys[i];
                    // Uma pesquisa que bloqueia ignora o adiamento
                    // anterior: no último dia não há mais como escapar.
                    if (s.blocking || skipped.indexOf(s.id) === -1) {
                        render(s);
                        return;
                    }
                }
            })
            .catch(function () { /* silencioso: nunca atrapalhar o uso do GLPI */ });
    });
})();
