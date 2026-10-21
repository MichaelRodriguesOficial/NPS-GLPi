# Histórico de versões — NPSphere

As versões mais recentes ficam no topo.

## v0.64.1 — correções encontradas no teste completo num GLPI 11.0.11

Testado de ponta a ponta num GLPI 11.0.11 com MariaDB:
- instalação, desinstalação e reinstalação;
- atualização a partir da 0.50.0 e da 0.63.0 (banco idêntico ao de uma instalação nova);
- convites, respostas e ações automáticas;
- envio real pela fila de e-mails do GLPI.

- **Resposta pelo link do e-mail às vezes não era gravada** ("Não conseguimos registrar sua resposta automaticamente"). Acontecia com quem não estava logado no GLPI, a partir do segundo clique no mesmo navegador.
  - **Causa:** o script das pesquisas obrigatórias (`pending.php`), carregado em todas as páginas, mandava o visitante para a tela de login. Essa tela limpa a sessão e levava junto o token de segurança que o clique do e-mail estava para usar.
  - **Correção:** o `pending.php` passa a responder sem exigir login. Para quem não está logado, ele já devolvia uma lista vazia.
- **Pesquisa nova já vinha com "Permitir várias respostas: Sim"** (e a externa com "Anônima: Sim"). Sem valor inicial, nenhuma opção ficava marcada e o navegador escolhia a primeira. Agora o formulário de pesquisa nova abre com "Não" nos dois, como o padrão do banco.
- **Balões ⓘ:** no GLPI, os balões dos campos da coluna da esquerda ficavam por baixo do menu de abas e eram cortados. Agora crescem para a direita; os do lado direito, para a esquerda.

## v0.64.0 — aba Template mais limpa (ajuda em ⓘ e sub-aba Ajuda)

- As sub-abas **Convite, Lembrete, Relatório e Aviso** mostram só o essencial: título, uma linha de descrição, campos, números, prévias e **avisos só quando há problema** (ação automática desativada, ninguém recebe, entidade mal configurada, pesquisa anônima).
- A ajuda de cada campo foi para um ícone **ⓘ** ao lado do nome: passe o mouse (ou toque) para ler.
- Ao lado do título de cada quadro há o link **"Como funciona"**, que abre a sub-aba de ajuda já no assunto certo.
- A sub-aba **Variáveis** virou **Ajuda**, com dois quadros recolhidos no mesmo padrão:
  - **Como funciona**: Convite, Perguntas dentro do e-mail, Lembrete, Relatório diário, Aviso de nota baixa, Quem recebe os links e **Ações automáticas** (tabela com as 4 ações do NPSphere, se estão ativas, e a fila do GLPI);
  - **Variáveis**: as do convite e lembrete, do relatório e do aviso.
- O aviso "Perguntas dentro do e-mail" só aparece quando a pesquisa tem mais de uma pergunta **e** a mensagem do convite usa alguma delas.
- Nos editores do relatório e do aviso, os marcadores viraram só botões (o significado aparece ao passar o mouse e está na Ajuda).
- Nenhuma mudança de funcionamento: nada novo no banco, nos envios ou nas ações automáticas.

## v0.63.0 — convite do chamado com espera (avaliação no chamado primeiro)

- Ao encerrar o chamado, a pesquisa aparece na hora no próprio chamado, mas o **e-mail espera** (padrão **15 minutos**). Passada a espera:
  - se o requerente **já avaliou no chamado**, o e-mail **não sai** (nem os lembretes);
  - se **não avaliou**, o convite sai normalmente, com a pesquisa que o chamado usa naquele momento;
  - se o chamado foi **reaberto** nesse meio-tempo (o GLPI apaga a pesquisa dele), nada sai.
- A espera é configurável na sub-aba **Convite** da pesquisa de chamado, no quadro "Quando o convite sai": não esperar (como antes), 5, 10, **15**, 30 ou 60 minutos. O quadro ganhou o número "+ N min — o e-mail sai, se ainda não avaliou".
- Nova ação automática **npsphereConvite** (a cada minuto, o dia todo). O convite agendado fica em `glpi_plugin_npsphere_deliveries` com o estado `agendado` e a hora em `date_due` (novo campo); não conta nos números do lembrete até sair.
- Novo campo na pesquisa: `invite_delay` (minutos; padrão 15 — vale também para as pesquisas de chamado existentes).

## v0.62.1 — três correções da revisão

- **"Último envio" do relatório só muda quando um e-mail sai.** Em dia sem resposta (nada enviado), o campo mudava mesmo assim. Agora há dois campos: `date_last_report` continua marcando o dia processado (de onde o próximo relatório começa) e o novo `date_report_sent` guarda o último envio de verdade, que é o que a tela mostra. Na atualização, ele começa com o último dia processado.
- **Remover pesquisa definitivamente apaga tudo dela:** perguntas, respostas e seus valores, público-alvo, registros de conclusão, convites e envios do lembrete. Antes só os envios do lembrete eram apagados e o resto ficava órfão no banco. A satisfação nativa do GLPI (do chamado) não é tocada.
- **Duplicar pesquisa:** a cópia nasce sem as datas de envio da original (último relatório, último envio, último aviso). Se o aviso estiver ligado, ele conta a partir da criação da cópia.

## v0.62.0 — aviso independente do relatório

- A sub-aba **Aviso** passa a ter a sua **própria configuração**, como o Relatório:
  - **Nota baixa** própria (até 0…9; padrão 6) — independente da do relatório;
  - **Usuários e grupos que recebem**, nos campos do GLPI;
  - os números **pessoa(s) recebem**, **abrem os resultados no GLPI** e **último envio** (novo);
  - a lista de quem recebe com **Recebe os links / Sem os links (sem acesso)**.
- O envio (`npsphereAviso`) usa a lista e a nota do aviso e grava o **último envio** do aviso (`date_last_alert`). A prévia do aviso acompanha a nota baixa da sub-aba Aviso.
- **Atualização:** nas pesquisas existentes, o aviso recebe uma vez a lista e a nota baixa do relatório (era o que valia até aqui), para nada mudar até alguém editar.
- Novos campos: `alert_nps_cut`, `alert_recipients`, `date_last_alert`. O bloco de "quem recebe" virou um componente único usado pelo Relatório e pelo Aviso.

## v0.61.5 — aba Template com 5 sub-abas

- A aba Template agora tem **Convite** (antes "Modelo"), **Lembrete**, **Relatório**, **Aviso** e **Variáveis**. A antiga "Relatório e aviso" foi separada em duas, cada uma com o seu botão Salvar e o seu selo "ligado".
- Na sub-aba **Aviso**, a linha "Quem recebe e o que é nota baixa" lembra que são os da sub-aba Relatório e tem um botão **"Mudar na sub-aba Relatório"**, que leva direto para lá.
- Textos que citavam "Relatório e aviso" ou "Modelo" atualizados (Variáveis, modelos de notificação, ação automática, rodapé do relatório padrão). "Variáveis do modelo e lembrete" passou a "Variáveis do convite e lembrete".

## v0.61.4 — sub-aba Variáveis: observações sempre dentro do grupo

- A observação sobre as variáveis "bloco pronto" ficava solta no fim do quadro do relatório e aviso, enquanto as do convite e lembrete ficavam dentro do grupo. Agora o padrão é um só: **cada grupo tem as suas observações dentro dele**, abrindo e fechando junto com a tabela.
- Relatório diário e Aviso imediato de nota baixa ganharam cada um a sua observação, citando os próprios blocos e a regra dos links de cada e-mail.

## v0.61.3 — sub-aba Variáveis: colunas alinhadas e tabelas recolhidas

- As três tabelas (Convite e lembrete, Relatório diário, Aviso imediato de nota baixa) usam as **mesmas larguras de coluna** (Variável 30%, Significado 40%, terceira coluna 30%) e o mesmo alinhamento: as colunas ficam uma embaixo da outra.
- As tabelas abrem **recolhidas**: cada grupo é uma linha com o nome e a quantidade de variáveis; clicar abre ou fecha a tabela (e as observações daquele grupo).

## v0.61.2 — sub-aba Variáveis com as do relatório e aviso

- O quadro "Variáveis da mensagem" passou a se chamar **"Variáveis do modelo e lembrete"**, deixando claro que vale para os dois (convite e lembrete usam as mesmas).
- Novo quadro **"Variáveis do relatório e aviso"**, com uma tabela para o **Relatório diário** e outra para o **Aviso imediato de nota baixa**: a variável, o significado e **onde usar** — "Assunto e mensagem" para as de texto, "Só na mensagem (bloco pronto)" para as que trazem uma parte inteira do e-mail já formatada.
- Uma nota explica que cada e-mail tem as suas variáveis (a de um, escrita no outro, sai como texto) e que os blocos não são trocados no assunto.

## v0.61.1 — {{link}} completo na prévia da pesquisa de chamado

- Na pesquisa de chamado, a variável `{{link}}` (e o botão e as opções) da **prévia** e da sub-aba **Variáveis** saía só com o caminho (`/marketplace/npsphere/front/survey.form.php?id=…`), sem o endereço do GLPI. Agora sai completa (`https://servidor/marketplace/npsphere/front/survey.form.php?id=…`), com a "URL da aplicação" da entidade ou a geral, como os demais links.
- O e-mail enviado de verdade não era afetado: ele já usava o link completo de avaliação do chamado (`ticket.answer.php`).

## v0.61.0 — relatório e aviso: quadros recolhidos e modelo de e-mail editável

- Sub-aba **Relatório e aviso**: com o interruptor **desligado**, cada quadro mostra só o título, a descrição e o interruptor ("Enviar o relatório diário" / "Avisar na hora quando chegar uma nota baixa"). Ao **ligar**, aparece o resto: configuração, quem recebe, avisos, o botão da ação automática e o e-mail.
- **E-mail do relatório** e **E-mail do aviso** editáveis, no mesmo padrão do convite e do lembrete: **assunto**, **prévia em HTML5** ao lado do **código**, botão de **restaurar o padrão** e os **marcadores** clicáveis, com a descrição de cada um ao lado. A prévia acompanha a digitação, o assunto, a nota de corte e a espera da tela.
- O **modelo padrão** é o layout atual (o texto da 0.60.x), agora montado com variáveis:
  - relatório: `{{pesquisa}}`, `{{periodo}}`, `{{data}}`, `{{total_respostas}}`, `{{total_baixas}}`, `{{nota_media}}`, `{{nota_corte}}` e os blocos `{{numeros}}`, `{{notas_baixas}}`, `{{respostas_por_pessoa}}`, `{{link_resultados}}`;
  - aviso: `{{pesquisa}}`, `{{nota}}`, `{{pessoa}}`, `{{email}}`, `{{telefone}}`, `{{chamado_id}}`, `{{chamado_titulo}}`, `{{chamado}}`, `{{data}}`, `{{espera}}`, `{{nota_corte}}` e os blocos `{{cartao}}`, `{{respostas}}`, `{{botao_resposta}}`.
- Os links "Ver resposta" / "Abrir os resultados" continuam só para quem abre a pesquisa no GLPI, também com modelo próprio.
- Novos campos na pesquisa: `report_subject`, `report_body`, `alert_subject`, `alert_body` (em branco = padrão).

## v0.60.2 — prévias junto de cada quadro e só quando ligado

- Sub-aba **Relatório e aviso**: a **Prévia do relatório** saiu do quadro separado (que ficava abaixo do aviso) e agora fica **dentro do quadro "Relatório diário"**; a **Prévia do aviso**, dentro do quadro "Aviso imediato de nota baixa". Cada quadro tem tudo o que é dele.
- Cada prévia só aparece com o seu interruptor **ligado** ("Enviar o relatório diário" / "Avisar na hora quando chegar uma nota baixa"). Ao ligar, ela é montada na hora; ao desligar, some.

## v0.60.1 — prévia do relatório com "Não foi possível montar a prévia"

- Na sub-aba **Relatório e aviso**, as duas prévias (relatório e aviso) pediam o token de segurança (CSRF) ao mesmo tempo. Os dois tokens eram gravados na sessão em paralelo e um sobrescrevia o outro; o pedido que ficava com o token perdido era recusado pelo GLPI e a prévia do relatório mostrava "Não foi possível montar a prévia".
- Agora os pedidos da aba Template saem **um de cada vez** (fila): cada um busca o seu token e só então o próximo começa.

## v0.60.0 — aviso imediato de nota baixa

- Novo quadro **"Aviso imediato de nota baixa"** na sub-aba **Relatório e aviso** (antes "Relatório"): um e-mail curto para os gestores alguns minutos depois de chegar uma resposta com nota baixa, para falar com o cliente no mesmo dia — além do relatório diário.
- **Espera configurável** (5, 10, 15, 30 ou 60 minutos; padrão 10), contada da última alteração da resposta: dá tempo de a pessoa terminar o comentário depois de clicar a nota no e-mail, e o aviso sai com a resposta completa.
- **Quem recebe e o que é nota baixa:** os mesmos do relatório diário — os usuários e grupos escolhidos e a mesma nota de corte (a nota da coluna "Nota" da aba Resultados). O link "Ver resposta no GLPI" só vai para quem abre a pesquisa, como no relatório.
- O e-mail traz a nota em destaque, quem respondeu com **e-mail e telefone** (para contato), o chamado com link, a data e todas as perguntas e respostas. Pesquisa anônima: sem identificação.
- **Um aviso por resposta** (nova coluna `date_alerted` nas respostas). Se a pessoa mudar a nota para baixa depois, o aviso sai nessa hora. Só valem as respostas a partir do momento em que o aviso é ligado — ligar não dispara e-mails sobre respostas antigas.
- **Prévia** na própria sub-aba, com a nota baixa mais recente dos últimos 30 dias.
- Nova ação automática **npsphereAviso** (a cada 5 minutos, o dia todo), novo modelo de notificação **"NPSphere — Aviso de nota baixa"** e evento próprio na fila.

## v0.59.2 — URL completa na entidade e links do relatório só para quem tem acesso

- **URL da pesquisa de chamado completa de novo:** a aba Pesquisa de satisfação mostrava só o caminho (`/marketplace/npsphere/front/ticket.php?...`), sem o servidor — regressão da 0.51.0. Volta a mostrar o endereço completo (`https://servidor/marketplace/npsphere/front/ticket.php?tickets_id=[TICKET_ID]&surveys_id=N`), com a "URL da aplicação" da entidade da pesquisa ou a geral, a mesma base dos links do e-mail.
- **Relatório diário — links por pessoa:** "Ver resposta" e "Abrir os resultados" só vão para quem consegue abrir a pesquisa no GLPI. A conferência é a mesma regra do GLPI ao abrir a pesquisa, feita sem a sessão da pessoa (o relatório sai pela ação automática): perfil da interface padrão com acesso à entidade da pesquisa, leitura em "Pesquisas de satisfação", e ser quem criou a pesquisa ou administrador geral. Quem não passa recebe o relatório completo, sem esses dois links, com uma linha explicando o motivo. O link do chamado vai para todos.
- Na sub-aba **Relatório**, a lista de quem recebe mostra, pessoa por pessoa, se recebe os links ou não, e um número "abrem os resultados no GLPI".

## v0.59.1 — relatório diário mais claro e com a mesma nota da aba Resultados

- **Divergência corrigida:** o relatório marcava "nota baixa" pela nota de UMA pergunta (ex.: NPS 6 numa resposta que também tinha emoji "Muito satisfeito"), enquanto a aba Resultados mostra a **nota da resposta** (a média das perguntas com nota, de 0 a 10 — nesse exemplo, 8). E o selo "Nota baixa" ficava no nome da pessoa, dando a impressão de que todas as respostas dela eram baixas.
- Agora cada resposta leva **a mesma nota e a mesma cor da coluna "Nota"** da aba Resultados, e "nota baixa" é dessa nota. A configuração ficou com uma nota de corte só: **"Nota baixa: até N"** (0 a 10, padrão 6). O campo de estrelas/emoji saiu, porque eles já entram convertidos na nota da resposta.
- Novo layout do e-mail, em três partes:
  1. números do dia: respostas, com nota baixa e nota média;
  2. **Notas baixas**: uma linha por resposta baixa (nota, pessoa, chamado, "Ver resposta");
  3. **Todas as respostas, por pessoa**: cada resposta num bloco próprio com a nota, o chamado, a data e as perguntas. Nenhum selo no nome da pessoa.
