<?php

/**
 * -------------------------------------------------------------------------
 * NPSphere plugin for GLPI
 * -------------------------------------------------------------------------
 * hook.php — instalação, desinstalação e ganchos de ciclo de vida.
 * -------------------------------------------------------------------------
 */

use GlpiPlugin\Npsphere\Answer;
use GlpiPlugin\Npsphere\AnswerValue;
use GlpiPlugin\Npsphere\Completion;
use GlpiPlugin\Npsphere\Profile as PluginNpsphereProfile;
use GlpiPlugin\Npsphere\Question;
use GlpiPlugin\Npsphere\Survey;
use GlpiPlugin\Npsphere\Target;

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can not access directly to this file');
}

/**
 * Instalação do plugin: cria as tabelas ("surveys" e, a partir da fase 2,
 * "questions"), registra os direitos de perfil e prepara migrações futuras.
 *
 * @return boolean
 */
function plugin_npsphere_install()
{
    global $DB;

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();

    // A classe Migration versiona a estrutura do banco a cada release
    // do plugin, permitindo evoluir o schema sem recriar tabelas.
    $migration = new Migration(PLUGIN_NPSPHERE_VERSION);

    // -----------------------------------------------------------------
    // Tabela: glpi_plugin_npsphere_surveys
    // -----------------------------------------------------------------
    $table = Survey::getTable();
    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
            `id`                     int unsigned NOT NULL AUTO_INCREMENT,
            `entities_id`            int unsigned NOT NULL DEFAULT '0',
            `is_recursive`           tinyint NOT NULL DEFAULT '0',
            `name`                   varchar(255) NOT NULL DEFAULT '',
            `description`            text,
            `is_active`              tinyint NOT NULL DEFAULT '0',
            `type`                   varchar(32) NOT NULL DEFAULT 'internal',
            `date_begin`             date DEFAULT NULL,
            `date_end`               date DEFAULT NULL,
            `is_anonymous`           tinyint NOT NULL DEFAULT '0',
            `require_identification` tinyint NOT NULL DEFAULT '0',
            `mail_subject` varchar(255) DEFAULT NULL,
            `mail_body` text,
            `mail_recipients` text,
            `target_all`             tinyint NOT NULL DEFAULT '0',
            `is_mandatory`           tinyint NOT NULL DEFAULT '0',
            `allow_multiple_answers` tinyint NOT NULL DEFAULT '0',
            `allow_public_access`    tinyint NOT NULL DEFAULT '0',
            `public_token`           varchar(64) DEFAULT NULL,
            `display_mode`           varchar(32) NOT NULL DEFAULT 'single_page',
            `creator_id`             int unsigned NOT NULL DEFAULT '0',
            `is_deleted`             tinyint NOT NULL DEFAULT '0',
            `date_creation`          timestamp NULL DEFAULT NULL,
            `date_mod`               timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `is_recursive` (`is_recursive`),
            KEY `is_active` (`is_active`),
            KEY `creator_id` (`creator_id`),
            KEY `is_deleted` (`is_deleted`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`),
            UNIQUE KEY `public_token` (`public_token`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET={$default_charset}
          COLLATE={$default_collation}";
        $DB->doQuery($query) or die($DB->error());
    }

    // Exemplo de uso do Migration para futuras versões (>= 0.3.0):
    // if ($DB->tableExists($table) && !$DB->fieldExists($table, 'novo_campo')) {
    //     $migration->addField($table, 'novo_campo', 'string');
    //     $migration->addKey($table, 'novo_campo');
    // }

    // v0.4.0: campo "is_nps_enabled" removido -- o NPS agora é habilitado
    // implicitamente pela existência de perguntas do tipo "nps" na
    // pesquisa (ver Survey::hasNpsQuestion()), evitando o estado
    // contraditório de ter perguntas de NPS numa pesquisa com
    // "NPS habilitado = Não".
    if ($DB->tableExists($table) && $DB->fieldExists($table, 'is_nps_enabled')) {
        $migration->dropField($table, 'is_nps_enabled');
    }

    // v0.22.0: identificação do respondente em pesquisas públicas NÃO
    // anônimas -- nome, e-mail e telefone pedidos no início do
    // formulário público. Não faz sentido em pesquisa anônima (aí o
    // objetivo é justamente não identificar) nem no fluxo autenticado
    // (o GLPI já sabe quem é o usuário).
    if ($DB->tableExists($table) && !$DB->fieldExists($table, 'require_identification')) {
        $migration->addField($table, 'require_identification', 'bool', ['value' => 0]);
    }

    // -----------------------------------------------------------------
    // v0.51.0 (Fase 9): corpo do e-mail POR PESQUISA
    // -----------------------------------------------------------------
    // Cada pesquisa tem o seu texto. Uma mensagem única para todas
    // obrigaria a escrever algo genérico o bastante para servir a
    // qualquer contexto -- e genérico demais é o que faz as pessoas
    // ignorarem o convite.
    if ($DB->tableExists($table) && !$DB->fieldExists($table, 'mail_subject')) {
        $migration->addField($table, 'mail_subject', 'string', ['value' => null]);
    }
    if ($DB->tableExists($table) && !$DB->fieldExists($table, 'mail_body')) {
        $migration->addField($table, 'mail_body', 'text', ['value' => null]);
    }

    // Lista de destinatários da pesquisa EXTERNA (link público).
    if ($DB->tableExists($table) && !$DB->fieldExists($table, 'mail_recipients')) {
        $migration->addField($table, 'mail_recipients', 'text', ['value' => null]);
    }

    // v0.5.0: "users_id" renomeado para "creator_id". O GLPI reconhece
    // magicamente uma coluna chamada "users_id" e a exibe como um campo
    // "Usuário" editável no formulário genérico (permitindo reatribuir
    // o "dono" do registro), o que confundia o campo de auditoria
    // "quem criou a pesquisa" com uma opção de seleção. Renomear a
    // coluna faz o GLPI parar de tratá-la como campo mágico -- a
    // informação de quem criou continua disponível (na aba Histórico),
    // só não é mais exibida como dropdown editável no formulário.
    if ($DB->tableExists($table) && $DB->fieldExists($table, 'users_id')) {
        $migration->changeField($table, 'users_id', 'creator_id', 'integer', [
            'value' => 0,
        ]);
    }

    // -----------------------------------------------------------------
    // Tabela: glpi_plugin_npsphere_questions (Fase 2)
    // -----------------------------------------------------------------
    $questions_table = Question::getTable();
    if (!$DB->tableExists($questions_table)) {
        $query = "CREATE TABLE `$questions_table` (
            `id`            int unsigned NOT NULL AUTO_INCREMENT,
            `surveys_id`    int unsigned NOT NULL DEFAULT '0',
            `name`          varchar(255) NOT NULL DEFAULT '',
            `description`   text,
            `type`          varchar(32) NOT NULL DEFAULT 'text',
            `position`      int unsigned NOT NULL DEFAULT '0',
            `is_required`   tinyint NOT NULL DEFAULT '0',
            `is_active`     tinyint NOT NULL DEFAULT '1',
            `configuration` text,
            `condition_questions_id` int unsigned NOT NULL DEFAULT '0',
            `condition_operator`     varchar(16) NOT NULL DEFAULT '',
            `condition_value`        varchar(255) NOT NULL DEFAULT '',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod`      timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `surveys_id` (`surveys_id`),
            KEY `position` (`position`),
            KEY `is_active` (`is_active`),
            KEY `condition_questions_id` (`condition_questions_id`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET={$default_charset}
          COLLATE={$default_collation}";
        $DB->doQuery($query) or die($DB->error());
    }

    // v0.6.0: campo "is_nps_main" removido -- toda pergunta do tipo "nps"
    // conta automaticamente para o cálculo do NPS (ver
    // Survey::hasNpsQuestion()), então não fazia sentido marcar uma
    // pergunta NPS específica como "principal": ela já é, por
    // definição, uma pergunta de NPS.
    if ($DB->tableExists($questions_table) && $DB->fieldExists($questions_table, 'is_nps_main')) {
        $migration->dropKey($questions_table, 'is_nps_main');
        $migration->dropField($questions_table, 'is_nps_main');
    }

    // v0.18.0 (Fase 5 -- regras condicionais): uma pergunta pode ficar
    // condicionada à resposta de OUTRA pergunta da mesma pesquisa.
    // Ex.: "Descreva o motivo" só aparece se "Gostou?" = "Não", ou se a
    // nota do NPS for <= 6. Os três campos juntos formam a condição:
    //   condition_questions_id <operador> condition_value
    // Vazio/0 em condition_questions_id = pergunta sempre visível.
    if ($DB->tableExists($questions_table)) {
        if (!$DB->fieldExists($questions_table, 'condition_questions_id')) {
            $migration->addField($questions_table, 'condition_questions_id', 'integer', ['value' => 0]);
            $migration->addKey($questions_table, 'condition_questions_id');
        }
        if (!$DB->fieldExists($questions_table, 'condition_operator')) {
            $migration->addField($questions_table, 'condition_operator', 'string', ['value' => '']);
        }
        if (!$DB->fieldExists($questions_table, 'condition_value')) {
            $migration->addField($questions_table, 'condition_value', 'string', ['value' => '']);
        }
    }

    // -----------------------------------------------------------------
    // Tabelas: glpi_plugin_npsphere_answers e ..._answer_values (Fase 3)
    // -----------------------------------------------------------------
    $answers_table = Answer::getTable();
    if (!$DB->tableExists($answers_table)) {
        $query = "CREATE TABLE `$answers_table` (
            `id`            int unsigned NOT NULL AUTO_INCREMENT,
            `surveys_id`    int unsigned NOT NULL DEFAULT '0',
            `tickets_id`    int unsigned NOT NULL DEFAULT '0',
            `users_id`      int unsigned NOT NULL DEFAULT '0',
            `entities_id`   int unsigned NOT NULL DEFAULT '0',
            `token`         varchar(64) DEFAULT NULL,
            `duration`      int unsigned NOT NULL DEFAULT '0',
            `is_anonymous`  tinyint NOT NULL DEFAULT '0',
            `respondent_name`  varchar(255) NOT NULL DEFAULT '',
            `respondent_email` varchar(255) NOT NULL DEFAULT '',
            `respondent_phone` varchar(32) NOT NULL DEFAULT '',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod`      timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `surveys_id` (`surveys_id`),
            KEY `tickets_id` (`tickets_id`),
            KEY `users_id` (`users_id`),
            KEY `entities_id` (`entities_id`),
            KEY `date_creation` (`date_creation`),
            UNIQUE KEY `token` (`token`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET={$default_charset}
          COLLATE={$default_collation}";
        $DB->doQuery($query) or die($DB->error());
    }

    // Colunas de identificação também nas instalações já existentes
    if ($DB->tableExists($answers_table)) {
        foreach ([
            'respondent_name'  => 'string',
            'respondent_email' => 'string',
            'respondent_phone' => 'string',
        ] as $field => $type) {
            if (!$DB->fieldExists($answers_table, $field)) {
                $migration->addField($answers_table, $field, $type, ['value' => '']);
            }
        }
        if (!$DB->fieldExists($answers_table, 'respondent_email')) {
            $migration->addKey($answers_table, 'respondent_email');
        }
    }

    $answer_values_table = AnswerValue::getTable();
    if (!$DB->tableExists($answer_values_table)) {
        $query = "CREATE TABLE `$answer_values_table` (
            `id`            int unsigned NOT NULL AUTO_INCREMENT,
            `answers_id`    int unsigned NOT NULL DEFAULT '0',
            `questions_id`  int unsigned NOT NULL DEFAULT '0',
            `value`         text,
            `numeric_value` decimal(10,2) DEFAULT NULL,
            `text_value`    text,
            `option_value`  varchar(255) DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `answers_id` (`answers_id`),
            KEY `questions_id` (`questions_id`),
            KEY `numeric_value` (`numeric_value`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET={$default_charset}
          COLLATE={$default_collation}";
        $DB->doQuery($query) or die($DB->error());
    }

    // v0.23.0: o tipo "independent" foi dividido em "internal" (usuários
    // do GLPI) e "external" (link público). Pesquisas já existentes
    // viram "internal" -- o valor mais conservador: não expõe nada
    // publicamente sem o administrador decidir isso de forma explícita.
    if ($DB->tableExists($table) && $DB->fieldExists($table, 'type')) {
        $DB->update($table, ['type' => 'internal'], ['type' => 'independent']);
    }

    // -----------------------------------------------------------------
    // Tabela: glpi_plugin_npsphere_targets (Fase 6 -- público-alvo)
    // -----------------------------------------------------------------
    $targets_table = Target::getTable();
    if (!$DB->tableExists($targets_table)) {
        $query = "CREATE TABLE `$targets_table` (
            `id`            int unsigned NOT NULL AUTO_INCREMENT,
            `surveys_id`    int unsigned NOT NULL DEFAULT '0',
            `itemtype`      varchar(64) NOT NULL DEFAULT '',
            `items_id`      int unsigned NOT NULL DEFAULT '0',
            `is_excluded`   tinyint NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `surveys_id` (`surveys_id`),
            KEY `item` (`itemtype`, `items_id`),
            UNIQUE KEY `unicity` (`surveys_id`, `itemtype`, `items_id`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET={$default_charset}
          COLLATE={$default_collation}";
        $DB->doQuery($query) or die($DB->error());
    }

    // -----------------------------------------------------------------
    // Tabela: glpi_plugin_npsphere_completions
    // -----------------------------------------------------------------
    // Registra QUE um usuário concluiu uma pesquisa, sem ligação com o
    // conteúdo respondido. Necessário para pesquisas anônimas, em que a
    // resposta é gravada sem users_id: sem isto não havia como saber
    // que a pessoa já respondeu, e o aviso obrigatório voltava sempre.
    $completions_table = Completion::getTable();
    if (!$DB->tableExists($completions_table)) {
        $query = "CREATE TABLE `$completions_table` (
            `id`            int unsigned NOT NULL AUTO_INCREMENT,
            `surveys_id`    int unsigned NOT NULL DEFAULT '0',
            `users_id`      int unsigned NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `surveys_id` (`surveys_id`),
            UNIQUE KEY `unicity` (`surveys_id`, `users_id`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET={$default_charset}
          COLLATE={$default_collation}";
        $DB->doQuery($query) or die($DB->error());
    }

    // -----------------------------------------------------------------
    // Tabela: glpi_plugin_npsphere_invites (v0.52.0)
    // -----------------------------------------------------------------
    // Um convite por destinatário da pesquisa externa. O código (token)
    // vai no link do e-mail e liga a resposta à pessoa: abrir o link de
    // novo altera a resposta em vez de criar outra. Ver Invite.php.
    $invites_table = \GlpiPlugin\Npsphere\Invite::getTable();
    if (!$DB->tableExists($invites_table)) {
        $query = "CREATE TABLE `$invites_table` (
            `id`            int unsigned NOT NULL AUTO_INCREMENT,
            `surveys_id`    int unsigned NOT NULL DEFAULT '0',
            `email`         varchar(255) NOT NULL DEFAULT '',
            `name`          varchar(255) NOT NULL DEFAULT '',
            `token`         varchar(64) NOT NULL DEFAULT '',
            `answers_id`    int unsigned NOT NULL DEFAULT '0',
            `date_sent`     timestamp NULL DEFAULT NULL,
            `date_answered` timestamp NULL DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod`      timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `token` (`token`),
            UNIQUE KEY `survey_email` (`surveys_id`, `email`),
            KEY `answers_id` (`answers_id`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET={$default_charset}
          COLLATE={$default_collation}";
        $DB->doQuery($query) or die($DB->error());
    }

    // -----------------------------------------------------------------
    // v0.58.0: LEMBRETE para quem não respondeu
    // -----------------------------------------------------------------
    // Configuração por pesquisa (sub-aba "Lembrete" da aba Template) e
    // a tabela de convites enviados, que a ação automática percorre.
    // Ver Delivery.php.
    if ($DB->tableExists($table)) {
        foreach ([
            'reminder_active'  => ['bool', ['value' => 0]],
            'reminder_days'    => ['integer', ['value' => 3]],
            'reminder_max'     => ['integer', ['value' => 2]],
            'reminder_subject' => ['string', ['value' => null]],
            'reminder_body'    => ['text', ['value' => null]],
        ] as $campo => [$tipo_campo, $opcoes]) {
            if (!$DB->fieldExists($table, $campo)) {
                $migration->addField($table, $campo, $tipo_campo, $opcoes);
            }
        }
    }

    // v0.62.1: na atualização, o "último envio" começa com o último
    // relatório processado (até aqui eram o mesmo campo).
    $iniciar_envio_relatorio = $DB->tableExists($table)
        && $DB->fieldExists($table, 'date_last_report')
        && !$DB->fieldExists($table, 'date_report_sent');

    // v0.62.0: o aviso passa a ter lista e nota baixa próprias. Na
    // atualização, quem já tinha o aviso recebe a lista e a nota do
    // relatório (era o que valia até aqui) -- feito depois da migração.
    $copiar_para_o_aviso = $DB->tableExists($table)
        && $DB->fieldExists($table, 'report_recipients')
        && !$DB->fieldExists($table, 'alert_recipients');

    // v0.59.0: RELATÓRIO DIÁRIO das respostas para os gestores (sub-aba
    // "Relatório" da aba Template). Ver DailyReport.php.
    if ($DB->tableExists($table)) {
        foreach ([
            'report_active'     => ['bool', ['value' => 0]],
            'report_hour'       => ['integer', ['value' => 18]],
            'report_nps_cut'    => ['integer', ['value' => 6]],
            'report_stars_cut'  => ['integer', ['value' => 2]],
            'report_recipients' => ['text', ['value' => null]],
            'date_last_report'  => ['timestamp', ['value' => null]],
            // v0.60.0: aviso imediato de nota baixa.
            'alert_active'      => ['bool', ['value' => 0]],
            'alert_delay'       => ['integer', ['value' => 10]],
            'alert_since'       => ['timestamp', ['value' => null]],
            // v0.61.0: assunto e mensagem editáveis do relatório e do aviso.
            'report_subject'    => ['string', ['value' => null]],
            'report_body'       => ['text', ['value' => null]],
            'alert_subject'     => ['string', ['value' => null]],
            'alert_body'        => ['text', ['value' => null]],
            // v0.62.0: o aviso com a sua própria lista, nota baixa e
            // último envio (antes usava os do relatório).
            'alert_nps_cut'     => ['integer', ['value' => 6]],
            'alert_recipients'  => ['text', ['value' => null]],
            'date_last_alert'   => ['timestamp', ['value' => null]],
            // v0.62.1: "último envio" do relatório só quando um e-mail sai
            // (date_last_report continua marcando o dia processado).
            'date_report_sent'  => ['timestamp', ['value' => null]],
            // v0.63.0: espera do convite do chamado, em minutos.
            'invite_delay'      => ['integer', ['value' => 15]],
        ] as $campo => [$tipo_campo, $opcoes]) {
            if (!$DB->fieldExists($table, $campo)) {
                $migration->addField($table, $campo, $tipo_campo, $opcoes);
            }
        }
    }

    // v0.60.0: quando a resposta já gerou o aviso de nota baixa (um aviso
    // por resposta).
    if ($DB->tableExists($answers_table) && !$DB->fieldExists($answers_table, 'date_alerted')) {
        $migration->addField($answers_table, 'date_alerted', 'timestamp', ['value' => null]);
    }

    $deliveries_table = \GlpiPlugin\Npsphere\Delivery::getTable();
    if (!$DB->tableExists($deliveries_table)) {
        $query = "CREATE TABLE `$deliveries_table` (
            `id`                 int unsigned NOT NULL AUTO_INCREMENT,
            `surveys_id`         int unsigned NOT NULL DEFAULT '0',
            `chave`              varchar(255) NOT NULL DEFAULT '',
            `tickets_id`         int unsigned NOT NULL DEFAULT '0',
            `users_id`           int unsigned NOT NULL DEFAULT '0',
            `email`              varchar(255) NOT NULL DEFAULT '',
            `name`               varchar(255) NOT NULL DEFAULT '',
            `status`             varchar(16) NOT NULL DEFAULT 'aguardando',
            `date_invited`       timestamp NULL DEFAULT NULL,
            `reminders_sent`     int unsigned NOT NULL DEFAULT '0',
            `date_last_reminder` timestamp NULL DEFAULT NULL,
            `date_creation`      timestamp NULL DEFAULT NULL,
            `date_mod`           timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `survey_chave` (`surveys_id`, `chave`),
            KEY `status` (`status`),
            KEY `tickets_id` (`tickets_id`),
            KEY `users_id` (`users_id`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET={$default_charset}
          COLLATE={$default_collation}";
        $DB->doQuery($query) or die($DB->error());
    }
    // v0.63.0: hora em que o convite do chamado agendado deve sair.
    if ($DB->tableExists($deliveries_table) && !$DB->fieldExists($deliveries_table, 'date_due')) {
        $migration->addField($deliveries_table, 'date_due', 'timestamp', ['value' => null]);
        $migration->addKey($deliveries_table, 'date_due');
    }

    // v0.32.1: pesquisas INTERNAS não podem ficar marcadas como
    // anônimas. A opção só existe no tipo Externo, então esse valor só
    // podia ter vindo por herança de quando a pesquisa era de outro
    // tipo -- e produzia um estado contraditório: o administrador via
    // uma pesquisa identificada, mas as respostas eram gravadas sem
    // users_id, como anônimas.
    if ($DB->tableExists($table) && $DB->fieldExists($table, 'is_anonymous')) {
        $DB->update($table, ['is_anonymous' => 0], ['type' => 'internal', 'is_anonymous' => 1]);
    }

    // v0.30.0: pesquisa interna de resposta obrigatória. O usuário pode
    // adiar até o penúltimo dia; no último, precisa responder para
    // seguir usando o sistema.
    if ($DB->tableExists($table) && !$DB->fieldExists($table, 'is_mandatory')) {
        $migration->addField($table, 'is_mandatory', 'bool', ['value' => 0]);
    }

    // "Todos os usuários" é um caso à parte: não faz sentido cadastrar
    // uma linha por usuário da base, então vira uma marcação na pesquisa.
    if ($DB->tableExists($table) && !$DB->fieldExists($table, 'target_all')) {
        $migration->addField($table, 'target_all', 'bool', ['value' => 0]);
    }

    // v0.65.0: exceções no público-alvo ("grupo X, exceto fulano").
    if ($DB->tableExists($targets_table) && !$DB->fieldExists($targets_table, 'is_excluded')) {
        $migration->addField($targets_table, 'is_excluded', 'bool', ['value' => 0, 'after' => 'items_id']);
    }

    // IMPORTANTE: executeMigration() precisa ser a ÚLTIMA chamada depois
    // de TODOS os addField/dropField/changeField. Ela é o que de fato
    // aplica as alterações no banco -- qualquer campo declarado depois
    // dela seria silenciosamente ignorado (foi exatamente o que
    // aconteceu com as colunas respondent_* na v0.22.0, causando
    // "Unknown column 'respondent_name'" ao abrir a aba Resultados).
    $migration->executeMigration();

    if ($iniciar_envio_relatorio) {
        foreach ($DB->request([
            'SELECT' => ['id', 'date_last_report'],
            'FROM'   => $table,
            'WHERE'  => ['NOT' => ['date_last_report' => null]],
        ]) as $linha) {
            $DB->update($table, ['date_report_sent' => $linha['date_last_report']], ['id' => (int) $linha['id']]);
        }
    }

    if ($copiar_para_o_aviso) {
        foreach ($DB->request([
            'SELECT' => ['id', 'report_recipients', 'report_nps_cut'],
            'FROM'   => $table,
        ]) as $linha) {
            $DB->update($table, [
                'alert_recipients' => $linha['report_recipients'],
                'alert_nps_cut'    => (int) ($linha['report_nps_cut'] ?? 6),
            ], ['id' => (int) $linha['id']]);
        }
    }

    // v0.51.9: modelo de notificação "Convite de pesquisa", para o
    // convite aparecer na fila do GLPI com o campo "Modelo de
    // notificação" preenchido. v0.58.1: e o "Lembrete de pesquisa".
    // Não recria os que já existem.
    \GlpiPlugin\Npsphere\NotificationTargetSurvey::instalarModelo();

    // v0.58.0: ação automática do lembrete (Configurar › Ações
    // automáticas). Não recria se já existir.
    \GlpiPlugin\Npsphere\Delivery::registrarAcaoAutomatica();

    // v0.59.0: ação automática do relatório diário.
    \GlpiPlugin\Npsphere\DailyReport::registrarAcaoAutomatica();

    // -----------------------------------------------------------------
    // Direitos de perfil
    // -----------------------------------------------------------------
    // ProfileRight::addProfileRights() NÃO é idempotente: ele faz um
    // INSERT direto em glpi_profilerights (profiles_id, name), então
    // chamá-lo de novo numa atualização (quando o direito já existe
    // para algum perfil) quebra a constraint UNIQUE ('unicity') com
    // "Duplicate entry ... for key 'unicity'". É preciso checar,
    // direito por direito, se ele já está registrado antes de adicionar
    // -- exatamente como o próprio core do GLPI faz internamente
    // (ver Profile::getRightsToDisplay(), que consulta
    // ProfileRight::getAllPossibleRights() antes de chamar
    // addProfileRights()).
    // Perfis de interface SIMPLIFICADA não podem ter o direito de
    // GERENCIAR pesquisas: a tela vive em Administração, menu que essa
    // interface não exibe. Um perfil nessa situação teria um direito
    // que nunca se concretiza -- e a aba de Perfis nem oferece mais
    // essa opção para eles.
    if ($DB->tableExists('glpi_profilerights')) {
        $helpdesk_profiles = [];
        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_profiles',
            'WHERE'  => ['interface' => 'helpdesk'],
        ]) as $row) {
            $helpdesk_profiles[] = (int) $row['id'];
        }

        if (!empty($helpdesk_profiles)) {
            $DB->update(
                'glpi_profilerights',
                ['rights' => 0],
                ['name' => 'plugin_npsphere_survey', 'profiles_id' => $helpdesk_profiles]
            );
        }
    }

    // -----------------------------------------------------------------
    // Direito do PAINEL DE SATISFAÇÃO (Fase 10)
    // -----------------------------------------------------------------
    // Nasce concedido a quem JÁ tem leitura em "Pesquisas de
    // satisfação": quem acompanhava os resultados continua podendo
    // acompanhar o painel, sem ter de reconfigurar perfil por perfil.
    // Perfis de interface simplificada nunca recebem -- o painel é
    // ferramenta de gestão, e essa interface nem exibe o menu Gestão.
    //
    // As linhas são criadas uma a uma, e não só por
    // ProfileRight::addProfileRights(): aquele cria o direito apenas
    // para os perfis existentes NA PRIMEIRA vez, e perfis sem linha
    // ficam sem ter onde gravar a marcação feita na tela.
    if ($DB->tableExists('glpi_profilerights')) {
        $ja_tem = [];
        foreach ($DB->request([
            'SELECT' => ['profiles_id'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => ['name' => 'plugin_npsphere_dashboard'],
        ]) as $row) {
            $ja_tem[(int) $row['profiles_id']] = true;
        }

        $gestores = [];
        foreach ($DB->request([
            'SELECT' => ['profiles_id', 'rights'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => ['name' => 'plugin_npsphere_survey'],
        ]) as $row) {
            if (((int) $row['rights'] & READ) === READ) {
                $gestores[(int) $row['profiles_id']] = true;
            }
        }

        foreach ($DB->request([
            'SELECT' => ['id', 'interface'],
            'FROM'   => 'glpi_profiles',
        ]) as $profile) {
            $pid = (int) $profile['id'];
            if (isset($ja_tem[$pid])) {
                continue; // preserva a escolha já feita
            }

            $concede = isset($gestores[$pid]) && ($profile['interface'] ?? '') !== 'helpdesk';

            $DB->insert('glpi_profilerights', [
                'profiles_id' => $pid,
                'name'        => 'plugin_npsphere_dashboard',
                'rights'      => $concede ? READ : 0,
            ]);
        }
    }

    // Concede "Minhas pesquisas" a todos os perfis na primeira vez que
    // o direito é criado. Padrão permissivo de propósito: se ninguém
    // pudesse responder até alguém marcar a caixa, a funcionalidade
    // pareceria quebrada logo após a atualização. Para restringir,
    // basta desmarcar em Administração > Perfis.
    $existing_rights = ProfileRight::getAllPossibleRights();

    // Garante que TODO perfil tenha uma linha para este direito.
    //
    // addProfileRights() sozinho não bastava: ele cria a linha apenas
    // para os perfis existentes no momento em que o direito é criado, e
    // só na primeira vez. Perfis criados depois -- ou instalações onde
    // o direito foi criado numa tentativa anterior sem valor -- ficavam
    // sem linha alguma. Sem linha, Session::haveRight() responde
    // "não tem", e o resultado é o pior possível: o item some do menu
    // (ou aparece e nega acesso ao clicar) mesmo com a caixa marcada na
    // tela de Perfis, porque não havia onde gravar a marcação.
    if (!array_key_exists('plugin_npsphere_answer', $existing_rights)) {
        ProfileRight::addProfileRights(['plugin_npsphere_answer']);
        $existing_rights = ProfileRight::getAllPossibleRights();
    }

    if ($DB->tableExists('glpi_profilerights')) {
        $com_direito = [];
        foreach ($DB->request([
            'SELECT' => ['profiles_id'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => ['name' => 'plugin_npsphere_answer'],
        ]) as $row) {
            $com_direito[] = (int) $row['profiles_id'];
        }

        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => 'glpi_profiles']) as $profile) {
            if (in_array((int) $profile['id'], $com_direito, true)) {
                continue; // já tem linha: preserva a escolha do administrador
            }

            // Linha nova nasce com READ -- padrão permissivo, para a
            // funcionalidade não parecer quebrada logo após atualizar.
            $DB->insert('glpi_profilerights', [
                'profiles_id' => (int) $profile['id'],
                'name'        => 'plugin_npsphere_answer',
                'rights'      => READ,
            ]);
        }

        // Perfis de autoatendimento cuja linha existe mas está ZERADA
        // recebem o direito.
        //
        // Nas versões anteriores este direito foi criado com valor 0
        // para os perfis existentes -- e, como responder pesquisas é
        // justamente a única coisa que esses perfis fazem no plugin,
        // manter zero significa que a funcionalidade nunca funciona
        // para eles. Quem quiser restringir, desmarca depois; o
        // contrário (ficar quebrado e ninguém entender por quê) é bem
        // pior.
        // Busca os ids primeiro e atualiza com uma lista simples --
        // evita subconsulta dentro do UPDATE, construção cuja aceitação
        // pelo DB do GLPI eu não tenho como verificar aqui.
        $perfis_helpdesk = [];
        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_profiles',
            'WHERE'  => ['interface' => 'helpdesk'],
        ]) as $row) {
            $perfis_helpdesk[] = (int) $row['id'];
        }

        if (!empty($perfis_helpdesk)) {
            $DB->update(
                'glpi_profilerights',
                ['rights' => READ],
                [
                    'name'        => 'plugin_npsphere_answer',
                    'rights'      => 0,
                    'profiles_id' => $perfis_helpdesk,
                ]
            );
        }
    }

    foreach (PluginNpsphereProfile::getAllRights() as $right) {
        if (!isset($existing_rights[$right['field']])) {
            ProfileRight::addProfileRights([$right['field']]);
        }
    }

    // Concede todos os direitos ao(s) perfil(is) "Super-Admin" (convenção
    // nos plugins GLPI) E TAMBÉM ao perfil atualmente ativo de quem está
    // instalando o plugin -- isso evita que o administrador fique sem
    // acesso caso o perfil super-admin tenha sido renomeado/traduzido.
    $profiles_to_grant = [];

    $profile = new \Profile();
    foreach ($profile->find(['interface' => 'central']) as $data) {
        if (($data['name'] ?? '') === 'Super-Admin') {
            $profiles_to_grant[] = $data['id'];
        }
    }

    if (!empty($_SESSION['glpiactiveprofile']['id'])) {
        $profiles_to_grant[] = (int) $_SESSION['glpiactiveprofile']['id'];
    }

    foreach (array_unique($profiles_to_grant) as $profiles_id) {
        foreach (PluginNpsphereProfile::getAllRights() as $right) {
            ProfileRight::updateProfileRights($profiles_id, [
                $right['field'] => ALLSTANDARDRIGHT,
            ]);
        }
    }

    return true;
}

