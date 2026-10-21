<?php

namespace GlpiPlugin\Npsphere;

use CommonGLPI;
use Session;

/**
 * Entrada de menu "Minhas pesquisas" em Ferramentas.
 *
 * Existe separada de Survey porque o menu principal do plugin fica em
 * Administração e exige o direito administrativo -- que o respondente
 * comum não tem. Esta entrada é visível para qualquer usuário
 * autenticado.
 */
class MySurveysMenu extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Minhas pesquisas', 'npsphere');
    }

    public static function getIcon()
    {
        return 'ti ti-clipboard-check';
    }

    public static function getMenuName()
    {
        return self::getTypeName();
    }

    public static function getMenuContent()
    {
        // Mesma razão do Survey: o GLPI monta o menu a partir daqui, e
        // não a partir de canView(). Sem esta verificação o item
        // continuava listado em Ferramentas para quem não tem o
        // direito de responder pesquisas.
        if (!self::canView()) {
            return false;
        }

        return [
            'title' => self::getTypeName(),
            'page'  => \Plugin::getWebDir('npsphere', false) . '/front/mysurveys.php',
            'icon'  => self::getIcon(),
        ];
    }

    /** Direito que controla quem pode responder pesquisas */
    public static $rightname = 'plugin_npsphere_answer';

    /**
     * Quem tem o direito "Minhas pesquisas" vê a tela.
     *
     * É um direito PRÓPRIO, separado do de gerenciar pesquisas:
     * responder não é tarefa de administração, e o perfil de
     * autoatendimento -- que é quem mais responde -- não tem (nem
     * deveria ter) acesso às telas de gestão.
     */
    public static function canView(): bool
    {
        return Session::getLoginUserID() !== false && self::hasAnswerRight();
    }

    /**
     * O perfil ativo pode responder pesquisas?
     *
     * Consulta a SESSÃO e, se o direito não estiver lá, o BANCO.
     *
     * O motivo da segunda consulta: direitos de plugin criados depois
     * que uma sessão foi aberta não entram nela -- e, no
     * autoatendimento, o resultado era um beco sem saída. O direito
     * aparecia concedido na tela de Perfis (valor 1 no banco), mas a
     * tela negava acesso, e nem reabrir a sessão resolvia de forma
     * confiável.
     *
     * O banco é a fonte de verdade do direito; a sessão é só um cache.
     * Consultar o cache primeiro preserva o desempenho no caso normal.
     */
    public static function hasAnswerRight(): bool
    {
        if (Session::haveRight(self::$rightname, READ)) {
            return true;
        }

        global $DB;

        $profiles_id = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if ($profiles_id <= 0 || !$DB || !$DB->tableExists('glpi_profilerights')) {
            return false;
        }

        foreach ($DB->request([
            'SELECT' => ['rights'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => ['profiles_id' => $profiles_id, 'name' => self::$rightname],
        ]) as $row) {
            if (((int) $row['rights'] & READ) === READ) {
                // Preenche o cache da sessão, para as próximas
                // verificações desta requisição e das seguintes não
                // precisarem consultar o banco de novo.
                $_SESSION['glpiactiveprofile'][self::$rightname] = (int) $row['rights'];
                return true;
            }
        }

        return false;
    }
}
