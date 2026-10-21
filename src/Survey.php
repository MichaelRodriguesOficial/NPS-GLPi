<?php

namespace GlpiPlugin\Npsphere;

use CommonDBTM;
use Glpi\Application\View\TemplateRenderer;
use Log;
use Notepad;
use Session;

/**
 * Itemtype principal do plugin: uma Pesquisa de Satisfação.
 *
 * Fase 1 (fundação): cadastro, entidades, permissões, CRUD básico.
 * As perguntas, público-alvo, regras e respostas serão modeladas em
 * classes próprias nas próximas fases (ver README do plugin).
 */
class Survey extends CommonDBTM
{
    /** Nome do direito usado pelo sistema de permissões do GLPI */
    public static $rightname = 'plugin_npsphere_survey';

    /** Habilita o histórico automático (aba "Histórico") */
    public $dohistory = true;

    /** Tipos de pesquisa suportados (seção 6 do escopo) */
    public const TYPE_TICKET   = 'ticket';
    /** Interna: respondida por usuários do próprio GLPI (autenticados) */
    public const TYPE_INTERNAL = 'internal';
    /** Externa: respondida por pessoas de fora, via link público */
    public const TYPE_EXTERNAL = 'external';

    /**
     * Valor legado: até a v0.22.0 o tipo "independente" acumulava os
     * papéis de interna e externa. Instalações antigas continuam com
     * esse valor no banco, então ele segue sendo aceito e tratado como
     * "interna" (ver migração em hook.php).
     */
    public const TYPE_INDEPENDENT_LEGACY = 'independent';

    /** Modos de exibição do formulário de resposta (seção 22) */
    public const DISPLAY_SINGLE_PAGE  = 'single_page';
    public const DISPLAY_STEP_BY_STEP = 'step_by_step';

    /**
     * URLs pelo caminho REAL de instalação do plugin.
     *
     * O gerador genérico do GLPI produz "/plugins/npsphere/front/...",
     * enquanto os demais menus do plugin usavam Plugin::getWebDir(), que
     * devolve o caminho onde o plugin realmente está ("/marketplace/..."
     * quando instalado pelo Marketplace). O resultado eram dois padrões
     * de URL no mesmo plugin.
     *
     * getWebDir() foi o escolhido por acompanhar a instalação: continua
     * correto se o plugin for instalado em "plugins/" em vez de
     * "marketplace/".
     */
    public static function getFormURL($full = true)
    {
        return \Plugin::getWebDir('npsphere', (bool) $full) . '/front/survey.form.php';
    }

    public static function getSearchURL($full = true)
    {
        return \Plugin::getWebDir('npsphere', (bool) $full) . '/front/survey.php';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Pesquisa de satisfação', 'Pesquisas de satisfação', $nb, 'npsphere');
    }

    public static function getIcon()
    {
        return 'ti ti-mood-smile-beam';
    }

    /**
     * Gera um token público seguro (não sequencial) para acesso via link,
     * e define o criador da pesquisa antes de inserir no banco.
     */
    /**
     * Valores padrão de uma pesquisa nova.
     */
    public function post_getEmpty()
    {
        // Padrões de uma pesquisa nova: interna (o valor mais
        // conservador -- nada exposto publicamente sem escolha
        // explícita do administrador) e sem identificação exigida.
        $this->fields['type']                   = self::TYPE_INTERNAL;
        $this->fields['require_identification'] = 0;
        $this->fields['allow_public_access']    = 0;
        $this->fields['is_mandatory']           = 0;
        $this->fields['display_mode']           = self::DISPLAY_SINGLE_PAGE;
    }

    public function prepareInputForAdd($input)
    {
        if (empty($input['public_token'])) {
            $input['public_token'] = bin2hex(random_bytes(32));
        }

        if (empty($input['creator_id'])) {
            $input['creator_id'] = Session::getLoginUserID();
        }

        if (!isset($input['entities_id'])) {
            $input['entities_id'] = Session::getActiveEntity();
        }

        return $this->applyTypeRules($input);
    }

