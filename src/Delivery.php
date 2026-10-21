<?php

namespace GlpiPlugin\Npsphere;

use CommonDBTM;

/**
 * Convites enviados e LEMBRETES para quem ainda não respondeu (v0.58.0).
 *
 * Cada convite que sai (pesquisa interna, externa identificada ou de
 * chamado) vira uma linha aqui: para quem foi, quando, e quantos
 * lembretes essa pessoa já recebeu. A ação automática "npsphereLembrete"
 * (Configurar › Ações automáticas) percorre essas linhas e manda o
 * lembrete a quem ainda não respondeu, no intervalo configurado na
 * sub-aba "Lembrete" da pesquisa.
 *
 * O GLPI não tem lembrete de pesquisa de satisfação no núcleo (só o
 * plugin "More satisfaction" oferece). O que ele tem é a DURAÇÃO da
 * pesquisa na entidade (inquest_duration, Entidade › Assistência):
 * passado esse prazo a pesquisa do chamado expira. A pesquisa de chamado
 * segue esse padrão -- nenhum lembrete sai depois que a pesquisa expirou.
 *
 * A chave da linha identifica a pessoa conforme o tipo:
 *   t:{chamado}  pesquisa de chamado (o requerente)
 *   u:{usuário}  pesquisa interna
 *   e:{e-mail}   pesquisa externa identificada
 *
 * Pesquisa externa ANÔNIMA não entra: o link é o mesmo para todos, então
 * não há como saber quem já respondeu -- e descobrir isso iria contra a
 * promessa de anonimato.
 */
class Delivery extends CommonDBTM
{
    public static $rightname = 'plugin_npsphere_survey';

    /** Nome da ação automática (Configurar › Ações automáticas). */
    public const CRON = 'npsphereLembrete';

    /** Ação automática do convite do chamado com espera (v0.63.0). */
    public const CRON_CONVITE = 'npsphereConvite';

    /** Convite do chamado esperando a hora de sair (v0.63.0). */
    public const AGENDADO   = 'agendado';
    public const AGUARDANDO = 'aguardando';
    public const RESPONDEU  = 'respondeu';
    public const ENCERRADO  = 'encerrado';

    /** Limites dos campos da sub-aba "Lembrete". */
    public const DIAS_MIN = 1;
    public const DIAS_MAX = 30;
    public const MAX_MIN  = 1;
    public const MAX_MAX  = 5;

    /** Opções de espera do convite do chamado, em minutos (0 = na hora). */
    public const ESPERAS_CONVITE = [0, 5, 10, 15, 30, 60];

    public static function getTypeName($nb = 0)
    {
        return _n('Envio de convite', 'Envios de convite', $nb, 'npsphere');
    }

    /** Escrita direta: a gravação acontece no envio, sem tela própria. */
    public static function canCreate(): bool
    {
        return true;
    }

    // ------------------------------------------------------------------
    // Registro dos envios
    // ------------------------------------------------------------------

    public static function chaveDoChamado(int $tickets_id): string
    {
        return 't:' . $tickets_id;
    }

    public static function chaveDoUsuario(int $users_id): string
    {
        return 'u:' . $users_id;
    }

    public static function chaveDoEmail(string $email): string
    {
        return 'e:' . mb_strtolower(trim($email));
    }

    /**
     * Anota que o CONVITE saiu para esta pessoa.
     *
     * Reenviar o convite (botão "Enviar convites" de novo) recomeça a
     * contagem: a pessoa acabou de receber um e-mail, o próximo lembrete
     * conta a partir dele.
     *
     * @param array{tickets_id?: int, users_id?: int, email?: string, name?: string} $dados
     */
    public static function registrarConvite(int $surveys_id, string $chave, array $dados): void
    {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return;
        }

