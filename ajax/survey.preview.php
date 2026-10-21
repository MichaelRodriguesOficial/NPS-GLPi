<?php

/**
 * Devolve o fragmento HTML da aba "Visualizar" de uma pesquisa.
 *
 * Usado pelo JS para recarregar essa aba sempre que o usuário volta a
 * ela: o GLPI mantém o conteúdo já carregado das abas no DOM, então,
 * sem isso, a aba reaparecia exatamente no estado em que foi deixada
 * (ex.: parada na tela de "Obrigado pela sua avaliação!" ou no meio de
 * um preenchimento antigo).
 */

use GlpiPlugin\Npsphere\Survey;
use GlpiPlugin\Npsphere\SurveyPreview;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

Session::checkLoginUser();

$surveys_id = (int) ($_GET['surveys_id'] ?? 0);

// Só quem criou a pesquisa (ou o administrador geral) a gerencia.
\GlpiPlugin\Npsphere\Survey::exigirGestao($surveys_id);

$survey = new Survey();
if ($surveys_id > 0 && $survey->getFromDB($surveys_id)) {
    SurveyPreview::showForSurvey($survey);
}