    /**
     * Caso o acesso público seja ativado depois e ainda não exista token,
     * garante a geração de um token seguro também na atualização.
     */
    /**
     * O tipo da pesquisa passa a ser a fonte única de verdade:
     *  - EXTERNA  => link público ligado; identificação exigida a menos
     *                que a pesquisa seja anônima;
     *  - INTERNA / VINCULADA A CHAMADO => sem link público, sem
     *                identificação (o GLPI já sabe quem é o usuário).
     *
     * Derivar isso no servidor evita estados contraditórios que seriam
     * possíveis se cada flag fosse editável de forma independente
     * (ex.: "link público ligado" numa pesquisa interna).
     */
    private function applyTypeRules(array $input): array
    {
        $type = $input['type'] ?? ($this->fields['type'] ?? self::TYPE_INTERNAL);

        if ($type === self::TYPE_EXTERNAL) {
            $input['allow_public_access'] = 1;
            $is_anonymous = array_key_exists('is_anonymous', $input)
                ? (int) $input['is_anonymous']
                : (int) ($this->fields['is_anonymous'] ?? 0);
            // Anônima não pede dados; não anônima exige nome/e-mail/telefone.
            $input['require_identification'] = $is_anonymous ? 0 : 1;
            // Obrigatoriedade não se aplica à externa: não há login
            // para interceptar.
            $input['is_mandatory'] = 0;
        } elseif ($type === self::TYPE_INTERNAL) {
            $input['allow_public_access']    = 0;
            $input['require_identification'] = 0;

            // A opção "Pesquisa anônima" só é oferecida no tipo Externo.
            // Se o campo não é editável aqui, ele não pode continuar
            // valendo 1 por herança de quando a pesquisa era de outro
            // tipo -- era esse estado contraditório que fazia a resposta
            // ser gravada sem users_id (comportamento de anônima) numa
            // pesquisa que o administrador vê como identificada, e o
            // aviso obrigatório voltar para sempre.
            $input['is_anonymous'] = 0;
        } else {
            $input['allow_public_access']    = 0;
            $input['require_identification'] = 0;
            $input['is_mandatory']           = 0;
            if ($type === self::TYPE_TICKET) {
                // Limpa o período: no tipo chamado ele não é editável, e
                // um valor herdado de quando a pesquisa era de outro
                // tipo bloquearia avaliações de chamados que o GLPI já
                // encaminhou -- sem nada na tela explicando o motivo.
                $input['date_begin'] = null;
                $input['date_end']   = null;

                // Numa pesquisa de chamado o respondente é sempre o
                // requerente -- anonimato não se aplica.
                $input['is_anonymous'] = 0;
            }
        }

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        $input = $this->applyTypeRules($input);
        $input = $this->applyReminderRules($input);
        $input = $this->applyReportRules($input);

        // Gera o token na primeira vez que a pesquisa vira externa.
        if (
            !empty($input['allow_public_access'])
            && empty($this->fields['public_token'])
            && empty($input['public_token'])
        ) {
            $input['public_token'] = bin2hex(random_bytes(32));
        }

        return $input;
    }

    /**
     * Sub-aba "Lembrete" (v0.58.0): intervalo e quantidade dentro dos
     * limites da tela, e lembrete desligado na externa anônima -- ali não
     * há como saber quem já respondeu (ver Delivery).
     */
    private function applyReminderRules(array $input): array
    {
        if (array_key_exists('reminder_days', $input)) {
            $input['reminder_days'] = max(Delivery::DIAS_MIN, min(Delivery::DIAS_MAX, (int) $input['reminder_days']));
        }
        if (array_key_exists('reminder_max', $input)) {
            $input['reminder_max'] = max(Delivery::MAX_MIN, min(Delivery::MAX_MAX, (int) $input['reminder_max']));
        }
        if (array_key_exists('invite_delay', $input)) {
            $input['invite_delay'] = in_array((int) $input['invite_delay'], Delivery::ESPERAS_CONVITE, true)
                ? (int) $input['invite_delay']
                : 15;
        }
        if (array_key_exists('reminder_active', $input)) {
            $input['reminder_active'] = empty($input['reminder_active']) ? 0 : 1;
        }

        $type = $input['type'] ?? ($this->fields['type'] ?? self::TYPE_INTERNAL);
        $anonima = array_key_exists('is_anonymous', $input)
            ? !empty($input['is_anonymous'])
            : !empty($this->fields['is_anonymous']);
        if ($type === self::TYPE_EXTERNAL && $anonima) {
            $input['reminder_active'] = 0;
        }

        return $input;
    }