- Saíram "pessoas" e "NPS do período" (com poucas respostas, o NPS do dia confundia mais do que ajudava).

## v0.59.0 — relatório diário das respostas para os gestores

- Nova sub-aba **Relatório** na aba Template (entre Lembrete e Variáveis), em todos os tipos de pesquisa:
  - liga/desliga o relatório diário;
  - **horário de envio** (padrão 18:00);
  - **nota baixa** configurável: NPS "até N" na escala de 0 a 10 (padrão 6, os detratores) e estrelas/emoji "até N" na escala de 1 a 5 (padrão 2). Em perguntas com outra escala (NPS de 0 a 5, por exemplo) a nota é convertida;
  - **quem recebe**: usuários e grupos do GLPI, nos campos nativos de múltipla escolha. Todos os membros dos grupos recebem; só usuários ativos e com e-mail;
  - **prévia** do e-mail com as respostas reais das últimas 24 horas (sem nenhuma, dos últimos 30 dias), acompanhando as notas de corte da tela.
- O e-mail traz **todas as respostas** desde o relatório anterior, **separadas por pessoa**, com quem deu nota baixa **primeiro** e marcado em vermelho. No topo: respostas, pessoas, com nota baixa e o NPS do período (mesmo cálculo da aba Resultados). Cada resposta tem a data, o **chamado** (com link, na pesquisa de chamado), todas as perguntas e respostas (comentários inclusive) e o link **"Ver esta resposta no GLPI"**, que abre a aba Resultados já com o detalhe daquela resposta.
- Dia sem resposta, nada é enviado. Na pesquisa anônima, o relatório nunca mostra quem respondeu.
- A resposta entra pela data da última alteração: quem clica a nota no e-mail e completa o comentário depois aparece uma vez só, com tudo.
- Quem envia é a nova ação automática **npsphereRelatorio** (Configurar › Ações automáticas), de hora em hora; a primeira execução depois do horário escolhido manda o relatório do dia. O primeiro relatório cobre as últimas 24 horas.
- Novo modelo de notificação **"NPSphere — Relatório diário de pesquisa"** e evento próprio na fila.

## v0.58.1 — modelo de notificação próprio para o lembrete

- Novo modelo **"NPSphere — Lembrete de pesquisa"** em Configurar › Notificações › Modelos de notificação, criado ao atualizar o plugin. Na Fila de notificações, os lembretes passam a mostrar esse modelo; os convites continuam com "NPSphere — Convite de pesquisa".
- O id de cada modelo fica guardado na configuração do plugin (os dois têm o mesmo tipo, a Pesquisa, e o nome pode ser trocado na tela). Nas instalações que vêm da 0.58.0, o modelo que já existia continua sendo o do convite.
- Nada muda no conteúdo: o modelo só repassa o assunto e a mensagem montados na aba Template (convite) e na sub-aba Lembrete.

## v0.58.0 — lembrete para quem não respondeu

- Nova sub-aba **Lembrete** na aba Template, entre Modelo e Variáveis, para pesquisas **internas**, **externas** e **de chamado**:
  - liga/desliga o lembrete;
  - **a cada N dias** sem resposta (1 a 30), contados do convite e depois do último lembrete;
  - **no máximo N lembretes** por pessoa (1 a 5);
  - assunto e mensagem próprios, no mesmo editor do convite (prévia ao lado do código, marcadores, restaurar o padrão). Em branco, usa um texto padrão de lembrete;
  - números da pesquisa: convites registrados, já responderam, aguardando, lembretes enviados.
- **Pesquisa de chamado:** o GLPI não tem lembrete de satisfação no núcleo (só o plugin "More satisfaction" oferece). O que ele define é a **Duração da pesquisa** da entidade (Entidade › Assistência › Pesquisa de satisfação): passado esse prazo, a pesquisa do chamado expira. O lembrete segue essa regra e para quando a pesquisa expira. A tela mostra a duração da entidade e quantos lembretes cabem nela com o intervalo escolhido. Duração 0 = sem limite.
- **Pesquisa externa anônima:** sem lembrete. O link é o mesmo para todos, então não há como saber quem respondeu.
- Quem envia é a nova ação automática **npsphereLembrete** (Configurar › Ações automáticas), de hora em hora, das 8h às 18h (ajustável lá), até 100 lembretes por execução. Os lembretes entram na fila de e-mails do GLPI com o evento próprio "Lembrete de pesquisa (NPSphere)".
- O lembrete usa o **mesmo link pessoal** do convite: clicar numa nota já registra a resposta.
- Não recebe lembrete quem já respondeu, quem saiu do público-alvo, quem foi tirado da lista de destinatários, nem pesquisa inativa, fora do período ou cujo chamado foi reaberto.
- Cada convite enviado passa a ser registrado na nova tabela `glpi_plugin_npsphere_deliveries` (classe `Delivery`). Enviar os convites de novo recomeça a contagem. **Só entram os convites enviados a partir desta versão.**

## v0.57.3 — "A ação que você requisitou não é permitida" ao enviar pelo link do e-mail

- Ao entrar pelo botão **Responder a pesquisa** do e-mail (sem login) e enviar o formulário, o GLPI recusava o envio com a mensagem de CSRF ("A ação que você requisitou não é permitida"): o token embutido na página não era aceito. O clique direto numa nota não tinha o problema, porque já buscava um token novo antes de gravar.
- Agora o formulário das páginas sem login (`ticket.answer.php` e `internal.answer.php`) busca um token novo imediatamente antes de enviar (`ajax/ticket.token.php` / `ajax/internal.token.php`), a mesma solução da página pública. Só um token válido (64 hexadecimais) é usado; se a busca falhar, o envio segue com o token da página.

## v0.57.2 — botões do e-mail no Outlook para Windows

- No Outlook para Windows, os botões de nota (0 a 10, emojis, estrelas, opções) e o "Responder a pesquisa" apareciam espremidos, quase só o texto. O Outlook desktop desenha o e-mail com o motor do Word, que ignora padding, largura mínima e `display:inline-block` em links.
- Agora cada botão é uma célula de tabela com `bgcolor`, largura e `mso-padding-alt` (o padrão "bulletproof button", `SurveyMailer::botaoBlindado()`), que o Word respeita. No Outlook web, Gmail e celular a aparência é a mesma de antes, porque eles continuam usando o padding do próprio link.

## v0.57.1 — e-mail do chamado enviava outra pesquisa

- **Bug:** com duas pesquisas de chamado, o chamado mostrava a pesquisa configurada na URL da entidade (`&surveys_id=N`), mas o e-mail levava outra. O envio (`MailPolicy::getPesquisaAplicavel()`) usava sempre a escolha automática por entidade e ignorava a URL.
- **Correção:** o envio agora lê a URL da pesquisa externa da entidade (com a mesma herança do GLPI) e usa a **mesma** resolução da página do chamado (`TicketLink::resolveSurvey()`). A escolha automática só vale quando a URL não indica a pesquisa.
- Na aba Template da pesquisa de chamado, um aviso aparece quando os chamados da entidade usam **outra** pesquisa: diz qual é, e que o convite desta não sai.

## v0.57.0 — aba Template da pesquisa de chamado

- Novo quadro **"Quando o convite sai"** (só na pesquisa de chamado), lido da configuração da entidade no GLPI (`MailPolicy::getConfigDaEntidade()`, com a mesma herança do GLPI):
  - para quem: o requerente do chamado;
  - quando: na hora do encerramento ou **N dias depois** (atraso da entidade);
  - quantos: o **percentual** de chamados encerrados que recebem;
  - de qual entidade vem a configuração, e o botão **Configurar na entidade**.
- Se a entidade estiver com **"Pesquisa interna"** do GLPI, o quadro avisa que o convite do NPSphere **não sai** e explica o que mudar.
- A **prévia** e a sub-aba Variáveis usam o **último chamado encerrado de verdade** (número, título, requerente, técnico), com link para ele. Sem chamado encerrado, os campos aparecem entre colchetes. Os nomes inventados ("Maria Silva", "#1234") saíram.
- Os links da prévia da pesquisa de chamado abrem a aba **Visualizar**: clicar neles não registra avaliação no chamado usado como exemplo.

## v0.56.0 — pesquisa interna respondida pelo e-mail sem entrar no GLPI

- O convite da pesquisa interna agora leva um **link pessoal** para a nova página `front/internal.answer.php`, liberada sem login. A chave assinada identifica **esta pessoa nesta pesquisa** (`Target::chave()`), e trocar qualquer número da URL invalida o link.
- Clicar numa opção registra a resposta **em nome da pessoa** e mostra a confirmação (`ajax/internal.oneclick.php`, token CSRF por `ajax/internal.token.php`). Abrir o link não grava nada.
- A resposta conta como feita dentro do GLPI: a pesquisa sai dos pendentes, o aviso ao entrar para de aparecer, e em "Minhas pesquisas" ela fica como respondida (o registro de conclusão é gravado em nome da pessoa do link).
- Regras na hora de responder: a pessoa precisa **continuar no público-alvo** e ativa; a pesquisa precisa estar ativa e no prazo. Sem "múltiplas respostas", um novo clique troca a nota.
- Dentro do GLPI nada muda: "Minhas pesquisas" e o aviso pós-login continuam em `front/answer.form.php`, com login.
- A assinatura dos links foi extraída para a nova classe `LinkSigner`, usada pelo chamado e pela pesquisa interna. Os links de chamado já enviados continuam valendo.
- Convites internos enviados antes desta versão ainda apontam para `answer.form.php` e pedem login.

## v0.55.1 — sem confirmação ao enviar convites

- Removida a janela de confirmação ao clicar em **Enviar convites** (pesquisa interna). O botão continua mostrando o total, e com zero pessoas segue avisando em vez de enviar.

## v0.55.0 — aba Template da pesquisa interna

- Novo quadro **"Quem recebe o convite"** (só na pesquisa interna), no lugar da lista de destinatários da externa:
  - três números: **vão receber**, **já responderam** (ficam de fora; some quando a pesquisa aceita várias respostas) e **sem e-mail no cadastro**;
  - lista com nome, e-mail e situação de cada pessoa do público-alvo, com quem vai receber primeiro;
  - botão **Editar público-alvo**, que abre a aba Público-alvo.
- Mesma regra do envio de verdade: usuários ativos, com e-mail; quem já respondeu é pulado (exceto com "múltiplas respostas"). Quem já respondeu é descoberto em duas consultas para o público inteiro (`Target::getResumoDoPublico()`).
- **Enviar convites (N)**: o botão mostra quantas pessoas vão receber e pede confirmação antes de enviar. Com zero, avisa em vez de enviar.
- A prévia e a sub-aba Variáveis usam como `{{destinatario}}` a **primeira pessoa que vai receber**. A sub-aba ganhou a explicação do nome na pesquisa interna.

## v0.54.0 — avaliar o chamado pelo e-mail sem entrar no GLPI

- O convite do chamado agora aponta para a nova página `front/ticket.answer.php`, liberada sem login. O link leva uma **chave assinada** (HMAC-SHA256 do par chamado + pesquisa, com um segredo guardado na configuração do plugin). Com ela, o requerente responde direto do e-mail. Trocar o número do chamado na URL não abre a avaliação de outra pessoa, porque a chave não confere.
- Clicar numa nota registra e mostra a confirmação, como nas demais pesquisas: novo `ajax/ticket.oneclick.php`, com token CSRF por `ajax/ticket.token.php`, ambos autorizados pela chave. Abrir o link não grava nada.
- A resposta é gravada em nome do **requerente** do chamado (`ResponseService::submit()` ganhou a opção `users_id`). Continua uma avaliação por chamado (novo clique troca a nota), e a satisfação nativa do GLPI é atualizada junto.
- A pesquisa precisa ser a mesma assinada no link: se ela for desativada ou trocada, o link deixa de valer.
- Dentro do GLPI nada muda: `front/ticket.php` (com login) continua sendo a URL da pesquisa externa na entidade e a que aparece embutida no chamado.
- Convites de chamado enviados antes desta versão ainda apontam para `ticket.php` e pedem login.

## v0.53.2 — tela de confirmação do clique no e-mail

- A confirmação ("Obrigado pela sua avaliação!") ficava espremida em linha: ícone, título e textos lado a lado, porque usava a classe `.alert` do tema, que no GLPI é flex horizontal.
- Agora é um bloco próprio, centralizado e empilhado: nome da pesquisa em destaque discreto, ícone de confirmação, título, a resposta registrada num quadro (pergunta + valor em destaque) e a dica "Mudou de ideia?". O título da pesquisa sai de cima para não repetir. Vale para a página pública e para as páginas da pesquisa interna e do chamado. Em celular, a tela continua legível.

## v0.53.1 — "Erro ao inserir e-mail na fila" ao aprovar a solução; formulário dentro da fila

- **Erro ao aprovar a solução:** o plugin cancelava a notificação nativa de satisfação do GLPI (para não chegarem dois e-mails) zerando a inserção na fila. O GLPI tratava isso como falha e mostrava "Erro ao inserir e-mail na fila". Agora a notificação nativa entra na fila **já na lixeira**: nunca é enviada, não gera erro, e a limpeza automática do GLPI (`queuednotificationclean`) a apaga depois.
- **Formulário aparecendo na fila de notificação:** o script que troca o link da pesquisa externa pelo formulário embutido rodava em qualquer tela com um link do plugin, inclusive na visualização do e-mail na fila. Agora só roda no formulário do chamado (`/front/ticket.form.php`) e ignora os links das opções do e-mail (`&pergunta=`, `&valor=`). O e-mail em si não tinha problema.

## v0.53.0 — clique no e-mail registra a resposta também na pesquisa interna e na de chamado

- **Pesquisa de chamado:** o clique numa nota do e-mail abre a página do chamado, que grava sozinha e mostra a confirmação. Só o requerente pode responder (como antes). Continua **uma avaliação por chamado**: um novo clique troca a nota dessa mesma avaliação e da satisfação nativa do GLPI. Abrir a página sem clique, com o chamado já avaliado, mostra o agradecimento, como antes.
- **Pesquisa interna:** o clique abre `answer.form.php` (com login no GLPI, se preciso), que grava sozinha. Só quem é do público-alvo. Sem "múltiplas respostas", um novo clique troca a resposta em vez de bloquear com "Você já respondeu".
- **Várias perguntas:** a nota clicada é gravada na hora, e o formulário completa a **mesma** resposta (campo oculto `answers_id`, conferido no servidor: só vale a resposta do próprio usuário).
- Gravação por POST para o novo `ajax/answer.oneclick.php` (login exigido), feito pelo script da página. Abrir o link não grava, então os filtros de e-mail continuam sem gerar respostas.
- `ResponseService::canRespond()` aceita `$substituindo`. Novos `getRespostaDoUsuario()` e `getRespostaDoChamado()`. `Question::rotuloDaResposta()` passa a ser compartilhado pelas duas gravações em um clique.
- Pesquisa **externa anônima** segue só pré-marcando a opção (ver v0.52.0).

## v0.52.0 — clique no e-mail registra a resposta (pesquisa externa)

- **Link pessoal por destinatário:** na pesquisa externa identificada, cada pessoa da lista recebe o seu próprio link, com o código do convite (`&convite=...`). Nova tabela `glpi_plugin_npsphere_invites` e classe `Invite`. Reenviar para o mesmo e-mail reaproveita o convite.
- **Um clique, resposta registrada:** clicar numa opção do e-mail abre a página, que grava a resposta sozinha e mostra "Obrigado pela sua avaliação! Sua resposta foi registrada: 9".
  - Quem grava é o script da página, no navegador da pessoa, por um POST para `ajax/public.oneclick.php` (liberado sem login, autorizado pelo token da pesquisa + código do convite). Abrir o link não grava nada. Os filtros de segurança de e-mail (Defender, Proofpoint, Gmail…) abrem os links para verificar, mas não fazem esse POST, e por isso não geram respostas falsas.
  - Se a gravação automática falhar, o formulário continua na tela com a opção marcada, e a pessoa envia normalmente.
