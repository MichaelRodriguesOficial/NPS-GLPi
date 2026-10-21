<?php

/**
 * Lista de pesquisas (motor de busca padrão do GLPI).
 */

use GlpiPlugin\Npsphere\Survey;

// GLPI 11: o bootstrap (sessão, autoload, permissões básicas) já é feito
// automaticamente pelo LegacyFileLoadController antes deste arquivo ser
// incluído -- não é mais necessário (nem correto) incluir inc/includes.php
// manualmente. Ver: Upgrade guide GLPI 11.0, seção "Includes".

Session::checkRight(Survey::$rightname, READ);

Html::header(
    Survey::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'admin',
    Survey::class,
    'survey'
);

Search::show(Survey::class);

Html::footer();
