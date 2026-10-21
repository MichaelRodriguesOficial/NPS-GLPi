<?php

/**
 * Token CSRF novo para a pesquisa interna respondida pelo link do
 * e-mail. Autorizado pela chave assinada da pessoa
 * (Target::resolverPorChave), já que quem responde não está logado.
 * Liberado sem login no setup.php.
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\Target;

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (Target::resolverPorChave($_GET)['erro'] !== null) {
    http_response_code(403);
    exit;
}

TemplateRenderer::getInstance()->display('@npsphere/csrf_token.html.twig', []);
