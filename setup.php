<?php

/**
 * -------------------------------------------------------------------------
 * NPSphere plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * Pesquisas de Satisfação — plataforma de pesquisas (NPS e outras)
 * integrada ao GLPI 11.
 *
 * @package   npsphere
 * -------------------------------------------------------------------------
 */

// Não permitir acesso direto ao arquivo fora do bootstrap do GLPI
if (!defined('GLPI_ROOT')) {
    die('Sorry. You can not access directly to this file');
}

define('PLUGIN_NPSPHERE_VERSION', '0.65.0');

// Versão mínima do GLPI suportada (inclusive)
define('PLUGIN_NPSPHERE_MIN_GLPI_VERSION', '11.0.0');
// Versão máxima do GLPI suportada (exclusive)
define('PLUGIN_NPSPHERE_MAX_GLPI_VERSION', '11.99.99');

/**
 * Função de inicialização do plugin.
 * Chamada em (quase) todas as páginas do GLPI.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_npsphere()
{
    global $PLUGIN_HOOKS;

    // ---------------------------------------------------------------
    // OBRIGATÓRIO: declara que o plugin trata corretamente o token CSRF
    // nos seus próprios formulários (front/*.php e ajax/*.php, via o
    // campo _glpi_csrf_token + csrf_token() nos templates Twig). Sem
    // este hook, o GLPI considera o plugin "não compatível com CSRF" e
    // passa a rejeitar (403) os POSTs enviados pelas suas páginas.
    // ---------------------------------------------------------------
    $PLUGIN_HOOKS['csrf_compliant']['npsphere'] = true;

    // ---------------------------------------------------------------
    // Acesso público (link sem login) -- mecanismo OFICIAL do GLPI 11.
    //
    // Por padrão, todo script PHP de plugin só é acessível por usuários
    // autenticados. A documentação de upgrade para o GLPI 11.0 define
    // Firewall::addPluginStrategyForLegacyScripts() como a forma de
    // alterar essa política, e STRATEGY_NO_CHECK como a estratégia que
    // permite acesso a qualquer um, inclusive não autenticado.
    //
    // A liberação é DELIBERADAMENTE restrita a estes dois scripts (a
    // expressão regular é ancorada com ^ e $): a página pública de
    // resposta e o endpoint que grava essa resposta. Nenhuma outra tela
    // do plugin fica exposta. A autorização de verdade acontece dentro
    // deles, pelo token da pesquisa.
    // ---------------------------------------------------------------
    \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
        'npsphere',
        '#^/front/public.php$#',
        \Glpi\Http\Firewall::STRATEGY_NO_CHECK
    );
    \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
        'npsphere',
        '#^/ajax/public.submit.php$#',
        \Glpi\Http\Firewall::STRATEGY_NO_CHECK
    );
    \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
        'npsphere',
        '#^/ajax/public.identity.check.php$#',
        \Glpi\Http\Firewall::STRATEGY_NO_CHECK
    );
    \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
        'npsphere',
        '#^/ajax/public.token.php$#',
        \Glpi\Http\Firewall::STRATEGY_NO_CHECK
    );
    // Grava a opção clicada no e-mail (convite pessoal). A autorização
    // é pelo token da pesquisa + código do convite, dentro do script.
    \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
        'npsphere',
        '#^/ajax/public.oneclick.php$#',
        \Glpi\Http\Firewall::STRATEGY_NO_CHECK
    );
    // Avaliação do chamado pelo link do e-mail, sem login. Autorizada
    // pela chave assinada do chamado (TicketLink::resolverPorChave).
    // Mesmo esquema para a pesquisa INTERNA (chave da pessoa + pesquisa).
    foreach ([
        '/front/ticket.answer.php', '/ajax/ticket.oneclick.php', '/ajax/ticket.token.php',
        '/front/internal.answer.php', '/ajax/internal.oneclick.php', '/ajax/internal.token.php',
        // v0.64.1: o pending-survey.js roda em todas as páginas, inclusive
        // nas de resposta sem login. Com a checagem padrão, o GLPI
        // redirecionava o visitante para a tela de login, que limpa a
        // sessão -- e levava junto o token CSRF que o clique do e-mail
        // estava para usar (a resposta não era gravada). O script já
        // devolve lista vazia para quem não está logado.
        '/ajax/pending.php',
    ] as $script) {
        \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
            'npsphere',
            '#^' . preg_quote($script, '#') . '$#',
            \Glpi\Http\Firewall::STRATEGY_NO_CHECK
        );
    }

    // ---------------------------------------------------------------
    // Menu principal do plugin (dentro de Administração)
    // ---------------------------------------------------------------
    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::MENU_TOADD]['npsphere'] = [
        'admin' => \GlpiPlugin\Npsphere\Survey::class,
    ];

    // "Minhas pesquisas": disponível para qualquer usuário autenticado,
    // em Ferramentas -- o menu principal do plugin fica em Administração
    // e exige direito administrativo, que o respondente não tem.
    // Painel consolidado (Fase 10) na seção GESTÃO.
    //
    // O hook aceita uma entrada por seção: "admin" já é a gestão de
    // pesquisas e "tools" é "Minhas pesquisas". Registrar uma segunda
    // chave numa seção ocupada não gera erro -- o item apenas não
    // aparece, que foi o que aconteceu nas tentativas anteriores.
    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::MENU_TOADD]['npsphere']['management'] =
        \GlpiPlugin\Npsphere\DashboardMenu::class;

    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::MENU_TOADD]['npsphere']['tools'] =
        \GlpiPlugin\Npsphere\MySurveysMenu::class;

    // A interface SIMPLIFICADA (autoatendimento) NÃO usa o menu acima:
    // ela tem um hook próprio. Sem isto, a tela existia mas era
    // inalcançável justamente para o perfil que mais responde pesquisa.
    //
    // O hook apenas insere o link -- ele não avalia permissão sozinho,
    // por isso a verificação do direito é feita aqui.
    // Registro CONDICIONAL: sem o direito, o plugin nem aparece no menu
    // "Plugins" do autoatendimento.
    //
    // Isto só é possível agora porque hasAnswerRight() consulta o BANCO
    // quando o direito não está na sessão. Uma tentativa anterior usava
    // Session::haveRight() aqui e falhava sempre: plugin_init roda cedo
    // demais no ciclo da requisição, quando a sessão ainda pode não ter
    // os direitos do perfil -- e o item nunca aparecia, mesmo com a
    // permissão concedida.
    if (\GlpiPlugin\Npsphere\MySurveysMenu::hasAnswerRight()) {
        $PLUGIN_HOOKS['helpdesk_menu_entry']['npsphere'] = '/front/mysurveys.php';
        $PLUGIN_HOOKS['helpdesk_menu_entry_icon']['npsphere'] = 'ti ti-clipboard-check';
        $PLUGIN_HOOKS['redefine_menus']['npsphere'] = 'plugin_npsphere_redefine_menus';
    }

    // NÃO registramos uma aba própria no chamado.
    //
    // Chegou a existir uma, e o resultado foi a pesquisa renderizada
    // DUAS vezes na mesma página (a aba e a substituição do link). Como
    // as duas cópias têm os mesmos IDs de campo, os cliques iam para a
    // cópia errada e a navegação por etapas se aplicava a uma só --
    // sintomas que nenhuma limpeza no navegador resolveu de forma
    // confiável.
    //
    // A pesquisa aparece em UM lugar: no ponto onde o GLPI mostraria o
    // link da pesquisa externa, dentro do chamado.

    // Ícone do menu (tabler-icons)
    $PLUGIN_HOOKS['menu_icon']['npsphere'] = 'ti ti-mood-smile-beam';

    // ---------------------------------------------------------------
    // Aba de permissões dentro do formulário de Perfil (Profile)
    // ---------------------------------------------------------------
    \Plugin::registerClass(\GlpiPlugin\Npsphere\Profile::class, [
        'addtabon' => \Profile::class,
    ]);

    // Pesquisa como tipo de modelo de notificação: é o que faz o modelo
    // "Convite de pesquisa" aparecer em Configurar › Notificações ›
    // Modelos, com os marcadores de NotificationTargetSurvey.
    \Plugin::registerClass(\GlpiPlugin\Npsphere\Survey::class, [
        'notificationtemplates_types' => true,
    ]);

    // ---------------------------------------------------------------
    // Botão "Configurar" na tela Configurar > Plugins. Leva direto para
    // a listagem de pesquisas (não há ainda uma tela de configurações
    // gerais separada -- ver seção 4.9 do escopo, prevista para uma
    // próxima fase).
    // ---------------------------------------------------------------
    // -----------------------------------------------------------------
    // Notificação de satisfação (Fase 9)
    // -----------------------------------------------------------------
    // Dois ganchos, um para cada lado da troca:
    //
    //  1. quando o GLPI cria a satisfação do chamado, enviamos o NOSSO
    //     convite -- assim herdamos o gatilho, o atraso e o percentual
    //     de chamados já configurados na entidade;
    //  2. quando o GLPI vai enfileirar a notificação NATIVA daquele
    //     mesmo chamado, ela é cancelada -- do contrário a pessoa
    //     receberia dois e-mails.
    //
    // Ambos consultam a MESMA regra (MailPolicy). Com a pesquisa do
    // plugin inativa, ou a entidade em "Pesquisa interna", nenhum dos
    // dois age e tudo volta a ser do GLPI.
    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ITEM_ADD]['npsphere'] = [
        'TicketSatisfaction' => 'plugin_npsphere_satisfacao_criada',
    ];

    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::PRE_ITEM_ADD]['npsphere'] = [
        'QueuedNotification' => 'plugin_npsphere_antes_de_enfileirar',
    ];

    $PLUGIN_HOOKS['config_page']['npsphere'] = 'front/survey.php';

    // ---------------------------------------------------------------
    // CSS do plugin (carregado em todas as páginas do GLPI)
    // ---------------------------------------------------------------
    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_CSS]['npsphere'] = 'css/npsphere.css';

    // ---------------------------------------------------------------
    // JS do construtor de pesquisas (builder), carregado apenas nas
    // telas do próprio plugin para não pesar o restante do GLPI.
    // Verifica apenas "npsphere/" (não um prefixo fixo como
    // "/plugins/npsphere/"), pois a URL pública pode variar conforme a
    // instalação (ex.: "/marketplace/npsphere/" em vez de
    // "/plugins/npsphere/").
    // ---------------------------------------------------------------
    // pending-survey.js precisa rodar em TODAS as páginas: é ele que
    // intercepta o usuário logo após o login quando há uma pesquisa
    // obrigatória pendente. É pequeno e sai cedo quando não há nada
    // pendente.
    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['npsphere'] = [
        'js/pending-survey.js',
    ];

    // Já o builder só é necessário nas telas do próprio plugin.
    if (strpos($_SERVER['REQUEST_URI'] ?? '', 'npsphere/') !== false) {
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['npsphere'][] = 'js/survey-builder.js';
    }

    // v0.65.0: textos dos JS traduzidos (ver src/JsI18n.php). Fica no
    // post_init porque as traduções do plugin só são carregadas depois
    // desta função.
    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::POST_INIT]['npsphere'] = 'plugin_npsphere_post_init';
}

/**
 * Depois de iniciar todos os plugins (traduções já carregadas).
 */
