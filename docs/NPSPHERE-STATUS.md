# NPSphere — Status do Projeto

**Plugin de pesquisas de satisfação para GLPI 11**
Versão: **0.64.2** · atualizado em 21/10/2026 · Autor: Michael Rodrigues

---

## Legenda

| Símbolo | Significado |
|---|---|
| 🟢 **FINALIZADO** | Implementado, testado e validado em uso real |
| 🟡 **PARCIAL** | Funciona, mas há partes previstas ainda não entregues |
| ⚪ **PENDENTE** | Não iniciado |
| ⛔ **DESCARTADO** | Decidido não fazer |

---

## FASE 1 — Fundação 🟢

| Item | |
|---|---|
| Estrutura do plugin (setup, hooks, PSR-4, Twig) | 🟢 |
| Tabela `surveys` + CRUD | 🟢 |
| Menu em Administração | 🟢 |
| Instalação/desinstalação idempotente | 🟢 |

## FASE 2 — Perguntas 🟢

| Item | |
|---|---|
| **7 tipos**: NPS, Emoticons, Estrelas, Sim/Não, Escolha única, Múltiplas opções, Texto | 🟢 |
| Arquitetura extensível (`QuestionTypeManager` + classe por tipo) | 🟢 |
| Reordenação por arrastar-e-soltar | 🟢 |
| Configuração por tipo (escalas 0–10/0–5, 3/5 níveis, 3/5/10 estrelas) | 🟢 |
| Pré-visualização ao vivo no editor | 🟢 |

## FASE 3 — Respostas 🟢

| Item | |
|---|---|
| Formulário de resposta (autenticado) | 🟢 |
| **Página única** e **uma pergunta por etapa** | 🟢 |
| Validação por tipo, obrigatoriedade, prazo, duplicidade | 🟢 |
| Aba **Visualizar** — pré-visualização que **não grava** | 🟢 |

## FASE 4 — Métricas e Resultados 🟢

| Item | |
|---|---|
| Aba **Resultados** (Visão geral / Respostas) | 🟢 |
| NPS + classificação (Crítico → Excelente) | 🟢 |
| **Nota média** normalizada 0–10 | 🟢 |
| Denominador correto em perguntas condicionais | 🟢 |
| Busca (nome, e-mail, telefone, entidade, nº do chamado) | 🟢 |
| Filtros por data, nota e **entidade** | 🟢 |
| Ordenação, paginação, resposta individual com os widgets reais | 🟢 |
| Exportação **CSV** respeitando o filtro ativo | 🟢 |
| Impressão/PDF com escopo e cabeçalho | 🟢 |
| Exportação ODS / XLSX | ⚪ |

## FASE 5 — Regras Condicionais 🟢

| Item | |
|---|---|
| Pergunta exibida conforme resposta de outra | 🟢 |
| 6 operadores (=, ≠, <, ≤, >, ≥) | 🟢 |
| Validação **no navegador e no servidor** (mesma lógica) | 🟢 |
| Etapas ocultas puladas e fora da contagem | 🟢 |

## FASE 6 — Pesquisa Externa 🟢

| Item | |
|---|---|
| Acesso anônimo por token (`STRATEGY_NO_CHECK`) | 🟢 |
| Token de 256 bits, comparação em tempo constante | 🟢 |
| Bloqueio após 10 tentativas inválidas | 🟢 |
| Identificação (nome, e-mail, telefone internacional) | 🟢 |
| Validação de e-mail rigorosa (navegador + servidor) | 🟢 |
| Anti-duplicidade por e-mail/telefone | 🟢 |

## FASE 7 — Pesquisa Interna 🟢

| Item | |
|---|---|
| Público-alvo: entidade, perfil, grupo, usuário, "todos" | 🟢 |
| Recursividade de entidades respeitada | 🟢 |
| Tela **Minhas pesquisas** (pendentes + respondidas) | 🟢 |
| Direito próprio **"Minhas pesquisas"** | 🟢 |
| Acesso pelo **autoatendimento** | 🟢 |
| **Resposta obrigatória** com modal pós-login | 🟢 |
| Registro de conclusão (funciona em pesquisa anônima) | 🟢 |
| Quadro no painel do helpdesk | ⛔ |

## FASE 8 — Pesquisa Vinculada a Chamado 🟢

