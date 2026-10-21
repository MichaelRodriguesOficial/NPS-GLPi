<?php

namespace GlpiPlugin\Npsphere;

use NotificationTarget;

/**
 * Alvo de notificação da Pesquisa.
 *
 * Existe para o convite aparecer no GLPI como as notificações nativas:
 * com um "Modelo de notificação" de verdade na fila (Configurar ›
 * Notificações › Fila de notificação) e o modelo listado em
 * Configurar › Notificações › Modelos de notificação.
 *
 * O GLPI acha esta classe pelo nome: para o itemtype
 * GlpiPlugin\Npsphere\Survey ele procura
 * GlpiPlugin\Npsphere\NotificationTargetSurvey
 * (NotificationTarget::getInstanceClass).
 *
 * O CONTEÚDO do convite continua sendo montado pelo plugin, a partir da
 * aba "Template" de cada pesquisa. O modelo do GLPI só repassa o que o
 * plugin montou (##npsphere.assunto## e ##npsphere.mensagem##); editar o
 * texto dele não muda o e-mail.
 */
class NotificationTargetSurvey extends NotificationTarget
{
    public const EVENTO_CONVITE = 'npsphere_convite';

    /** Lembrete para quem ainda não respondeu (v0.58.0). */
    public const EVENTO_LEMBRETE = 'npsphere_lembrete';

    /** Relatório diário das respostas, para os gestores (v0.59.0). */
    public const EVENTO_RELATORIO = 'npsphere_relatorio';

    /** Aviso imediato de nota baixa, para os gestores (v0.60.0). */
    public const EVENTO_AVISO = 'npsphere_aviso';

    public function getEvents()
    {
        return [
            self::EVENTO_CONVITE  => __('Convite de pesquisa (NPSphere)', 'npsphere'),
            self::EVENTO_LEMBRETE => __('Lembrete de pesquisa (NPSphere)', 'npsphere'),
            self::EVENTO_RELATORIO => __('Relatório diário de pesquisa (NPSphere)', 'npsphere'),
            self::EVENTO_AVISO     => __('Aviso de nota baixa (NPSphere)', 'npsphere'),
        ];
    }

    public function addDataForTemplate($event, $options = [])
    {
        $this->data['##npsphere.pesquisa##'] = (string) ($this->obj->fields['name'] ?? '');
        $this->data['##npsphere.assunto##']  = (string) ($options['assunto'] ?? '');
        $this->data['##npsphere.mensagem##'] = (string) ($options['mensagem'] ?? '');
        $this->data['##npsphere.texto##']    = (string) ($options['texto'] ?? '');

        $this->getTags();
        foreach ($this->tag_descriptions[NotificationTarget::TAG_LANGUAGE] ?? [] as $tag => $values) {
            if (!isset($this->data[$tag])) {
                $this->data[$tag] = $values['label'];
            }
        }
    }

    public function getTags()
    {
        $tags = [
            'npsphere.pesquisa' => __('Nome da pesquisa', 'npsphere'),
            'npsphere.assunto'  => __('Assunto do e-mail (convite, lembrete ou relatório), montado na aba Template da pesquisa', 'npsphere'),
            'npsphere.mensagem' => __('Mensagem do e-mail (HTML), montada na aba Template da pesquisa', 'npsphere'),
            'npsphere.texto'    => __('Mensagem do e-mail em texto puro', 'npsphere'),
        ];

        foreach ($tags as $tag => $label) {
            $this->addTagToList([
                'tag'    => $tag,
                'label'  => $label,
                'value'  => true,
                'events' => NotificationTarget::TAG_FOR_ALL_EVENTS,
            ]);
        }

        asort($this->tag_descriptions);

        return $this->tag_descriptions;
    }

    /**
     * Modelos do plugin, um por evento (v0.58.1): o convite e o lembrete
     * aparecem separados em Configurar › Notificações › Modelos de
     * notificação e na coluna "Modelo de notificação" da fila.
     *
     * @return array<string, array{nome: string, comentario: string}>
     */
    private static function getModelos(): array
    {
        return [
            self::EVENTO_CONVITE => [
                'nome'       => __('NPSphere — Convite de pesquisa', 'npsphere'),
                'comentario' => __('Usado pelos convites do NPSphere. O assunto e a mensagem são configurados na aba "Template" de cada pesquisa; este modelo só repassa o que foi montado lá.', 'npsphere'),
            ],
            self::EVENTO_LEMBRETE => [
                'nome'       => __('NPSphere — Lembrete de pesquisa', 'npsphere'),
                'comentario' => __('Usado pelos lembretes do NPSphere. O assunto e a mensagem são configurados na sub-aba "Lembrete" da aba Template de cada pesquisa; este modelo só repassa o que foi montado lá.', 'npsphere'),
            ],
            self::EVENTO_RELATORIO => [
                'nome'       => __('NPSphere — Relatório diário de pesquisa', 'npsphere'),
                'comentario' => __('Usado pelo relatório diário das respostas, enviado aos gestores escolhidos na sub-aba "Relatório" da aba Template de cada pesquisa. O conteúdo é montado pelo plugin; este modelo só repassa.', 'npsphere'),
            ],
            self::EVENTO_AVISO => [
                'nome'       => __('NPSphere — Aviso de nota baixa', 'npsphere'),
                'comentario' => __('Usado pelo aviso imediato de nota baixa, enviado aos gestores escolhidos na sub-aba "Relatório" (configuração do aviso na sub-aba "Aviso") da aba Template de cada pesquisa. O conteúdo é montado pelo plugin; este modelo só repassa.', 'npsphere'),
            ],
        ];
    }