/**
 * Desinstalação do plugin.
 *
 * A remoção definitiva dos dados só ocorre quando o administrador
 * confirma a ação na tela "Configurar > Plugins" do GLPI (o próprio
 * core exibe essa confirmação antes de chamar esta função).
 *
 * @return boolean
 */
function plugin_npsphere_uninstall()
{
    global $DB;

    // Ordem importa: revogar os direitos ANTES de derrubar as tabelas.
    // Um plugin real (addressing) já teve um bug em produção por fazer
    // isso na ordem inversa -- se getAllRights() algum dia precisar
    // instanciar um itemtype (ex.: via Profile::getRightsFor()), isso
    // dispara CommonDBTM::getEmpty() -> $DB->listFields($table), que
    // falha se a tabela já tiver sido removida.
    foreach (PluginNpsphereProfile::getAllRights() as $right) {
        ProfileRight::deleteProfileRights([$right['field']]);
    }

    // Modelo de notificação do convite (e, junto, as traduções e os
    // convites que ainda estavam na fila).
    \GlpiPlugin\Npsphere\NotificationTargetSurvey::removerModelo();

    // Ação automática do lembrete. (O GLPI também remove as ações dos
    // plugins ao desinstalar; aqui fica explícito.)
    \CronTask::unregister('npsphere');

    // Segredo das chaves dos links de avaliação do chamado.
    \Config::deleteConfigurationValues(
        'plugin:npsphere',
        array_merge(['chave_assinatura'], \GlpiPlugin\Npsphere\NotificationTargetSurvey::getChavesDaConfig())
    );

    $tables = [
        Survey::getTable(),
        Question::getTable(),
        Target::getTable(),
        Completion::getTable(),
        Answer::getTable(),
        AnswerValue::getTable(),
        \GlpiPlugin\Npsphere\Invite::getTable(),
        \GlpiPlugin\Npsphere\Delivery::getTable(),
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    return true;
}

/**
 * Restringe a LISTAGEM de pesquisas às criadas pelo usuário.
 *
 * Função com nome convencionado que o motor de busca do GLPI chama por
 * conta própria (Search::addDefaultWhere) -- documentada em
 * "Search Engine" no guia do desenvolvedor.
 *
 * O administrador geral vê todas. A mesma regra vale no formulário e
 * nos endpoints de gestão (Survey::podeGerenciar), para a listagem não
 * esconder o que o acesso direto por ID ainda permitiria abrir.
 */
function plugin_npsphere_addDefaultWhere($itemtype)
{
    if ($itemtype !== \GlpiPlugin\Npsphere\Survey::class) {
        return '';
    }

    if (\GlpiPlugin\Npsphere\Survey::isAdministradorGeral()) {
        return '';
    }

    $tabela = \GlpiPlugin\Npsphere\Survey::getTable();
    $usuario = (int) Session::getLoginUserID();

    return "`$tabela`.`creator_id` = $usuario";
}