| Item | |
|---|---|
| Plugin como destino da **pesquisa externa nativa** do GLPI | 🟢 |
| Vínculo `answers.tickets_id` gravado | 🟢 |
| Pesquisa **embutida no chamado** (iframe isolado, no lugar do link) | 🟢 |
| Herança de tema e paleta do GLPI dentro do iframe | 🟢 |
| **Múltiplas pesquisas** — `&surveys_id=N` na URL, validado | 🟢 |
| Apenas o **requerente** responde · **uma avaliação por chamado** | 🟢 |
| Coluna **Chamado** nos resultados, com link direto | 🟢 |
| **Gravação em `glpi_ticketsatisfactions`** (`satisfaction` 0–10, `satisfaction_scaled_to_5`, `date_answered`, `comment`) | 🟢 |
| Nota preservada na desinstalação do plugin | 🟢 |

---

## FASE 10 — Painel Consolidado 🟢 **CONCLUÍDA**

*(feita antes da Fase 9, por decisão de prioridade)*

**Gestão > Painel de satisfação** — direito próprio (`plugin_npsphere_dashboard`).

| Item | |
|---|---|
| NPS consolidado, nota média, respostas, distribuição | 🟢 |
| **Mesmo cálculo da aba Resultados** (função compartilhada) | 🟢 |
| Gráfico de **rosca** (SVG, sem biblioteca externa) | 🟢 |
| **Evolução mensal**, com linha do zero e meses vazios preservados | 🟢 |
| Recortes: **Pesquisa · Entidade · Requerente · Técnico · Grupo técnico · Categoria** | 🟢 |
| Um **filtro por dimensão**, com seleção múltipla | 🟢 |
| **Comparação com o período anterior** (mesma duração, mesmos filtros) | 🟢 |
| **Amostra pequena** sinalizada (< 5 respostas) | 🟢 |
| **Clicar na linha** filtra o painel por ela | 🟢 |
| "Por pesquisa" abre a pesquisa **direto na aba Resultados** | 🟢 |
| **Exportação CSV** e impressão, com os mesmos filtros | 🟢 |
| Escopo: cada pessoa vê as pesquisas que criou | 🟢 |

### Decisões de cálculo registradas

- Notas **normalizadas para 0–10** antes de qualquer agregação;
- **Técnico e grupo técnico: o primeiro atribuído** — a soma dos
  recortes bate com o total;
- Respostas **sem chamado ficam fora** dos recortes de técnico,
  categoria, grupo e requerente;
- Ordenação **por volume**, não por NPS;
- Conjunto vazio devolve **NPS nulo, não zero**.

---

## SEGURANÇA — escopo por criador 🟢

Aplicado em **toda a gestão**, não só no painel:

| Ponto | Mecanismo |
|---|---|
| Listagem em Administração | `plugin_npsphere_addDefaultWhere()` |
| Abrir / editar / excluir / restaurar | `canViewItem()` e afins + checagem no formulário |
| Perguntas | a pesquisa vem do **banco**, não do formulário |
| Resultados, exportação, pré-visualização, público-alvo, detalhe de resposta | checagem em cada endpoint |

Administrador geral (direito de alterar a configuração do GLPI) gerencia
todas. **As rotas de resposta não são restritas** — quem responde não
criou a pesquisa.

---

## FASE 9 — Notificações e convites por e-mail 🟡 **EM ANDAMENTO**

Entregue de 0.51.3 a 0.54.0.

### Aba Template (e-mail do convite)

| Item | |
|---|---|
| Aba **Template** entre Visualizar e Resultados | 🟢 |
| Sub-abas **Modelo** e **Variáveis** (variável, significado, exemplo real) | 🟢 |
| Mensagem **lado a lado**: prévia HTML5 à esquerda, código à direita, atualizando ao digitar | 🟢 |
| Restaurar modelo padrão; marcadores clicáveis | 🟢 |
| **Uma variável por pergunta**, com o nome da pergunta (`{{como_voce_avalia...}}`) | 🟢 |
| Aviso: ideal só com **uma pergunta** no e-mail, com o motivo | 🟢 |
| Prévia com o **primeiro destinatário real** (sem nome inventado) | 🟢 |
| E-mail centralizado e com respiro (padding nas células) | 🟢 |
| Template da **pesquisa interna**: quadro "Quem recebe o convite" (números + lista + situação), envio com contagem (sem janela de confirmação, 0.55.1) | 🟢 |
| Template da **pesquisa de chamado**: quadro "Quando o convite sai" (requerente, atraso, percentual da entidade) e prévia com o último chamado encerrado real | 🟢 *validado em 14/10* |

