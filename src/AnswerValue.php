<?php

namespace GlpiPlugin\Npsphere;

use CommonDBChild;

/**
 * Valor de resposta de UMA pergunta dentro de UMA resposta completa
 * (Answer). Uma linha por pergunta respondida.
 */
class AnswerValue extends CommonDBChild
{
    public static $itemtype = Answer::class;
    public static $items_id = 'answers_id';

    public static $rightname = 'plugin_npsphere_survey';

    public static function getTypeName($nb = 0)
    {
        return _n('Valor de resposta', 'Valores de resposta', $nb, 'npsphere');
    }

    public static function canCreate(): bool
    {
        // Sempre true de propósito: quem pode responder NÃO é decidido
        // por um direito de perfil do GLPI. Uma resposta pode vir de um
        // usuário autenticado (front/answer.form.php) OU de um visitante
        // anônimo via link público (front/public.php), que por definição
        // não tem perfil algum. A autorização real acontece antes, em
        // PublicAccess::resolve() e ResponseService::canRespond().
        return true;
    }
}
