<?php

namespace GlpiPlugin\Npsphere;

use CommonDBChild;
use Group_User;
use Profile_User;
use Session;

/**
 * Público-alvo de uma pesquisa (Fase 6).
 *
 * Cada linha liga a pesquisa a UM destinatário, que pode ser uma
 * entidade, um perfil, um grupo ou um usuário específico. Vários
 * critérios se somam (união): estar em qualquer um deles basta para
 * poder responder. Não há regras de exclusão -- se um dia forem
 * necessárias ("todo o grupo X, exceto fulano"), a estrutura comporta
 * uma coluna adicional sem migração destrutiva.
 */
class Target extends CommonDBChild
{
    public static $itemtype = Survey::class;
    public static $items_id = 'surveys_id';

    public static $rightname = 'plugin_npsphere_survey';

    /** Tipos de destinatário suportados */
    public const TYPE_ENTITY  = 'Entity';
    public const TYPE_PROFILE = 'Profile';
    public const TYPE_GROUP   = 'Group';
    public const TYPE_USER    = 'User';

    public static function getTypeName($nb = 0)
    {
        return _n('Público-alvo', 'Públicos-alvo', $nb, 'npsphere');
    }

    public static function getIcon()
    {
        return 'ti ti-users';
    }

    /**
     * Tipos disponíveis, com rótulo traduzido.
     */
    public static function getTargetTypes(): array
    {
        return [
            self::TYPE_ENTITY  => __('Entidade'),
            self::TYPE_PROFILE => __('Perfil'),
            self::TYPE_GROUP   => __('Grupo'),
            self::TYPE_USER    => __('Usuário'),
        ];
    }