### Destinatários (pesquisa externa)

| Item | |
|---|---|
| Campos **Nome + E-mail + [+]**, lista editável com remover | 🟢 |
| **Importar CSV** (`;`, `email;nome`, aceita `,`) e **modelo CSV** para baixar | 🟢 |
| Repetidos e inválidos recusados, com aviso | 🟢 |

### Envio pela fila do GLPI

| Item | |
|---|---|
| Envio por `QueuedNotification` (fila nativa, ação `queuednotification`) | 🟢 |
| Todos os campos preenchidos: remetente, **Reply-to**, **Message-ID**, **cabeçalhos**, HTML completo, texto | 🟢 |
| **Modelo de notificação** "NPSphere — Convite de pesquisa" (`NotificationTargetSurvey`) | 🟢 |
| Links com o **endereço completo** do GLPI (URL da aplicação, https) | 🟢 |
| Convite do **chamado** no lugar da notificação nativa (sem e-mail duplicado) | 🟢 |
| Nativa suprimida **sem** "Erro ao inserir e-mail na fila" (vai direto para a lixeira) | 🟢 |
| CSRF correto nas chamadas AJAX (prévia, importação, envio) | 🟢 |
| E-mail do chamado leva a **mesma pesquisa** da URL da entidade (`&surveys_id=`) — 0.57.1 | 🟢 *validado (link com surveys_id=6)* |
| Botões do e-mail no **Outlook para Windows** (padrão bulletproof) — 0.57.2 | 🟢 *validado em 14/10* |
| Envio do formulário aberto pelo botão do e-mail **sem login** (token novo antes de enviar) — 0.57.3 | 🟢 *validado em 14/10* |

### Responder direto do e-mail (um clique)

O risco levantado antes da fase (filtros de e-mail abrindo os links)
foi resolvido assim: **abrir o link não grava nada**; quem grava é o
script da página, no navegador da pessoa. Clicar de novo **troca** a
resposta, sem duplicar.

| Item | |
|---|---|
| **Externa identificada**: link pessoal por destinatário (`invites`), resposta com nome/e-mail da lista | 🟢 |
| **Interna**: link pessoal com chave assinada, **sem login**; grava em nome da pessoa; só o público-alvo | 🟢 *validado em 14/10, inclusive o teste de link adulterado* |
| **Chamado**: grava em nome do requerente e atualiza a satisfação do GLPI | 🟢 |
| **Chamado sem login** (link com chave assinada HMAC) | 🟢 *validado em 14/10* |
| Várias perguntas: grava a clicada e o formulário **completa a mesma** resposta | 🟢 |
| Pesquisa que exige identificação: abre o formulário com nome/e-mail preenchidos | 🟢 |
| Tela de **confirmação** redesenhada | 🟢 |
| **Externa anônima** gravando no clique | ⛔ *decidido não fazer: anônima continua só marcando a opção; a pessoa confirma em Enviar* |

### Ainda da Fase 9

