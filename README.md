# NPSphere — Pesquisas de satisfação e NPS para o GLPI 11

O NPSphere é um plugin do **GLPI 11** para criar, enviar e medir pesquisas de satisfação: **NPS**, estrelas, emoji e outras perguntas. Ele funciona em três cenários:

- **pesquisas de chamado**, no lugar da pesquisa de satisfação nativa do GLPI;
- **pesquisas internas**, para usuários do GLPI;
- **pesquisas externas**, para quem não tem login.

Os resultados ficam num painel consolidado, e os gestores recebem relatório diário e aviso de nota baixa por e-mail.

> Versão atual: **0.64.0** · GLPI **11.0.0 a 11.99.99** · PHP **8.1+** · Licença **GPL v3+**

---

## Funcionalidades

### Pesquisas e perguntas
- **7 tipos de pergunta:**
  - NPS (0–10);
  - Emoticons (3 ou 5 níveis);
  - Estrelas (3, 5 ou 10);
  - Sim/Não;
  - Escolha única;
  - Múltiplas opções;
  - Texto.
- **Construção da pesquisa:** perguntas reordenadas arrastando, pré-visualização ao vivo e aba **Visualizar**, que testa a pesquisa sem gravar resposta.
- **Regras condicionais:** uma pergunta só aparece conforme a resposta de outra. Há 6 operadores, e a validação é a mesma no navegador e no servidor.
- **Formato:** página única ou uma pergunta por etapa.

### Três tipos de pesquisa
| Tipo | Quem responde | Como chega |
|---|---|---|
| **Vinculada a chamado** | O requerente do chamado | Embutida no próprio chamado e por e-mail. A nota é gravada também em `glpi_ticketsatisfactions` (satisfação nativa do GLPI) |
| **Interna** | Usuários do Público-alvo (entidade, perfil, grupo, usuário ou todos) | Tela **Minhas pesquisas**, autoatendimento, resposta obrigatória após o login (opcional) e convite por e-mail |
| **Externa** | Qualquer pessoa, sem login | Link público com token (anônimo ou com identificação) e convite por e-mail para uma lista ou CSV |

### E-mails (aba Template)
- **Convite** com modelo editável em HTML5 ou código, variáveis e prévia ao vivo. Responde **com um clique**, direto do e-mail.
- **Convite do chamado com espera** (padrão 15 min): só sai se o requerente não avaliar no próprio chamado.
- **Lembrete** automático para quem não respondeu, com intervalo e máximo configuráveis. Na pesquisa de chamado, respeita a *Duração da pesquisa* da entidade.
- **Relatório diário** para gestores, no horário escolhido: notas baixas primeiro, depois todas as respostas, agrupadas por pessoa.
- **Aviso imediato de nota baixa**, alguns minutos depois da resposta, com nota de corte e destinatários próprios.
- **Links para os resultados só para quem tem acesso:** quem não pode abrir a pesquisa no GLPI recebe o e-mail sem eles.
- Todos os envios passam pela **fila de e-mails do GLPI** e usam modelos de notificação próprios: Convite, Lembrete, Relatório diário e Aviso de nota baixa.

### Resultados e painel
- Aba **Resultados**:
  - NPS, nota média normalizada (0–10) e distribuição;
  - busca e filtros por data, nota e entidade;
  - detalhe de cada resposta;
  - exportação **CSV** e impressão/PDF.
- **Painel de satisfação** (Gestão):
  - NPS consolidado e evolução mensal;
  - comparação com o período anterior;
  - recortes por pesquisa, entidade, requerente, técnico, grupo técnico e categoria.

### Segurança
- Cada pessoa gerencia **as pesquisas que criou**. O administrador geral gerencia todas.
- **Link público:** token de 256 bits, comparação em tempo constante e bloqueio após tentativas inválidas.
- **Links do e-mail:** assinados, um por destinatário.
- **CSRF** conferido em todas as requisições, no padrão do GLPI 11.

---

## Requisitos

- GLPI **11.x**
- PHP **8.1** ou superior
- E-mail de saída configurado no GLPI (Configurar › Notificações), para convites, lembretes, relatório e aviso
- Ações automáticas do GLPI rodando. Recomenda-se o modo **CLI**, pelo cron do servidor, para os horários serem cumpridos mesmo sem ninguém usando o GLPI.

---

## Instalação

