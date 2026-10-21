<?php

namespace GlpiPlugin\Npsphere;

use CommonDBTM;

/**
 * Convite pessoal da pesquisa externa: um por destinatário.
 *
 * O link público é o MESMO para todo mundo, então não diz quem clicou.
 * Cada convite enviado por e-mail leva, além do token da pesquisa, o
 * código desta linha (&convite=...). Com ele:
 *
 *  - a resposta fica ligada à pessoa (nome e e-mail da lista);
 *  - clicar de novo, ou abrir o link outra vez, ALTERA a resposta em
 *    vez de criar outra -- um convite, uma resposta;
 *  - a opção clicada no e-mail pode ser gravada direto (ver
 *    ajax/public.oneclick.php), sem o risco de uma pessoa gerar várias
 *    respostas clicando várias vezes.
 *
 * Reenviar para o mesmo e-mail reaproveita o convite: o link antigo e o
 * novo valem a mesma resposta.
 */
class Invite extends CommonDBTM
{
    public static $rightname = 'plugin_npsphere_survey';

    public static function getTypeName($nb = 0)
    {
        return _n('Convite', 'Convites', $nb, 'npsphere');
    }

    /** Escrever direto na tabela: quem responde não tem perfil no GLPI. */
    public static function canCreate(): bool
    {
        return true;
    }

    /**
     * Convite desta pessoa nesta pesquisa; cria se ainda não existir.
     *
     * @return string o código do convite (64 caracteres hexadecimais)
     */
    public static function obterToken(int $surveys_id, string $email, string $nome): string
    {
        global $DB;

        $email = trim($email);
        $chave = mb_strtolower($email);
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        $linha = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['surveys_id' => $surveys_id, 'email' => $chave],
            'LIMIT' => 1,
        ])->current();

        if ($linha) {
            $DB->update(self::getTable(), [
                'name'      => $nome !== '' ? $nome : (string) $linha['name'],
                'date_sent' => $agora,
                'date_mod'  => $agora,
            ], ['id' => $linha['id']]);

            return (string) $linha['token'];
        }

        $token = bin2hex(random_bytes(32));

        $DB->insert(self::getTable(), [
            'surveys_id'    => $surveys_id,
            'email'         => $chave,
            'name'          => $nome,
            'token'         => $token,
            'answers_id'    => 0,
            'date_sent'     => $agora,
            'date_creation' => $agora,
            'date_mod'      => $agora,
        ]);

        return $token;
    }

    /**
     * Convite a partir do código do link, SÓ se for desta pesquisa.
     *
     * Código fora do formato ou de outra pesquisa vale como "sem
     * convite": a página abre normalmente, como o link público comum.
     *
     * @return array<string, mixed>|null
     */
    public static function resolver(Survey $survey, $token): ?array
    {
        global $DB;

        // Pesquisa anônima não usa convite pessoal (ver SurveyMailer).
        if (!empty($survey->fields['is_anonymous'])) {
            return null;
        }

        $token = is_string($token) ? trim($token) : '';
        if (!preg_match('/^[a-f0-9]{64}$/', $token) || !$DB->tableExists(self::getTable())) {
            return null;
        }

        $linha = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['token' => $token, 'surveys_id' => $survey->getID()],
            'LIMIT' => 1,
        ])->current();

        if (!$linha || !hash_equals((string) $linha['token'], $token)) {
            return null;
        }

        // Resposta apagada pelo administrador: o convite volta a valer
        // como novo, em vez de apontar para uma resposta que não existe.
        if ((int) $linha['answers_id'] > 0) {
            $existe = $DB->request([
                'COUNT' => 'cpt',
                'FROM'  => Answer::getTable(),
                'WHERE' => ['id' => (int) $linha['answers_id'], 'surveys_id' => $survey->getID()],
            ])->current();

            if ((int) ($existe['cpt'] ?? 0) === 0) {
                $linha['answers_id'] = 0;
            }
        }

        return $linha;
    }

    public static function vincularResposta(int $invites_id, int $answers_id): void
    {
        global $DB;

        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        $DB->update(self::getTable(), [
            'answers_id'    => $answers_id,
            'date_answered' => $agora,
            'date_mod'      => $agora,
        ], ['id' => $invites_id]);
    }

    /**
     * Respostas já gravadas, no formato do formulário (answer_ID =>
     * valor), para reabrir o formulário com o que a pessoa respondeu.
     *
     * @return array<string, mixed>
     */
    public static function getRespostasGravadas(int $answers_id, int $surveys_id): array
    {
        global $DB;

        if ($answers_id <= 0) {
            return [];
        }

        $perguntas = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'type'],
            'FROM'   => Question::getTable(),
            'WHERE'  => ['surveys_id' => $surveys_id],
        ]) as $q) {
            $perguntas[(int) $q['id']] = (string) $q['type'];
        }

        $saida = [];
        foreach ($DB->request([
            'FROM'  => AnswerValue::getTable(),
            'WHERE' => ['answers_id' => $answers_id],
        ]) as $v) {
            $qid  = (int) $v['questions_id'];
            $tipo = $perguntas[$qid] ?? '';
            $bruto = (string) ($v['value'] ?? '');

            $saida['answer_' . $qid] = $tipo === 'multiple_choice'
                ? array_values(array_filter(array_map('trim', explode(' | ', $bruto)), 'strlen'))
                : $bruto;
        }

        return $saida;
    }
}