| Item | |
|---|---|
| **Lembrete** para quem não respondeu — sub-aba "Lembrete" (interna, externa identificada e chamado), ação automática `npsphereLembrete` — 0.58.0 | 🟢 *validado em 15/10 (lembrete da pesquisa interna recebido no Outlook; respondido pelo próprio lembrete)* |
| Modelo de notificação próprio **"NPSphere — Lembrete de pesquisa"** — 0.58.1 | 🟡 *em teste* |
| Lembrete do chamado limitado pela **Duração da pesquisa** da entidade (padrão do GLPI; o núcleo não tem lembrete próprio) — 0.58.0 | 🟡 *em teste* |
| Lembrete na **externa anônima** | ⛔ *não se aplica: link único, não há como saber quem respondeu* |
| Aviso ao gestor em nota baixa → virou **relatório diário** (decisão de 14/10): todas as respostas do dia por pessoa, notas baixas primeiro; nota de corte configurável sobre a **nota da resposta** (a mesma da aba Resultados, 0 a 10); usuários e grupos do GLPI; horário por pesquisa; ação `npsphereRelatorio` — 0.59.0, layout refeito na 0.59.1 | 🟢 *validado em 14/10 (e-mail recebido)*; histórico: *em teste — 0.59.0 achado confuso e com nota divergente dos Resultados, corrigido na 0.59.1; links só para quem abre a pesquisa no GLPI na 0.59.2* |
| **Aviso imediato de nota baixa** (espera configurável, padrão 10 min; mesmos destinatários e nota de corte do relatório; ação `npsphereAviso` a cada 5 min) — 0.60.0 | 🟡 *em teste — prévia do aviso OK; prévia do relatório falhava com as duas prévias juntas (tokens CSRF em paralelo), corrigido na 0.60.1; prévias dentro de cada quadro e só com o interruptor ligado na 0.60.2; **e-mail de aviso recebido em 14/10*** |
| **Aviso independente**: nota baixa, usuários/grupos, links e último envio próprios — 0.62.0 | 🟢 *validado em 14/10 (configuração copiada, só a lista do aviso recebe, nota separada, último envio, não repete, respostas antigas não disparam)* |
| **Convite do chamado com espera** (padrão 15 min): só sai se o requerente não avaliar no próprio chamado; ação `npsphereConvite` — 0.63.0 | 🟢 *validado no teste automatizado de 21/10 (avaliado no chamado → sem e-mail; sem avaliar → e-mail; reaberto → nada)* |
| Aba Template com **5 sub-abas** (Convite, Lembrete, Relatório, Aviso, Variáveis) — 0.61.5 | 🟢 *validado em 14/10* |
| **Aba Template mais limpa**: ajuda dos campos em ícones ⓘ, link "Como funciona" em cada quadro, avisos só quando há problema; sub-aba **Variáveis → Ajuda** (Como funciona por assunto + Ações automáticas + Variáveis) — 0.64.0 | 🟡 *em teste* |
| Relatório e aviso: quadros recolhidos quando desligados; **modelo de e-mail editável** (assunto, HTML5 + código, restaurar padrão, variáveis com descrição) — 0.61.0 | 🟢 *validado em 14/10* |
| Sub-aba Variáveis: "Variáveis do modelo e lembrete" + novo quadro "Variáveis do relatório e aviso" — 0.61.2; colunas alinhadas e tabelas recolhidas — 0.61.3; observações dentro de cada grupo — 0.61.4 | 🟢 *validado em 14/10* |
| `{{link}}` completo na prévia/Variáveis da pesquisa de chamado — 0.61.1 | 🟢 *validado em 14/10* |
| URL completa da pesquisa de chamado na aba Pesquisa de satisfação (regressão da 0.51.0) — 0.59.2 | 🟢 *validado em 14/10* |

## FASE 11 — Acabamentos ⚪

| Item | |
|---|---|
| Exportação ODS / XLSX | ⚪ |
| Regras de exclusão no público-alvo ("grupo X exceto fulano") | ⚪ |
| Auditoria / histórico de alterações | ⚪ |
| Limite de requisições por tempo no link público | 🟡 |
| Tradução completa (`.mo` compilado) | 🟡 |

---

## Resumo

| Fase | Status |
|---|---|
| 1. Fundação | 🟢 |
| 2. Perguntas | 🟢 |
| 3. Respostas | 🟢 |
| 4. Métricas | 🟢 |
| 5. Regras condicionais | 🟢 |
| 6. Pesquisa externa | 🟢 |
| 7. Pesquisa interna | 🟢 |
| 8. Vinculada a chamado | 🟢 |
| 9. Notificações e convites | 🟡 **em andamento** |
| **10. Painel consolidado** | **🟢** |
| 11. Acabamentos | ⚪ |

**9 de 11 fases finalizadas; a 9 está em andamento.**

---

## Inventário técnico

**Tabelas (8):** `surveys`, `questions`, `answers`, `answer_values`,
`targets`, `completions`, **`invites`** *(0.52.0)*, **`deliveries`** *(0.58.0 — convites enviados, para o lembrete)*
*(além da gravação em `glpi_ticketsatisfactions`, que pertence ao GLPI)*

