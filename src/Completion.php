<?php

namespace GlpiPlugin\Npsphere;

use CommonDBTM;
use Session;

/**
 * Registro de que um usuário concluiu uma pesquisa.
 *
 * Existe separado da resposta (Answer) para resolver um conflito real:
 * numa pesquisa ANÔNIMA a resposta é gravada sem users_id -- é o que
 * garante o anonimato -- e por isso não havia como saber que aquela
 * pessoa já respondeu. O aviso obrigatório então voltava a aparecer
 * indefinidamente.
 *
 * Aqui guardamos apenas o par (pesquisa, usuário, data). Não há
 * ligação com o conteúdo respondido: saber QUE alguém respondeu não
 * revela O QUE respondeu, então o anonimato continua intacto.
 */
class Completion extends CommonDBTM
{
    public static $rightname = 'plugin_npsphere_survey';

    public static function getTypeName($nb = 0)
    {
        return _n('Conclusão', 'Conclusões', $nb, 'npsphere');
    }

    /**
     * Marca que o usuário concluiu esta pesquisa.
     *
     * Silencioso e idempotente: registrar duas vezes não é erro, e uma
     * falha aqui nunca deve impedir a resposta de ser gravada.
     */
    public static function register(int $surveys_id, ?int $users_id = null): void
    {
        $users_id = $users_id ?? (int) Session::getLoginUserID();
        if ($surveys_id <= 0 || !$users_id) {
            return;
        }

        if (self::hasCompleted($surveys_id, $users_id)) {
            return;
        }

        $completion = new self();
        $completion->add([
            'surveys_id' => $surveys_id,
            'users_id'   => $users_id,
        ]);
    }

    public static function hasCompleted(int $surveys_id, ?int $users_id = null): bool
    {
        global $DB;

        $users_id = $users_id ?? (int) Session::getLoginUserID();
        if ($surveys_id <= 0 || !$users_id) {
            return false;
        }

        // Defensivo: se a migração ainda não rodou, não derruba a tela.
        if (!$DB->tableExists(self::getTable())) {
            return false;
        }

        $count = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => self::getTable(),
            'WHERE' => ['surveys_id' => $surveys_id, 'users_id' => $users_id],
        ])->current()['cpt'];

        return (int) $count > 0;
    }
}