- **Um convite, uma resposta:** clicar de novo ou reabrir o link **altera** a resposta, em vez de criar outra. O formulário reabre com o que a pessoa já respondeu.
- **Identificação pela lista:** a resposta registra o nome e o e-mail do destinatário ("Quem respondeu" passa a mostrar a pessoa).
- **Várias perguntas:** a nota clicada é gravada na hora (resposta parcial) e as demais ficam no formulário. Ao enviar, a mesma resposta é completada.
- **Exceções:** na pesquisa que pede identificação (nome, e-mail e telefone), o clique abre o formulário com nome e e-mail preenchidos, para a pessoa informar o telefone. Na pesquisa **anônima** não há link pessoal (ligar convite e resposta revelaria quem respondeu); ali o clique continua só pré-marcando.
- `ResponseService::submit()` ganhou as opções `answers_id` (substituir uma resposta existente) e `parcial` (gravar sem exigir as obrigatórias que faltam).
- Convites enviados antes desta versão não têm o código pessoal e continuam funcionando como antes.
- **Atualização:** clique em **Atualizar** no plugin (Configurar › Plugins) para criar a tabela de convites.

## v0.51.10 — links do e-mail com o endereço completo do GLPI

- Os links do convite saíam só com o caminho (`/marketplace/npsphere/front/public.php?...`), sem servidor nem `https`. O Gmail completava como `http:///marketplace/...` e mostrava "Aviso de redirecionamento". Causa: `Plugin::getWebDir('npsphere', true)` devolve apenas o caminho, que só funciona dentro do próprio GLPI.
- Agora os links (opções, botão, `{{link}}`, convite de chamado) usam a **URL da aplicação** do GLPI: a da entidade, se configurada, senão a de Configurar › Geral. É a mesma regra das notificações nativas.
- O link público mostrado no formulário da pesquisa também passou a ser o endereço completo, pronto para copiar e enviar.

## v0.51.9 — destinatários um a um; modelo de notificação; e-mail centralizado

- **Destinatários:** campos **Nome** e **E-mail** com o botão **+** para adicionar um a um (Enter também adiciona). A lista aparece em tabela editável, com botão de remover em cada linha. E-mail inválido ou repetido é recusado com aviso. A importação de CSV preenche a tabela sozinha. O que vai para o banco continua sendo o mesmo campo (`email;nome` por linha).
- **Modelo de notificação:** novo modelo "NPSphere — Convite de pesquisa" (Configurar › Notificações › Modelos de notificação), criado na instalação ou atualização do plugin. Os convites entram na fila ligados a ele. O conteúdo continua vindo da aba Template de cada pesquisa; o modelo só repassa (`##npsphere.assunto##`, `##npsphere.mensagem##`). Nova classe `NotificationTargetSurvey`. A Pesquisa foi registrada como tipo de modelo de notificação.
- **E-mail:** cartão centralizado e com respiro nas bordas. O espaçamento foi para as células (`<td>`), porque a fila do GLPI e o Outlook ignoram padding em `<table>`, e o cartão ficava encostado na borda.
- **Atualização:** depois de trocar a pasta, clique em **Atualizar** no plugin (Configurar › Plugins) para o modelo ser criado.

## v0.51.8 — mensagem lado a lado; fila do GLPI completa

- **Mensagem:** prévia HTML5 à esquerda e código à direita, lado a lado. A prévia atualiza enquanto se digita (pausa de 0,4 s), também ao inserir um marcador ou restaurar o modelo. Os ícones de alternar modo saíram; o botão de restaurar o modelo padrão continua. Em tela estreita, um fica embaixo do outro.
- **Destinatários:** "Baixar modelo CSV" e "Importar CSV" foram para o cabeçalho do quadro, no canto direito.
- **Fila de notificação:** o convite agora entra com todos os campos que as notificações do próprio GLPI preenchem:
  - **Reply-to** e **Nome Reply-To**: o "responder para" configurado; sem ele, o "não responder"; sem nenhum, o próprio remetente;
  - **ID da mensagem**: gerado pelo GLPI (`NotificationTarget::getMessageIdForEvent`);
  - **Cabeçalhos adicionais**: `Auto-Submitted: auto-generated` e `X-Auto-Response-Suppress`, para o servidor de quem recebe não devolver resposta automática de férias;
  - **HTML**: o corpo vai como documento HTML completo. A tela da fila só mostra o que está entre `<body>` e `</body>`, e por isso a aba HTML ficava em branco;
  - **Texto**: gerado pela conversão do próprio GLPI, com parágrafos e o endereço de cada link.

## v0.51.7 — uma variável por pergunta no e-mail

- `{{opcoes}}` saiu da lista de variáveis. No lugar, cada pergunta ativa da pesquisa ganha a sua variável, criada sozinha a partir do texto da pergunta (sem acentos e sem espaços). Exemplo: "Como você avalia o atendimento?" vira `{{como_voce_avalia_o_atendimento}}`. Nomes repetidos recebem `_2`, `_3`…
- No e-mail, a variável mostra o texto da pergunta e as opções para clicar:
  - NPS, estrelas e emoji: a escala, como antes;
  - Sim/Não e escolha única ou múltipla: as próprias opções;
  - texto livre: um link "Responder esta pergunta".
- Cada link leva a pergunta e o valor (`&pergunta=ID&valor=X`). A página abre com aquela opção **já marcada**, sem gravar nada. Isso agora vale nas três páginas de resposta: chamado, pesquisa interna e link público (antes só a de chamado pré-marcava).
- O modelo padrão da mensagem usa a variável da primeira pergunta com nota.
- Mensagens antigas com `{{opcoes}}` continuam funcionando (primeira pergunta com nota), e links de convites já enviados também.
- Aviso no cartão **E-mail**: o ideal é colocar perguntas no e-mail só quando a pesquisa tem uma única pergunta, com o motivo explicado. Quando a pesquisa tem mais de uma, o aviso mostra quantas são.

## v0.51.6 — prévia sem aviso de "Blocked script execution" no console

- O iframe da prévia bloqueava qualquer script. Extensões do navegador tentam injetar script em todo iframe, e o Chrome registrava "Blocked script execution in 'about:srcdoc'" a cada prévia.
- Agora a prévia roda com `allow-scripts` mas **sem** `allow-same-origin`: fica numa origem isolada, sem acesso à sessão nem à página do GLPI. O isolamento continua; só some o aviso.
- A altura da prévia passa a ser informada pela própria prévia (`postMessage`), e acompanha o conteúdo também quando ele diminui.

## v0.51.5 — convite travava na fila do GLPI; prévia sem nome inventado

- **Envio:** o convite entrava na fila, mas o GLPI falhava ao mandar ("Argument #2 ($name) must be of type string, null given" e "An email must have a To header"). A linha da fila ia sem `recipientname`, e o GLPI 11 exige texto nesse campo. Agora todos os nomes vão preenchidos, e o "Para:" sai como **Nome <email>** quando a lista tem nome.
- Remetente e "responder para" passam a seguir as regras das notificações do próprio GLPI (`Config::getEmailSender` / `getReplyToEmailSender`), considerando a entidade da pesquisa.
- **Prévia:** o `{{destinatario}}` deixou de ser o fictício "Maria Silva". Agora é o primeiro nome da lista de destinatários (como está na tela, mesmo antes de salvar). Sem ninguém na lista, aparece "[nome do destinatário]". A prévia atualiza quando a lista muda ou um CSV é importado.
- O exemplo dentro do campo Destinatários não usa mais nomes de pessoas.

## v0.51.4 — prévia e envio barrados pelo CSRF (403)

- A prévia da mensagem, a importação de CSV e o botão **Enviar** davam 403 ("CSRF check failed"): o GLPI 11 confere o token CSRF em todo POST, inclusive nos arquivos de `ajax/`, e essas chamadas não mandavam token.
- Agora cada chamada busca um token novo em `ajax/csrf.token.php` e o envia no corpo (`_glpi_csrf_token`) e no cabeçalho `X-Glpi-Csrf-Token`. O token do campo oculto da página continua reservado para o **Salvar**.

## v0.51.3 — aba "Template": pesquisa externa (link público)

- A aba **Template** agora fica entre **Visualizar** e **Resultados**.
- **Destinatários** passou para o topo da aba.
- **Importar CSV** separado por `;` (colunas `email;nome`). Os endereços importados são somados aos que já estão no campo, inclusive os ainda não salvos, sem repetir. Arquivo salvo com vírgula também é aceito.
- **Baixar modelo CSV**: planilha pronta para preencher e importar (UTF-8 com BOM, abre certo no Excel).
- Nova sub-aba **Variáveis**: tabela com cada marcador (`{{pesquisa}}`, `{{destinatario}}`, `{{link}}`, `{{botao}}`, `{{opcoes}}`), o que significa e o valor que ele assume nesta pesquisa.
- **Mensagem** já abre com o modelo padrão preenchido e mostra o e-mail formatado (HTML5).
- Dois ícones na mensagem: **HTML5** (prévia formatada, atualiza enquanto edita) e **código** (edição do HTML com os botões de marcadores). A escolha fica lembrada no navegador. Botão para restaurar o modelo padrão.
- A prévia usa a mesma montagem do envio real, isolada num iframe sem scripts.
- **Enviar** usa a fila de e-mails do próprio GLPI (Configurar › Notificações › Fila de notificações), entregue pela ação automática `queuednotification`. Na pesquisa sem chamado, o item da fila é a própria Pesquisa.

Arquivos novos: `ajax/recipients.template.php`, `ajax/recipients.import.php`, `ajax/mail.preview.php`.

## v0.38.0 — Fase 8: pesquisa vinculada a chamado (base)

### A estratégia mudou por causa das tags

As tags `[TICKET_ID]`, `[ITILCATEGORY_NAME]` e companhia que aparecem ao
escolher "pesquisa externa" na entidade revelam que **o GLPI já tem todo
o mecanismo pronto**: ele decide quando criar a pesquisa (encerramento
do chamado, com atraso e percentual configuráveis por entidade), envia a
notificação e registra que aquele chamado tem pesquisa.

Meu plano anterior era interceptar mudanças de status do chamado. Isso
**competiria** com o mecanismo nativo — com risco real de duas pesquisas
para o mesmo chamado, ou de nenhuma quando as regras divergissem.

Agora o plugin é o **destino** da pesquisa externa nativa. Aproveita o
gatilho, o prazo e a notificação do GLPI; entra apenas onde agrega:
apresentar a pesquisa configurada e registrar a resposta.

### Como ativar

Na tela da pesquisa (tipo "Vinculada a chamado") aparecem as instruções
e a URL a colar em **Administração > Entidades > Assistência**:

```
<glpi>/plugins/npsphere/front/ticket.php?tickets_id=[TICKET_ID]
```

### O que foi implementado

| Item | |
|---|---|
| Resolução da pesquisa por entidade do chamado | 🟢 |
| Herança por entidade-pai (respeitando "recursivo") | 🟢 |
| Preferência pela entidade **mais específica** | 🟢 |
| Apenas o **requerente** responde | 🟢 |
| Uma avaliação por chamado | 🟢 |
| Gravação do vínculo `answer.tickets_id` | 🟢 |
| Funciona nas duas interfaces (padrão e simplificada) | 🟢 |
| Instruções de configuração na tela | 🟢 |

Sobre o `tickets_id`: a coluna existia desde a Fase 3, mas **nunca era
preenchida**. É ela que permitirá, na Fase 10, cruzar satisfação com
técnico, categoria e tempo de atendimento.

### Ainda pendente nesta fase

- **Aba no chamado** mostrando a avaliação recebida (hoje a resposta é
  gravada e aparece nos Resultados, mas não dentro do chamado);
- **Captura de técnico/categoria** no momento da resposta, para os
  cruzamentos da Fase 10;
- Orientação para **desativar a pesquisa nativa** onde ela conviver com
  esta — o GLPI não permite que um plugin faça isso sozinho.

## v0.36.4 — cabeçalho no documento impresso

> Esta versão parte da **v0.36.3**. A tentativa de exportação em PDF
> pelo servidor (TCPDF) foi **descartada**: o resultado ficou visualmente
> inferior à impressão, que já estava boa. A impressão pelo navegador
> continua sendo o caminho para gerar PDF.

Única mudança: o documento impresso passou a ter **cabeçalho**, com

- **nome** da pesquisa,
- **descrição** (quando houver),
- **data e hora de geração**.

O cabeçalho fica oculto na tela e é clonado para o documento no momento
da impressão. Antes a folha impressa não se identificava — o que a
tornava inútil para arquivar ou enviar a alguém.

Vale para os três escopos de impressão já existentes: visão geral,
lista de respostas e resposta individual.

## v0.36.3 — limpeza de código obsoleto

Varredura sistemática por código morto: templates, arquivos de rota,
classes, métodos, funções JavaScript, regras CSS, imports e pendências
marcadas.

### Removido

| Item | Por que estava sobrando |
|---|---|
| `Survey::isExternal()` | Nunca chamado — a verificação é feita direto pela constante `TYPE_EXTERNAL` |
| `use GlpiPlugin\Npsphere\Survey` em `front/mysurveys.php` | Import sem uso |
| `.npsphere-byline` | A linha "Criada por" saiu do formulário |
| `.npsphere-detail-list` (e descendentes) | O detalhe da resposta passou a usar cartões |
| `.npsphere-detail-value` | idem |
| `.npsphere-info-banner` | Nunca chegou a ser usada |
| `.npsphere-summary-link` | Link "Ver resultados completos", removido na v0.23.1 |

### Mantido de propósito

- **Migrações antigas** (`is_nps_enabled`, `is_nps_main`, renomeação de
  `users_id`...) — parecem obsoletas, mas quem atualizar de uma versão
  antiga ainda precisa delas;
- **Classes CSS geradas dinamicamente** (`npsphere-rating-*`,
  `npsphere-nps-zone-*`, `npsphere-score-*`) — não aparecem literalmente
  no código, são montadas por concatenação. Uma busca ingênua as
  acusaria de mortas;
- **Classes que só servem de gancho para o JavaScript**
  (`npsphere-open-form`, `npsphere-submit-btn`...) — não têm estilo, e
  não precisam ter;
- **`getDefaultValues()`, `getQuestionTypeClass()`, `hasCondition()`,
  `isValidEmail()`** — são chamados dentro do próprio arquivo, o que a
  primeira varredura não detectou.

### Verificação

Comparei o CSS final com o da v0.33 e confirmei que **exatamente** as
cinco classes pretendidas foram removidas, sem efeito colateral. Toda a
bateria de testes continua passando.

## v0.36.2 — enviar na pré-visualização não é mais barrado

A v0.36.1 corrigiu a EXIBIÇÃO da aba, mas ao clicar em "Enviar
avaliação" ainda aparecia "Você já respondeu esta pesquisa".

**Causa:** `submit()` verificava a elegibilidade (já respondeu? está no
prazo? pesquisa ativa?) **antes** de chegar ao ponto onde a
pré-visualização é tratada. Eram dois lugares com a mesma raiz, e eu
corrigi só um.

Essa verificação existe para proteger os **dados**: impedir resposta
duplicada ou fora do prazo. Como a pré-visualização não grava nada, não
há dado a proteger — então ela deixou de se aplicar ali.

O que **continua valendo** na pré-visualização é a validação das
perguntas: obrigatória em branco, formato inválido e regras
condicionais seguem sendo verificados, porque é justamente isso que o
administrador quer conferir.