**Classes (27):** Survey, Question, Answer, AnswerValue, Target,
Completion, TicketLink, GlpiSatisfaction, ResponseService,
MetricsCalculator, Dashboard, DashboardMenu, ConditionEvaluator,
PublicAccess, RespondentIdentity, QuestionTypeManager, Profile,
MySurveysMenu, SurveyPreview, SurveyResults, SurveyTargets,
**SurveyTemplate**, **SurveyMailer**, **MailPolicy**,
**NotificationTargetSurvey**, **Invite**, **LinkSigner**, **Delivery** *(lembrete)*, **DailyReport** *(relatório e aviso)*

**Ações automáticas (4):** `npsphereConvite` (a cada minuto), `npsphereLembrete` (de hora em hora, 8h–18h), `npsphereRelatorio` (de hora em hora), `npsphereAviso` (a cada 5 min)

**Modelos de notificação (4):** Convite, Lembrete, Relatório diário, Aviso de nota baixa

**Tipos de pergunta (7):** Nps, Emoticon, Stars, YesNo, SingleChoice,
MultipleChoice, Text *(+ 2 classes abstratas)*

**Rotas:** 9 em `front/`, 23 em `ajax/` *(sem login: `public.php`, `ticket.answer.php` e os AJAX que eles usam)*

**Direitos (3):** `plugin_npsphere_survey` (gerenciar),
`plugin_npsphere_dashboard` (painel), `plugin_npsphere_answer`
(responder)

**Menus:** Administração (pesquisas), Gestão (painel), Ferramentas
(Minhas pesquisas), autoatendimento

**URLs:** dentro do GLPI, `Plugin::getWebDir()`, que acompanha o local
real da instalação (`/marketplace/npsphere/front/…`). **No e-mail**, o
endereço completo a partir da URL da aplicação do GLPI
(`SurveyMailer::getBaseAbsoluta()`), desde a 0.51.10.

A entrada do menu do **autoatendimento** é exceção deliberada: quem a
monta é o GLPI, e ela sai como `/plugins/npsphere/front/mysurveys.php`.
Existiu uma versão (0.50.2) que a reescrevia para o mesmo padrão das
demais, mas ela foi **descartada**: reescrever um endereço gerado pelo
GLPI significa que, se o roteamento de plugins mudar numa versão futura,
o plugin continuaria apontando para o caminho antigo. Deixando como
está, a entrada acompanha a mudança sozinha.

> Os dois caminhos funcionam no GLPI 11. A diferença é cosmética, e a
> regra vale a pena: **padronizamos o que nós geramos; não reescrevemos
> o que o GLPI gera.**

---

## Aprendizados registrados

Cada um custou várias rodadas:

- **Confirmar o efeito, não o retorno da chamada.** Na integração com a
  satisfação nativa, três camadas devolveram "sucesso" sem gravar nada;
- **Dois caminhos para a mesma coisa não dão redundância** — dão um que
  funciona e outro que falha em silêncio (foi o caso do NPS divergindo
  entre o painel e a aba Resultados, resolvido com **uma** função
  compartilhada);
- **Instrumentar em vez de supor.** As causas difíceis só apareceram
  depois que a tela ou o log passaram a dizer o motivo;
- **Entregar só o que foi pedido.** Refinamentos embutidos na mesma
  versão misturam duas mudanças no mesmo teste e atrasam o diagnóstico;
- **Um seletor de menu por seção.** Uma segunda entrada na mesma seção é
  ignorada pelo GLPI, sem erro nenhum.

---

## Onde estamos agora

**Fase 9: entregue e testada de ponta a ponta (0.64.1; 0.64.2 com o botão Duplicar e o README com telas).** Repositório público: https://github.com/MichaelRodriguesOficial/NPS-GLPi

### Teste completo de 21/10 — GLPI 11.0.11 + MariaDB 10.11 + PHP 8.3

Feito num GLPI montado só para o teste, com navegador automatizado e envio real por SMTP de teste. Tudo ✅:

- **Instalação:** 8 tabelas, 4 ações automáticas, 4 modelos e os direitos de perfil. **Desinstalação** limpa tudo e preserva a nota gravada na satisfação nativa. **Reinstalação** OK.
- **Atualização** a partir da 0.50.0 e da 0.63.0: o banco fica idêntico ao de uma instalação nova (colunas, tipos, padrões e índices).
- **Telas:** todas as abas das pesquisas interna, externa e de chamado abrem, mais Minhas pesquisas, painel e listagem, sem erro de PHP ou JavaScript. As 4 prévias da aba Template montam. **Salvar** a aba Template grava e preserva o resto.
- **Respostas:**
  - pela tela (logado);
  - pelo link do e-mail sem login, na interna, na externa e no chamado; clicar de novo troca a nota sem duplicar;
  - pelo link público;
  - pela pesquisa dentro do chamado, gravando em `glpi_ticketsatisfactions`.