    /**
     * Destinatários cadastrados de uma pesquisa.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getForSurvey(int $surveys_id): array
    {
        global $DB;

        $out = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['surveys_id' => $surveys_id],
            'ORDER' => ['itemtype ASC', 'id ASC'],
        ]) as $row) {
            $out[] = $row;
        }

        return $out;
    }

    /**
     * O usuário informado faz parte do público-alvo desta pesquisa?
     *
     * Regras, nesta ordem:
     *  1. Pesquisa marcada como "todos os usuários" => sempre sim;
     *  2. Sem nenhum destinatário cadastrado => NÃO. É uma escolha
     *     deliberada: uma pesquisa sem público definido não deve vazar
     *     para toda a base. O administrador precisa dizer para quem é
     *     (ou marcar "todos" explicitamente);
     *  3. Caso contrário, basta o usuário atender a QUALQUER critério.
     */
    public static function isUserTargeted(Survey $survey, ?int $users_id = null): bool
    {
        global $DB;

        $users_id = $users_id ?? (int) Session::getLoginUserID();
        if (!$users_id) {
            return false;
        }

        if (!empty($survey->fields['target_all'])) {
            return true;
        }

        $targets = self::getForSurvey($survey->getID());
        if (empty($targets)) {
            return false;
        }

        // Agrupa por tipo para consultar cada dimensão uma única vez
        $by_type = [];
        foreach ($targets as $t) {
            $by_type[$t['itemtype']][] = (int) $t['items_id'];
        }

        if (!empty($by_type[self::TYPE_USER])
            && in_array($users_id, $by_type[self::TYPE_USER], true)) {
            return true;
        }

        if (!empty($by_type[self::TYPE_GROUP])) {
            $count = $DB->request([
                'COUNT' => 'cpt',
                'FROM'  => Group_User::getTable(),
                'WHERE' => ['users_id' => $users_id, 'groups_id' => $by_type[self::TYPE_GROUP]],
            ])->current()['cpt'];
            if ((int) $count > 0) {
                return true;
            }
        }

        if (!empty($by_type[self::TYPE_PROFILE])) {
            $count = $DB->request([
                'COUNT' => 'cpt',
                'FROM'  => Profile_User::getTable(),
                'WHERE' => ['users_id' => $users_id, 'profiles_id' => $by_type[self::TYPE_PROFILE]],
            ])->current()['cpt'];
            if ((int) $count > 0) {
                return true;
            }
        }

        if (!empty($by_type[self::TYPE_ENTITY])) {
            // Entidade: considera as entidades às quais o usuário tem
            // acesso, respeitando a recursividade do GLPI (uma entidade
            // pai alcança as filhas quando is_recursive está marcado).
            foreach ($DB->request([
                'SELECT' => ['entities_id', 'is_recursive'],
                'FROM'   => Profile_User::getTable(),
                'WHERE'  => ['users_id' => $users_id],
            ]) as $row) {
                $entity = (int) $row['entities_id'];

                if (in_array($entity, $by_type[self::TYPE_ENTITY], true)) {
                    return true;
                }

                if (!empty($row['is_recursive'])) {
                    $descendants = getSonsOf('glpi_entities', $entity);
                    if (array_intersect($by_type[self::TYPE_ENTITY], array_map('intval', $descendants))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * É o último dia para responder esta pesquisa?
     *
     * No último dia, uma pesquisa obrigatória deixa de poder ser
     * adiada. Uma pesquisa SEM data de término nunca chega ao "último
     * dia" -- seria bloquear o usuário para sempre, o que não é uma
     * decisão que o plugin deva tomar em nome do administrador.
     */
    /**
     * Usuários que devem receber o convite de uma pesquisa INTERNA.
     *
     * É o caminho inverso do isUserTargeted(): em vez de perguntar "esta
     * pessoa é alvo?", resolve "quem é alvo desta pesquisa?".
     *
     * Só entram usuários ATIVOS e com e-mail: enfileirar mensagem para
     * conta desativada ou sem endereço gera falha de envio que ninguém
     * vai investigar.
     *
     * @return array<int, array{id: int, nome: string, email: string}>
     */
    public static function getUsuariosAlvo(Survey $survey): array
    {
        $saida = [];
        foreach (self::getPessoasDoPublico($survey) as $pessoa) {
            if ($pessoa['email'] !== '') {
                $saida[$pessoa['id']] = $pessoa;
            }
        }

        return $saida;
    }

    /**
     * Todos os usuários ATIVOS do público-alvo, com ou sem e-mail.
     * getUsuariosAlvo() é este conjunto sem quem não tem endereço.
     *
     * @return array<int, array{id: int, nome: string, email: string}>
     */
    private static function getPessoasDoPublico(Survey $survey): array
    {
        global $DB;

        $alvos = self::getForSurvey($survey->getID());
        $ids = [];

        // "Todos" e alvo por ENTIDADE resolvem pela lotação do usuário;
        // perfil e grupo, pelos vínculos correspondentes.
        if (!empty($survey->fields['target_all'])) {
            $ids = self::getUsuariosDaEntidade((int) $survey->fields['entities_id'],
                                               !empty($survey->fields['is_recursive']));
        }

        foreach ($alvos as $alvo) {
            $itemtype = $alvo['itemtype'];
            $items_id = (int) $alvo['items_id'];

            if ($itemtype === 'Entity') {
                $ids = array_merge($ids, self::getUsuariosDaEntidade($items_id, true));
            } elseif ($itemtype === 'Profile') {
                $ids = array_merge($ids, self::getUsuariosPorVinculo('glpi_profiles_users', 'profiles_id', $items_id));
            } elseif ($itemtype === 'Group') {
                $ids = array_merge($ids, self::getUsuariosPorVinculo('glpi_groups_users', 'groups_id', $items_id));
            } elseif ($itemtype === 'User') {
                $ids[] = $items_id;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return [];
        }

        $saida = [];

        foreach ($DB->request([
            'SELECT' => ['id', 'name', 'realname', 'firstname'],
            'FROM'   => 'glpi_users',
            'WHERE'  => ['id' => $ids, 'is_active' => 1, 'is_deleted' => 0],
        ]) as $row) {
            $user = new \User();
            $email = $user->getFromDB((int) $row['id']) && method_exists($user, 'getDefaultEmail')
                ? (string) $user->getDefaultEmail()
                : '';

            $completo = trim(($row['firstname'] ?? '') . ' ' . ($row['realname'] ?? ''));

            $saida[(int) $row['id']] = [
                'id'    => (int) $row['id'],
                'nome'  => $completo !== '' ? $completo : (string) $row['name'],
                'email' => $email,
            ];
        }

        return $saida;
    }

    /**
     * Quem recebe o convite da pesquisa interna, para a aba Template.
     *
     * Mesma regra do envio (SurveyMailer::enviarConvites): só usuários
     * ativos com e-mail, e -- se a pesquisa não aceita múltiplas
     * respostas -- pulando quem já respondeu. Quem já respondeu é
     * descoberto em DUAS consultas para o público inteiro, e não duas
     * por pessoa: com "Todos os usuários" o público pode ter milhares.
     *
     * @return array{
     *   pessoas: array<int, array{id: int, nome: string, email: string, situacao: string}>,
     *   recebem: int, responderam: int, sem_email: int, multiplas: bool
     * }
     */
    public static function getResumoDoPublico(Survey $survey): array
    {
        global $DB;

        $pessoas   = self::getPessoasDoPublico($survey);
        $multiplas = !empty($survey->fields['allow_multiple_answers']);

        $responderam = [];
        if (!$multiplas && $pessoas !== []) {
            $ids = array_keys($pessoas);

            if ($DB->tableExists(Completion::getTable())) {
                foreach ($DB->request([
                    'SELECT' => ['users_id'],
                    'FROM'   => Completion::getTable(),
                    'WHERE'  => ['surveys_id' => $survey->getID(), 'users_id' => $ids],
                ]) as $row) {
                    $responderam[(int) $row['users_id']] = true;
                }
            }

            foreach ($DB->request([
                'SELECT'   => ['users_id'],
                'DISTINCT' => true,
                'FROM'     => Answer::getTable(),
                'WHERE'    => ['surveys_id' => $survey->getID(), 'users_id' => $ids],
            ]) as $row) {
                $responderam[(int) $row['users_id']] = true;
            }
        }

        $saida = ['pessoas' => [], 'recebem' => 0, 'responderam' => 0, 'sem_email' => 0, 'multiplas' => $multiplas];

        foreach ($pessoas as $id => $pessoa) {
            if ($pessoa['email'] === '') {
                $situacao = 'sem_email';
            } elseif (isset($responderam[$id])) {
                $situacao = 'respondeu';
            } else {
                $situacao = 'recebe';
            }

            $saida[$situacao === 'recebe' ? 'recebem' : ($situacao === 'respondeu' ? 'responderam' : 'sem_email')]++;
            $saida['pessoas'][] = $pessoa + ['situacao' => $situacao];
        }

        // Quem vai receber primeiro; dentro de cada grupo, por nome.
        $ordem = ['recebe' => 0, 'respondeu' => 1, 'sem_email' => 2];
        usort($saida['pessoas'], static function (array $a, array $b) use ($ordem): int {
            return [$ordem[$a['situacao']], mb_strtolower($a['nome'])] <=> [$ordem[$b['situacao']], mb_strtolower($b['nome'])];
        });

        return $saida;
    }

    private static function getUsuariosDaEntidade(int $entities_id, bool $recursivo): array
    {
        global $DB;

        $entidades = [$entities_id];
        if ($recursivo && function_exists('getSonsOf')) {
            $entidades = array_map('intval', getSonsOf('glpi_entities', $entities_id));
        }

        $ids = [];
        foreach ($DB->request([
            'SELECT'   => ['users_id'],
            'DISTINCT' => true,
            'FROM'     => 'glpi_profiles_users',
            'WHERE'    => ['entities_id' => $entidades],
        ]) as $row) {
            $ids[] = (int) $row['users_id'];
        }

        return $ids;
    }

    private static function getUsuariosPorVinculo(string $tabela, string $coluna, int $valor): array
    {
        global $DB;

        if (!$DB->tableExists($tabela)) {
            return [];
        }

        $ids = [];
        foreach ($DB->request([
            'SELECT'   => ['users_id'],
            'DISTINCT' => true,
            'FROM'     => $tabela,
            'WHERE'    => [$coluna => $valor],
        ]) as $row) {
            $ids[] = (int) $row['users_id'];
        }

        return $ids;
    }

    public static function isLastDay(Survey $survey): bool
    {
        if (empty($survey->fields['date_end'])) {
            return false;
        }

        return date('Y-m-d') === substr((string) $survey->fields['date_end'], 0, 10);
    }

    /**
     * Pesquisas obrigatórias pendentes para o usuário, com a informação
     * de bloqueio (último dia).
     *
     * @return array<int, array{survey: Survey, blocking: bool}>
     */
    public static function getPendingMandatory(?int $users_id = null): array
    {
        // Resolve o usuário ANTES de usá-lo: o parâmetro é opcional, e
        // repassar o null adiante quebrava hasAnswered(), que exige int.
        $users_id = $users_id ?? (int) Session::getLoginUserID();
        if (!$users_id) {
            return [];
        }

        $out = [];
        foreach (self::getSurveysForUser($users_id) as $survey) {
            if (empty($survey->fields['is_mandatory'])) {
                continue;
            }

            // "Pendente" significa NÃO RESPONDIDA -- independentemente de
            // a pesquisa aceitar múltiplas respostas.
            //
            // Era exatamente aqui que estava o laço: canRespond() só
            // verifica resposta anterior quando "permitir múltiplas
            // respostas" está desligado, então uma pesquisa obrigatória
            // que aceita várias respostas continuava aparecendo mesmo
            // depois de respondida -- e, no último dia, prendia o
            // usuário para sempre.
            if (self::hasAnswered($survey, $users_id)) {
                continue;
            }
            $out[] = [
                'survey'   => $survey,
                'blocking' => self::isLastDay($survey),
            ];
        }

        // Pesquisas que bloqueiam vêm primeiro: se houver uma delas,
        // é a que o usuário precisa resolver agora.
        usort($out, static fn($a, $b) => ($b['blocking'] <=> $a['blocking']));

        return $out;
    }

    /**
     * Este usuário já respondeu esta pesquisa alguma vez?
     *
     * Diferente de ResponseService::hasAlreadyAnswered(), que responde
     * "pode responder de novo?" (e por isso respeita a permissão de
     * múltiplas respostas), esta pergunta é literal: existe resposta
     * dele registrada?
     */
    public static function hasAnswered(Survey $survey, ?int $users_id = null): bool
    {
        global $DB;

        // Aceita null e resolve aqui dentro: o mesmo cuidado que os
        // outros métodos desta classe já tinham. Exigir int obrigava
        // todo chamador a lembrar de resolver antes -- e bastou um
        // esquecer para derrubar a requisição inteira com erro 500.
        $users_id = $users_id ?? (int) Session::getLoginUserID();
        if (!$users_id) {
            return false;
        }

        // Fonte 1: registro de conclusão. É a única que funciona em
        // pesquisa ANÔNIMA, onde a resposta não guarda users_id.
        if (Completion::hasCompleted($survey->getID(), $users_id)) {
            return true;
        }

        // Fonte 2: a própria resposta. Continua valendo para respostas
        // gravadas ANTES de existir o registro de conclusão -- sem isso,
        // quem já respondeu no passado seria cobrado de novo.
        $count = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => Answer::getTable(),
            'WHERE' => ['surveys_id' => $survey->getID(), 'users_id' => $users_id],
        ])->current()['cpt'];

        return (int) $count > 0;
    }

    /**
     * Pesquisas internas que este usuário pode responder agora.
     *
     * @return array<int, Survey>
     */
    public static function getSurveysForUser(?int $users_id = null): array
    {
        global $DB;

        $users_id = $users_id ?? (int) Session::getLoginUserID();
        if (!$users_id) {
            return [];
        }

        $out = [];
        foreach ($DB->request([
            'FROM'  => Survey::getTable(),
            'WHERE' => [
                'type'       => Survey::TYPE_INTERNAL,
                'is_active'  => 1,
                'is_deleted' => 0,
            ],
            'ORDER' => 'name ASC',
        ]) as $row) {
            $survey = new Survey();
            $survey->fields = $row;

            if (!self::isUserTargeted($survey, $users_id)) {
                continue;
            }

            // Reaproveita as MESMAS regras do fluxo de resposta (prazo,
            // pesquisa ativa, já respondida) -- listar uma pesquisa que
            // a pessoa não conseguiria responder seria frustrante.
            $eligibility = ResponseService::canRespond($survey);
            if (!$eligibility['can']) {
                continue;
            }

            $out[] = $survey;
        }

        return $out;
    }

    // -----------------------------------------------------------------
    // Resposta pelo link do e-mail, sem login (v0.56.0)
    // -----------------------------------------------------------------

    /** Chave do link pessoal da pesquisa interna: "esta pessoa, esta pesquisa". */
    public static function chave(int $users_id, int $surveys_id): string
    {
        return LinkSigner::assinar('npsphere-interna:' . $users_id . ':' . $surveys_id);
    }

    /**
     * Pesquisa e pessoa a partir do link do e-mail.
     *
     * Além da chave, a pessoa precisa CONTINUAR no público-alvo e ativa no
     * GLPI: quem saiu do grupo depois do envio não responde por um link
     * antigo.
     *
     * @return array{survey: ?Survey, users_id: int, erro: ?string}
     */
    public static function resolverPorChave(array $p): array
    {
        $surveys_id = (int) ($p['surveys_id'] ?? 0);
        $users_id   = (int) ($p['usuario'] ?? 0);

        $falha = static fn(string $m): array => ['survey' => null, 'users_id' => 0, 'erro' => $m];

        if (
            $surveys_id <= 0 || $users_id <= 0
            || !LinkSigner::confere($p['chave'] ?? null, 'npsphere-interna:' . $users_id . ':' . $surveys_id)
        ) {
            return $falha(__('Link inválido ou expirado.', 'npsphere'));
        }

        $survey = new Survey();
        if (
            !$survey->getFromDB($surveys_id)
            || ($survey->fields['type'] ?? '') !== Survey::TYPE_INTERNAL
            || !empty($survey->fields['is_deleted'])
        ) {
            return $falha(__('Esta pesquisa não está mais disponível.', 'npsphere'));
        }

        $elegivel = ResponseService::canRespond($survey, true);
        if (!$elegivel['can']) {
            return $falha((string) $elegivel['reason']);
        }

        $user = new \User();
        if (!$user->getFromDB($users_id) || empty($user->fields['is_active']) || !empty($user->fields['is_deleted'])) {
            return $falha(__('Esta pesquisa não está disponível para você.', 'npsphere'));
        }

        if (!self::isUserTargeted($survey, $users_id)) {
            return $falha(__('Esta pesquisa não está disponível para você.', 'npsphere'));
        }

        return ['survey' => $survey, 'users_id' => $users_id, 'erro' => null];
    }
}
