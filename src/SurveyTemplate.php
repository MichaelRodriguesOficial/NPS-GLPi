<?php

namespace GlpiPlugin\Npsphere;

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;

/**
 * Aba "Template" no formulário da Pesquisa (Fase 9).
 *
 * O e-mail do convite tinha nascido como um cartão a mais no formulário
 * principal, junto das instruções da pesquisa de chamado. Com a lista de
 * destinatários e o botão de envio, o bloco cresceu e disputava atenção
 * com os campos da pesquisa. Movido para uma aba própria, cada tela faz
 * uma coisa só.
 *
 * A aba só existe para pesquisas JÁ SALVAS. Antes de salvar, o id ainda
 * é zero -- a aba apareceria sem conseguir guardar o que fosse digitado.
 */
class SurveyTemplate extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Template', 'npsphere');
    }

    public static function getIcon()
    {
        return 'ti ti-mail';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Survey && $item->getID()) {
            return self::createTabEntry(self::getTypeName());
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Survey && $item->getID()) {
            self::showForSurvey($item);
        }

        return true;
    }

    public static function showForSurvey(Survey $survey): void
    {
        // Marcadores e textos padrão variam por tipo: pesquisa interna
        // não tem {{chamado_id}}, por exemplo.
        $tipo  = (string) ($survey->fields['type'] ?? Survey::TYPE_INTERNAL);
        $ajax  = \Plugin::getWebDir('npsphere', true) . '/ajax';
        $padrao = SurveyMailer::getCorpoPadrao($tipo, $survey);
        $perguntas = SurveyMailer::getPerguntasComMarcador($survey);

        // Campo "Mensagem" nunca abre vazio: sem texto gravado, já vem com
        // o modelo padrão, para a pessoa partir de algo pronto e ver na
        // hora como o e-mail fica.
        $corpo = trim((string) ($survey->fields['mail_body'] ?? ''));

        TemplateRenderer::getInstance()->display('@npsphere/template.html.twig', [
            'item'            => $survey,
            'mail_marcadores' => SurveyMailer::getMarcadores($tipo, $survey),
            'mail_exemplos'   => self::exemplosEmTexto($survey, $perguntas),
            // Sub-aba Variáveis: as do relatório e do aviso (v0.61.2).
            'vars_gestor'     => [
                [
                    'titulo'     => __('Relatório diário', 'npsphere'),
                    'icone'      => 'ti ti-report-analytics',
                    'marcadores' => DailyReport::getMarcadoresRelatorio(),
                    'blocos'     => DailyReport::getBlocosRelatorio(),
                    'links'      => __('Os links "Ver resposta" e "Abrir os resultados" só aparecem para quem abre a pesquisa no GLPI, qualquer que seja o modelo.', 'npsphere'),
                ],
                [
                    'titulo'     => __('Aviso imediato de nota baixa', 'npsphere'),
                    'icone'      => 'ti ti-bell-exclamation',
                    'marcadores' => DailyReport::getMarcadoresAviso(),
                    'blocos'     => DailyReport::getBlocosAviso(),
                    'links'      => __('O botão "Ver resposta no GLPI" só aparece para quem abre a pesquisa no GLPI, qualquer que seja o modelo.', 'npsphere'),
                ],
            ],
            'total_perguntas' => count($perguntas),
            // v0.64.0: o aviso "perguntas dentro do e-mail" só aparece
            // quando há mais de uma pergunta E a mensagem usa alguma.
            'perguntas_no_email' => count(array_filter(
                array_keys($perguntas),
                fn ($m) => str_contains($corpo !== '' ? $corpo : $padrao, (string) $m)
            )),
            'destinatarios'   => SurveyMailer::paraTela(SurveyMailer::getListaExterna($survey)),
            // Chamado: quando e para quem o convite sai (configuração da
            // entidade no GLPI) e qual chamado real a prévia usa.
            'chamado'         => $tipo === Survey::TYPE_TICKET ? self::infoDoChamado($survey) : null,
            // Interna: quem recebe, quem é pulado e por quê.
            'publico'         => $tipo === Survey::TYPE_INTERNAL ? Target::getResumoDoPublico($survey) : null,
            'publico_url'     => Survey::getFormURLWithID($survey->getID())
                . '&forcetab=' . rawurlencode(SurveyTargets::class . '$1'),
            'mail_padrao'     => [
                'assunto' => SurveyMailer::getAssuntoPadrao($tipo),
                'corpo'   => $padrao,
            ],
            'mail_corpo'      => $corpo !== '' ? $corpo : $padrao,
            // Sub-aba "Lembrete" (v0.58.0).
            'lembrete'        => self::infoDoLembrete($survey, $tipo),
            // Sub-aba "Relatório" (v0.59.0).
            'relatorio'       => self::infoDoRelatorio($survey),
            'relatorio_previa_url' => $ajax . '/report.preview.php',
            'aviso_previa_url' => $ajax . '/alert.preview.php',
            'mail_send_url'   => $ajax . '/mail.send.php',
            'previa_url'      => $ajax . '/mail.preview.php',
            'csv_modelo_url'  => $ajax . '/recipients.template.php',
            'csv_import_url'  => $ajax . '/recipients.import.php',
            'csrf_url'        => $ajax . '/csrf.token.php',
            'form_url'        => Survey::getFormURL(true),
        ]);
    }

    /**
     * Dados da sub-aba "Relatório": configuração, quem recebe (campos
     * nativos do GLPI, de múltipla escolha) e a situação da ação
     * automática.
     *
     * @return array<string, mixed>
     */
    private static function infoDoRelatorio(Survey $survey): array
    {
        return [
            'ativo'         => !empty($survey->fields['report_active']),
            'hora'          => DailyReport::getHora($survey),
            'corte'         => DailyReport::getCorte($survey, 'report'),
            // Quem recebe (campos do GLPI), quem abre os resultados e o
            // último envio -- o relatório e o aviso têm cada um o seu.
            'destinos'      => self::destinos($survey, 'report', 'date_report_sent'),
            'anonima'       => !empty($survey->fields['is_anonymous']),
            'acao'          => DailyReport::getAcaoAutomatica(),
            // Modelo do e-mail do relatório (v0.61.0).
            'modelo'        => self::modelo($survey, 'report', DailyReport::getAssuntoPadraoRelatorio(), DailyReport::getCorpoPadraoRelatorio(), DailyReport::getMarcadoresRelatorio()),
            // Aviso imediato de nota baixa (v0.60.0).
            'aviso'         => [
                'modelo'  => self::modelo($survey, 'alert', DailyReport::getAssuntoPadraoAviso(), DailyReport::getCorpoPadraoAviso(), DailyReport::getMarcadoresAviso()),
                'ativo'   => !empty($survey->fields['alert_active']),
                'espera'  => DailyReport::getEspera($survey),
                'esperas' => DailyReport::ESPERAS,
                'acao'    => DailyReport::getAcaoAutomatica(DailyReport::CRON_AVISO),
                'corte'   => DailyReport::getCorte($survey, 'alert'),
                'destinos' => self::destinos($survey, 'alert', 'date_last_alert'),
            ],
        ];
    }

    /**
     * Quem recebe o relatório ('report') ou o aviso ('alert'): os campos
     * NATIVOS do GLPI de múltipla escolha (busca, entidades, aparência,
     * como na aba Público-alvo), a lista resolvida com quem abre os
     * resultados, e o último envio.
     *
     * @return array<string, mixed>
     */
    private static function destinos(Survey $survey, string $prefixo, string $campo_ultimo): array
    {
        $escolhidos = DailyReport::getEscolhidos($survey, $prefixo);

        // 'display' => false devolve o HTML; o ob_start() cobre o caso de
        // o campo ser impresso direto.
        $campo = static function (callable $gerar): string {
            ob_start();
            $html = $gerar();
            $impresso = ob_get_clean();

            return is_string($html) && $html !== '' ? $html : (string) $impresso;
        };

        $ultimo = (string) ($survey->fields[$campo_ultimo] ?? '');

        return [
            'prefixo'        => $prefixo,
            'campo_usuarios' => $campo(static fn() => \User::dropdown([
                'name'     => $prefixo . '_users[]',
                'value'    => $escolhidos['users'],
                'multiple' => true,
                'right'    => 'all',
                'entity'   => -1,
                'width'    => '100%',
                'display'  => false,
            ])),
            'campo_grupos'   => $campo(static fn() => \Dropdown::show(\Group::class, [
                'name'     => $prefixo . '_groups[]',
                'value'    => $escolhidos['groups'],
                'multiple' => true,
                'entity'   => -1,
                'width'    => '100%',
                'display'  => false,
            ])),
            'pessoas'        => array_values(DailyReport::getDestinatarios($survey, $prefixo)),
            'ultimo'         => $ultimo !== '' ? \Html::convDateTime($ultimo) : '',
        ];
    }

    /**
     * Assunto e mensagem de um modelo do relatório/aviso para o editor.
     * A mensagem nunca abre vazia: sem texto gravado, vem o padrão.
     *
     * @param array<string, string> $marcadores
     * @return array<string, mixed>
     */
    private static function modelo(Survey $survey, string $prefixo, string $assunto_padrao, string $corpo_padrao, array $marcadores): array
    {
        $corpo = trim((string) ($survey->fields[$prefixo . '_body'] ?? ''));

        return [
            'campo_assunto' => $prefixo . '_subject',
            'campo_corpo'   => $prefixo . '_body',
            'assunto'       => (string) ($survey->fields[$prefixo . '_subject'] ?? ''),
            'corpo'         => $corpo !== '' ? $corpo : $corpo_padrao,
            'padrao'        => ['assunto' => $assunto_padrao, 'corpo' => $corpo_padrao],
            'marcadores'    => $marcadores,
        ];
    }

    /**
     * Dados da sub-aba "Lembrete".
     *
     * Na pesquisa de CHAMADO o prazo é o do GLPI: a "Duração da pesquisa"
     * da entidade (Entidade › Assistência › Pesquisa de satisfação). O
     * GLPI não tem lembrete próprio -- só esse prazo, depois do qual a
     * pesquisa expira. Aqui ele vira o limite: a tela mostra quantos
     * lembretes cabem nele com o intervalo escolhido.
     *
     * @return array<string, mixed>
     */
    private static function infoDoLembrete(Survey $survey, string $tipo): array
    {
        $padrao_corpo = SurveyMailer::getCorpoPadraoLembrete($tipo, $survey);
        $corpo = trim((string) ($survey->fields['reminder_body'] ?? ''));
        $dias  = Delivery::getIntervalo($survey);
        $max   = Delivery::getMaximo($survey);

        $prazo = null;
        if ($tipo === Survey::TYPE_TICKET) {
            $config  = MailPolicy::getConfigDaEntidade((int) ($survey->fields['entities_id'] ?? 0));
            $duracao = (int) ($config['duracao'] ?? 0);
            $prazo = [
                'duracao'      => $duracao,
                // Lembretes nos dias D, 2D, 3D... que ainda caem ANTES de a
                // pesquisa expirar.
                'cabem'        => $duracao > 0 ? intdiv(max(0, $duracao - 1), $dias) : null,
                'entidade'     => (string) ($config['entidade'] ?? ''),
                'entidade_url' => $config !== null ? \Entity::getFormURLWithID((int) $config['entities_id']) : '',
            ];
        }

        return [
            'aceita'   => Delivery::aceitaLembrete($survey),
            'ativo'    => !empty($survey->fields['reminder_active']),
            'dias'     => $dias,
            'max'      => $max,
            'limites'  => [
                'dias_min' => Delivery::DIAS_MIN, 'dias_max' => Delivery::DIAS_MAX,
                'max_min'  => Delivery::MAX_MIN, 'max_max' => Delivery::MAX_MAX,
            ],
            'prazo'    => $prazo,
            'resumo'   => Delivery::getResumo($survey),
            'acao'     => Delivery::getAcaoAutomatica(),
            'acoes_url' => \CronTask::getSearchURL(),
            'assunto'  => (string) ($survey->fields['reminder_subject'] ?? ''),
            'padrao'   => [
                'assunto' => SurveyMailer::getAssuntoPadraoLembrete($tipo),
                'corpo'   => $padrao_corpo,
            ],
            'corpo'    => $corpo !== '' ? $corpo : $padrao_corpo,
        ];
    }

    /**
     * @return array{config: ?array, entidade_url: string, exemplo: ?array, exemplo_url: string}
     */
    private static function infoDoChamado(Survey $survey): array
    {
        $entidade = (int) ($survey->fields['entities_id'] ?? 0);
        $config   = MailPolicy::getConfigDaEntidade($entidade);
        $exemplo  = SurveyMailer::getChamadoExemplo($survey);

        // Qual pesquisa os chamados desta entidade usam de fato (a da URL
        // da entidade, ou a automática). Se não for esta, o convite desta
        // pesquisa não sai -- e a tela precisa dizer isso.
        $em_uso = TicketLink::resolveSurvey($entidade, $config['surveys_id'] ?? null);

        return [
            'config'       => $config,
            'em_uso'       => $em_uso !== null && $em_uso->getID() !== $survey->getID()
                ? ['id' => $em_uso->getID(), 'nome' => (string) $em_uso->fields['name'], 'url' => Survey::getFormURLWithID($em_uso->getID())]
                : null,
            'entidade_url' => $config !== null ? \Entity::getFormURLWithID($config['entities_id']) : '',
            'exemplo'      => $exemplo,
            'exemplo_url'  => $exemplo !== null ? \Ticket::getFormURLWithID($exemplo['id']) : '',
            // v0.63.0: espera do e-mail (avaliação no próprio chamado antes).
            'espera'       => Delivery::getEsperaConvite($survey),
            'esperas'      => Delivery::ESPERAS_CONVITE,
            'acao_convite' => Delivery::getAcaoAutomatica(Delivery::CRON_CONVITE),
        ];
    }

    /**
     * Valor de cada marcador nesta pesquisa, para a sub-aba "Variáveis".
     *
     * {{botao}} e as variáveis de pergunta viram HTML (tabelas, estilos
     * embutidos); na tabela isso apareceria como código cru. Para esses,
     * um texto curto descreve o que sai e remete à prévia.
     *
     * @param array<string, array<string, mixed>> $perguntas
     * @return array<string, string>
     */
    private static function exemplosEmTexto(Survey $survey, array $perguntas): array
    {
        $saida = [];
        foreach (SurveyMailer::getValoresExemplo($survey) as $marcador => $valor) {
            if (isset($perguntas[$marcador])) {
                $saida[$marcador] = $perguntas[$marcador]['type'] === 'text'
                    ? __('O texto da pergunta e um link para responder — veja na prévia', 'npsphere')
                    : __('O texto da pergunta e as opções para clicar — veja na prévia', 'npsphere');
                continue;
            }

            $saida[$marcador] = strpos($valor, '<') !== false
                ? __('(bloco HTML — veja na prévia da mensagem)', 'npsphere')
                : $valor;
        }

        return $saida;
    }
}