function plugin_npsphere_post_init(): void
{
    \GlpiPlugin\Npsphere\JsI18n::registrarCabecalho();
}

/**
 * Nome, versão e pré-requisitos do plugin.
 * REQUIRED
 *
 * @return array
 */
function plugin_version_npsphere()
{
    // O menu "Plugins" do autoatendimento rotula a entrada com o NOME do
    // plugin -- não há hook para mudar só esse rótulo. Como a tela de
    // Plugins (onde "NPSphere" faz sentido) existe apenas na interface
    // padrão, devolver um nome diferente no autoatendimento é seguro:
    // são contextos que nunca se cruzam, e o usuário de lá vê o nome da
    // tela para onde o link leva.
    $is_helpdesk = isset($_SESSION['glpiactiveprofile']['interface'])
        && $_SESSION['glpiactiveprofile']['interface'] === 'helpdesk';

    return [
        'name'           => $is_helpdesk ? __('Minhas pesquisas', 'npsphere') : 'NPSphere',
        'version'        => PLUGIN_NPSPHERE_VERSION,
        'author'         => 'Michael Rodrigues',
        'license'        => 'GPL v3+',
        'homepage'       => 'https://github.com/MichaelRodriguesOficial/NPS-GLPi',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_NPSPHERE_MIN_GLPI_VERSION,
                'max' => PLUGIN_NPSPHERE_MAX_GLPI_VERSION,
            ],
        ],
    ];
}

