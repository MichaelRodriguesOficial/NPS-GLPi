<?php

/**
 * Verifica, ANTES de o visitante preencher a pesquisa inteira, se o
 * e-mail/telefone informado já respondeu.
 *
 * É apenas conveniência: avisar na primeira etapa em vez de deixar a
 * pessoa responder tudo para só então descobrir que não vale. A
 * validação que de fato bloqueia continua sendo a do envio, em
 * front/public.php -- este endpoint pode ser ignorado por quem
 * manipular o navegador, e isso não é problema.
 *
 * Liberado para acesso anônimo no setup.php (mesma estratégia da página
 * pública), e responde apenas true/false: nunca revela nomes, e-mails
 * ou qualquer dado de quem já respondeu.
 *
 * Implementado como GET, não POST: é uma consulta de leitura pura (não
 * grava nada), então não precisa de token CSRF -- e, como já
 * observamos neste projeto, POST para certos caminhos de plugin retorna
 * 403 nesta instalação, enquanto GET funciona de forma confiável.
 */

use GlpiPlugin\Npsphere\PublicAccess;
use GlpiPlugin\Npsphere\RespondentIdentity;

// v0.65.0: limite de requisições por IP nas rotas sem login.
\GlpiPlugin\Npsphere\RateLimit::proteger(\GlpiPlugin\Npsphere\RateLimit::PAGINA, true);

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$resolved = PublicAccess::resolve($_GET['token'] ?? null);
$survey   = $resolved['survey'];

if ($survey === null) {
    http_response_code(404);
    echo json_encode(['already_answered' => false]);
    exit;
}

// Só faz sentido checar quando a pesquisa aceita uma resposta por pessoa
// e exige identificação.
if (
    !empty($survey->fields['allow_multiple_answers'])
    || !RespondentIdentity::isRequiredFor($survey)
) {
    echo json_encode(['already_answered' => false]);
    exit;
}

$check = RespondentIdentity::validate($_GET);

echo json_encode([
    'already_answered' => PublicAccess::hasAnsweredByIdentity(
        $survey->getID(),
        $check['data']['respondent_email'] ?? '',
        $check['data']['respondent_phone'] ?? ''
    ),
]);
