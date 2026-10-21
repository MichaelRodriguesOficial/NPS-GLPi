<?php

namespace GlpiPlugin\Npsphere;

use Session;

/**
 * Vínculo entre pesquisa e chamado (Fase 8).
 *
 * ESTRATÉGIA: o plugin não intercepta mudanças de status do chamado.
 * Ele se torna o DESTINO da "pesquisa externa" nativa do GLPI,
 * configurada em Entidade > Assistência.
 *
 * Por quê: o GLPI já decide quando criar a pesquisa (encerramento do
 * chamado, com atraso e percentual configuráveis por entidade), já
 * envia a notificação e já registra que aquele chamado tem pesquisa.
 * Duplicar isso significaria competir com o mecanismo nativo -- com
 * risco de duas pesquisas para o mesmo chamado, ou de nenhuma quando
 * as regras divergissem.
 *
 * A URL a configurar na entidade é:
 *   <glpi>/marketplace/npsphere/front/ticket.php?tickets_id=[TICKET_ID]
 */
class TicketLink
{
    /**
     * Pesquisa aplicável a um chamado.
     *
     * Escolhe entre as pesquisas do tipo "vinculada a chamado" que
     * estejam ativas, dentro do prazo e visíveis para a entidade do
     * chamado. Havendo mais de uma, vence a da entidade MAIS ESPECÍFICA
     * (a própria entidade antes de uma entidade-pai recursiva) -- assim
     * uma entidade pode ter sua pesquisa própria sem deixar de herdar a
     * geral quando não tiver.
     */
    public static function getSurveyForTicket(int $entities_id): ?Survey
    {
        global $DB;

        $candidatas = [];

        foreach ($DB->request([
            'FROM'  => Survey::getTable(),
            'WHERE' => [
                'type'       => Survey::TYPE_TICKET,
                'is_active'  => 1,
                'is_deleted' => 0,
            ],
        ]) as $row) {
            $survey = new Survey();
            $survey->fields = $row;

            if (!self::aplicaAEntidade($row, $entities_id)) {
                continue;
            }

            // Prazo e demais regras de disponibilidade: reaproveita as
            // MESMAS verificações do fluxo comum, em vez de reescrevê-las.
            $eligibility = ResponseService::canRespond($survey);
            if (!$eligibility['can'] && $eligibility['reason'] !== null) {
                // "Já respondeu" não desqualifica a pesquisa em si --
                // essa checagem é feita por CHAMADO, mais abaixo.
                if (!self::disponivelPorPeriodo($row)) {
                    continue;
                }
            }

            $candidatas[(int) $row['entities_id']] = $survey;
        }

        if (empty($candidatas)) {
            return null;
        }

        // Preferência pela entidade exata; depois, a mais próxima na árvore
        if (isset($candidatas[$entities_id])) {
            return $candidatas[$entities_id];
        }

        $ancestrais = array_reverse(getAncestorsOf('glpi_entities', $entities_id));
        foreach ($ancestrais as $ancestral) {
            if (isset($candidatas[(int) $ancestral])) {
                return $candidatas[(int) $ancestral];
            }
        }

        return null;
    }

    /**
     * Diagnóstico: por que cada pesquisa do tipo "chamado" foi ou não
     * escolhida para uma entidade.
     *
     * Existe porque "não há pesquisa configurada" tem várias causas
     * possíveis -- entidade diferente, recursividade desmarcada,
     * pesquisa inativa, fora do prazo -- e todas produzem exatamente a
     * mesma tela. Sem isto, investigar vira adivinhação.
     */
    public static function diagnosticar(int $entities_id): array
    {
        global $DB;

        $linhas = [];

        foreach ($DB->request([
            'FROM'  => Survey::getTable(),
            'WHERE' => ['type' => Survey::TYPE_TICKET],
        ]) as $row) {
            $linhas[] = [
                'id'                => (int) $row['id'],
                'nome'              => $row['name'],
                'ativa'             => (bool) $row['is_active'],
                'excluida'          => (bool) $row['is_deleted'],
                'entidade_pesquisa' => (int) $row['entities_id'],
                'recursiva'         => (bool) $row['is_recursive'],
                'entidade_chamado'  => $entities_id,
                'aplica_a_entidade' => self::aplicaAEntidade($row, $entities_id),
                'dentro_do_prazo'   => self::disponivelPorPeriodo($row),
            ];
        }

        return [
            'entidade_do_chamado' => $entities_id,
            'ancestrais'          => array_map('intval', getAncestorsOf('glpi_entities', $entities_id)),
            'pesquisas'           => $linhas,
        ];
    }

