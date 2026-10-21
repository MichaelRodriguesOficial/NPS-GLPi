<?php

namespace GlpiPlugin\Npsphere;

use CommonGLPI;
use Session;

/**
 * Entrada de menu do painel consolidado (Fase 10).
 *
 * Fica na seção **Gestão**, e não em Administração, por uma razão
 * prática: o hook de menu do GLPI aceita UMA entrada por seção, e
 * "admin" já é ocupada pela gestão de pesquisas. Tentativas anteriores
 * de acrescentar uma segunda chave na mesma seção -- ou de embutir o
 * painel como link com ícone -- simplesmente não apareciam, sem gerar
 * erro algum.
 */
class DashboardMenu extends CommonGLPI
{
    public static $rightname = 'plugin_npsphere_dashboard';

    public static function getTypeName($nb = 0)
    {
        return __('Painel de satisfação', 'npsphere');
    }

    public static function getIcon()
    {
        return 'ti ti-chart-histogram';
    }

    public static function getMenuName()
    {
        return self::getTypeName();
    }

    /**
     * Mesmo direito de quem analisa resultados: é ferramenta de gestão,
     * não de resposta.
     */
    public static function canView(): bool
    {
        return self::temDireito();
    }

    /**
     * O perfil ativo pode ver o painel?
     *
     * Consulta a SESSÃO e, se o direito não estiver lá, o BANCO -- mesma
     * estratégia de "Minhas pesquisas". Direitos de plugin criados
     * depois que a sessão foi aberta não entram nela, e sem esta
     * segunda consulta o direito aparecia concedido em Perfis mas o
     * painel continuava inacessível.
     */
    public static function temDireito(): bool
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
                $_SESSION['glpiactiveprofile'][self::$rightname] = (int) $row['rights'];
                return true;
            }
        }

        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getMenuContent()
    {
        // O GLPI monta o menu a partir DESTE método; retornar false
        // remove o item de quem não tem o direito.
        if (!self::canView()) {
            return false;
        }

        $pagina = \Plugin::getWebDir('npsphere', false) . '/front/dashboard.php';

        return [
            'title' => self::getTypeName(),
            'page'  => $pagina,
            'icon'  => self::getIcon(),
            'links' => ['search' => $pagina],
        ];
    }
}