    /**
     * Sub-aba "Relatório" (v0.59.0): horário e notas de corte dentro dos
     * limites, e os destinatários (usuários e grupos escolhidos nos
     * campos do GLPI) gravados juntos em report_recipients, como JSON.
     */
    private function applyReportRules(array $input): array
    {
        if (array_key_exists('report_active', $input)) {
            $input['report_active'] = empty($input['report_active']) ? 0 : 1;
        }
        if (array_key_exists('report_hour', $input)) {
            $input['report_hour'] = max(0, min(23, (int) $input['report_hour']));
        }
        if (array_key_exists('report_nps_cut', $input)) {
            $input['report_nps_cut'] = max(0, min(9, (int) $input['report_nps_cut']));
        }
        if (array_key_exists('alert_nps_cut', $input)) {
            $input['alert_nps_cut'] = max(0, min(9, (int) $input['alert_nps_cut']));
        }
        if (array_key_exists('report_stars_cut', $input)) {
            $input['report_stars_cut'] = max(1, min(4, (int) $input['report_stars_cut']));
        }

        // Aviso imediato (v0.60.0). Ao LIGAR, anota o momento: só as
        // respostas daí em diante geram aviso -- ligar não dispara e-mails
        // sobre respostas antigas.
        if (array_key_exists('alert_active', $input)) {
            $input['alert_active'] = empty($input['alert_active']) ? 0 : 1;
            if ($input['alert_active'] === 1 && empty($this->fields['alert_active'])) {
                $input['alert_since'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
            }
        }
        if (array_key_exists('alert_delay', $input)) {
            $input['alert_delay'] = in_array((int) $input['alert_delay'], DailyReport::ESPERAS, true)
                ? (int) $input['alert_delay']
                : 10;
        }

        // Os dois campos vêm juntos do formulário da aba Template (com um
        // campo oculto vazio antes, para "nenhum selecionado" também
        // chegar). Fora dela, nada muda.
        $ids = static fn($v): array => array_values(array_unique(array_filter(
            array_map('intval', is_array($v) ? $v : []),
            static fn(int $id): bool => $id > 0
        )));

        // Relatório (report_*) e aviso (alert_*), cada um com a sua lista.
        foreach (['report', 'alert'] as $prefixo) {
            if (array_key_exists($prefixo . '_users', $input) || array_key_exists($prefixo . '_groups', $input)) {
                $input[$prefixo . '_recipients'] = json_encode([
                    'users'  => $ids($input[$prefixo . '_users'] ?? []),
                    'groups' => $ids($input[$prefixo . '_groups'] ?? []),
                ]);
            }
            unset($input[$prefixo . '_users'], $input[$prefixo . '_groups']);
        }

        return $input;
    }

    /**
     * Pesquisa removida de vez (lixeira esvaziada): tudo o que pertence a
     * ela vai junto -- perguntas, respostas e os valores delas, público-
     * alvo, registros de conclusão, convites e envios do lembrete. Antes
     * só os envios do lembrete eram apagados, e o resto ficava órfão no
     * banco (v0.62.1).
     *
     * A satisfação nativa do GLPI (glpi_ticketsatisfactions) é do chamado
     * e fica como está.
     */
    public function cleanDBonPurge()
    {
        global $DB;

        $sid = (int) $this->getID();
        if ($sid <= 0) {
            return;
        }

        // Valores das respostas primeiro (ligados à resposta, não à
        // pesquisa).
        if ($DB->tableExists(Answer::getTable()) && $DB->tableExists(AnswerValue::getTable())) {
            $respostas = [];
            foreach ($DB->request([
                'SELECT' => ['id'],
                'FROM'   => Answer::getTable(),
                'WHERE'  => ['surveys_id' => $sid],
            ]) as $linha) {
                $respostas[] = (int) $linha['id'];
            }

            foreach (array_chunk($respostas, 500) as $lote) {
                $DB->delete(AnswerValue::getTable(), ['answers_id' => $lote]);
            }
        }

        foreach ([
            Answer::getTable(),
            Question::getTable(),
            Target::getTable(),
            Completion::getTable(),
            Invite::getTable(),
            Delivery::getTable(),
        ] as $tabela) {
            if ($DB->tableExists($tabela)) {
                $DB->delete($tabela, ['surveys_id' => $sid]);
            }
        }
    }

    /**
     * Tipos de pesquisa disponíveis, no formato valor => rótulo.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_INTERNAL => __('Interna (usuários do GLPI)', 'npsphere'),
            self::TYPE_EXTERNAL => __('Externa (link público)', 'npsphere'),
            self::TYPE_TICKET   => __('Vinculada a chamado', 'npsphere'),
        ];
    }

    public function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs)
            // Ordem intencional: primeiro PARA QUEM é a pesquisa
            // (público-alvo), depois O QUE se pergunta, depois como
            // fica, então o convite que leva a ela e por fim os
            // resultados -- é a sequência natural de quem está
            // montando a pesquisa.
            //
            // "Template" vem depois de "Visualizar" de propósito: só
            // faz sentido escrever o convite depois de conferir a
            // pesquisa que ele vai abrir.
            ->addStandardTab(SurveyTargets::class, $tabs, $options)
            ->addStandardTab(Question::class, $tabs, $options)
            ->addStandardTab(SurveyPreview::class, $tabs, $options)
            ->addStandardTab(SurveyTemplate::class, $tabs, $options)
            ->addStandardTab(SurveyResults::class, $tabs, $options)
            ->addStandardTab(Notepad::class, $tabs, $options)
            ->addStandardTab(Log::class, $tabs, $options);

        return $tabs;
    }

    public function showForm($ID, $options = [])
    {
        $this->initForm($ID, $options);

        // Link público: agora existe de fato (front/public.php, liberado
        // para acesso anônimo no setup.php). Só é exibido quando a opção
        // está ligada e há token gerado.
        $public_url = null;
        if (!empty($this->fields['allow_public_access']) && !empty($this->fields['public_token'])) {
            global $CFG_GLPI;
            // Endereço completo (com o servidor): este link é copiado e
            // enviado para fora do GLPI, onde um caminho sem servidor não abre.
            $public_url = SurveyMailer::getBaseAbsoluta((int) ($this->fields['entities_id'] ?? 0))
                . '/front/public.php?token=' . $this->fields['public_token'];
        }

        // Nome de quem criou a pesquisa, exibido no rodapé junto das
        // datas de criação/atualização (a coluna se chama "creator_id",
        // e não "users_id", para o GLPI não a exibir como um dropdown
        // editável no formulário -- ver changelog v0.5.0).
        $creator_name = null;
        if (!empty($this->fields['creator_id'])) {
            $creator = new \User();
            if ($creator->getFromDB($this->fields['creator_id'])) {
                $creator_name = $creator->getFriendlyName();
            }
        }

        // Resumo de resultados exibido em destaque no topo do formulário
        // (só depois de existir ao menos uma resposta).
        $summary = null;
        if (!$this->isNewItem()) {
            global $DB;
            $questions = [];
            foreach ($DB->request(['FROM' => Question::getTable(), 'WHERE' => ['surveys_id' => $this->getID()]]) as $row) {
                $questions[] = $row;
            }
            $summary = MetricsCalculator::getSummary($this->getID(), $questions);
            if ($summary['total_answers'] === 0) {
                $summary = null;
            }
        }

        // URL a configurar na entidade quando a pesquisa é do tipo
        // "vinculada a chamado" -- ver TicketLink para o porquê da
        // estratégia.
        // Sempre calculada: o bloco é renderizado oculto e mostrado
        // pelo JavaScript quando o tipo "vinculada a chamado" é
        // escolhido -- assim ele aparece na hora, sem salvar a
        // pesquisa, igual acontece com os campos da pesquisa externa.
        // A URL leva o ID DESTA pesquisa: assim cada entidade aponta
        // para a pesquisa certa, mesmo havendo várias do tipo chamado.
        $ticket_url = TicketLink::getExternalUrl(
            $this->getID() ?: null,
            (int) ($this->fields['entities_id'] ?? Session::getActiveEntity())
        );

        TemplateRenderer::getInstance()->display('@npsphere/survey.form.html.twig', [
            'ticket_url'      => $ticket_url,

            'summary'         => $summary,
            // O GLPI abre uma aba específica via "forcetab", no formato
            // <Classe>$<numero_da_aba>. É bem mais confiável do que
            // tentar localizar e clicar no link da aba pelo JS, já que
            // a marcação das abas muda entre versões do GLPI.
            'item'            => $this,
            'params'          => $options,
            'public_url'      => $public_url,
            'creator_name'    => $creator_name,
            'types'           => self::getTypes(),
            'display_modes' => [
                self::DISPLAY_SINGLE_PAGE  => __('Página única', 'npsphere'),
                self::DISPLAY_STEP_BY_STEP => __('Uma pergunta por etapa', 'npsphere'),
            ],
        ]);

        return true;
    }

    public static function getMenuName($nb = 0)
    {
        return self::getTypeName($nb);
    }

    /**
     * O NPS é considerado "habilitado" para esta pesquisa quando ela tem
     * pelo menos uma pergunta ativa do tipo "nps" (0 a 10). Todas as
     * perguntas desse tipo entram automaticamente no cálculo do NPS --
     * não existe mais uma opção manual de "pergunta principal" (ver
     * changelog v0.6.0 no README: cada pergunta do tipo NPS já é, por
     * definição, uma pergunta de NPS; marcar uma como "principal" só
     * fazia sentido se pudesse haver mais de uma sem contar, o que não
     * é o caso).
     */
    public function hasNpsQuestion(): bool
    {
        global $DB;

        $iterator = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => Question::getTable(),
            'WHERE' => [
                'surveys_id' => $this->getID(),
                'type'       => 'nps',
                'is_active'  => 1,
            ],
        ]);