/**
 * Verificação de pré-requisitos / configuração mínima do plugin.
 * Chamada em (quase) todas as páginas do GLPI.
 * REQUIRED
 *
 * @param boolean $verbose Exibir mensagens de erro
 *
 * @return boolean
 */
function plugin_npsphere_check_prerequisites()
{
    if (version_compare(PHP_VERSION, '8.1.0', '<')) {
        echo sprintf(__('Este plugin requer PHP >= %s', 'npsphere'), '8.1');
        return false;
    }
    return true;
}

/**
 * Verificação de configuração do plugin (após instalado).
 * Chamada em (quase) todas as páginas do GLPI.
 *
 * @param boolean $verbose Exibir mensagens de erro
 *
 * @return boolean
 */
function plugin_npsphere_check_config($verbose = false)
{
    return true;
}

/**
 * Renomeia o item do plugin no menu do autoatendimento.
 *
 * O GLPI usa o nome do plugin como título da entrada criada por
 * helpdesk_menu_entry; "Minhas pesquisas" é bem mais claro para quem
 * vai responder. O nome na tela de Plugins continua NPSphere.
 */
function plugin_npsphere_redefine_menus($menu)
{
    if (!is_array($menu) || !isset($menu['npsphere'])) {
        return $menu;
    }

    // Este é o ponto certo para checar permissão: o menu é montado com
    // a sessão já completa. Sem o direito, o item é removido.
    if (!\GlpiPlugin\Npsphere\MySurveysMenu::hasAnswerRight()) {
        unset($menu['npsphere']);
        return $menu;
    }

    // O helpdesk rotula a entrada com o NOME DO PLUGIN ("NPSphere"),
    // que não diz nada para quem só quer responder.
    $menu['npsphere']['title'] = __('Minhas pesquisas', 'npsphere');

    return $menu;
}