Testado com pesquisa inativa, fora do prazo e já respondida: a
pré-visualização passa em todos os casos; a resposta real continua
barrada em todos eles.

## v0.36.1 — "Visualizar" sempre mostra o formulário

A aba exibia "Você já respondeu esta pesquisa" no lugar do formulário
assim que o administrador testava uma vez.

**Causa:** ela reaproveitava `canRespond()` — a mesma verificação do
fluxo real. Fazia sentido quando responder ali gravava de verdade; deixou
de fazer na v0.36.0, quando a pré-visualização parou de gravar. O
propósito da aba é conferir **como a pesquisa ficou**, e isso independe
de o administrador poder respondê-la.

Agora o formulário é **sempre** exibido. O motivo do bloqueio, quando
existe (já respondida, fora do prazo, pesquisa inativa), aparece como
**aviso acima do formulário**, explicando que o respondente veria aquela
mensagem no lugar dele — informação útil que antes se perdia junto com a
pré-visualização.

## v0.36.0 — aba "Visualizar" não computa mais as respostas

Responder pela aba "Visualizar" gravava a resposta de verdade: aquele
teste do administrador entrava no NPS, na nota média e na lista de quem
respondeu — corrompendo justamente os números que ele estava
conferindo.

**A experiência continua idêntica**, como você pediu: o formulário
funciona igual, respeita etapas e regras condicionais, valida
obrigatoriedade e formato, e mostra a tela de confirmação. **Só a
gravação é dispensada.**

### Onde a decisão foi colocada, e por quê

A marca `preview` viaja **no próprio POST** (campo oculto), e quem
decide não gravar é o **servidor** — dentro de `ResponseService::submit()`.

Duas razões para não deixar isso no JavaScript:

1. Um atributo lido só pelo JS deixaria de valer se o script falhasse
   ou estivesse em cache antigo — e a resposta de teste entraria nas
   métricas sem ninguém perceber;
2. Estando dentro de `submit()`, **qualquer rota nova** que venha a
   chamar esse método herda a proteção, em vez de depender de alguém
   lembrar de repetir a verificação.

O corte acontece **depois** de toda a validação: o administrador vê
exatamente o comportamento que o respondente teria, incluindo os erros
de pergunta obrigatória em branco.

### Confirmação honesta

A tela de agradecimento na pré-visualização diz, com todas as letras,
que **nenhuma resposta foi gravada** — senão o administrador ficaria em
dúvida se acabou de sujar os próprios dados.

### O que NÃO mudou

- A tela de resposta real (Ferramentas, autoatendimento e link público)
  continua gravando normalmente;
- O modal pós-login **não** envia a marca de pré-visualização —
  verificado.

## v0.35.10 — nota média sem depender de NPS na tela de configuração

Na aba "Pesquisa de satisfação", a faixa de resumo mostrava **NPS**,
**Nota média** e **Respostas** — mas, sem pergunta de NPS, sobrava
apenas "Respostas".

**Causa:** a nota média estava dentro do mesmo bloco condicional do
NPS. Quando não havia NPS, ela desaparecia junto — mesmo em pesquisas
de emoticons ou estrelas, que têm nota tanto quanto.

Os dois indicadores agora são independentes:

- **NPS** — exibido apenas quando existe pergunta desse tipo;
- **Nota média** — exibida sempre que houver alguma pergunta com nota
  (NPS, estrelas ou emoticons), normalizada para 0–10.

Pesquisas sem nenhuma pergunta pontuável (só texto ou escolhas)
continuam mostrando apenas "Respostas" — ali realmente não há média a
calcular.

A aba "Resultados" já tratava isso corretamente desde a v0.33.0; o
acoplamento existia só na tela de configuração.

## v0.35.9 — "Ocorreu um erro inesperado" ao abrir pesquisa em Ferramentas

Abrir uma pesquisa pelo menu Ferramentas (interface padrão) derrubava a
página. **Dois defeitos meus, um sobre o outro:**

**1. Recursão infinita.** As funções auxiliares que montam o
cabeçalho/rodapé chamavam **a si mesmas** no ramo da interface padrão:

```php
$abrir_pagina = static function () use ($is_helpdesk): void {
    if ($is_helpdesk) {
        Html::helpHeader(...);
    } else {
        $abrir_pagina();   // <- chamava a si mesma
    }
};
```

Sobrou de uma substituição automática mal feita, quando troquei as
chamadas de `Html::header(...)` pelas funções auxiliares — a chamada
**dentro** da própria função também foi substituída.

**2. Menu errado no cabeçalho.** A tela apontava para o menu
**Administração**, mas ela é de quem **responde** — e quem responde
normalmente não tem o direito de gerenciar pesquisas. O GLPI recusa
montar uma página apontando para um menu ao qual o usuário não tem
acesso. Agora aponta para **Ferramentas**, que é de onde o respondente
vem.

Varri os demais arquivos com o mesmo padrão de refatoração à procura de
outra recursão — não havia.

## v0.35.8 — modal ignorava etapas e regras condicionais

A v0.35.7 removeu a imposição de "página única" no modal, mas o
problema continuou: além do formato, as **regras condicionais** também
eram ignoradas (uma pergunta condicionada aparecia de imediato).

**Causa real:** o script era carregado sob demanda, mas eu contava que
ele **se inicializasse sozinho no momento certo** — e não havia
momento certo. Quando o script chega, o formulário do modal **já está
na tela**: o evento de carregamento da página passou há muito, e o
observador de DOM só enxerga o que é inserido **depois** dele. O script
carregava e não fazia nada.

**Correção:** a inicialização passou a ser **explícita**. O
`survey-builder.js` expõe `window.NPSPHERE_INIT`, e o modal a chama no
`onload` do script (ou imediatamente, se o script já estiver
carregado). Nada mais depende de coincidência de tempo.

Com isso o modal passa a respeitar tudo o que a tela de resposta já
respeitava: formato por etapas, regras condicionais e os widgets das
perguntas.

Validado com um DOM simulado no cenário exato do problema — página já
carregada, script chegando depois.

## v0.35.7 — modal pós-login respeita "uma pergunta por etapa"

A mesma pesquisa aparecia **por etapas** ao ser aberta pela tela de
resposta, e **em página única** no aviso pós-login.

**Causa: uma decisão minha.** No modal eu sobrescrevia o formato com
`form.dataset.displayMode = 'single_page'`, porque o script que faz a
navegação por etapas só era carregado nas telas do plugin — e o modal
aparece em qualquer página do GLPI. Era uma solução de contorno que
virou incoerência: a configuração da pesquisa era ignorada justamente
onde a maioria vai respondê-la.

**Correção:** o modal passou a **respeitar o formato configurado** e a
carregar o script sob demanda, com o mesmo marcador usado na tela de
resposta para não carregá-lo duas vezes.

Para isso o `survey-builder.js` também precisou mudar: ele
inicializava apenas no evento `DOMContentLoaded`, que **já passou**
quando o arquivo é injetado depois. Agora verifica o `readyState` e
inicializa na hora se a página já estiver pronta.

Verifiquei que a navegação por etapas convive com o envio interceptado
do modal: o botão "Enviar avaliação" é **movido** para a barra de
navegação, mas permanece dentro do formulário — e o envio escuta o
evento do formulário, não o clique do botão.

## v0.35.6 — "uma pergunta por etapa" ignorado no autoatendimento

No autoatendimento, uma pesquisa configurada como **"uma pergunta por
etapa"** aparecia toda numa página só.

**Causa:** a navegação por etapas é feita pelo `survey-builder.js`, e a
interface simplificada **não carrega os mesmos assets** que a interface
padrão. Sem o script, o formulário fica exatamente como veio do
servidor — ou seja, página única. O CSS do plugin sofria do mesmo
problema.

**Correção:** a tela de resposta passou a carregar os próprios assets
quando eles não estão presentes:

- o **JavaScript** é carregado sob demanda, guardado por um marcador
  (`window.NPSPHERE_BUILDER_LOADED`) para nunca carregar duas vezes;
- o **CSS** é verificado por sondagem: um elemento invisível recebe uma
  classe do plugin e o estilo aplicado é conferido. Se a regra não
  existe, a folha é carregada. Assim não há requisição desnecessária
  onde os assets já vieram.

## v0.35.5 — plugin oculto sem permissão e rótulo no autoatendimento

### Sem o direito, o plugin não aparece no menu "Plugins"

O registro do hook `helpdesk_menu_entry` voltou a ser **condicional** —
sem o direito, o item nem chega a ser criado.

Isso só é possível agora porque `hasAnswerRight()` consulta o banco
quando o direito não está na sessão (v0.35.4). A tentativa anterior de
condicionar aqui usava `Session::haveRight()` e falhava sempre:
`plugin_init` roda cedo demais no ciclo da requisição, quando a sessão
ainda pode não ter os direitos do perfil.

### Rótulo "Minhas pesquisas" no autoatendimento

O menu "Plugins" rotula a entrada com o **nome do plugin**, e não há
hook para alterar apenas esse rótulo. Como a tela de Plugins — onde
"NPSphere" faz sentido — existe somente na interface padrão, o nome
devolvido passa a depender da interface em uso: **"Minhas pesquisas"**
no autoatendimento, **"NPSphere"** na interface padrão. São contextos
que nunca se cruzam.

> **Ressalva honesta:** se a sua instalação do GLPI ler o nome do plugin
> a partir do registro em banco (feito na instalação) em vez de chamar
> `plugin_version()` a cada requisição, o rótulo continuará "NPSphere".
> Não tenho como verificar isso daqui. Se acontecer, me avise — a
> alternativa é renomear o plugin inteiro, o que tem outras implicações.

## v0.35.4 — direito presente no banco, ausente na sessão

O diagnóstico da v0.35.3 entregou a causa exata:

```
Perfil: #1 (Self-Service)
Valor no banco: 1
Valor na sessão: ausente
```

O direito **estava concedido**. O problema é que direitos de plugin
criados depois que uma sessão foi aberta não entram nela — e reabrir a
sessão não resolvia de forma confiável. O resultado era um beco sem
saída: a tela de Perfis mostrava o direito marcado, e a página negava
acesso.

**Correção:** a verificação passou a consultar a **sessão primeiro e,
se o direito não estiver lá, o banco** — que é a fonte de verdade; a
sessão é apenas um cache. Quando o banco confirma, o valor é gravado na
sessão para as próximas verificações não repetirem a consulta.

Aplicado em todos os pontos: menu de Ferramentas, menu do
autoatendimento, página "Minhas pesquisas", formulário de resposta e
endpoint de envio.

Revogar o direito continua funcionando: com valor 0 no banco, o acesso
é negado em todos esses pontos.

### Nota sobre os testes

O stub de `Session::haveRight()` usado nos testes retornava sempre
`true`, o que mascarava justamente este caminho — o fallback nunca era
exercitado. Corrigido para refletir o comportamento real (ler o direito
do perfil ativo na sessão); os cinco cenários agora são verificados de
fato, incluindo o exato que você reportou.

## v0.35.3 — direito zerado em perfis de autoatendimento

Mesmo após a v0.35.2, o acesso continuava negado. A linha do direito
existia na tabela — por isso a correção anterior a preservava, achando
que era uma escolha do administrador — **mas valia zero**, porque foi
criada assim pelas versões anteriores.

Como responder pesquisas é a **única** coisa que um perfil de
autoatendimento faz neste plugin, manter zero significa que a
funcionalidade nunca funciona para ele. A atualização agora concede
`READ` aos perfis de interface simplificada cuja linha esteja zerada.
Quem quiser restringir, desmarca depois — o contrário (ficar quebrado
sem ninguém entender por quê) é bem pior.

### Diagnóstico na própria tela

Quando o acesso é negado, a página passa a mostrar o **valor no banco**
e o **valor na sessão** do direito. As duas situações possíveis
produziam exatamente a mesma tela:

- *sem registro no banco* → falta clicar em "Atualizar" na tela de Plugins;
- *tem no banco, ausente na sessão* → falta encerrar e reabrir a sessão.

Sem essa distinção, investigar virava adivinhação — e já custou duas
rodadas.

## v0.35.2 — acesso negado no autoatendimento

Dois sintomas com **a mesma causa**: o item aparecia no menu Plugins mas
negava acesso, e continuava aparecendo mesmo sem o direito habilitado.
Em ambos os casos, o direito **não estava realmente gravado** no perfil.

### Por que a marcação não gravava

`ProfileRight::addProfileRights()` cria a linha do direito apenas para
os perfis existentes **no momento em que o direito é criado**, e só na
primeira vez. Perfis criados depois — ou instalações onde o direito
havia sido criado numa tentativa anterior — ficavam **sem linha
alguma** na tabela.

Sem linha, `Session::haveRight()` responde "não tem" — e marcar a caixa
na tela de Perfis não adiantava, porque não havia registro onde gravar.

Agora a atualização percorre **todos** os perfis e cria a linha faltante
(com `READ`), preservando as escolhas já feitas nos perfis que já a
tinham.

### Mensagem útil no lugar do erro genérico

Sem o direito, a página exibia "Você não tem permissão para executar
essa ação" — numa tela sem contexto, na interface simplificada. Agora
explica o que falta e onde habilitar.

### Formulário de resposta na interface simplificada

`front/answer.form.php` montava o cabeçalho da interface padrão em suas
**quatro** rotas de saída. Como o respondente chega ali justamente pelo
autoatendimento, clicar numa pesquisa levava a uma tela quebrada. Todas
passaram a detectar a interface em uso.

## v0.35.1 — item não aparecia no menu do autoatendimento

O registro do hook `helpdesk_menu_entry` estava dentro de um
`if (Session::haveRight(...))` no `plugin_init`. **Esse era o erro:**
`plugin_init` roda muito cedo no ciclo da requisição, quando os direitos
do perfil ainda podem não estar carregados na sessão — a verificação
dava falso e o hook sequer chegava a ser registrado. O item nunca
aparecia, independentemente da permissão concedida.

Agora o hook é registrado **incondicionalmente**, e a permissão é
aplicada em `plugin_npsphere_redefine_menus()` — que roda na **montagem
do menu**, com a sessão já completa. Sem o direito, o item é removido
ali.

Confirmei na documentação de hooks e num tópico recente do fórum
(respondido por um mantenedor do GLPI) que `helpdesk_menu_entry` +
`helpdesk_menu_entry_icon` são de fato os hooks corretos para a
interface simplificada — o problema era o momento da verificação, não o
hook escolhido.

## v0.35.0 — perfis de interface simplificada

Perfis que usam a **interface simplificada** (autoatendimento) agora
têm tratamento próprio na aba "Pesquisas de satisfação" de
Administração > Perfis:

- **Só o direito "Minhas pesquisas" é exibido** — a linha "Pesquisas de
  satisfação" (gestão) nem aparece, com um aviso explicando o motivo.
  O que não pode ser concedido não deve aparecer como se pudesse;
- **A gestão fica bloqueada no servidor** para essa interface, mesmo
  que o direito tenha sido herdado de uma configuração anterior. Sem
  isso, o perfil conseguiria abrir a tela de gestão digitando a URL;
- **Uma migração zera** o direito de gestão nos perfis simplificados
  que já o possuíam.

O motivo é simples: as telas de gestão vivem no menu **Administração**,
que a interface simplificada não exibe. Conceder ali um direito que
nunca se concretiza só gera confusão.

### Onde o item aparece no autoatendimento

Com "Minhas pesquisas" marcado como **Ler**, o link passa a aparecer no
menu **Plugins** da interface simplificada — que é o comportamento
documentado do hook `helpdesk_menu_entry` (o GLPI exibe o menu
"Plugins" no autoatendimento assim que ao menos um plugin registra uma
entrada ali). Confirmei isso na documentação antes de implementar.

> Lembrete: o GLPI monta o menu **no login** e mantém os direitos em
> cache na sessão. Depois de alterar um perfil, encerre e reabra a
> sessão para ver a mudança.

## v0.34.1 — menus e edição respeitando as permissões

### Menus continuavam visíveis sem permissão

