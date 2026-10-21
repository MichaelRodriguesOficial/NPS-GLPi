<?php

/**
 * Fragmento HTML da aba "Resultados", para o JS recarregar os números
 * sempre que o usuário volta a essa aba (o GLPI guarda o conteúdo já
 * carregado das abas no DOM).
 */

use GlpiPlugin\Npsphere\Survey;
use GlpiPlugin\Npsphere\SurveyResults;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

Session::checkRight(Survey::$rightname, READ);

$surveys_id = (int) ($_GET['surveys_id'] ?? 0);

// Só quem criou a pesquisa (ou o administrador geral) a gerencia.
\GlpiPlugin\Npsphere\Survey::exigirGestao($surveys_id);

$survey = new Survey();
if ($surveys_id > 0 && $survey->getFromDB($surveys_id)) {
    SurveyResults::showForSurvey($survey);
}