        $agora = self::agora();
        $campos = [
            'tickets_id'         => (int) ($dados['tickets_id'] ?? 0),
            'users_id'           => (int) ($dados['users_id'] ?? 0),
            'email'              => mb_substr((string) ($dados['email'] ?? ''), 0, 255),
            'name'               => mb_substr((string) ($dados['name'] ?? ''), 0, 255),
            'status'             => self::AGUARDANDO,
            'date_invited'       => $agora,
            'reminders_sent'     => 0,
            'date_last_reminder' => null,
            'date_mod'           => $agora,
        ];

        $linha = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['surveys_id' => $surveys_id, 'chave' => $chave],
            'LIMIT'  => 1,
        ])->current();

        if ($linha) {
            $DB->update(self::getTable(), $campos, ['id' => (int) $linha['id']]);
            return;
        }

        $DB->insert(self::getTable(), $campos + [
            'surveys_id'    => $surveys_id,
            'chave'         => $chave,
            'date_creation' => $agora,
        ]);
    }

    private static function marcar(int $id, string $status): void
    {
        global $DB;

        $DB->update(self::getTable(), ['status' => $status, 'date_mod' => self::agora()], ['id' => $id]);
    }

    private static function contarLembrete(int $id, int $enviados): void
    {
        global $DB;

        $agora = self::agora();
        $DB->update(self::getTable(), [
            'reminders_sent'     => $enviados + 1,
            'date_last_reminder' => $agora,
            'date_mod'           => $agora,
        ], ['id' => $id]);
    }

    // ------------------------------------------------------------------
    // Quem ainda deve receber
    // ------------------------------------------------------------------

    /**
     * Situação de UMA pessoa, consultada na hora (respondeu depois do
     * último cálculo? o chamado expirou?).
     *
     * @param array<string, mixed> $linha
     * @return string self::AGUARDANDO | self::RESPONDEU | self::ENCERRADO
     */
    public static function situacao(Survey $survey, array $linha): string
    {
        global $DB;

        $chave = (string) $linha['chave'];
        $tipo  = (string) ($survey->fields['type'] ?? '');

        // ---------------------------------------------------- chamado
        if (str_starts_with($chave, 't:')) {
            $tickets_id = (int) $linha['tickets_id'];

            if (TicketLink::jaRespondido($tickets_id)) {
                return self::RESPONDEU;
            }

            // A satisfação do GLPI é a referência do prazo: sem ela (o
            // chamado foi reaberto, ou apagado), não há o que lembrar.
            $satisfacao = $DB->request([
                'SELECT' => ['date_begin', 'date_answered'],
                'FROM'   => 'glpi_ticketsatisfactions',
                'WHERE'  => ['tickets_id' => $tickets_id],
                'LIMIT'  => 1,
            ])->current();

            if (!$satisfacao) {
                return self::ENCERRADO;
            }
            if (!empty($satisfacao['date_answered'])) {
                return self::RESPONDEU;
            }

            $ticket = new \Ticket();
            if (!$ticket->getFromDB($tickets_id) || !empty($ticket->fields['is_deleted'])) {
                return self::ENCERRADO;
            }

            // Mesma regra do GLPI (CommonITILSatisfaction): expirou quando
            // a duração não é 0 e já passou desde o início da pesquisa.
            $duracao = self::getDuracaoDoChamado((int) $ticket->fields['entities_id']);
            $inicio  = strtotime((string) ($satisfacao['date_begin'] ?? ''));
            if ($duracao > 0 && $inicio && (self::agoraTs() - $inicio) > $duracao * DAY_TIMESTAMP) {
                return self::ENCERRADO;
            }

            // A entidade passou a usar outra pesquisa (ou a interna do
            // GLPI): o lembrete desta não faz mais sentido.
            $aplicavel = MailPolicy::getPesquisaAplicavel($tickets_id);
            if ($aplicavel === null || $aplicavel->getID() !== $survey->getID()) {
                return self::ENCERRADO;
            }

            return self::AGUARDANDO;
        }

        // Interna e externa: pesquisa com período encerrado não cobra.
        if (!empty($survey->fields['date_end']) && strtotime($survey->fields['date_end'] . ' 23:59:59') < self::agoraTs()) {
            return self::ENCERRADO;
        }

        // ---------------------------------------------------- interna
        if (str_starts_with($chave, 'u:')) {
            $users_id = (int) $linha['users_id'];

            if (!empty($survey->fields['allow_multiple_answers'])) {
                // Várias respostas por pessoa: conta a que veio DEPOIS
                // deste convite.
                $n = $DB->request([
                    'COUNT' => 'cpt',
                    'FROM'  => Answer::getTable(),
                    'WHERE' => [
                        'surveys_id'    => $survey->getID(),
                        'users_id'      => $users_id,
                        'date_creation' => ['>=', (string) $linha['date_invited']],
                    ],
                ])->current();
                if ((int) ($n['cpt'] ?? 0) > 0) {
                    return self::RESPONDEU;
                }
            } elseif (Target::hasAnswered($survey, $users_id)) {
                return self::RESPONDEU;
            }

            // Saiu do público-alvo, ou o usuário foi desativado.
            $user = new \User();
            if (
                !$user->getFromDB($users_id)
                || empty($user->fields['is_active'])
                || !empty($user->fields['is_deleted'])
                || !Target::isUserTargeted($survey, $users_id)
            ) {
                return self::ENCERRADO;
            }

            return self::AGUARDANDO;
        }

        // ---------------------------------------------------- externa
        if ($tipo !== Survey::TYPE_EXTERNAL || !empty($survey->fields['is_anonymous'])) {
            return self::ENCERRADO;
        }

        $email = (string) $linha['email'];

        $convite = $DB->request([
            'SELECT' => ['answers_id'],
            'FROM'   => Invite::getTable(),
            'WHERE'  => ['surveys_id' => $survey->getID(), 'email' => mb_strtolower($email)],
            'LIMIT'  => 1,
        ])->current();

        $answers_id = (int) ($convite['answers_id'] ?? 0);
        $condicoes  = [['respondent_email' => $email]];
        if ($answers_id > 0) {
            $condicoes[] = ['id' => $answers_id];
        }

        // Pelo convite, ou pelo e-mail informado na identificação (quem
        // respondeu pelo link comum, sem o código do convite).
        $n = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => Answer::getTable(),
            'WHERE' => ['surveys_id' => $survey->getID(), 'OR' => $condicoes],
        ])->current();

        if ((int) ($n['cpt'] ?? 0) > 0) {
            return self::RESPONDEU;
        }

        // Tirado da lista de destinatários: não cobra mais.
        $na_lista = false;
        foreach (SurveyMailer::getListaExterna($survey) as $pessoa) {
            if (mb_strtolower($pessoa['email']) === mb_strtolower($email)) {
                $na_lista = true;
                break;
            }
        }

        return $na_lista ? self::AGUARDANDO : self::ENCERRADO;
    }

    /**
     * "Duração da pesquisa" da entidade, em dias (0 = sem limite) -- da
     * entidade que define a pesquisa, com a herança do GLPI.
     */
    public static function getDuracaoDoChamado(int $entities_id): int
    {
        return (int) (MailPolicy::getConfigDaEntidade($entities_id)['duracao'] ?? 0);
    }

    /**
     * A pesquisa aceita lembrete? (A anônima não: ver a classe.)
     */
    public static function aceitaLembrete(Survey $survey): bool
    {
        return !(($survey->fields['type'] ?? '') === Survey::TYPE_EXTERNAL && !empty($survey->fields['is_anonymous']));
    }

    /**
     * Números para a sub-aba "Lembrete".
     *
     * @return array{convidados: int, responderam: int, aguardando: int, encerrados: int, lembretes: int, completos: int}
     */
    public static function getResumo(Survey $survey): array
    {
        global $DB;

        $saida = ['convidados' => 0, 'responderam' => 0, 'aguardando' => 0, 'encerrados' => 0, 'lembretes' => 0, 'completos' => 0];

        if (!$DB->tableExists(self::getTable())) {
            return $saida;
        }

        $maximo = self::getMaximo($survey);

        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['surveys_id' => $survey->getID()]]) as $linha) {
            if ($linha['status'] === self::AGENDADO) {
                continue; // convite do chamado que ainda não saiu
            }
            $saida['convidados']++;
            $saida['lembretes'] += (int) $linha['reminders_sent'];

            $situacao = $linha['status'] === self::AGUARDANDO ? self::situacao($survey, $linha) : (string) $linha['status'];

            if ($situacao === self::RESPONDEU) {
                $saida['responderam']++;
            } elseif ($situacao === self::ENCERRADO) {
                $saida['encerrados']++;
            } elseif ((int) $linha['reminders_sent'] >= $maximo) {
                // Ainda sem resposta, mas já recebeu todos os lembretes.
                $saida['completos']++;
            } else {
                $saida['aguardando']++;
            }
        }

        return $saida;
    }

    public static function getIntervalo(Survey $survey): int
    {
        return max(self::DIAS_MIN, min(self::DIAS_MAX, (int) ($survey->fields['reminder_days'] ?? 3)));
    }

    public static function getMaximo(Survey $survey): int
    {
        return max(self::MAX_MIN, min(self::MAX_MAX, (int) ($survey->fields['reminder_max'] ?? 2)));
    }

    // ------------------------------------------------------------------
    // Ação automática
    // ------------------------------------------------------------------

    /**
     * Espera do convite do chamado, em minutos (sub-aba Convite da
     * pesquisa de chamado). Padrão 15.
     */
    public static function getEsperaConvite(Survey $survey): int
    {
        $min = (int) ($survey->fields['invite_delay'] ?? 15);

        return in_array($min, self::ESPERAS_CONVITE, true) ? $min : 15;
    }

    /**
     * Agenda o convite do chamado para daqui a $minutos (v0.63.0).
     *
     * Quando o chamado é encerrado, a pesquisa aparece na hora no próprio
     * chamado. O e-mail espera: se nesse tempo o requerente avaliar ali,
     * o e-mail não sai (nem os lembretes). Quem envia é a ação automática
     * npsphereConvite (cronNpsphereConvite).
     */
    public static function agendarConvite(Survey $survey, int $tickets_id, int $minutos): void
    {
        global $DB;

        if (!$DB->tableExists(self::getTable()) || $tickets_id <= 0) {
            return;
        }

        $agora = self::agora();
        $chave = self::chaveDoChamado($tickets_id);
        $campos = [
            'tickets_id'         => $tickets_id,
            'users_id'           => TicketLink::getRequerenteId($tickets_id),
            'status'             => self::AGENDADO,
            'date_due'           => date('Y-m-d H:i:s', strtotime($agora . ' +' . max(0, $minutos) . ' minutes')),
            'date_invited'       => null,
            'reminders_sent'     => 0,
            'date_last_reminder' => null,
            'date_mod'           => $agora,
        ];

        $linha = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['surveys_id' => $survey->getID(), 'chave' => $chave],
            'LIMIT'  => 1,
        ])->current();

        if ($linha) {
            $DB->update(self::getTable(), $campos, ['id' => (int) $linha['id']]);
            return;
        }

        $DB->insert(self::getTable(), $campos + [
            'surveys_id'    => $survey->getID(),
            'chave'         => $chave,
            'email'         => '',
            'name'          => '',
            'date_creation' => $agora,
        ]);
    }

    /**
     * Envia os convites de chamado cuja espera acabou -- só para quem
     * ainda não avaliou no próprio chamado.
     *
     * @return int 1 = enviou algo, 0 = nada a fazer
     */
    public static function cronNpsphereConvite($task = null): int
    {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return 0;
        }

        $agora    = self::agora();
        $enviados = 0;
        $pulados  = 0;

        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'status'   => self::AGENDADO,
                'date_due' => ['<=', $agora],
            ],
            'ORDER' => 'date_due ASC',
        ]) as $linha) {
            $tickets_id = (int) $linha['tickets_id'];

            // Chamado reaberto (o GLPI apaga a pesquisa dele) ou apagado:
            // não há o que convidar.
            if (!self::temSatisfacao($tickets_id)) {
                self::marcar((int) $linha['id'], self::ENCERRADO);
                continue;
            }

            // Avaliou no chamado durante a espera: sem e-mail e sem
            // lembrete.
            if (TicketLink::jaRespondido($tickets_id) || self::avaliadoNoGlpi($tickets_id)) {
                self::marcar((int) $linha['id'], self::RESPONDEU);
                $pulados++;
                continue;
            }

            // A MESMA regra do envio imediato: a pesquisa que o chamado
            // usa agora (a entidade pode ter mudado de pesquisa, ou de
            // "Pesquisa externa" para a interna do GLPI).
            $survey = MailPolicy::getPesquisaAplicavel($tickets_id);
            if ($survey === null) {
                self::marcar((int) $linha['id'], self::ENCERRADO);
                continue;
            }

            // enviar() grava o convite (status "aguardando", base do
            // lembrete) na linha desta pesquisa.
            $ok = SurveyMailer::enviar($survey, $tickets_id);
            if ($ok) {
                $enviados++;
            }

            // Agendado para outra pesquisa (a entidade mudou): esta linha
            // não serve mais.
            if (!$ok || $survey->getID() !== (int) $linha['surveys_id']) {
                self::marcar((int) $linha['id'], self::ENCERRADO);
            }
        }

        if ($task !== null) {
            $task->addVolume($enviados);
            if ($enviados > 0 || $pulados > 0) {
                $task->log(sprintf(__('%1$d convite(s) enfileirado(s); %2$d já avaliado(s) no chamado (sem e-mail).', 'npsphere'), $enviados, $pulados));
            }
        }

        return $enviados > 0 ? 1 : 0;
    }

    /** O chamado ainda tem a pesquisa de satisfação do GLPI? */
    private static function temSatisfacao(int $tickets_id): bool
    {
        global $DB;

        $linha = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => 'glpi_ticketsatisfactions',
            'WHERE' => ['tickets_id' => $tickets_id],
        ])->current();

        return (int) ($linha['cpt'] ?? 0) > 0;
    }

    /** A satisfação nativa do GLPI já foi respondida? */
    private static function avaliadoNoGlpi(int $tickets_id): bool
    {
        global $DB;

        $linha = $DB->request([
            'SELECT' => ['date_answered'],
            'FROM'   => 'glpi_ticketsatisfactions',
            'WHERE'  => ['tickets_id' => $tickets_id],
            'LIMIT'  => 1,
        ])->current();

        return !empty($linha['date_answered']);
    }

    /**
     * Descrição na tela Configurar › Ações automáticas.
     *
     * @return array{description: string, parameter?: string}
     */
    public static function cronInfo($name)
    {
        if ($name === self::CRON) {
            return [
                'description' => __('NPSphere — lembrete para quem ainda não respondeu a pesquisa', 'npsphere'),
                'parameter'   => __('Máximo de lembretes por execução', 'npsphere'),
            ];
        }
        if ($name === self::CRON_CONVITE) {
            return [
                'description' => __('NPSphere — convite do chamado depois da espera (só se o requerente não avaliou no chamado)', 'npsphere'),
            ];
        }

        return [];
    }

    /**
     * Envia os lembretes vencidos.
     *
     * @return int 1 = enviou algo, 0 = nada a fazer, -1 = parou no limite
     *             por execução (o resto sai na próxima)
     */
    public static function cronNpsphereLembrete($task = null): int
    {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return 0;
        }

        $limite = max(1, (int) ($task->fields['param'] ?? 100));
        $agora  = self::agoraTs();
        $enviados = 0;
        $falhas   = 0;

        $pesquisas = $DB->request([
            'FROM'  => Survey::getTable(),
            'WHERE' => ['reminder_active' => 1, 'is_active' => 1, 'is_deleted' => 0],
        ]);

        foreach ($pesquisas as $dados) {
            $survey = new Survey();
            if (!$survey->getFromDB((int) $dados['id']) || !self::aceitaLembrete($survey)) {
                continue;
            }

            $intervalo = self::getIntervalo($survey) * DAY_TIMESTAMP;
            $maximo    = self::getMaximo($survey);

            foreach ($DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => [
                    'surveys_id'     => $survey->getID(),
                    'status'         => self::AGUARDANDO,
                    'reminders_sent' => ['<', $maximo],
                ],
                'ORDER' => 'date_invited ASC',
            ]) as $linha) {
                $base = strtotime((string) ($linha['date_last_reminder'] ?: $linha['date_invited']));
                if (!$base || ($agora - $base) < $intervalo) {
                    continue; // ainda não venceu
                }

                $situacao = self::situacao($survey, $linha);
                if ($situacao !== self::AGUARDANDO) {
                    self::marcar((int) $linha['id'], $situacao);
                    continue;
                }

                if ($enviados >= $limite) {
                    $task?->addVolume($enviados);
                    $task?->log(sprintf(__('%d lembrete(s) enfileirado(s); o restante sai na próxima execução.', 'npsphere'), $enviados));
                    return -1;
                }

                $ok = str_starts_with((string) $linha['chave'], 't:')
                    ? SurveyMailer::enviar($survey, (int) $linha['tickets_id'], true)
                    : SurveyMailer::enviarLembrete($survey, $linha);

                if ($ok) {
                    self::contarLembrete((int) $linha['id'], (int) $linha['reminders_sent']);
                    $enviados++;
                } else {
                    $falhas++;
                }
            }
        }

        if ($task !== null) {
            $task->addVolume($enviados);
            if ($enviados > 0 || $falhas > 0) {
                $task->log(sprintf(__('%1$d lembrete(s) enfileirado(s), %2$d falha(s).', 'npsphere'), $enviados, $falhas));
            }
        }

        return $enviados > 0 ? 1 : 0;
    }

    /**
     * Situação da ação automática, para avisar na tela quando ela está
     * desativada (sem ela, nenhum lembrete sai).
     *
     * @return array{id: int, ativa: bool, url: string, ultima: string}|null
     */
    public static function getAcaoAutomatica(string $nome = self::CRON): ?array
    {
        $cron = new \CronTask();
        if (!$cron->getFromDBbyName(self::class, $nome)) {
            return null;
        }

        return [
            'id'     => (int) $cron->getID(),
            'ativa'  => (int) $cron->fields['state'] !== \CronTask::STATE_DISABLE,
            'url'    => \CronTask::getFormURLWithID((int) $cron->getID()),
            'ultima' => (string) ($cron->fields['lastrun'] ?? ''),
            'hourmin' => (int) ($cron->fields['hourmin'] ?? 0),
            'hourmax' => (int) ($cron->fields['hourmax'] ?? 24),
        ];
    }

    /** Cria a ação automática (instalação/atualização; pode repetir). */
    public static function registrarAcaoAutomatica(): void
    {
        \CronTask::register(self::class, self::CRON, HOUR_TIMESTAMP, [
            'state'   => \CronTask::STATE_WAITING,
            'param'   => 100,
            // Lembrete no horário comercial: e-mail de cobrança às 3h da
            // manhã passa a impressão de spam. Ajustável na tela da ação.
            'hourmin' => 8,
            'hourmax' => 18,
            'comment' => __('Envia o lembrete da pesquisa (sub-aba "Lembrete" da aba Template) a quem ainda não respondeu.', 'npsphere'),
        ]);

        // Convite do chamado com espera: a cada minuto, o dia todo -- o
        // e-mail sai no máximo um minuto depois da espera escolhida.
        \CronTask::register(self::class, self::CRON_CONVITE, MINUTE_TIMESTAMP, [
            'state'   => \CronTask::STATE_WAITING,
            'hourmin' => 0,
            'hourmax' => 24,
            'comment' => __('Envia o convite da pesquisa de chamado depois da espera (sub-aba "Convite"), só se o requerente não avaliou no próprio chamado.', 'npsphere'),
        ]);
    }

    private static function agora(): string
    {
        return $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
    }

    private static function agoraTs(): int
    {
        return strtotime(self::agora()) ?: time();
    }
}
