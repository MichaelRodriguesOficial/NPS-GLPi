<?php

/**
 * Devolve um token CSRF novo (texto puro), usado pelo JS do plugin
 * para renovar periodicamente os tokens embutidos nos formulários de
 * páginas que podem ficar abertas por muito tempo antes do envio
 * (ex.: pesquisa "uma pergunta por etapa" com várias perguntas, ou o
 * builder de perguntas ficando aberto entre reordenações).
 *
 * Causa raiz do padrão observado ("só funciona na segunda tentativa",
 * "403 ao enviar depois de preencher várias perguntas"): o token CSRF
 * embutido na página no carregamento expira por tempo decorrido, não
 * só por uso -- então qualquer ação demorada o suficiente falha na
 * primeira tentativa (a segunda funciona porque, nesse meio tempo,
 * algum recarregamento de página já gerou um token novo).
 */

use Glpi\Application\View\TemplateRenderer;

Session::checkLoginUser();

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

TemplateRenderer::getInstance()->display('@npsphere/csrf_token.html.twig', []);
