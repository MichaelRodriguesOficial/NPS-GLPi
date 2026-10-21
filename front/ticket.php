<?php

/**
 * Pesquisa de satisfação vinculada a um chamado (Fase 8).
 *
 * Este é o destino da "pesquisa externa" do GLPI: em
 * Entidade > Assistência, configura-se a URL
 *
 *   <glpi>/plugins/npsphere/front/ticket.php?tickets_id=[TICKET_ID]
 *
 * O GLPI substitui a tag [TICKET_ID] e envia o requerente para cá
 * quando o chamado é encerrado (respeitando o atraso e o percentual
 * configurados na entidade). Assim o plugin aproveita o gatilho, o
 * prazo e a notificação nativos, em vez de competir com eles.
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\ResponseService;
use GlpiPlugin\Npsphere\Survey;
use GlpiPlugin\Npsphere\TicketLink;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

Session::checkLoginUser();

// Modo EMBUTIDO: a página é exibida dentro de um <iframe>, no lugar do
// link da pesquisa externa, dentro do chamado.
//
// Por que um iframe, e não injeção no DOM do chamado: injetando, os
// campos passam a conviver com o restante da página do GLPI -- IDs
// colidem, o JavaScript do plugin concorre com o do GLPI e o
// observador de DOM reprocessa o formulário a cada alteração. Foi essa
// convivência que exigia vários cliques para marcar uma opção e
// impedia a navegação por etapas. Dentro do iframe a pesquisa roda
// isolada, exatamente como na tela cheia -- onde sempre funcionou.
$is_embed = !empty($_GET['embed']);

$is_helpdesk = (Session::getCurrentInterface() === 'helpdesk');

$abrir_pagina = static function () use ($is_helpdesk, $is_embed): void {
    if ($is_embed) {
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<link rel="stylesheet" href="'
            . Plugin::getWebDir('npsphere', true) . '/css/npsphere.css">'
            . '<style>html,body{margin:0;padding:0;background:transparent;}</style>'
            . '</head><body class="npsphere-embed">'
            // Container próprio: a altura é medida NELE, e não no body.
            // Medir o body criava um laço -- o pai aumentava o iframe,
            // o body crescia junto, e a altura subia indefinidamente.
            . '<div id="npsphere-embed-root">';
        return;
    }

    if ($is_helpdesk) {
        Html::helpHeader(__('Pesquisa de satisfação', 'npsphere'));
        return;
    }
    Html::header(__('Pesquisa de satisfação', 'npsphere'), $_SERVER['PHP_SELF'], 'helpdesk');
};

$fechar_pagina = static function () use ($is_helpdesk, $is_embed): void {
    if ($is_embed) {
        // Informa a altura ao chamado, para o iframe acompanhar o
        // conteúdo (inclusive ao trocar de etapa).
        echo '</div>';
        echo '<script src="' . Plugin::getWebDir('npsphere', true) . '/js/survey-builder.js"></script>';
        echo '<script>
(function () {
    // Herda os estilos da página que embute esta.
    //
    // Sozinho, o iframe só teria o CSS do plugin -- e ele é construído
    // sobre o tema do GLPI (botões, campos, cores). Sem o tema, a
    // pesquisa aparecia sem formatação nenhuma. Como as duas páginas
    // são do mesmo domínio, basta copiar as folhas de estilo do pai.
    try {
        if (window.parent && window.parent !== window) {
            var atuais = {};
            document.querySelectorAll("link[rel=stylesheet]").forEach(function (l) {
                atuais[l.href] = true;
            });

            window.parent.document.querySelectorAll("link[rel=stylesheet]").forEach(function (l) {
                if (atuais[l.href]) {
                    return;
                }
                var copia = document.createElement("link");
                copia.rel = "stylesheet";
                copia.href = l.href;
                document.head.appendChild(copia);
            });

            // Alguns temas definem as variáveis de cor num <style> no
            // cabeçalho, e não num arquivo .css -- esses também vêm.
            window.parent.document.querySelectorAll("head style").forEach(function (st) {
                var copia = document.createElement("style");
                copia.textContent = st.textContent;
                document.head.appendChild(copia);
            });

            // Copia os ATRIBUTOS de <html> e <body> do chamado.
            //
            // Só as folhas de estilo não bastam: o GLPI seleciona a
            // paleta por atributos e classes no documento (tema claro
            // ou escuro, paleta ativa). Sem eles, as variáveis de cor
            // caem nos valores padrão -- e um botão "btn-primary"
            // aparecia amarelo dentro do chamado enquanto era vermelho
            // no resto do sistema.
            ["class", "data-bs-theme", "data-glpi-theme", "data-theme", "lang", "dir"]
                .forEach(function (attr) {
                    var valor = window.parent.document.documentElement.getAttribute(attr);
                    if (valor) {
                        document.documentElement.setAttribute(attr, valor);
                    }
                });

            var corpoPai = window.parent.document.body;
            if (corpoPai) {
                corpoPai.getAttributeNames().forEach(function (attr) {
                    if (attr === "class" || attr.indexOf("data-") === 0) {
                        var valor = corpoPai.getAttribute(attr);
                        if (!valor) {
                            return;
                        }
                        if (attr === "class") {
                            // Acrescenta às classes próprias, sem
                            // substituí-las -- npsphere-embed precisa
                            // permanecer.
                            valor.split(/\s+/).forEach(function (c) {
                                if (c) {
                                    document.body.classList.add(c);
                                }
                            });
                            return;
                        }
                        document.body.setAttribute(attr, valor);
                    }
                });
            }

            // Neutraliza o fundo DEPOIS das folhas copiadas.
            //
            // O tema do GLPI pinta o corpo da página de cinza. Dentro
            // do iframe isso vira uma moldura cinza em volta da
            // pesquisa, visível sobretudo em telas largas, onde sobra
            // espaço. Este ajuste precisa vir por último para prevalecer
            // sobre o tema.
            var ajuste = document.createElement("style");
            ajuste.textContent =
                "html,body{background:transparent !important;margin:0;padding:0;}"
                // Centraliza e limita a largura: sem isso o formulário
                // acompanha a largura inteira do chamado e, em telas
                // grandes, as opções ficam esparramadas e coladas à
                // esquerda. A medida acompanha a da tela cheia, onde a
                // pesquisa já era legível.
                + "#npsphere-embed-root{background:transparent;max-width:720px;margin:0 auto;padding:0 1rem;}"
                + ".npsphere-embed .npsphere-answer-page{padding:0;margin:0;}";
            document.head.appendChild(ajuste);
        }
    } catch (e) {
        // Outro domínio: segue apenas com o CSS do plugin.
    }

    var raiz = document.getElementById("npsphere-embed-root");
    var ultima = 0;

    function avisarAltura() {
        if (!raiz) {
            return;
        }

        var altura = raiz.getBoundingClientRect().height;

        // Só avisa quando muda de verdade. Reenviar a mesma altura
        // fazia o pai reajustar o iframe sem parar.
        if (Math.abs(altura - ultima) < 2) {
            return;
        }

        ultima = altura;
        parent.postMessage({ npsphere: "altura", valor: Math.ceil(altura) }, "*");
    }

    window.addEventListener("load", avisarAltura);
    document.addEventListener("click", function () { setTimeout(avisarAltura, 80); });

    // Acompanha mudanças de conteúdo (troca de etapa, mensagens de erro)
    if (window.ResizeObserver && raiz) {
        new ResizeObserver(avisarAltura).observe(raiz);
    } else {
        setInterval(avisarAltura, 500);
    }
})();
        </script>';
        echo '</body></html>';
        return;
    }

    if ($is_helpdesk) {
        Html::helpFooter();
        return;
    }
    Html::footer();
};

$mensagem = static function (string $texto, ?Survey $survey = null) use ($abrir_pagina, $fechar_pagina): void {
    $abrir_pagina();
    TemplateRenderer::getInstance()->display('@npsphere/answer/blocked.html.twig', [
        'survey' => $survey,
        'reason' => $texto,
    ]);
    $fechar_pagina();
};

$tickets_id = (int) ($_GET['tickets_id'] ?? $_POST['tickets_id'] ?? 0);

// ID da pesquisa vindo da URL configurada na entidade.
//
// Opcional: sem ele, a pesquisa é resolvida pela entidade do chamado.
// Com ele, cada entidade aponta explicitamente para a sua pesquisa --
// necessário quando existe mais de uma pesquisa do tipo chamado.
$surveys_id_url = (int) ($_GET['surveys_id'] ?? $_POST['surveys_id'] ?? 0);

$ticket = new Ticket();
if ($tickets_id <= 0 || !$ticket->getFromDB($tickets_id)) {
    $mensagem(__('Chamado não encontrado.', 'npsphere'));
    return;
}

// Só o requerente avalia o próprio atendimento.
if (!TicketLink::podeResponder($ticket)) {
    $mensagem(__('Apenas o requerente do chamado pode responder esta pesquisa.', 'npsphere'));
    return;
}

$survey = TicketLink::resolveSurvey((int) $ticket->fields['entities_id'], $surveys_id_url);
if ($survey === null) {
    $abrir_pagina();

    echo "<div class='alert alert-warning'>"
        . __('Não há pesquisa de satisfação configurada para este chamado.', 'npsphere')
        . "</div>";

    // Diagnóstico para quem administra: a mesma tela aparece por
    // várias causas diferentes (entidade divergente, recursividade
    // desmarcada, pesquisa inativa ou fora do prazo). Exibido apenas a
    // quem pode gerenciar pesquisas -- para o requerente comum seria
    // ruído sem utilidade.
    if (Session::haveRight(Survey::$rightname, READ)) {
        $d = TicketLink::diagnosticar((int) $ticket->fields['entities_id']);

        echo "<div class='alert alert-info'><strong>" . __('Diagnóstico', 'npsphere') . "</strong>";
        echo "<p>" . __('Entidade do chamado', 'npsphere') . ": <b>#{$d['entidade_do_chamado']}</b>";
        if (!empty($d['ancestrais'])) {
            echo ' · ' . __('entidades-pai', 'npsphere') . ': ' . implode(', ', $d['ancestrais']);
        }
        echo "</p>";

        if (empty($d['pesquisas'])) {
            echo "<p>" . __('Nenhuma pesquisa do tipo "Vinculada a chamado" foi criada ainda.', 'npsphere') . "</p>";
        } else {
            echo "<table class='table table-sm'><thead><tr>"
                . "<th>" . __('Pesquisa', 'npsphere') . "</th>"
                . "<th>" . __('Entidade', 'npsphere') . "</th>"
                . "<th>" . __('Recursiva', 'npsphere') . "</th>"
                . "<th>" . __('Ativa', 'npsphere') . "</th>"
                . "<th>" . __('No prazo', 'npsphere') . "</th>"
                . "<th>" . __('Serve a este chamado', 'npsphere') . "</th>"
                . "</tr></thead><tbody>";

            foreach ($d['pesquisas'] as $p) {
                $sim = static fn(bool $v): string => $v
                    ? "<span class='text-success'>" . __('sim', 'npsphere') . "</span>"
                    : "<span class='text-danger'>" . __('não', 'npsphere') . "</span>";

                echo '<tr>'
                    . '<td>' . htmlspecialchars((string) $p['nome']) . ' (#' . $p['id'] . ')</td>'
                    . '<td>#' . $p['entidade_pesquisa'] . '</td>'
                    . '<td>' . $sim($p['recursiva']) . '</td>'
                    . '<td>' . $sim($p['ativa'] && !$p['excluida']) . '</td>'
                    . '<td>' . $sim($p['dentro_do_prazo']) . '</td>'
                    . '<td>' . $sim($p['aplica_a_entidade']) . '</td>'
                    . '</tr>';
            }
            echo '</tbody></table>';
            echo "<p class='mb-0'>" . __('Para a pesquisa valer em entidades-filhas, marque "Recursiva" nela.', 'npsphere') . "</p>";
        }
        echo "</div>";
    }

    $fechar_pagina();
    return;
}

$surveys_id_resolvido = $survey->getID();

// Opção clicada no e-mail (link com &pergunta=...&valor=...).
$clicado = $is_embed ? [] : Question::getPreenchimentoDoLink($surveys_id_resolvido, $_GET);

// Avaliação já existente deste chamado: um novo clique no e-mail, ou o
// envio do formulário que completa a nota gravada no clique, TROCA esta
// mesma avaliação -- continua sendo uma por chamado.
$existente = ResponseService::getRespostaDoChamado($tickets_id);

// Uma avaliação por chamado -- mesmo comportamento da pesquisa nativa.
// (Exceto com clique no e-mail ou envio: aí a avaliação é trocada.)
if ($existente > 0 && $clicado === [] && !isset($_POST['submit_answer'])) {
    // Mesma tela de agradecimento do envio, e não um aviso seco: para
    // quem responde, "já avaliado" e "acabei de avaliar" são a mesma
    // situação -- a avaliação está registrada.
    $abrir_pagina();
    TemplateRenderer::getInstance()->display('@npsphere/answer/thankyou.html.twig', [
        'survey'        => $survey,
        'ja_respondida' => true,
        'is_embed'      => $is_embed,
    ]);
    $fechar_pagina();
    return;
}

$surveys_id = $survey->getID();

if (isset($_POST['submit_answer'])) {
    $started_at = (int) ($_POST['_started_at'] ?? time());

    // tickets_id entra no POST para ser gravado junto da resposta: é o
    // que permite, depois, cruzar satisfação com técnico, categoria e
    // tempo de atendimento.
    $post = $_POST;
    $post['tickets_id'] = $tickets_id;

    $result = ResponseService::submit(
        $survey,
        $post,
        $started_at,
        [],
        $existente > 0 ? ['answers_id' => $existente] : []
    );

    if ($result['success']) {
        $abrir_pagina();
        TemplateRenderer::getInstance()->display('@npsphere/answer/thankyou.html.twig', [
            'survey'   => $survey,
            'is_embed' => $is_embed,
        ]);
        $fechar_pagina();
        return;
    }

    $abrir_pagina();
    TemplateRenderer::getInstance()->display('@npsphere/answer/form.html.twig', [
        'survey'     => $survey,
        'fields'     => Question::renderAnswerFields($surveys_id, $_POST),
        'errors'     => $result['errors'],
        'started_at' => time(),
        'tickets_id' => $tickets_id,
        'js_url'     => Plugin::getWebDir('npsphere', true) . '/js/survey-builder.js',
        'css_url'    => Plugin::getWebDir('npsphere', true) . '/css/npsphere.css',
    ]);
    $fechar_pagina();
    return;
}

// Opção clicada no E-MAIL: a página grava sozinha.
//
// O GET (abrir o link) NÃO grava: filtros de segurança de e-mail
// (Defender, Proofpoint, antivírus corporativos) abrem os links das
// mensagens para verificá-los, antes de a pessoa ler. Quem grava é o
// script da página, no navegador do requerente logado, com um POST para
// ajax/answer.oneclick.php.
$preenchido = $clicado + ($existente > 0 && $clicado !== []
    ? \GlpiPlugin\Npsphere\Invite::getRespostasGravadas($existente, $surveys_id)
    : []);

$um_clique = null;
if ($clicado !== []) {
    $um_clique = [
        'url'        => Plugin::getWebDir('npsphere', true) . '/ajax/answer.oneclick.php',
        'csrf_url'   => Plugin::getWebDir('npsphere', true) . '/ajax/csrf.token.php',
        'surveys_id' => $surveys_id_url,
        'tickets_id' => $tickets_id,
        'pergunta'   => (int) ($_GET['pergunta'] ?? 0),
        'valor'      => (string) ($_GET['valor'] ?? ''),
    ];
}

$abrir_pagina();
TemplateRenderer::getInstance()->display('@npsphere/answer/form.html.twig', [
    'survey'     => $survey,
    'fields'     => Question::renderAnswerFields($surveys_id, $preenchido),
    'errors'     => [],
    'started_at' => time(),
    'tickets_id' => $tickets_id,
    'surveys_id_url' => $surveys_id_url,
    'is_embed'   => $is_embed,
    'um_clique'  => $um_clique,
]);
$fechar_pagina();