- **Segurança:** link com chave adulterada é recusado.
- **Resultados, CSV e painel** batem com as respostas.
- **Convite:** convites enfileirados; a notificação nativa de satisfação vai para a lixeira, então não chegam dois e-mails.
- **Convite com espera:** avaliado no chamado → sem e-mail; sem avaliar → e-mail; chamado reaberto → nada.
- **Lembrete:**
  - só para quem não respondeu;
  - não repete antes do intervalo;
  - para no máximo.
- **Relatório diário:**
  - envio único no dia;
  - **2b** (dia sem resposta: nada na fila e o "último envio" não muda);
  - links só para quem abre a pesquisa no GLPI.
- **Aviso de nota baixa:**
  - respeita a espera;
  - só respostas depois de ligado;
  - um aviso por resposta;
  - grava o último envio.
- **Remover definitivamente** apaga perguntas, respostas, valores e envios, e preserva a satisfação nativa. **Duplicar** (POST) zera as datas na cópia.
- **E-mails reais** pelo SMTP do GLPI: 22 do NPSphere, todos com HTML e texto, variáveis trocadas e links absolutos.

**Corrigidos na 0.64.1:**
- clique do e-mail que às vezes não gravava para quem não estava logado;
- pesquisa nova vindo com "várias respostas: Sim" e "anônima: Sim";
- balões ⓘ cortados pelo menu de abas.

### Decisões de 21/10

- **Duplicar pesquisa:**
  - ✅ botão criado na 0.64.2;
  - copia a configuração, as perguntas (com as regras condicionais) e os modelos;
  - **não** copia o público-alvo (nem "todos os usuários" ou a lista de destinatários da externa);
  - a cópia nasce **inativa**.
- **Prévia do convite em tela estreita** (linha 0–10 cortada só na prévia): OK como está.
- **Modelo padrão do convite de chamado** com a 1ª pergunta no e-mail: explicado ao Michael; aguardando decisão.
- **README em inglês**, no padrão do Chatwoot for GLPI:
  - logo, destaques, telas por assunto (incluindo self-service) e 5 GIFs;
  - segurança, solução de problemas e doações (Sponsors + Pix com descrição "Cafe para o plugin NPSphere").
- **docs/FAQ.md**: guia de uso completo, em inglês.
- **Releases no GitHub:**
  - a release v0.64.2 já tem o ZIP anexado;
  - o workflow `.github/workflows/release.yml` publica uma release nova sozinho sempre que a versão do `setup.php` muda na main (notas em `.github/release-notes/vX.Y.Z.md`);
  - descrição e tópicos do repositório agora em inglês.
- Também corrigidos na 0.64.2:
  - telefone espremido na página pública no celular;
  - texto do aviso do clique no e-mail quebrado em colunas.

### Falta testar no ambiente real

- **Template limpo (0.64.0/0.64.1):** conferir visualmente no seu GLPI.
- **Botão Duplicar (0.64.2):** duplicar uma pesquisa e conferir perguntas, inativa e sem público-alvo.
- Relatório com muitas respostas (acima de ~60/dia o Gmail corta o e-mail) e ações automáticas no modo CLI.

### Pontos encontrados na revisão — corrigidos na 0.62.1 (validados em 21/10)

- "Último envio" do relatório só muda quando um e-mail sai (`date_report_sent`; `date_last_report` segue como dia processado).
- Remover pesquisa definitivamente apaga perguntas, respostas e valores, público-alvo, conclusões, convites e envios do lembrete.
- Duplicar pesquisa zera as datas de envio na cópia (e o aviso conta a partir da criação da cópia).
- Ainda válido: relatório com muitas respostas (acima de ~60/dia o Gmail corta) e ações automáticas no modo CLI.

### Fase 11 — acabamentos

Exportação ODS/XLSX; regras de exclusão no público-alvo; auditoria/histórico; limite de requisições no link público (parcial); tradução `.mo` (parcial).
Ideias registradas: relatório agrupado por técnico; comparação com a média dos últimos 30 dias.