/**
 * O GLPI criou a pesquisa de satisfação de um chamado.
 *
 * Envia o convite do plugin, quando a regra disser que a notificação é
 * nossa. Falhas são registradas no log e NÃO interrompem o GLPI: um
 * problema no e-mail não pode impedir a criação da satisfação.
 */
function plugin_npsphere_satisfacao_criada($item)
{
    if (!is_object($item) || empty($item->fields['tickets_id'])) {
        return $item;
    }

    $tickets_id = (int) $item->fields['tickets_id'];

    try {
        $survey = \GlpiPlugin\Npsphere\MailPolicy::getPesquisaAplicavel($tickets_id);

        if ($survey !== null) {
            // v0.63.0: o e-mail espera alguns minutos (sub-aba Convite):
            // a pesquisa já aparece no chamado, e se o requerente avaliar
            // ali o e-mail não sai. Espera 0 = envia na hora, como antes.
            $espera = \GlpiPlugin\Npsphere\Delivery::getEsperaConvite($survey);
            if ($espera > 0) {
                \GlpiPlugin\Npsphere\Delivery::agendarConvite($survey, $tickets_id, $espera);
            } else {
                \GlpiPlugin\Npsphere\SurveyMailer::enviar($survey, $tickets_id);
            }
        }
    } catch (\Throwable $e) {
        if (class_exists('\\Toolbox') && method_exists('\\Toolbox', 'logInFile')) {
            \Toolbox::logInFile('npsphere', '[FALHA] convite: ' . $e->getMessage() . "\n");
        }
    }

    return $item;
}

/**
 * O GLPI vai enfileirar uma notificação.
 *
 * Cancela a notificação NATIVA de satisfação quando o convite é do
 * plugin -- sem isso a pessoa receberia dois e-mails pedindo a mesma
 * avaliação.
 *
 * Cancelar é feito zerando o input, que é a forma do GLPI abortar uma
 * inserção a partir de um gancho.
 */
function plugin_npsphere_antes_de_enfileirar($item)
{
    if (!is_object($item) || !is_array($item->input ?? null)) {
        return $item;
    }

    $entrada = $item->input;

    if (($entrada['itemtype'] ?? '') !== 'Ticket' || empty($entrada['items_id'])) {
        return $item;
    }

    // Só as notificações de SATISFAÇÃO são canceladas. As demais do
    // chamado (nova mensagem, encerramento, aprovação) seguem normais.
    $evento = (string) ($entrada['event'] ?? '');
    if (!str_contains($evento, 'satisfaction')) {
        return $item;
    }

    try {
        if (\GlpiPlugin\Npsphere\MailPolicy::usarNotificacaoDoPlugin((int) $entrada['items_id'])) {
            // A notificação nativa entra na fila JÁ NA LIXEIRA: o envio
            // só pega o que não está excluído, então ela nunca sai -- e a
            // limpeza automática do GLPI (queuednotificationclean) a
            // apaga depois, como faz com as já enviadas.
            //
            // Antes o cancelamento era zerando o input. Funcionava, mas o
            // GLPI entende isso como falha ao inserir e mostrava
            // "Erro ao inserir e-mail na fila" a quem aprovava a solução.
            $item->input['is_deleted'] = 1;
        }
    } catch (\Throwable $e) {
        // Na dúvida, deixa passar: um e-mail a mais é melhor do que
        // nenhum aviso de pesquisa.
    }

    return $item;
}