Definir `canView()` não bastava: **o GLPI monta o menu a partir de
`getMenuContent()`**, e esse método montava a entrada
incondicionalmente. O item aparecia em Administração (e em Ferramentas)
para quem não tinha o direito, e só dava erro ao clicar.

Agora ambos retornam `false` quando falta o direito — o item some do
menu, como esperado.

> Se o item persistir após alterar o perfil, encerre e reabra a sessão:
> o GLPI monta o menu no login e mantém os direitos em cache na sessão.

### Perguntas eram editáveis com direito apenas de leitura

Abrir uma pergunta exigia apenas `READ`, mas o formulário vinha
**totalmente editável** — a falta de permissão só aparecia ao tentar
salvar.

Corrigido em duas frentes:
- **Servidor:** o formulário recebe `can_edit` (baseado em `UPDATE`);
- **Interface:** sem esse direito, todos os campos são desabilitados, o
  botão "Salvar" não é exibido e um aviso explica que o acesso é apenas
  de visualização.

Na listagem, o ícone da ação passou a ser honesto: **lápis** quando dá
para editar, **olho** quando é só leitura. Oferecer "editar" a quem não
pode editar é convidar para um erro de permissão.

### Ícone na aba de Perfis

A aba "Pesquisas de satisfação" em Administração > Perfis agora tem
ícone, como as demais.

## v0.34.0 — permissões no padrão do GLPI e acesso no autoatendimento

> Esta versão parte da **v0.33.0**. As versões 0.34 a 0.37 anteriores
> foram descartadas: introduziram regressões (aba "Visualizar" em
> branco e permissões que não gravavam) sem entregar valor equivalente.

### Aba de Perfis no padrão do GLPI

A aba "Pesquisas de satisfação" em Administração > Perfis usava um
formulário próprio, com controles diferentes dos do resto do sistema.
Substituído por `displayRightsChoiceMatrix()`, o componente **nativo**:
os controles ficam idênticos aos das demais abas (inclusive
marcar/desmarcar a coluna inteira), e a gravação passa a ser feita pelo
próprio GLPI — sem reimplementar leitura, escrita e token CSRF.

O template e o endpoint próprios (`templates/profile.html.twig`,
`front/profile.rights.form.php`) foram removidos.

### Novo direito: "Minhas pesquisas"

A aba tinha apenas um direito ("Pesquisas de satisfação"), que controla
a **gestão**. Agora há dois:

| Direito | Controla |
|---|---|
| Pesquisas de satisfação | criar, editar e ver resultados |
| **Minhas pesquisas** | responder pesquisas |

São separados porque responder não é tarefa de administração: o perfil
de autoatendimento precisa do segundo sem nunca receber o primeiro.

> **Detalhe que importa:** os dois direitos usam `itemtype` diferentes.
> A matriz do GLPI usa o itemtype como chave de linha — dois direitos
> com o mesmo itemtype se sobrepõem, e o segundo não aparece nem é
> gravado.

O direito é **concedido a todos os perfis** na atualização. Padrão
permissivo de propósito: se ninguém pudesse responder até alguém marcar
a caixa, a funcionalidade pareceria quebrada logo após atualizar.

### "Minhas pesquisas" na interface simplificada

Com o direito habilitado, o item passa a aparecer também no
**autoatendimento**. Isso exigiu duas coisas que faltavam:

1. O hook `helpdesk_menu_entry` — a interface simplificada **não** é
   alimentada pelo `menu_toadd` usado na interface padrão;
2. `Html::helpHeader()`/`helpFooter()` na página, detectando a
   interface atual. Com o cabeçalho da interface padrão, o
   autoatendimento veria uma tela sem menu.

O rótulo no autoatendimento é "Minhas pesquisas" (via `redefine_menus`),
e não o nome do plugin, que é o padrão do GLPI ali.

A verificação do direito acontece no menu, no hook do autoatendimento
**e na própria página** — esconder um link não impede ninguém de
digitar a URL.

# NPSphere — Pesquisas de Satisfação para GLPI 11

Plugin de criação, distribuição, resposta e análise de **pesquisas de
satisfação** para o GLPI 11. Suporta NPS como uma das metodologias
disponíveis (não exclusivamente), pesquisas vinculadas ou não a chamados,
múltiplos tipos de pergunta, regras condicionais, público-alvo segmentado
e link público seguro.

Nome técnico do plugin (diretório/chave): `npsphere`.

## ⚠️ Estado atual: Fase 3 — Respostas (+ correções v0.4.0)

Este pacote entrega as **Fases 1, 2 e 3** do roadmap descrito abaixo.

### Fase 1 — Fundação

- Estrutura de diretórios e arquivos obrigatórios (`setup.php`, `hook.php`,
  `composer.json`, `src/`, `front/`, `templates/`, `css/`, `locales/`);
- Instalação/desinstalação via `Migration` (tabela
  `glpi_plugin_npsphere_surveys`);
- Sistema de permissões próprio (`plugin_npsphere_survey`), com aba de
  direitos no formulário de Perfil do GLPI;
- Itemtype `Survey` (`GlpiPlugin\Npsphere\Survey`) com CRUD completo
  (criar, editar, duplicar, ativar/desativar via campo `is_active`,
  colocar na lixeira, restaurar, excluir definitivamente);
- Suporte nativo a **entidades** (`entities_id` / `is_recursive`) e ao
  histórico de alterações (aba "Histórico", via `Log`);
- Geração de token público seguro (256 bits, não sequencial) para a
  futura funcionalidade de link público;
- Menu próprio em GLPI ("Pesquisas de satisfação"), tela de listagem
  (motor de busca padrão) e formulário de cadastro.

### Fase 2 — Perguntas e construtor (novidade desta entrega)

- **Tabela `glpi_plugin_npsphere_questions`**, criada/migrada
  automaticamente via `Migration` ao atualizar o plugin;
- **Itemtype `Question`** (`CommonDBChild` de `Survey`), exibido como
  aba **"Perguntas"** dentro do formulário da pesquisa;
- **Arquitetura extensível de tipos de pergunta** (seção 48 do escopo):
  interface `QuestionTypeInterface` + `AbstractQuestionType` +
  `QuestionTypeManager` (registro central). Adicionar um novo tipo no
  futuro (CES, CSAT, ranking, matriz, upload, data...) significa apenas
  criar uma nova classe e registrá-la no `QuestionTypeManager` — nada
  mais precisa ser tocado;
- **7 tipos implementados**, cada um com renderização de configuração
  própria e validação server-side:
  - `nps` — NPS (0 a 10), com apresentação em números ou escala visual;
  - `emoticon` — níveis configuráveis (emoji + descrição textual
    obrigatória, para acessibilidade);
  - `stars` — 5 ou 10 estrelas;
  - `yesno` — Sim/Não (com opção reservada para "Não sei");
  - `single_choice` — múltipla escolha (uma opção);
  - `multiple_choice` — múltiplas opções (várias respostas);
  - `text` — texto curto ou longo, com limite de caracteres;
- **Regra da seção 11 implementada e testada**: apenas UMA pergunta
  pode ser marcada como "NPS principal" por pesquisa. Uma tentativa de
  marcar uma segunda é **bloqueada** (a gravação inteira é abortada) e
  o administrador recebe uma mensagem de erro explicando o motivo;
- **Builder com reordenação por arrastar-e-soltar** (`js/survey-builder.js`),
  implementado com a API nativa de Drag and Drop do HTML5 (sem
  dependências externas), persistindo a nova ordem via
  `ajax/question.reorder.php` (protegido por permissão + token CSRF +
  verificação de que a pergunta pertence à pesquisa informada);
- CRUD completo de perguntas: criar, editar, duplicar, excluir
  (`front/question.form.php`).

> A lógica de negócio da Fase 2 (validação de cada tipo de pergunta e
> a regra de "uma única pergunta NPS principal") foi testada de forma
> isolada, com testes automatizados executados contra stubs mínimos do
> núcleo do GLPI — incluindo o cenário de conflito (duas perguntas NPS
> principal), que é corretamente bloqueado.

Campos da tabela `glpi_plugin_npsphere_questions`: `id`, `surveys_id`,
`name`, `description`, `type`, `position`, `is_required`, `is_active`,
`is_nps_main`, `configuration` (JSON específico do tipo), `date_creation`,
`date_mod`.

Campos da pesquisa (tabela `glpi_plugin_npsphere_surveys`):
`id`, `entities_id`, `is_recursive`, `name`, `description`, `is_active`,
`type` (`ticket` | `independent`), `date_begin`, `date_end`,
`is_anonymous`, `allow_multiple_answers`, `allow_public_access`,
`public_token`, `display_mode` (`single_page` | `step_by_step`),
`is_nps_enabled`, `users_id`, `is_deleted`, `date_creation`, `date_mod`.

> Os nomes `date_creation`/`date_mod`/`users_id` seguem a convenção
> nativa do GLPI (em vez de `created_at`/`updated_at` sugeridos no
> briefing original) porque o núcleo do GLPI já preenche e gerencia
> esses campos automaticamente em qualquer `CommonDBTM`.

**Ainda não implementado nesta entrega** (propositalmente — ver
"Próximas fases"): motor de regras condicionais, público-alvo,
respostas (formulário público), cálculo de NPS, dashboard,
relatórios/exportação CSV, integração com o ciclo de vida de chamados
e notificações.

## Correções de compatibilidade com GLPI 11 (pós-teste em ambiente real)

Esta revisão corrige 5 problemas encontrados ao testar o plugin em uma
instalação GLPI 11 real (via Marketplace):

1. **Bootstrap legado removido.** GLPI 11 roteia todas as requisições
   através de `public/index.php` (Symfony). O antigo
   `include('../../../inc/includes.php')` não é mais necessário — o core
   já faz esse bootstrap automaticamente antes de carregar o arquivo do
   plugin — e, nesse novo mecanismo de roteamento, o caminho relativo
   nem resolve mais corretamente. A linha foi removida de todos os
   arquivos `front/*.php` e `public/ajax/*.php`, conforme o guia oficial
   de upgrade para 11.0.
2. **`css/`, `js/` e `ajax/` movidos para dentro de `public/`.** A partir
   do GLPI 11, apenas os diretórios `front/`, `ajax/` e `report/` na
   raiz do plugin continuam acessíveis por convenção legada; qualquer
   outro arquivo (assets estáticos, endpoints AJAX, scripts) deve estar
   em `public/`. Fizemos a migração física dos três diretórios; as URLs
   não mudam (o GLPI remove o segmento `public/` automaticamente — ex.:
   `public/css/npsphere.css` responde em `/plugins/npsphere/css/npsphere.css`),
   então nenhuma referência no código precisou ser alterada.
3. **Erro fatal ao adicionar pergunta corrigido.** Faltava
   `use Glpi\Application\View\TemplateRenderer;` em
   `front/question.form.php` — sem esse import, `TemplateRenderer` era
   resolvido para o namespace global (inexistente) em vez de
   `Glpi\Application\View\TemplateRenderer`, causando
   `ClassNotFoundError`.
4. **Link público 404 corrigido.** `front/public.php` (o formulário de
   resposta) é uma entrega da **Fase 3**, que ainda não existe neste
   pacote. O link deixou de ser exibido como clicável — mesmo com
   "Permitir acesso via link público" ativado, o token é gerado
   normalmente, mas o link só aparece quando a Fase 3 for entregue.
5. **Atalho do menu movido de "Plugins" para "Administração"**
   (`$PLUGIN_HOOKS['menu_toadd']['npsphere'] = ['admin' => Survey::class]`).

## Correções adicionais (v0.2.1 — após teste em produção)

Depois do primeiro teste em ambiente real, três ajustes finos:

6. **Breadcrumb/ícone do menu ainda mostrava "Plug-ins".** O hook de
   menu (`menu_toadd`) havia sido movido para `admin`, mas as chamadas
   `Html::header(..., 'plugins', ...)` dentro de `front/survey.php`,
   `front/survey.form.php` e `front/question.form.php` continuavam
   apontando para a categoria antiga — o GLPI usa esse mesmo valor tanto
   para o breadcrumb quanto para destacar o ícone correto na barra
   lateral. Todas foram trocadas para `'admin'`.
7. **Botão "Nova pesquisa" explícito na listagem.** Adicionado em
   `front/survey.php` (visível apenas para quem tem direito de criação),
   para garantir a criação de pesquisas independentemente de o tema/
   layout renderizar ou não um botão de "adicionar" automático na tela
   de busca.
8. **Concessão de direitos na instalação mais robusta.** Antes, o
   plugin só concedia direitos completos ao perfil chamado exatamente
   `"Super-Admin"`. Agora, além disso, o perfil **atualmente ativo de
   quem está instalando o plugin** também recebe os direitos
   automaticamente — evitando ficar sem acesso caso o perfil de
   super-administrador tenha outro nome.

> Se você já tinha uma versão anterior instalada, basta clicar em
> **Atualizar** na tela de Plugins — a versão foi incrementada para
> `0.2.1` justamente para que o GLPI detecte a atualização e reexecute
> a concessão de direitos automaticamente.

## Correção crítica de instalação (v0.2.2)

**Bug:** ao clicar em "Atualizar" (ou reinstalar), a instalação quebrava
com:

```
RuntimeException: MySQL query error: Duplicate entry '4-plugin_npsphere_survey'
for key 'unicity' (1062) in ... ProfileRight::addProfileRights()
```

**Causa:** `ProfileRight::addProfileRights()` faz um `INSERT` direto na
tabela `glpi_profilerights`. Chamá-lo de novo sem checar se o direito
já estava registrado (o que acontece toda vez que `plugin_npsphere_install()`
roda de novo numa atualização de versão) violava a constraint `UNIQUE`
da tabela.

**Correção:** antes de chamar `addProfileRights()`, o código agora
consulta `ProfileRight::getAllPossibleRights()` e só adiciona os
direitos que ainda não existem — o mesmo padrão usado internamente pelo
próprio `Profile` do núcleo do GLPI. Testado isoladamente simulando
duas instalações consecutivas (reproduzindo o erro relatado): a
segunda chamada não dispara mais exceção.

## v0.17.0 — formulário da pesquisa com layout próprio

As tentativas anteriores (v0.16.0/v0.16.1) tentavam organizar os campos
*dentro* da grade do formulário genérico do GLPI — rótulo à esquerda,
campo à direita, dois pares por linha. Essa grade é o problema em si:
em telas largas ela empurra rótulo e campo para extremos opostos, e
força pares de campos sem relação entre si a dividirem a mesma linha.

**Agora o formulário tem layout próprio**, em vez de lutar contra a
grade padrão:

- **Cartões temáticos** — "Sobre a pesquisa", "Disponibilidade", "Como
  será respondida", "Privacidade e acesso" — cada um com ícone, título
  e uma linha explicando o propósito da seção;
- **Rótulo acima do campo** (padrão mais legível para formulários de
  configuração), em grade de duas colunas que vira uma só no mobile;
- **Textos de ajuda contextuais** abaixo dos campos que se beneficiam
  deles (ex.: o que muda entre pesquisa independente e vinculada a
  chamado; o que acontece se "múltiplas respostas" for "Não");
- **Largura máxima** de 940px centralizada, para as linhas não ficarem
  longas demais para leitura confortável;
- Autor exibido discretamente no rodapé do primeiro cartão, sem
  duplicar as datas que o rodapé nativo do GLPI já mostra.

Os campos "Nome" e "Ativo" continuam sendo renderizados pelo próprio
GLPI no topo (não são sobrescritos), preservando o comportamento
padrão de itemtype.

## v0.16.1 — rodapé duplicado e largura do formulário

Dois problemas do layout novo, corrigidos:

**Dois rodapés.** O `generic_show_form` do GLPI já renderiza um rodapé
próprio com "Criado em / Última atualização em" — o bloco de metadados
que adicionei na v0.16.0 duplicava exatamente essa informação logo
acima. Removi o meu bloco; a informação de **quem criou** (que o rodapé
nativo do GLPI não mostra) passou para a seção "Sobre a pesquisa", como
uma linha de dica, sem repetir as datas.