A pasta do plugin precisa se chamar **`npsphere`**.

**Pelo Git:**
```bash
cd /caminho/do/glpi/marketplace      # ou /caminho/do/glpi/plugins
git clone https://github.com/MichaelRodriguesOficial/NPS-GLPi.git npsphere
```

**Pelo ZIP:** extraia o pacote `npsphere-X.Y.Z.zip` dentro de `marketplace/` (ou `plugins/`).

Depois:

1. Em **Configurar › Plugins**, clique em **Instalar** e depois em **Ativar** no NPSphere.
2. Em **Administração › Perfis**, dê os direitos aos perfis:
   - **Pesquisas de satisfação** (`plugin_npsphere_survey`): criar e gerenciar pesquisas;
   - **Painel de satisfação** (`plugin_npsphere_dashboard`): ver o painel consolidado;
   - **Minhas pesquisas** (`plugin_npsphere_answer`): responder pesquisas internas.
3. Confira em **Configurar › Ações automáticas** se estas estão ativas:

| Ação automática | O que faz | Frequência |
|---|---|---|
| `npsphereConvite` | Envia o convite do chamado depois da espera | A cada minuto |
| `npsphereLembrete` | Envia os lembretes | De hora em hora, das 8h às 18h |
| `npsphereRelatorio` | Envia o relatório diário | De hora em hora |
| `npsphereAviso` | Envia o aviso de nota baixa | A cada 5 minutos |
| `queuednotification` *(GLPI)* | Manda a fila de e-mails | Configurada no GLPI |

**Atualizar:** substitua os arquivos (ou `git pull`) e clique em **Atualizar** em Configurar › Plugins. As migrações do banco rodam sozinhas.

---

## Pesquisa vinculada a chamado

1. Crie a pesquisa com o tipo **Vinculada a chamado** e ative-a.
2. Na entidade, em **Assistência › Pesquisa de satisfação**, escolha **Pesquisa externa** e use a URL mostrada pelo plugin na aba da pesquisa:
   ```
   https://SEU-GLPI/marketplace/npsphere/front/ticket.php?tickets_id=[TICKET_ID]&surveys_id=N
   ```
3. O atraso, a porcentagem de chamados e a duração da pesquisa continuam sendo os da entidade, no padrão do GLPI.

Ao encerrar o chamado, a pesquisa aparece no próprio chamado. O e-mail sai depois da espera configurada, e só se o requerente ainda não tiver avaliado.

---

## Estrutura do projeto

```
npsphere/
├── setup.php           # registro do plugin, hooks, versão
├── hook.php            # instalação, migrações e desinstalação
├── src/                # classes (namespace GlpiPlugin\Npsphere)
│   └── QuestionType/   # um arquivo por tipo de pergunta
├── front/              # páginas (formulários, painel, resposta pública)
├── ajax/               # endpoints AJAX (prévias, envio, exportação…)
├── templates/          # Twig
├── public/             # CSS e JavaScript
├── locales/            # traduções (.pot)
├── vendor/             # autoload do Composer (sem dependências externas)
├── docs/               # status do projeto
└── CHANGELOG.md        # histórico de versões
```

**Tabelas:**
- `glpi_plugin_npsphere_surveys`, `_questions`, `_answers`, `_answer_values`, `_targets`, `_completions`, `_invites`, `_deliveries`;
- além da gravação em `glpi_ticketsatisfactions`, que é do GLPI.

---

## Roadmap

| Fase | Status |
|---|---|
| 1–8. Fundação, perguntas, respostas, métricas, regras condicionais, pesquisa externa, interna e de chamado | ✅ |
| 9. Notificações e convites por e-mail | 🟡 em testes finais |
| 10. Painel consolidado | ✅ |
| 11. Acabamentos: exportação ODS/XLSX, exclusões no público-alvo, auditoria, limite de requisições, tradução | ⏳ |

O detalhamento está em [`docs/NPSPHERE-STATUS.md`](docs/NPSPHERE-STATUS.md), e o histórico de cada versão, em [`CHANGELOG.md`](CHANGELOG.md).

---

## Licença

Distribuído sob a **GNU General Public License v3.0 ou posterior**. Veja [`LICENSE`](LICENSE).

## Autor

**Michael Rodrigues** — [@MichaelRodriguesOficial](https://github.com/MichaelRodriguesOficial)