    private static function aplicaAEntidade(array $survey, int $entities_id): bool
    {
        $survey_entity = (int) $survey['entities_id'];

        if ($survey_entity === $entities_id) {
            return true;
        }

        // Entidade-pai só alcança as filhas quando a pesquisa é recursiva
        if (empty($survey['is_recursive'])) {
            return false;
        }

        $ancestrais = array_map('intval', getAncestorsOf('glpi_entities', $entities_id));
        return in_array($survey_entity, $ancestrais, true);
    }

    private static function disponivelPorPeriodo(array $survey): bool
    {
        $hoje = date('Y-m-d');

        if (!empty($survey['date_begin']) && substr((string) $survey['date_begin'], 0, 10) > $hoje) {
            return false;
        }
        if (!empty($survey['date_end']) && substr((string) $survey['date_end'], 0, 10) < $hoje) {
            return false;
        }

        return true;
    }

    /**
     * Este chamado já tem resposta registrada?
     *
     * O controle é por CHAMADO, e não por usuário: cada chamado gera no
     * máximo uma avaliação, que é o comportamento da pesquisa nativa.
     */
    public static function jaRespondido(int $tickets_id): bool
    {
        global $DB;

        if ($tickets_id <= 0) {
            return false;
        }

        // Consulta sem resultado não pode virar erro: na dúvida,
        // considera NÃO respondido -- bloquear uma avaliação legítima
        // por causa de uma consulta vazia seria pior do que permitir uma
        // verificação a mais.
        $linha = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => Answer::getTable(),
            'WHERE' => ['tickets_id' => $tickets_id],
        ])->current();

        return is_array($linha) && (int) ($linha['cpt'] ?? 0) > 0;
    }


    /**
     * O usuário atual pode responder a pesquisa deste chamado?
     *
     * Regra: apenas quem é REQUERENTE do chamado. Quem abriu é quem
     * avalia o atendimento -- técnicos e observadores não deveriam
     * responder em nome dele. (Se um dia isso precisar mudar, é aqui.)
     */
    public static function podeResponder(\Ticket $ticket): bool
    {
        $users_id = (int) Session::getLoginUserID();
        if (!$users_id) {
            return false;
        }

        return $ticket->isUser(\CommonITILActor::REQUESTER, $users_id);
    }

    /**
     * URL a configurar em Entidade > Assistência > "Pesquisa externa".
     */
    /**
     * URL a configurar em Entidade > Assistência > "Pesquisa externa".
     *
     * Inclui o ID da PESQUISA quando informado.
     *
     * Sem ele, a escolha recai na resolução automática por entidade --
     * que funciona bem com uma pesquisa por entidade, mas fica ambígua
     * quando há várias: a mesma entidade poderia ter duas pesquisas
     * ativas, e a URL não diria qual usar. Com o ID, a configuração é
     * explícita e cada entidade aponta para a pesquisa que escolheu.
     */
    public static function getExternalUrl(?int $surveys_id = null, int $entities_id = 0): string
    {
        // Endereço COMPLETO (https://servidor/...): é ele que o GLPI usa
        // no link da pesquisa dentro do chamado e na notificação. Desde a
        // 0.51.0 a tela mostrava só o caminho (/marketplace/...), sem o
        // servidor. A base é a "URL da aplicação" da entidade ou a geral,
        // a mesma dos links do e-mail (SurveyMailer::getBaseAbsoluta).
        $url = SurveyMailer::getBaseAbsoluta($entities_id)
            . '/front/ticket.php?tickets_id=[TICKET_ID]';

        if ($surveys_id !== null && $surveys_id > 0) {
            $url .= '&surveys_id=' . $surveys_id;
        }

        return $url;
    }

    /**
     * Pesquisa a usar para um chamado, dando preferência ao ID vindo da
     * URL.
     *
     * O ID só é aceito se a pesquisa for do tipo "vinculada a chamado",
     * estiver ativa e servir à entidade do chamado -- caso contrário
     * bastaria alterar a URL para responder uma pesquisa de outra
     * entidade.
     */
    public static function resolveSurvey(int $entities_id, ?int $surveys_id = null): ?Survey
    {
        if ($surveys_id !== null && $surveys_id > 0) {
            $survey = new Survey();

            if (
                $survey->getFromDB($surveys_id)
                && ($survey->fields['type'] ?? '') === Survey::TYPE_TICKET
                && !empty($survey->fields['is_active'])
                && empty($survey->fields['is_deleted'])
                && self::aplicaAEntidade($survey->fields, $entities_id)
                && self::disponivelPorPeriodo($survey->fields)
            ) {
                return $survey;
            }

            // ID inválido para este chamado: em vez de negar a
            // avaliação, recai na resolução automática -- uma URL
            // desatualizada não deve impedir alguém de responder.
        }

        return self::getSurveyForTicket($entities_id);
    }

    // -----------------------------------------------------------------
    // Resposta pelo e-mail, sem login (v0.54.0)
    // -----------------------------------------------------------------
    //
    // O convite do chamado leva uma CHAVE: a assinatura (HMAC-SHA256) do
    // par chamado + pesquisa, feita com um segredo que só o servidor
    // conhece. Quem tem o link do e-mail responde sem entrar no GLPI; quem
    // troca o número do chamado na URL não tem a chave dele e é recusado.
    //
    // Assinatura, e não uma tabela de códigos: não há o que gravar nem
    // limpar, e convites antigos continuam valendo enquanto o chamado
    // puder ser avaliado.

    /**
     * Chave do link de avaliação do chamado. A mensagem assinada não muda
     * entre versões: os links já enviados continuam valendo.
     */
    public static function chave(int $tickets_id, int $surveys_id): string
    {
        return LinkSigner::assinar('npsphere-chamado:' . $tickets_id . ':' . $surveys_id);
    }

    /** A chave do link confere com este chamado e esta pesquisa? */
    public static function chaveValida($chave, int $tickets_id, int $surveys_id): bool
    {
        if ($tickets_id <= 0 || $surveys_id <= 0) {
            return false;
        }

        return LinkSigner::confere($chave, 'npsphere-chamado:' . $tickets_id . ':' . $surveys_id);
    }

    /** Primeiro requerente do chamado: é em nome dele que a resposta é gravada. */
    public static function getRequerenteId(int $tickets_id): int
    {
        global $DB;

        $linha = $DB->request([
            'SELECT' => ['users_id'],
            'FROM'   => 'glpi_tickets_users',
            'WHERE'  => ['tickets_id' => $tickets_id, 'type' => \CommonITILActor::REQUESTER],
            'ORDER'  => 'id ASC',
            'LIMIT'  => 1,
        ])->current();

        return (int) ($linha['users_id'] ?? 0);
    }

    /**
     * Chamado e pesquisa a partir do link do e-mail (sem login).
     *
     * A pesquisa precisa ser a MESMA assinada na chave: se ela foi
     * desativada ou trocada, o link deixa de valer em vez de gravar a
     * resposta noutra pesquisa.
     *
     * @return array{ticket: ?\Ticket, survey: ?Survey, erro: ?string}
     */
    public static function resolverPorChave(array $p): array
    {
        $tickets_id = (int) ($p['tickets_id'] ?? 0);
        $surveys_id = (int) ($p['surveys_id'] ?? 0);

        $falha = static fn(string $m): array => ['ticket' => null, 'survey' => null, 'erro' => $m];

        if (!self::chaveValida($p['chave'] ?? null, $tickets_id, $surveys_id)) {
            return $falha(__('Link inválido ou expirado.', 'npsphere'));
        }

        $ticket = new \Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return $falha(__('Chamado não encontrado.', 'npsphere'));
        }

        $survey = self::resolveSurvey((int) $ticket->fields['entities_id'], $surveys_id);
        if ($survey === null || $survey->getID() !== $surveys_id) {
            return $falha(__('Esta pesquisa não está mais disponível.', 'npsphere'));
        }

        return ['ticket' => $ticket, 'survey' => $survey, 'erro' => null];
    }
}
