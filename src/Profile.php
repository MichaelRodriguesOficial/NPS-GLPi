<?php

namespace GlpiPlugin\Npsphere;

use CommonDBTM;
use CommonGLPI;
use Profile as GlpiProfile;
use Html;
use Session;

/**
 * Gerencia a exibição/edição dos direitos do plugin dentro da tela
 * padrão de Perfis do GLPI (Administração > Perfis).
 */
class Profile extends CommonDBTM
{
    public static $rightname = 'profile';

    public static function getIcon()
    {
        return 'ti ti-mood-smile-beam';
    }

    public static function getTypeName($nb = 0)
    {
        return __('Pesquisas de satisfação', 'npsphere');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof GlpiProfile && $item->getField('id')) {
            return self::createTabEntry(self::getTypeName());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof GlpiProfile && $item->getField('id')) {
            return self::showForProfile($item->getID());
        }
        return true;
    }

    /**
     * Lista de direitos definidos pelo plugin.
     * Usada na instalação (registro em ProfileRight) e na exibição
     * do formulário de permissões.
     *
     * @return array
     */
    public static function getAllRights()
    {
        return [
            [
                'itemtype' => Survey::class,
                'label'    => __('Pesquisas de satisfação', 'npsphere'),
                'field'    => 'plugin_npsphere_survey',
            ],
            [
                // Direito PRÓPRIO do painel consolidado.
                //
                // Separado de "Pesquisas de satisfação" porque são
                // públicos diferentes: um gestor pode acompanhar os
                // indicadores sem poder criar ou editar pesquisas. O
                // itemtype é distinto dos demais -- a matriz do GLPI usa
                // o itemtype como chave de linha, e dois direitos com o
                // mesmo itemtype se sobrepõem.
                'itemtype' => DashboardMenu::class,
                'label'    => __('Painel de satisfação', 'npsphere'),
                'field'    => 'plugin_npsphere_dashboard',
                'rights'   => [READ => __('Read')],
            ],
            [
                // Direito SEPARADO para responder pesquisas.
                //
                // O itemtype precisa ser DIFERENTE do direito acima: a
                // matriz do GLPI usa o itemtype como chave de linha, e
                // dois direitos com o mesmo itemtype se sobrepõem --
                // o segundo não apareceria nem seria gravado.
                'itemtype' => MySurveysMenu::class,
                'label'    => __('Minhas pesquisas', 'npsphere'),
                'field'    => 'plugin_npsphere_answer',
                'rights'   => [READ => __('Read')],
            ],
        ];
    }

    public static function showForProfile($profiles_id = 0)
    {
        $profile = new GlpiProfile();
        $profile->getFromDB($profiles_id);

        $canedit = Session::haveRightsOr('profile', [CREATE, UPDATE, PURGE]);

        // Perfis de interface SIMPLIFICADA (autoatendimento) só podem
        // RESPONDER pesquisas. As telas de gestão vivem em
        // Administração, que essa interface nem exibe -- oferecer o
        // direito ali seria prometer um acesso que não se concretiza.
        //
        // Por isso a linha "Pesquisas de satisfação" nem é exibida
        // nesses perfis: o que não pode ser concedido não deve aparecer
        // como se pudesse.
        $is_helpdesk = (($profile->fields['interface'] ?? '') === 'helpdesk');

        $rights = self::getAllRights();
        if ($is_helpdesk) {
            $rights = array_values(array_filter(
                $rights,
                static fn(array $right): bool => $right['field'] === 'plugin_npsphere_answer'
            ));
        }

        // Matriz de direitos NATIVA do GLPI, em vez de um formulário
        // próprio. Assim os controles ficam idênticos aos das demais
        // abas de Perfis (inclusive marcar/desmarcar a coluna inteira),
        // e a gravação passa a ser feita pelo próprio GLPI -- sem
        // precisar reimplementar leitura, escrita e token CSRF.
        echo "<div class='firstbloc'>";
        if ($canedit) {
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
        }

        if ($is_helpdesk) {
            echo "<div class='alert alert-info'>"
                . __('Este perfil usa a interface simplificada: só é possível conceder o direito de responder pesquisas. A gestão de pesquisas fica em Administração, menu que essa interface não exibe.', 'npsphere')
                . "</div>";
        }

        $profile->displayRightsChoiceMatrix($rights, [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => __('Pesquisas de satisfação', 'npsphere'),
        ]);

        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
            echo "</div>";
            Html::closeForm();
        }
        echo "</div>";
    }
}
