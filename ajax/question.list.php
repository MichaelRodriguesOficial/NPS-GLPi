<?php

/**
 * Devolve apenas o fragmento HTML da listagem de perguntas de uma
 * pesquisa (sem o wrapper de alternância lista/formulário), usado pelo
 * JS do builder para recarregar a lista após salvar/editar uma
 * pergunta sem sair da aba "Pergunta".
 */

use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\Survey;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

Session::checkRight(Question::$rightname, READ);

$surveys_id = (int) ($_GET['surveys_id'] ?? 0);

// Só quem criou a pesquisa (ou o administrador geral) a gerencia.
\GlpiPlugin\Npsphere\Survey::exigirGestao($surveys_id);

$survey = new Survey();
if ($surveys_id > 0 && $survey->getFromDB($surveys_id)) {
    Question::showForSurvey($survey, true);
}