        return (int) $iterator->current()['cpt'] > 0;
    }

    /**
     * Quem administra o GLPI vê e gerencia TODAS as pesquisas.
     *
     * Identificado pelo direito de alterar a configuração geral -- o que
     * distingue o Super-Admin dos demais perfis --, e não pelo nome do
     * perfil: a regra continua certa se o perfil for renomeado.
     */
    public static function isAdministradorGeral(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    /**
     * O usuário atual pode GERENCIAR esta pesquisa?
     *
     * Regra única, usada pela listagem, pelo formulário e por todos os
     * endpoints de gestão: cada pessoa gerencia as pesquisas que criou;
     * o administrador geral, todas.
     *
     * Não se aplica a quem RESPONDE: o respondente não criou a pesquisa
     * e precisa acessá-la -- as rotas de resposta têm as suas próprias
     * regras (público-alvo, token, requerente do chamado).
     */
    public static function podeGerenciar(int $surveys_id): bool
    {
        if ($surveys_id <= 0) {
            return false;
        }

        if (self::isAdministradorGeral()) {
            return true;
        }

        global $DB;

        foreach ($DB->request([
            'SELECT' => ['creator_id'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['id' => $surveys_id],
        ]) as $row) {
            return (int) $row['creator_id'] === (int) Session::getLoginUserID();
        }

        return false;
    }

    /**
     * Encerra a requisição se o usuário não puder gerenciar a pesquisa.
     *
     * Para os endpoints AJAX de gestão. Filtrar apenas a listagem não
     * bastaria: quem soubesse o ID de uma pesquisa alheia a abriria pela
     * URL -- resultados, perguntas, exportação.
     */
    public static function exigirGestao(int $surveys_id): void
    {
        if (!self::podeGerenciar($surveys_id)) {
            http_response_code(403);
            exit;
        }
    }

    /**
     * Mesma checagem, para PÁGINAS: mostra a tela de acesso negado do
     * GLPI em vez de uma resposta vazia -- que numa página cheia
     * pareceria erro do sistema.
     */
    public static function exigirGestaoNaPagina(int $surveys_id): void
    {
        if (!self::podeGerenciar($surveys_id)) {
            \Html::displayRightError();
        }
    }

    /**
     * Permissões por ITEM: é o que o GLPI consulta ao abrir o formulário
     * da pesquisa, e também ao montar as ações em massa. O direito
     * genérico do perfil continua sendo exigido -- isto apenas o
     * restringe às pesquisas do próprio usuário.
     */
    public function canViewItem(): bool
    {
        return parent::canViewItem() && self::podeGerenciar((int) $this->getID());
    }

    public function canUpdateItem(): bool
    {
        return parent::canUpdateItem() && self::podeGerenciar((int) $this->getID());
    }

    public function canDeleteItem(): bool
    {
        return parent::canDeleteItem() && self::podeGerenciar((int) $this->getID());
    }

    public function canPurgeItem(): bool
    {
        return parent::canPurgeItem() && self::podeGerenciar((int) $this->getID());
    }

    public static function canView(): bool
    {
        // A interface simplificada não tem o menu Administração, onde
        // esta tela vive. Bloquear aqui evita um estado contraditório:
        // um perfil de autoatendimento com o direito herdado de antes
        // conseguiria abrir a gestão pela URL.
        if (Session::getCurrentInterface() === 'helpdesk') {
            return false;
        }

        return Session::haveRight(self::$rightname, READ);
    }

    public static function getMenuContent()
    {
        // Sem o direito de LEITURA, o item nem é montado.
        //
        // O GLPI monta o menu a partir DESTE método -- definir apenas
        // canView() não bastava: a entrada continuava aparecendo em
        // Administração para quem não tinha o direito, e só dava erro
        // ao clicar. Retornar false remove o item do menu.
        if (!self::canView()) {
            return false;
        }

        $search = self::getSearchURL(false);
        $form   = self::getFormURL(false);

        // Como o plugin tem apenas um itemtype de primeiro nível, usamos
        // a estrutura de menu de UM único nível (sem o array "options"),
        // exatamente como documentado: "It is possible to have only one
        // menu level for the plugin (3 globally), just move the links
        // part to the first level of the $menu array." Isso evita o
        // segmento repetido no breadcrumb ("Pesquisas de satisfação /
        // Pesquisas de satisfação").
        $menu = [
            'title' => __('Pesquisas de satisfação', 'npsphere'),
            'page'  => $search,
            'icon'  => self::getIcon(),
            'links' => [
                'search' => $search,
                'add'    => (self::canCreate() ? $form : ''),

            ],
        ];

        return $menu;
    }

    public function rawSearchOptions()
    {
        $options = [];

        $options[] = [
            'id'   => 'common',
            'name' => __('Características', 'npsphere'),
        ];

        $options[] = [
            'id'       => 1,
            'table'    => self::getTable(),
            'field'    => 'name',
            'name'     => __('Nome'),
            'datatype' => 'itemlink',
        ];

        $options[] = [
            'id'    => 2,
            'table' => self::getTable(),
            'field' => 'id',
            'name'  => __('ID'),
        ];

        $options[] = [
            'id'       => 3,
            'table'    => self::getTable(),
            'field'    => 'is_active',
            'name'     => __('Ativa'),
            'datatype' => 'bool',
        ];

        $options[] = [
            'id'         => 4,
            'table'      => self::getTable(),
            'field'      => 'type',
            'name'       => __('Tipo de pesquisa', 'npsphere'),
            'datatype'   => 'specific',
            'searchtype' => ['equals', 'notequals'],
        ];

        $options[] = [
            'id'       => 6,
            'table'    => self::getTable(),
            'field'    => 'date_begin',
            'name'     => __('Data de início', 'npsphere'),
            'datatype' => 'date',
        ];

        $options[] = [
            'id'       => 7,
            'table'    => self::getTable(),
            'field'    => 'date_end',
            'name'     => __('Data de término', 'npsphere'),
            'datatype' => 'date',
        ];

        $options[] = [
            'id'       => 8,
            'table'    => self::getTable(),
            'field'    => 'allow_public_access',
            'name'     => __('Acesso via link público', 'npsphere'),
            'datatype' => 'bool',
        ];

        $options[] = [
            'id'            => 19,
            'table'         => self::getTable(),
            'field'         => 'date_mod',
            'name'          => __('Última atualização'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];

        $options[] = [
            'id'            => 121,
            'table'         => self::getTable(),
            'field'         => 'date_creation',
            'name'          => __('Data de criação'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];

        $options[] = [
            'id'       => 80,
            'table'    => 'glpi_entities',
            'field'    => 'completename',
            'name'     => __('Entidade'),
            'datatype' => 'dropdown',
        ];

        return $options;
    }
}