    /** Chave da configuração do plugin que guarda o id do modelo. */
    private static function chaveDaConfig(string $evento): string
    {
        return 'modelo_' . $evento;
    }

    /**
     * Id do modelo do evento, para a coluna notificationtemplates_id da
     * fila. 0 quando ainda não existe (plugin atualizado sem passar pela
     * instalação).
     *
     * O id fica guardado na configuração do plugin: os dois modelos têm o
     * mesmo itemtype (a Pesquisa), e o nome pode ser trocado na tela do
     * GLPI -- nenhum dos dois serve para distinguir um do outro.
     */
    public static function getModeloId(string $evento = self::EVENTO_CONVITE): int
    {
        global $DB;

        $chave = self::chaveDaConfig($evento);
        $id = (int) (\Config::getConfigurationValues('plugin:npsphere', [$chave])[$chave] ?? 0);

        if ($id > 0) {
            $existe = $DB->request([
                'COUNT' => 'cpt',
                'FROM'  => \NotificationTemplate::getTable(),
                'WHERE' => ['id' => $id, 'itemtype' => Survey::class],
            ])->current();

            if ((int) ($existe['cpt'] ?? 0) > 0) {
                return $id;
            }
        }

        // Instalações até a 0.58.0 tinham um modelo só, sem id guardado: o
        // mais antigo da Pesquisa é o do convite.
        if ($evento === self::EVENTO_CONVITE) {
            $outros = [];
            foreach (array_keys(self::getModelos()) as $e) {
                if ($e !== $evento) {
                    $outro = (int) (\Config::getConfigurationValues('plugin:npsphere', [self::chaveDaConfig($e)])[self::chaveDaConfig($e)] ?? 0);
                    if ($outro > 0) {
                        $outros[] = $outro;
                    }
                }
            }

            $where = ['itemtype' => Survey::class];
            if ($outros !== []) {
                $where['NOT'] = ['id' => $outros];
            }

            $linha = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => \NotificationTemplate::getTable(),
                'WHERE'  => $where,
                'ORDER'  => 'id ASC',
                'LIMIT'  => 1,
            ])->current();

            return (int) ($linha['id'] ?? 0);
        }

        return 0;
    }

    /**
     * Cria os modelos (convite e lembrete) e as traduções padrão que
     * ainda não existirem. Chamado na instalação/atualização do plugin;
     * pode rodar de novo.
     */
    public static function instalarModelo(): void
    {
        foreach (self::getModelos() as $evento => $dados) {
            $id = self::getModeloId($evento);

            if ($id <= 0) {
                $id = (int) (new \NotificationTemplate())->add([
                    'name'     => $dados['nome'],
                    'itemtype' => Survey::class,
                    'comment'  => $dados['comentario'],
                ]);

                if ($id <= 0) {
                    continue;
                }

                (new \NotificationTemplateTranslation())->add([
                    'notificationtemplates_id' => $id,
                    'language'                 => '',
                    'subject'                  => '##npsphere.assunto##',
                    'content_text'             => '##npsphere.texto##',
                    'content_html'             => '##npsphere.mensagem##',
                ]);
            }

            \Config::setConfigurationValues('plugin:npsphere', [self::chaveDaConfig($evento) => $id]);
        }
    }

    /** Chaves de configuração dos modelos, para a desinstalação. */
    public static function getChavesDaConfig(): array
    {
        return array_map([self::class, 'chaveDaConfig'], array_keys(self::getModelos()));
    }

    /**
     * Remove o(s) modelo(s) do plugin. A remoção definitiva leva junto as
     * traduções e as linhas da fila que apontam para ele
     * (NotificationTemplate::cleanDBonPurge).
     */
    public static function removerModelo(): void
    {
        global $DB;

        $modelo = new \NotificationTemplate();
        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => \NotificationTemplate::getTable(),
            'WHERE'  => ['itemtype' => Survey::class],
        ]) as $linha) {
            $modelo->delete(['id' => $linha['id']], true);
        }
    }
}
