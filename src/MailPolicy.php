<?php

namespace GlpiPlugin\Npsphere;

/**
 * Decide DE QUEM é a notificação de satisfação de um chamado (Fase 9).
 *
 * Só existe uma regra, e ela vale para os dois lados:
 *
 *   pesquisa do plugin ATIVA para a entidade
 *   + entidade configurada como "Pesquisa EXTERNA"
 *   → a notificação é NOSSA (a nativa é cancelada)
 *
 *   caso contrário
 *   → a notificação é do GLPI (não interferimos)
 *
 * A segunda linha é tão importante quanto a primeira: desativar a
 * pesquisa do plugin, ou voltar a entidade para "Pesquisa interna",
 * devolve o comportamento nativo sem exigir nenhuma outra ação. Quem
 * desliga o plugin não pode ficar sem notificação nenhuma.
 */
class MailPolicy
{
    /**
     * Valor de `inquest_config` para pesquisa EXTERNA no GLPI.
     *
     * Documentado em CommonITILSatisfaction: 1 = interna, 2 = externa.
     */
    private const TIPO_EXTERNA = 2;

    /**
     * A notificação deste chamado é do plugin?
     */
    public static function usarNotificacaoDoPlugin(int $tickets_id): bool
    {
        return self::getPesquisaAplicavel($tickets_id) !== null;
    }

    /**
     * Pesquisa do plugin que deve notificar este chamado, se houver.
     *
     * Devolve null -- e a notificação segue sendo a nativa -- quando:
     *  - não há pesquisa do plugin ativa para a entidade;
     *  - a entidade não está configurada como "Pesquisa externa";
     *  - o chamado já foi avaliado.
     */
    public static function getPesquisaAplicavel(int $tickets_id): ?Survey
    {
        if ($tickets_id <= 0 || !class_exists('\\Ticket')) {
            return null;
        }

        $ticket = new \Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return null;
        }

        $entities_id = (int) $ticket->fields['entities_id'];

        if (!self::entidadeUsaPesquisaExterna($entities_id)) {
            return null;
        }

        // A MESMA pesquisa que o chamado mostra: a da URL configurada na
        // entidade (&surveys_id=N), com a resolução automática só quando
        // a URL não diz qual. Antes o e-mail usava sempre a automática --
        // com duas pesquisas de chamado, o chamado mostrava uma e o e-mail
        // levava a outra.
        $config = self::getConfigDaEntidade($entities_id);
        $survey = TicketLink::resolveSurvey($entities_id, $config['surveys_id'] ?? null);
        if ($survey === null) {
            return null;
        }

        // Já avaliado: não há convite a enviar. Acontece quando o
        // chamado é reaberto e encerrado de novo.
        if (TicketLink::jaRespondido($tickets_id)) {
            return null;
        }

        return $survey;
    }

    /**
     * A entidade está configurada como "Pesquisa externa"?
     *
     * Lido direto da tabela de entidades, subindo a árvore enquanto o
     * valor indicar herança (negativo) -- o mesmo caminho usado para a
     * escala da nota, pelo mesmo motivo: o acessor do GLPI devolvia o
     * valor padrão em vez do configurado.
     */
    public static function entidadeUsaPesquisaExterna(int $entities_id): bool
    {
        global $DB;

        if (!$DB->tableExists('glpi_entities')) {
            return false;
        }

        $atual = $entities_id;
        $visitadas = [];

        // Limite de profundidade: evita laço infinito caso a árvore de
        // entidades esteja inconsistente.
        while ($atual >= 0 && count($visitadas) < 20 && !isset($visitadas[$atual])) {
            $visitadas[$atual] = true;

            $achou = false;
            foreach ($DB->request([
                'SELECT' => ['inquest_config', 'entities_id'],
                'FROM'   => 'glpi_entities',
                'WHERE'  => ['id' => $atual],
            ]) as $row) {
                $achou = true;
                $config = $row['inquest_config'] ?? null;

                if ($config !== null && (int) $config >= 0) {
                    return (int) $config === self::TIPO_EXTERNA;
                }

                $atual = (int) ($row['entities_id'] ?? -1);
            }

            if (!$achou) {
                break;
            }
        }

        return false;
    }

    /**
     * Configuração de pesquisa de satisfação que vale para a entidade,
     * para mostrar na aba Template ("quando e para quem o convite sai").
     *
     * Atraso e percentual são herdados JUNTO com o tipo, como no GLPI:
     * vêm da mesma entidade de onde saiu o `inquest_config`.
     *
     * @return array{externa: bool, atraso: int, percentual: int, entities_id: int, entidade: string, url: string, surveys_id: ?int}|null
     */
    public static function getConfigDaEntidade(int $entities_id): ?array
    {
        global $DB;

        if (!$DB->tableExists('glpi_entities')) {
            return null;
        }

        $atual = $entities_id;
        $visitadas = [];

        while ($atual >= 0 && count($visitadas) < 20 && !isset($visitadas[$atual])) {
            $visitadas[$atual] = true;

            $row = $DB->request([
                'SELECT' => ['id', 'completename', 'entities_id', 'inquest_config', 'inquest_delay', 'inquest_rate', 'inquest_duration', 'inquest_URL'],
                'FROM'   => 'glpi_entities',
                'WHERE'  => ['id' => $atual],
            ])->current();

            if (!$row) {
                return null;
            }

            $config = $row['inquest_config'] ?? null;
            if ($config !== null && (int) $config >= 0) {
                $url = (string) ($row['inquest_URL'] ?? '');

                return [
                    'externa'     => (int) $config === self::TIPO_EXTERNA,
                    'atraso'      => max(0, (int) ($row['inquest_delay'] ?? 0)),
                    'percentual'  => max(0, (int) ($row['inquest_rate'] ?? 0)),
                    // "Duração da pesquisa" em dias (0 = sem limite): passado
                    // o prazo, o GLPI considera a pesquisa expirada. Vem da
                    // MESMA entidade que define a pesquisa, como no GLPI
                    // (Entity::getUsedConfig('inquest_config', ..., 'inquest_duration')).
                    'duracao'     => max(0, (int) ($row['inquest_duration'] ?? 0)),
                    'entities_id' => (int) $row['id'],
                    'entidade'    => (string) ($row['completename'] ?? ''),
                    'url'         => $url,
                    // Pesquisa escolhida na URL da entidade (&surveys_id=N);
                    // null quando a URL não diz qual.
                    'surveys_id'  => preg_match('/[?&]surveys_id=(\d+)/', $url, $m) ? (int) $m[1] : null,
                ];
            }

            $atual = (int) ($row['entities_id'] ?? -1);
        }

        return null;
    }
}