**Campos esparramados.** Em telas largas, o rótulo ficava colado à
esquerda e o campo lá na direita, com um vão enorme no meio — efeito da
grade padrão do formulário genérico ocupando 100% da largura
disponível. O formulário da pesquisa agora tem largura máxima definida
e espaçamento vertical mais compacto entre os campos.

## v0.16.0 — layout do formulário da pesquisa reorganizado

A tela estava bagunçada porque todos os campos herdavam o fluxo de duas
colunas do formulário genérico do GLPI, sem agrupamento — o que fazia
campos sem relação entre si aparecerem lado a lado (ex.: "Data de
término" ao lado de "Permitir múltiplas respostas") e os textos
explicativos caírem em pontos aleatórios do meio do formulário.

**Reorganização em seções temáticas**, cada uma ocupando a linha inteira
e reabrindo sua própria grade interna:

- **Sobre a pesquisa** — Descrição, Tipo de pesquisa
- **Disponibilidade** — Data de início, Data de término
- **Como será respondida** — Formato de exibição, Permitir múltiplas
  respostas (+ a nota sobre o cálculo do NPS)
- **Privacidade e acesso** — Pesquisa anônima, Link público (some
  inteira quando o tipo é "Vinculada a chamado")

Os textos explicativos viraram blocos de dica com ícone e fundo
próprio, em vez de parágrafos soltos no meio dos campos.

### Autor no rodapé

O rodapé agora mostra **quem criou** a pesquisa, junto das datas de
criação e atualização, num bloco único e alinhado ("Criada por Michael
Rodrigues · Criada em … · Atualizada em …").

## v0.15.1 — correção da recarga da aba "Visualizar"

O `IntersectionObserver` da v0.15.0 não resolveu: o GLPI esconde as abas
inativas com `display:none`, e nesse caso o observer não dispara de
forma útil (o elemento nunca "entra" na viewport no sentido que ele
mede). Troquei por uma verificação direta de visibilidade
(`offsetParent !== null`, que é `null` justamente quando o elemento ou
um ancestral está com `display:none`), sondada a cada 400ms. Simulei as
transições de estado antes de empacotar, cobrindo os dois casos: aba já
visível ao carregar a página, e aba aberta só depois de passar por
outra.

## v0.15.0 — aba "Visualizar" sempre atualizada

O GLPI mantém no DOM o conteúdo das abas que já foram carregadas, então
a aba "Visualizar" reaparecia exatamente no estado em que foi deixada
— inclusive parada na tela "Obrigado pela sua avaliação!" depois de um
envio, ou no meio de um preenchimento antigo.

**Correção:** novo endpoint `ajax/survey.preview.php` devolve o
fragmento da aba, e o JS recarrega esse conteúdo sempre que a aba volta
a ficar visível (detectado com `IntersectionObserver`, sem depender de
adivinhar eventos internos de aba do GLPI). Navegar entre "Pesquisa de
satisfação", "Pergunta" e "Visualizar" agora sempre traz a aba
"Visualizar" começando do zero.

A primeira exibição não dispara recarga (o conteúdo acabou de vir do
servidor) — só a partir da segunda vez que a aba fica visível.

## v0.14.0 — envio de resposta movido para ajax/

A evidência decisiva veio dos seus próprios testes: **reordenar
perguntas funciona** (POST para `ajax/question.reorder.php`), mas o
POST para `front/answer.form.php` retorna 403 de forma consistente,
mesmo com token recém-buscado. Ou seja: nesta instalação, POSTs para
`ajax/*.php` passam e POSTs para esse arquivo em `front/` não —
independentemente do token.

Em vez de continuar tentando variações do mesmo caminho que falha,
segui o padrão que já está comprovadamente funcionando:

- Novo endpoint **`ajax/answer.submit.php`**, que recebe o envio da
  resposta e devolve JSON `{success, errors}` — mesma estrutura de
  `ajax/question.reorder.php`, que funciona;
- O formulário embutido na aba "Visualizar" agora envia para esse
  endpoint (`data-submit-url`), não mais para `front/answer.form.php`;
- `front/answer.form.php` continua existindo e funcionando para o
  acesso direto/standalone (navegação de página cheia, sem
  JavaScript), e segue sendo o `action` do formulário — usado apenas
  como último fallback.

A busca de token fresco antes de cada tentativa (v0.13.0) e a renovação
periódica (v0.12.0) continuam ativas.

## v0.13.0 — a mesma solução aplicada de novo, na raiz

Você identificou certo: é o mesmo padrão da vez em que a
pré-visualização (POST repetido) invalidava o token antes do "Salvar"
conseguir usá-lo. Desta vez o problema estava escondido dentro do
próprio mecanismo de recuperação que eu tinha criado: o *fallback* do
envio de resposta tentava o `fetch()` (1ª tentativa, POST, pode
consumir o token mesmo falhando por outro motivo) e, se falhasse, caía
para `form.submit()` **reaproveitando o mesmo token que a 1ª tentativa
já tinha usado** — condenado a falhar de novo.

**Correção, no mesmo espírito de antes:** antes de QUALQUER tentativa
de envio (a primeira E a de retentativa), busco um token garantidamente
novo via GET (`ajax/csrf.token.php`) e só então faço o POST. Nunca mais
duas tentativas reaproveitam o mesmo valor de token. Apliquei essa
mesma lógica nos três pontos que fazem POST no plugin:

1. **Envio de resposta** (aba "Visualizar") — token fresco antes da
   1ª tentativa, e outro token fresco antes da retentativa/fallback;
2. **Salvar pergunta** (builder embutido) — token fresco imediatamente
   antes do clique em "Salvar" ser de fato enviado;
3. **Reordenar perguntas** (arrastar-e-soltar) — token fresco
   imediatamente antes de persistir a nova ordem, em vez de reaproveitar
   o token capturado quando a lista carregou.

A renovação periódica de 60 segundos (v0.12.0) continua ativa como uma
segunda camada de proteção, mas agora nenhuma ação crítica depende
só dela — cada uma garante seu próprio token fresco no momento exato
em que precisa dele.

## v0.12.1

### Sobre o log "Unable to load plugin information" / "version changed... deactivated"

**Isso não é um bug** — é o comportamento normal e documentado do GLPI
11 quando a versão de um plugin muda:

1. As mensagens `"Unable to load plugin information"` (repetidas
   algumas vezes) são transitórias — acontecem se o GLPI tentar ler o
   plugin bem no meio da extração/upload do `.zip` (arquivos parciais).
   Somem sozinhas assim que o upload termina.
2. `"Plugin version changed. It has been deactivated as its update
   process has to be launched"` é o GLPI **desativando
   automaticamente** o plugin porque detectou que a versão mudou —
   isso acontece a cada `.zip` novo que você sobe, sempre que a versão
   é diferente da última instalada. **É necessário clicar em
   "Atualizar" na tela Configurar > Plugins depois de cada upload**
   para o plugin voltar a ficar ativo.

Se os testes foram feitos sem clicar em "Atualizar" depois de subir o
`.zip`, o plugin estava desativado — o que explicaria qualquer
comportamento estranho, independente das correções já aplicadas.

### Correção defensiva: detecção de tela do plugin mais robusta

O carregamento do JS do builder verificava literalmente a substring
`/plugins/npsphere/` na URL. Como sua instalação usa
`/marketplace/npsphere/`, deixei a verificação mais genérica (só
`npsphere/`, sem prefixo fixo) — mais resiliente a diferentes formas de
expor os plugins que uma instalação GLPI pode ter.

## v0.12.0 — causa raiz real: token CSRF expira por tempo, não só por uso

O vídeo confirmou o padrão de forma inequívoca: reordenar perguntas só
funcionava na segunda tentativa, e enviar uma avaliação com várias
perguntas dava 403 mesmo com o *fallback* de envio tradicional (ou
seja, o problema **não** era específico de AJAX). Os dois sintomas têm
a mesma causa: **o token CSRF embutido na página expira pelo tempo
decorrido**, não só por já ter sido usado uma vez. Qualquer ação que
demore o suficiente (preencher 8 perguntas, ou só deixar a aba
"Pergunta" aberta um tempo antes de arrastar) acaba usando um token já
vencido na primeira tentativa — a segunda funciona porque, nesse meio
tempo, algum recarregamento de página já gerou um token novo.

**Correção definitiva:** implementei uma renovação automática e
universal de token, em vez de mitigações pontuais:

- Novo endpoint `ajax/csrf.token.php`, que devolve um token novo a
  qualquer momento (basta estar logado);
- O JS renova esse token a cada 60 segundos, em **qualquer tela do
  plugin**, atualizando de uma vez todos os campos ocultos
  `_glpi_csrf_token` da página e todos os atributos `data-csrf-token`
  usados pelas chamadas AJAX do builder — não importa se é o
  formulário de pergunta, o builder de reordenação ou o formulário de
  resposta;
- A URL do endpoint é **descoberta dinamicamente** a partir de uma URL
  já gerada pelo servidor na própria página (não fica hardcoded) —
  testei especificamente contra o prefixo `/marketplace/npsphere/`
  que apareceu no seu log real, além do `/plugins/npsphere/` padrão
  da documentação, confirmando que funciona nos dois.

Com isso, mesmo que o usuário demore bastante para preencher uma
pesquisa longa, ou deixe a aba de administração aberta por um tempo
antes de reordenar perguntas, o token em uso nunca deveria passar de
60 segundos de idade.

## v0.11.1

### 403 no envio embutido: fallback automático para envio tradicional

O envio via AJAX na aba "Visualizar" (novo na v0.11.0) apanhou 403 em
teste real. Não dá pra confirmar com certeza se foi token expirado
(pesquisa longa, várias perguntas, tempo de preenchimento) ou outra
causa pontual — mas, em vez de continuar investigando às cegas, apliquei
a correção mais robusta possível: se o envio via AJAX falhar por
**qualquer motivo** (403, erro de rede, resposta inesperada), o JS cai
automaticamente para `form.submit()` — o envio tradicional do
navegador, o mesmo caminho que já funcionava de forma confiável antes
da v0.11.0. A resposta do usuário nunca fica presa esperando uma
melhoria de UX que falhou.

### Botão "Anterior" desproporcional — corrigido

Causa: eu tinha aplicado `flex: 1 1 0` diretamente no `<button>`, o que
faz o próprio botão esticar para preencher o espaço reservado (em vez
de só a área ao redor dele). Corrigido envolvendo o botão num
contêiner próprio que reserva o espaço, mantendo o botão no tamanho
natural — mesma técnica que já funcionava para o lado do "Próxima".

### NPS "Números": organização corrigida

A apresentação "Escala visual" já ficou bem organizada (cada opção
ocupa uma fração igual da largura, numa linha só). Apliquei a mesma
lógica na apresentação "Números" — antes ela podia quebrar em várias
linhas de forma desigual dependendo da largura da tela; agora as 11
caixas (ou 6, na escala 0–5) sempre ficam distribuídas numa única
linha, do mesmo jeito que a escala visual.

## v0.11.0

### Botões do builder de perguntas: voltaram ao estilo anterior, agrupados

"Cancelar" voltou a ser `btn-outline-secondary` (não mais o estilo de
link), e os dois botões (Cancelar, Salvar) ficam agrupados à direita,
lado a lado — o mesmo padrão usado no resto do GLPI 11 (ex.: "Colocar
na lixeira" + "Salvar" no formulário da pesquisa).

### Validação de obrigatoriedade agora acontece também ao clicar "Próxima"

No formato "Uma pergunta por etapa", uma pergunta obrigatória não
respondida bloqueava o avanço só no envio final — dava para clicar em
"Próxima" e pular ela. Agora cada clique em "Próxima" (e também no
botão "Enviar avaliação" da última pergunta) verifica se a pergunta
atual, quando obrigatória, foi de fato respondida (rádio marcado,
checkbox marcado ou campo de texto preenchido, dependendo do tipo) e
mostra um aviso bloqueando o avanço se não.

### "Pergunta X de Y" descentralizando na última etapa — corrigido

A causa: eu escondia o botão "Próxima" com `display:none` na última
pergunta, o que removia esse elemento do fluxo do layout flexível e
deslocava o texto de progresso para a ponta. Troquei para uma barra de
navegação com três áreas de largura fixa (Anterior | progresso |
Próxima-ou-Enviar) — agora o texto fica sempre centralizado,
independente de qual botão está visível do lado direito.

### Botão "Enviar avaliação" alinhado com "Anterior"

O próprio botão de envio (que já existia no formulário) é movido pelo
JS para dentro da barra de navegação na última pergunta, ocupando
visualmente o lugar de "Próxima" — mesma linha, mesmo alinhamento.

### Envio de resposta pela aba "Visualizar" não navega mais para outra tela

Antes, enviar uma resposta a partir da aba "Visualizar" fazia uma
submissão de página cheia normal, saindo da tela de administração.
Agora esse envio específico é interceptado e feito via AJAX — tanto o
sucesso ("Obrigado pela sua avaliação!") quanto eventuais erros de
validação aparecem dentro da própria aba, sem navegar para lugar
nenhum. (O formulário standalone, acessado diretamente por link
externo, continua fazendo uma submissão de página normal — faz
sentido nesse contexto, já que não há "aba" para voltar.)

## v0.10.0

### 🚨 BUG CRÍTICO CORRIGIDO: reordenar perguntas corrompia os dados

**Este era o problema mais sério encontrado até agora.** Ao arrastar
uma pergunta para reordenar, o endpoint chamava
`Question::update(['id' => X, 'position' => Y])` — um envio parcial,
só com `id` e `position`. Só que a validação interna
(`prepareCommonInput()`) sempre revalidava **todos** os campos
derivados a partir do que veio no envio, sem diferenciar "veio tudo"
de "veio só um pedaço":

- `is_required`: como não veio no envio, virava `0` -- **uma pergunta
  obrigatória perdia essa marcação só de ser arrastada**;
- `is_active`: como não veio, virava sempre `1`;
- `configuration`: como nenhum campo específico do tipo veio no envio
  (nem `choice_options`, nem `emoticon_emoji`, etc.), a validação do
  tipo caía nos valores padrão -- **as opções de Múltipla escolha, os
  emojis do Emoticon, tudo era substituído pelos placeholders
  genéricos ("Opção 1", "Opção 2"...) a cada arrastar-e-soltar.**

**Correção em duas camadas:**
1. `prepareCommonInput()` só revalida esses campos quando o envio é
   uma submissão completa do formulário (identificada pela presença
   do campo `type`) ou uma criação nova -- uma atualização parcial
   preserva o que já estava salvo;
2. `ajax/question.reorder.php` passou a fazer um `UPDATE` direto no
   banco (`$DB->update(...)`), sem passar pelo pipeline de validação
   de `Question::update()` -- reordenar não precisa (nem deve)
   revalidar tipo, configuração ou obrigatoriedade.

Testado isoladamente simulando exatamente o payload que o
arrastar-e-soltar envia, confirmando que nada mais é resetado.

> Perguntas que já foram reordenadas em versões anteriores podem ter
> perdido configuração/obrigatoriedade — vale conferir e reconfigurar
> se necessário.

### Estrelas: ordem e preenchimento corrigidos

Dois bugs reais, como você notou:
1. A ordem visual estava invertida (aparecia de trás para frente).
2. Só a estrela clicada ficava colorida, não as anteriores.

Ambos vinham da mesma causa: o HTML tinha `<input>` **dentro** do
`<label>`, o que impede o seletor CSS `~` de "enxergar" as estrelas
anteriores (ele só alcança irmãos, não filhos de outro elemento). Refiz
a estrutura com `<input>` e `<label>` como irmãos diretos (técnica
clássica de "rating em CSS puro") — agora a ordem de leitura é 1→N da
esquerda para a direita, e selecionar a estrela N acende todas de 1 a N.

### NPS: apresentações agora são visualmente diferentes, e a escala é configurável

- **"Números"**: caixas individuais clicáveis (como já era).
- **"Escala visual"**: agora é uma barra contínua colorida por zona
  (vermelho = Detrator, laranja = Neutro, verde = Promotor) — clara e
  visivelmente diferente da versão em números.
- **Escala 0–10 ou 0–5**, igual ao padrão já usado em Emoticons e
  Estrelas. Importante: **o NPS "oficial" (metodologia Reichheld) é
  definido especificamente como 0–10** — não existe uma versão 0–5
  padronizada no mercado. Implementei a opção porque você pediu, mas a
  classificação Detrator/Neutro/Promotor nessa escala é uma
  **reproporção aproximada** da faixa oficial (mantém as mesmas
  proporções: ~70% do topo = Promotor, ~90%+ = zona de Promotor
  "forte" etc.), não um padrão de mercado. Vale ter isso em mente ao
  comparar resultados entre pesquisas com escalas diferentes.

### Sobre "NPS ativo só para o NPS, não deveria ser para todos?"

Entendo a ideia — e concordo que faz sentido calcular um "NPS-like
score" a partir de qualquer pergunta com valor numérico (Estrelas,
Emoticons), não só do tipo NPS dedicado, além de uma nota geral por
pergunta. **Isso é exatamente o que a Fase 4 (Cálculo de NPS) vai
implementar** — ainda não construída. Prefiro desenhar esse mecanismo
de classificação por tipo (cada tipo definindo sua própria
faixa Detrator/Neutro/Promotor, como o `classify()` que acabei de
criar para o NPS) como parte dessa fase dedicada, em vez de encaixar
uma versão apressada agora. Fica confirmado como a direção certa para
quando chegarmos lá.

### Botão "Enviar avaliação" alinhado à direita

Nos dois lugares onde aparece: o formulário de resposta completo
(`front/answer.form.php`) e a aba "Visualizar".

### Botão "Cancelar" com visual mais integrado

Trocado de um botão com contorno (`btn-outline-secondary`) para um
estilo de link (`btn-link`), sem peso visual de botão — o padrão mais
comum para ações secundárias/cancelamento em interfaces administrativas.

## Ajustes v0.9.3

### Botões reorganizados

"Cancelar" à esquerda (volta para a lista sem salvar, dentro da aba),
"Salvar" à direita — mesma linha. Cabeçalho "Editar pergunta" e botão
"Voltar para lista" continuam removidos (confirmado: já não existem
desde a v0.9.2; o formulário começa direto em "Conteúdo").

### BUG: composição errada dos 3 níveis de Emoticon

Ao reduzir de 5 para 3 níveis, o JS pegava os **3 primeiros em
sequência** do conjunto canônico (`Muito insatisfeito, Insatisfeito,
Neutro`) em vez do negativo/neutro/positivo
(`Muito insatisfeito, Neutro, Muito satisfeito`). Corrigido usando os
índices certos (0, 2 e 4) do conjunto canônico de 5. Validado com um
teste isolado da lógica de mapeamento antes de empacotar.

## Correções v0.9.2 (causa raiz real dos bugs desta rodada)

### BUG DE DADOS: opções duplicando a cada salvamento

**Causa raiz encontrada:** `Múltipla escolha` e `Múltiplas opções` usam
o **mesmo nome de campo** (`choice_options[]`), e os dois blocos de
configuração ficam sempre presentes no HTML do formulário — só
escondidos com CSS (`display:none`) quando não é o tipo selecionado.
**CSS `display:none` não remove um campo do envio do formulário** — o
navegador manda os dois blocos igual, então `choice_options[]` chegava
duplicado no servidor a cada salvamento (2 opções configuradas viravam
4, depois 8...).

**Correção:** além de esconder, o JS agora **desabilita**
(`disabled = true`) todos os campos dos blocos de tipo não
selecionados. Campo desabilitado é automaticamente excluído do
`FormData()` do navegador — comportamento nativo do HTML, não depende
de nenhuma suposição sobre o GLPI. Isso corrige a duplicação na raiz
(não só para Múltipla escolha, mas para qualquer campo que viesse a
colidir de nome entre tipos no futuro).

> **Se você já tem perguntas com opções duplicadas** (criadas nas
> versões anteriores a esta), abra a pergunta para editar e apague as
> linhas repetidas manualmente (botão "×") antes de salvar — a partir
> daí a duplicação não vai mais acontecer.

### Troca de "Tipo de pergunta" — reforçada com um mecanismo à prova de suposições

Como não há forma de confirmar com certeza qual widget o GLPI usa para
estilizar o `<select>` de tipo, a troca de bloco/pré-visualização
agora tem **dupla garantia**: além do evento `change` (delegado no
formulário inteiro), um verificador por sondagem (a cada 250ms) detecta
qualquer mudança no valor do tipo e força a atualização — funciona
mesmo que o evento nativo não dispare como esperado.

### Edição de pergunta: reinicialização do JS não dependia mais de adivinhar o evento de aba do GLPI

O mecanismo anterior escutava um evento `tabsload` em elementos
`.glpi_tabs` — nomes que eu nunca consegui confirmar contra o código
real do GLPI 11. Trocado por um `MutationObserver`, que detecta
diretamente quando o conteúdo do plugin é inserido em qualquer lugar
da página (não importa o mecanismo usado pelo GLPI para carregar a
aba) e inicializa tudo automaticamente. Isso deve corrigir de vez o
"editar pergunta abre em página separada".

### Removidos: cabeçalho "Editar pergunta"/"Nova pergunta" e botão "Voltar para lista"

Como pedido — o formulário aparece direto, sem esse cabeçalho extra.
Depois de salvar, a lista é recarregada automaticamente; para
cancelar uma edição, basta trocar de aba e voltar (o formulário não
fica "preso" na tela).

## Melhorias v0.9.1

### Troca de tipo de pergunta mais robusta

A troca de "Tipo de pergunta" já deveria disparar a atualização do
bloco de configuração e da "Visualização", mas o mecanismo dependia de
capturar uma referência direta ao `<select>` no carregamento do
script. Reescrito para usar **delegação de eventos** no formulário
inteiro (`form.addEventListener('change', ...)` verificando
`event.target.name === 'type'`), que funciona de forma confiável
independentemente de qualquer widget que o GLPI use para estilizar o
dropdown, e continua funcionando depois do formulário ser reinjetado
via AJAX (builder embutido). Cada bloco de configuração agora só
mostra os campos específicos daquele tipo (ex.: "Tipo de campo"
curto/longo só existe dentro do bloco do tipo Texto).

### Emoticons: quantidade de níveis (3 ou 5)

Novo seletor "Quantidade de níveis" com as opções 3 ou 5, sempre
reaproveitando os mesmos 5 emojis padrão (😡🙁😐🙂😍) — a versão de 3
níveis usa o negativo, o neutro e o positivo desse mesmo conjunto. O
JS adiciona/remove as linhas de emoji+texto na hora, sem precisar
salvar primeiro. O admin ainda pode editar emoji e texto livremente
depois.

### Estrelas: adicionada a opção de 3

O seletor "Quantidade de estrelas" passou a oferecer 3, 5 ou 10 (antes
só 5 ou 10), seguindo a mesma lógica de quantidade configurável usada
nos Emoticons.

## Melhorias v0.9.0

### "Uma pergunta por etapa" implementado de verdade

O campo `display_mode` era salvo, mas nunca influenciava a
renderização — todas as perguntas sempre apareciam juntas, mesmo com
"Uma pergunta por etapa" selecionado. Implementado agora via JS
(`initStepByStep`, compartilhado entre o formulário de resposta e a
aba "Visualizar"): mostra uma pergunta de cada vez, com botões
Anterior/Próxima e indicador "Pergunta X de Y". Com "Página única"
selecionado, o comportamento continua sendo mostrar todas juntas.

### Editar pergunta agora acontece dentro da aba "Pergunta"

Antes, clicar em uma pergunta (ou em "+ Adicionar pergunta") navegava
para `front/question.form.php` como página cheia. Agora é um mini-SPA
dentro da própria aba: o formulário é carregado via `fetch()` e
injetado no lugar da lista, sem sair da tela; salvar recarrega a
lista automaticamente (também via `fetch()`) e volta para a
visualização de lista. `front/question.form.php` e
`ajax/question.list.php` continuam funcionando como páginas completas
também (usados como *fallback* automático se o JavaScript falhar ao
carregar o fragmento).

> Duplicar e excluir continuam como ações de página cheia (POST
> tradicional) por enquanto — eram menos prioritárias que resolver a
> navegação da edição, que era o problema relatado.

### Tela de pergunta unificada em um único card

Removido o layout de duas colunas (formulário + pré-visualização em
cards separados). Agora é um único card, com as seções "Conteúdo",
"Comportamento", a configuração específica do tipo, e por fim
**"Visualização"** (renomeado de "Como o respondente vai ver", no
lugar onde antes ficava o rótulo solto "Níveis (emoji + texto)").

### Pré-visualização reage imediatamente à troca de tipo

Trocar o "Tipo de pergunta" agora atualiza a pré-visualização na hora
(sem esperar os 300ms de debounce usados para digitação), já que essa
é uma ação discreta do usuário, não uma sequência de teclas.

### "+" para adicionar opções em Múltipla escolha / Múltiplas opções

Antes, só dava para adicionar uma opção nova por vez (era preciso
salvar para "ganhar" outra linha em branco). Agora um botão **"+
Adicionar opção"** insere uma nova linha na hora, e cada linha tem um
botão de remover — tudo no navegador, sem precisar salvar primeiro.

## Correções v0.8.0

### Provável causa do erro "persistir": cache do navegador

O log mostrou o navegador ainda enviando **POST** para
`question.preview.php` mesmo após a correção v0.7.0 (que trocou para
GET) — forte indício de que a página estava sendo servida do cache do
navegador. Adicionei cabeçalhos `Cache-Control: no-store` /
`Pragma: no-cache` em todas as páginas administrativas do plugin
(`survey.form.php`, `question.form.php`, `answer.form.php`), para que o
navegador nunca reutilize uma versão antiga dessas telas depois de uma
atualização do plugin. O endpoint de pré-visualização também passou a
aceitar tanto GET quanto POST (`$_REQUEST`), como reforço extra.

> Se o erro persistir mesmo assim: dê um refresh forçado
> (Ctrl+Shift+R / Cmd+Shift+R) na página do formulário de pergunta
> depois de atualizar o plugin.

### Bug real encontrado: pergunta nova nascia com "Ativa = Não"

`Question::getDefaultValues()` existia mas **nunca era chamado por
ninguém** — não tinha nenhuma ligação com o ciclo de vida real do
GLPI. Corrigido usando o hook oficial `post_getEmpty()` (chamado pelo
próprio `CommonDBTM::getEmpty()` do núcleo do GLPI), que é o jeito
correto e documentado de popular valores padrão num item novo.

### Nome do plugin encurtado

`Pesquisas de Satisfação (NPSphere)` → **`NPSphere`**.

### Layout do builder de perguntas melhorado

Cabeçalho com título/ícone ("Nova pergunta" / "Editar pergunta"),
campos agrupados em seções com rótulo ("Conteúdo", "Comportamento"), e
o painel de pré-visualização agora tem aparência de "tela" (fundo
diferenciado, bordas arredondadas) para deixar claro que ali é uma
simulação de como o respondente vai ver a pergunta.

### Ordem de desinstalação invertida (defensivo)

Revogar os direitos do plugin antes de derrubar as tabelas, não depois
— um plugin real (`addressing`) já teve um bug em produção por fazer
isso na ordem inversa. A minha `getAllRights()` atual não é afetada por
esse bug específico (não instancia nenhum itemtype), mas a ordem
correta protege contra isso no futuro.

## Correções v0.7.0 (causa raiz real dos 403)

### Nome do plugin encurtado

`Pesquisas de Satisfação (NPSphere)` → **`NPSphere`**, na tela
Configurar > Plugins.

### A causa raiz real do 403 ao salvar pergunta

O `csrf_compliant` e a mudança de `public/ajax` para `ajax/` (rodadas
anteriores) eram, de fato, necessários -- mas não eram a causa
completa. O problema real: a pré-visualização ao vivo chama
`question.preview.php` a cada tecla digitada, via **POST**, reenviando
o token CSRF capturado no carregamento da página. Se o GLPI rotaciona o
token a cada uso (prática comum de segurança), a primeira chamada de
preview já invalida esse token — e todas as chamadas seguintes (mais
previews, e principalmente o clique em **Salvar**, que usa o mesmo
token original do formulário) passam a falhar com 403, mesmo em uma
sessão de Super-Admin com todos os direitos. Isso também explica por
que a pré-visualização "não mudava": a primeira falha silenciosa (o
JS ignora erros de preview de propósito, por ser só cosmético) já
deixava tudo preso nesse estado.

**Correção:** `question.preview.php` agora é **GET**, não POST. É uma
operação 100% de leitura (não grava nada no banco), então não precisa
— e agora não usa mais — nenhum token CSRF, eliminando de vez a disputa
pelo mesmo token com o formulário de salvar. Simulei o cenário exato
(token rotacionando a cada POST) antes e depois da correção para
confirmar a causa raiz e validar a correção.

## Correções v0.6.0 (segunda rodada de testes reais)

### 403 persistente: `ajax/` voltou para a raiz do plugin

Mesmo com `csrf_compliant` restaurado, `question.form.php` e
`question.preview.php` continuaram retornando `403 Forbidden`, com a
tela padrão do GLPI de **permissão negada** (não um erro de CSRF). A
evidência (403 + página de "não permitida" limpa, sem crash de PHP) aponta
para os scripts em `public/ajax/` não recebendo o mesmo bootstrap de
sessão que os scripts em `ajax/`, `front/` e `report/` na raiz recebem
pela via legada oficialmente documentada. Mesmo o `public/` tendo sido
pedido explicitamente numa rodada anterior, a evidência de quebra é
mais forte que a preferência de organização — **os arquivos PHP de
`ajax/` voltaram para a raiz do plugin** (fora de `public/`), que é o
caminho comprovadamente funcional e documentado oficialmente. `css/` e
`js/`, que são estáticos de verdade (sem lógica de sessão/permissão),
continuam em `public/`. As URLs não mudam.

### Ícone da aba "Visualizar"

Faltava `getIcon()` na classe da aba — o GLPI usa esse método para
desenhar o ícone ao lado do nome da aba (mesmo padrão de `Question` e
`Survey`). Adicionado.

### Aba "Visualizar" agora É o formulário de resposta, não um link para ele

Antes: pré-visualização ilustrativa + um botão separado que levava a
`front/answer.form.php`. Agora: o formulário de resposta real e
funcional está **diretamente dentro da aba** — pode preencher e enviar
sem sair da tela de administração da pesquisa (o envio ainda faz uma
navegação de página normal até a tela de confirmação, já que uma
resposta de verdade precisa passar pela mesma validação de servidor
usada por qualquer respondente, mas não abre nova aba/janela nenhuma).

### "Pergunta NPS principal" removida — toda pergunta do tipo NPS conta

Reavaliando: não fazia sentido ter um tipo de pergunta chamado "NPS"
que só conta para o cálculo do NPS se **também** for marcada como
"principal" — isso era redundante desde o início. Agora **toda
pergunta do tipo "NPS" conta automaticamente** para o cálculo (Fase 4),
sem opção manual e sem limite de uma por pesquisa. A coluna
`is_nps_main` foi removida (`Migration::dropField`, automático na
atualização). O aviso confuso no formulário da pesquisa também foi
reescrito para refletir essa regra mais simples.

## Correções críticas e melhorias de UX v0.5.0 (pós-teste real)

### Erro crítico: `csrf_compliant` estava faltando

Os erros `403 Forbidden` na pré-visualização e ao salvar/editar
perguntas foram causados por eu ter **removido acidentalmente**, numa
limpeza de código anterior, o hook:

```php
$PLUGIN_HOOKS['csrf_compliant']['npsphere'] = true;
```

Esse hook é **obrigatório** em praticamente todo plugin GLPI — sem ele,
o GLPI considera o plugin "não compatível com CSRF" e passa a rejeitar
os POSTs enviados pelas suas páginas (confirmado em múltiplos plugins
reais e na documentação oficial de CSRF do GLPI). Restaurado.

### Pré-visualização do builder agora reage às mudanças

Consequência direta do bug acima: como as chamadas AJAX de
pré-visualização estavam sendo bloqueadas com 403, elas nunca
atualizavam a tela. Com o `csrf_compliant` restaurado, o mecanismo
já implementado (debounce de 300ms nos eventos `input`/`change` do
formulário, chamando `question.preview.php`) volta a funcionar.

### Salvar perguntas (novas e edições) corrigido

Mesma causa raiz: o POST para `front/question.form.php` também estava
sendo bloqueado com 403.

### Campo "Usuário" removido do formulário

O GLPI reconhece magicamente qualquer coluna chamada `users_id` e a
exibe como um dropdown editável ("reatribuir dono") no formulário
genérico. Renomeei a coluna para `creator_id` (via
`Migration::changeField`, aplicado automaticamente na atualização) —
o GLPI para de tratá-la como campo especial, então ela simplesmente
não aparece mais no formulário. A informação de quem criou a pesquisa
continua sendo registrada, mas agora só na aba **Histórico**, que é
onde ela deveria estar.

### Nova aba "Visualizar" (antes de "Histórico")

O botão "Responder a esta pesquisa", que antes abria uma nova aba do
navegador, virou uma aba própria no formulário da pesquisa —
**Pergunta → Visualizar → Histórico → Todos**. Ela mostra os campos de
resposta renderizados (a mesma função usada no formulário real, então
nunca diverge dele) diretamente ali, sem sair da tela. Um botão "Abrir
para responder de verdade" continua disponível para quem quiser
efetivamente testar o envio de uma resposta (isso ainda navega para
`front/answer.form.php`, já que uma submissão real precisa passar pela
mesma validação de servidor usada por qualquer respondente — deixar
isso simplificado, como uma navegação normal em vez de um envio via
AJAX embutido, foi uma escolha deliberada para não arriscar mais bugs
nesta rodada).

## Correções críticas e melhorias de UX (v0.4.0)

### Erro fatal corrigido

```
FatalError: Declaration of GlpiPlugin\Npsphere\Answer::canCreate()
must be compatible with CommonGLPI::canCreate(): bool
```

**Causa:** `Answer::canCreate()` e `AnswerValue::canCreate()` (adicionados
na Fase 3 para permitir que qualquer usuário autenticado responda,
sem depender do direito administrativo `plugin_npsphere_survey`) foram
declarados sem tipo de retorno. No GLPI 11, `CommonGLPI::canCreate()`
é declarado como `: bool`, e PHP exige assinaturas compatíveis ao
sobrescrever métodos. **Importante:** esse tipo de erro não é
detectável por `php -l` (que só verifica sintaxe) — só aparece quando
o PHP carrega as duas classes juntas e compara as assinaturas. Reproduzi
o cenário exato com um stub fiel à assinatura real do `CommonGLPI` antes
de corrigir, para confirmar a causa raiz e validar a correção.

### Navegação e UI

- **Breadcrumb duplicado corrigido** (`Administração / Pesquisas de
  satisfação / Pesquisas de s...`): a estrutura de menu tinha um nível
  de submenu (`options`) desnecessário — o plugin só tem um itemtype de
  primeiro nível, então essa camada extra só duplicava o próprio nome.
  Agora usa a estrutura de menu de nível único, como recomendado pela
  documentação oficial de plugins.
- **Botão "+ Nova pesquisa" removido**: era redundante com o botão
  "+ Adicionar" nativo do GLPI, que já funcionava corretamente.
- **Botão "Configurar"** adicionado na tela Configurar > Plugins
  (`config_page` hook), levando direto para a listagem de pesquisas.

### Formulário da pesquisa: campos duplicados corrigidos

O `generic_show_form.html.twig` do GLPI já renderiza automaticamente
os campos padrão de qualquer `CommonDBTM` (Nome, Ativo/a, e o campo
"Usuário" quando existe uma coluna `users_id` — nesse caso, mostrando
quem criou o registro). Eu também estava renderizando **manualmente**
esses mesmos campos dentro de `more_fields`, causando a duplicação
visível ("Nome" duas vezes, "Ativo"/"Ativa" duas vezes). Removi os
campos duplicados do template — agora "Nome" e "Ativo" aparecem uma
única vez (gerados automaticamente pelo GLPI), e o campo "Usuário"
(que mostra o criador da pesquisa) é um comportamento padrão do GLPI
para qualquer objeto com coluna `users_id`, não um bug.

### "NPS habilitado" removido (não fazia sentido como estava)

O campo era ambíguo: nada impedia marcar "NPS habilitado = Não" numa
pesquisa que já tinha uma pergunta marcada como "NPS principal" —
um estado contraditório. A coluna `is_nps_enabled` foi **removida**
(via `Migration::dropField`, aplicado automaticamente na atualização).
Agora o NPS é considerado habilitado **implicitamente** sempre que a
pesquisa tem uma pergunta ativa marcada como "Pergunta NPS principal"
(`Survey::hasNpsQuestion()`) — sem opção separada para confundir.

### Anonimato e link público: visíveis só para pesquisas independentes

"Pesquisa anônima" e "Permitir acesso via link público" só aparecem
agora quando "Tipo de pesquisa" = Independente (JS simples, sem
recarregar a página). Pesquisas vinculadas a chamado sempre identificam
o respondente (o requerente do chamado) e serão respondidas dentro do
próprio GLPI — anonimato e link público não se aplicam a esse caso.

### Pré-visualização ao vivo no builder de perguntas

A tela de edição de pergunta agora mostra, em tempo real, como a
pergunta vai aparecer para o respondente — reaproveitando exatamente
a mesma função `QuestionType::renderAnswerField()` usada no formulário
de resposta real (via um endpoint AJAX dedicado,
`public/ajax/question.preview.php`), então a pré-visualização nunca
diverge do resultado real. O botão **"+ Adicionar pergunta"** continua
no mesmo lugar, inalterado.

### Itens que ficam para uma próxima fase (deliberadamente não incluídos)

Dois pedidos desta rodada são grandes o suficiente para merecer sua
própria fase dedicada, em vez de uma implementação apressada:

1. **Aba de público-alvo** (entidade, perfil, grupo, usuário) — exige
   uma nova tabela (`glpi_plugin_npsphere_targets`) e uma UI própria;
   é exatamente o escopo da Fase 6 do roadmap original.
2. **Substituir a pesquisa de satisfação nativa do GLPI** por esta,
   quando a pesquisa for vinculada a chamado — exige detectar o evento
   de solução do chamado, suprimir a pesquisa nativa e injetar esta
   pesquisa na tela do chamado. Também é escopo da Fase 6, e requer
   mais investigação sobre os hooks corretos antes de qualquer código.

## Fase 3 — Respostas (v0.3.0)

Novidades desta fase (fluxo de resposta para usuário **autenticado** do
GLPI; ver nota sobre link público/anônimo logo abaixo):

- **Tabelas `glpi_plugin_npsphere_answers` e `..._answer_values`**
  (uma linha por resposta completa + uma linha por pergunta respondida,
  conforme a seção 24 do escopo — nunca tudo num único JSON);
- **`renderAnswerField()` e `validateAnswerValue()` implementados nos 7
  tipos de pergunta** — cada tipo sabe desenhar seu próprio campo e
  validar seu próprio valor no servidor;
- **`ResponseService`**: centraliza elegibilidade (`canRespond()` —
  pesquisa ativa? dentro do prazo? já respondeu quando não é permitido
  responder de novo?) e persistência (`submit()`), evitando duplicar
  regras entre o formulário autenticado e o futuro formulário público;
- **`front/answer.form.php`**: formulário de resposta completo, com
  template dedicado e CSS próprio (escala NPS, emoticons, estrelas);
- Botão **"Responder a esta pesquisa"** na tela administrativa, para
  testar o fluxo sem precisar do link público;
- **Bug corrigido**: a desinstalação da Fase 2 não removia a tabela de
  perguntas — corrigido junto nesta versão.

**Cobertura de testes desta fase:** 41 asserções automatizadas passando
— validação de cada um dos 7 tipos (casos válidos e inválidos nos
limites), elegibilidade (pesquisa inativa, fora do prazo, resposta
duplicada bloqueada/permitida) e submissão completa ponta a ponta
(incluindo o modo anônimo, verificando que `users_id` não é gravado).

### Sobre o link público/anônimo (ainda não incluso)

Pesquisei a fundo e não encontrei um mecanismo documentado e confiável
para uma página de *plugin* (não o `Form` nativo do GLPI) pular a
autenticação no GLPI 11 — inclusive há relatos de outros
desenvolvedores enfrentando o mesmo obstáculo, sem solução definitiva
publicada. Em vez de arriscar um hack não verificado, o acesso via link
público (`front/public.php`) fica como uma sub-fase dedicada, a ser
investigada e testada contra uma instância real antes de qualquer
entrega de código.

## Requisitos

- GLPI `>= 11.0.0, < 12.0.0`
- PHP `>= 8.1`

## Instalação

1. Copie (ou clone) a pasta `npsphere` para dentro de `.../glpi/plugins/`.
2. Rode `composer install` dentro de `plugins/npsphere` para gerar o
   autoload PSR-4.
3. Acesse **Configurar > Plugins** no GLPI, localize "Pesquisas de
   Satisfação (NPSphere)" e clique em **Instalar**, depois em **Ativar**.
   (Se você já tinha a Fase 1 instalada, basta clicar em **Atualizar** —
   a tabela de perguntas será criada automaticamente pela `Migration`,
   sem exigir reinstalação manual.)
4. Em **Administração > Perfis**, abra o perfil desejado, entre na aba
   "Pesquisas de satisfação" e conceda os direitos (Ler/Criar/
   Atualizar/Excluir/Remover definitivamente). O perfil Super-Admin
   recebe todos os direitos automaticamente na instalação.

## Desinstalação

Pela mesma tela **Configurar > Plugins**, desative e depois desinstale o
plugin. O GLPI solicitará confirmação antes de remover as tabelas
`glpi_plugin_npsphere_surveys` e `glpi_plugin_npsphere_questions` e os
dados associados — nada é apagado sem confirmação explícita.

## Arquitetura

```
npsphere/
├── setup.php                    # bootstrap, hooks, menu
├── hook.php                     # install/uninstall (Migration)
├── composer.json / composer.lock # autoload PSR-4 (GlpiPlugin\Npsphere\)
├── vendor/                      # autoloader gerado (composer install)
├── README.md
├── src/
│   ├── Survey.php                 # itemtype principal (CommonDBTM)
│   ├── SurveyPreview.php           # aba "Visualizar" (formulário de resposta embutido)
│   ├── Question.php                # perguntas de uma pesquisa (CommonDBChild)
│   ├── Answer.php                  # uma resposta completa (CommonDBTM)
│   ├── AnswerValue.php             # valor de resposta de 1 pergunta (CommonDBChild)
│   ├── ResponseService.php         # elegibilidade + validação + persistência
│   ├── QuestionTypeManager.php     # registro central dos tipos de pergunta
│   ├── QuestionType/
│   │   ├── QuestionTypeInterface.php
│   │   ├── AbstractQuestionType.php
│   │   ├── AbstractOptionListType.php
│   │   ├── Nps.php
│   │   ├── Emoticon.php
│   │   ├── Stars.php
│   │   ├── YesNo.php
│   │   ├── SingleChoice.php
│   │   ├── MultipleChoice.php
│   │   └── Text.php
│   └── Profile.php               # aba de direitos em Administração > Perfis
├── front/                        # continua na raiz (convenção legada suportada)
│   ├── survey.php                 # listagem (Search::show)
│   ├── survey.form.php            # CRUD da pesquisa
│   ├── question.form.php          # CRUD de pergunta
│   ├── answer.form.php            # formulário de resposta (usuário autenticado)
│   └── profile.rights.form.php    # salva os direitos do plugin por perfil
├── ajax/                         # lógica com sessão/permissão: fica na raiz
│   ├── question.reorder.php       # persiste a ordem (drag-and-drop)
│   ├── question.preview.php       # pré-visualização ao vivo da pergunta
│   ├── question.list.php          # fragmento da listagem (recarregar sem navegar)
│   └── csrf.token.php             # token CSRF fresco (renovação periódica via JS)
├── public/                       # apenas assets estáticos de verdade
│   ├── css/npsphere.css
│   └── js/survey-builder.js       # drag-and-drop nativo (HTML5)
├── templates/
│   ├── survey.form.html.twig
│   ├── csrf_token.html.twig       # template mínimo para ajax/csrf.token.php
│   ├── profile.html.twig
│   ├── question/
│   │   ├── list.html.twig         # wrapper (alterna lista ↔ formulário)
│   │   ├── list_table.html.twig   # miolo da listagem (reaproveitado via AJAX)
│   │   └── form.html.twig         # formulário de uma pergunta
│   └── answer/
│       ├── form.html.twig         # formulário de resposta
│       ├── thankyou.html.twig     # tela de agradecimento
│       └── blocked.html.twig      # pesquisa indisponível/já respondida
├── locales/npsphere.pot
└── install/                       # reservado para migrations dedicadas
```

Segue o princípio arquitetural do escopo original:
**Pesquisa → Perguntas → Regras → Respostas → Métricas → Relatórios**,
que será construído incrementalmente nas próximas fases, sem nunca
alterar arquivos do núcleo do GLPI.

## Roteiro (próximas fases)

Conforme o planejamento do próprio escopo funcional, o desenvolvimento
segue dividido em fases — cada uma entregável e testável isoladamente:

| Fase | Escopo |
|---|---|
| **1 — Fundação** ✅ | Estrutura, instalação, banco, permissões, entidades, cadastro de pesquisas |
| **2 — Perguntas** ✅ | Tabela `glpi_plugin_npsphere_questions`, construtor de perguntas (arrastar/soltar), tipos (NPS, emoticons, estrelas, sim/não, múltipla escolha, múltiplas opções, texto), ordenação, obrigatoriedade |
| **3 — Respostas** ✅ | Tabelas `answers`/`answer_values`, formulário de resposta (usuário autenticado), validação server-side por tipo, anonimato, prevenção de resposta duplicada |
| 3b — Link público | Acesso sem login via token (sub-fase dedicada, pendente de investigação contra a arquitetura de roteamento do GLPI 11) |
| 4 — NPS | Cálculo dinâmico do NPS a partir da pergunta `is_nps_main`, classificação Promotor/Neutro/Detrator |
| 5 — Regras | Tabela `glpi_plugin_npsphere_rules`, motor de condições (ex.: NPS ≤ 6 → pergunta obrigatória), extensível a novos operadores |
| 6 — Integração GLPI | Público-alvo (`glpi_plugin_npsphere_targets`: entidades, perfis, usuários, grupos, técnicos), disparo por chamado solucionado/fechado, notificações |
| 7 — Dashboard | Cards de NPS/respostas/distribuição, gráficos de evolução, integração com `Glpi\Dashboard\Grid` |
| 8 — Segurança e qualidade | Testes automatizados, auditoria, rate limiting no link público, revisão de permissões por entidade |
| 9 — Documentação | Manuais de instalação, administrativo, do usuário e da API |

Cada fase será entregue como uma migração incremental (nova versão do
plugin, gerenciada via `Migration` em `hook.php`), sem exigir
reinstalação manual.

## Referências oficiais usadas no desenvolvimento

- Guia de plugins do GLPI: <https://glpi-developer-documentation.readthedocs.io/en/master/plugins/>
- Tutorial de desenvolvimento de plugins: <https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html>
- Plugin de referência `pluginsGLPI/empty` (esqueleto oficial)
- Plugin de referência `pluginsGLPI/example` (uso exaustivo da API interna)
