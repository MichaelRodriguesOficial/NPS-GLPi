<?php

namespace GlpiPlugin\Npsphere;

use CommonDBTM;

/**
 * Uma resposta completa de um respondente a uma Pesquisa (todas as
 * perguntas respondidas em uma única submissão).
 *
 * Os valores individuais de cada pergunta ficam em AnswerValue
 * (um-para-muitos), não numa única coluna JSON -- conforme exigido
 * pela seção 24 do escopo, para permitir consultas/relatórios
 * eficientes por pergunta.
 */
class Answer extends CommonDBTM
{
    public static $rightname = 'plugin_npsphere_survey';

    public static function getTypeName($nb = 0)
    {
        return _n('Resposta', 'Respostas', $nb, 'npsphere');
    }

    public static function getIcon()
    {
        return 'ti ti-clipboard-check';
    }

    /**
     * Direitos separados dos de Survey/Question: qualquer usuário
     * autenticado pode CRIAR uma resposta (é o respondente, não um
     * administrador de pesquisas) -- ver AnswerService::canRespond().
     * Visualizar/exportar respostas continua exigindo
     * plugin_npsphere_survey.
     */
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
