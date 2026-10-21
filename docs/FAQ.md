# NPSphere — User guide & FAQ

How to use every part of the plugin, step by step. The plugin's screens are in Brazilian Portuguese,
so each step gives the label you'll see on screen *(in italics)*.

[← Back to the README](../README.md)

**Getting started**
- [How do I create a survey?](#how-do-i-create-a-survey)
- [Which question types are there?](#which-question-types-are-there)
- [How do I show a question only for some answers?](#how-do-i-show-a-question-only-for-some-answers)
- [How do I check what respondents will see?](#how-do-i-check-what-respondents-will-see)

**Who answers**
- [How do I survey requesters after every ticket?](#how-do-i-survey-requesters-after-every-ticket)
- [How do I survey my staff?](#how-do-i-survey-my-staff)
- [How do I make a survey mandatory?](#how-do-i-make-a-survey-mandatory)
- [How do I survey customers without a GLPI account?](#how-do-i-survey-customers-without-a-glpi-account)
- [Does it work in the self-service portal?](#does-it-work-in-the-self-service-portal)

**Emails**
- [How does answering from the email work?](#how-does-answering-from-the-email-work)
- [How do I customize the emails?](#how-do-i-customize-the-emails)
- [Which variables can I use?](#which-variables-can-i-use)
- [Why do I see the warning "Perguntas dentro do e-mail"?](#why-do-i-see-the-warning-perguntas-dentro-do-e-mail)
- [How do reminders work?](#how-do-reminders-work)
- [How does the daily report work?](#how-does-the-daily-report-work)
- [How does the low-score alert work?](#how-does-the-low-score-alert-work)
- [Who gets the links to the results?](#who-gets-the-links-to-the-results)

**Results**
- [Where do I see the results?](#where-do-i-see-the-results)
- [How are the NPS and the score calculated?](#how-are-the-nps-and-the-score-calculated)
- [How do I export the answers?](#how-do-i-export-the-answers)
- [Where do I see what changed in a survey?](#where-do-i-see-what-changed-in-a-survey)

**Other questions**
- [How do I clone (duplicate) a survey?](#how-do-i-clone-duplicate-a-survey)
- [Can a person answer more than once?](#can-a-person-answer-more-than-once)
- [What happens when a ticket is reopened?](#what-happens-when-a-ticket-is-reopened)
- [Can I have different ticket surveys per entity?](#can-i-have-different-ticket-surveys-per-entity)
- [Who can see and edit a survey?](#who-can-see-and-edit-a-survey)
- [Can I use it in English?](#can-i-use-it-in-english)

---

## Getting started

### How do I create a survey?

1. Go to **Administration › Satisfaction surveys** *(Administração › Pesquisas de satisfação)* and
   click **Add**.
2. Fill in the **name** and choose the **type**:
   - **Internal** *(Interna)*: answered by GLPI users;
   - **External** *(Externa)*: answered by anyone, through a public link or an email list;
   - **Ticket** *(Vinculada a chamado)*: answered by the requester when the ticket is closed.
3. Optionally set a start and end date, the **display mode** (one page or one question per step) and
   whether a person may answer more than once.
4. Save. The survey starts **inactive**: turn **Active** *(Ativo)* on when it's ready.
5. Open the **Questions** tab *(Perguntas)* and click **Add question** *(Adicionar pergunta)*. Type the
   question, choose its type and save. Drag the handle (⠿) to change the order.

![Survey settings](../screenshots/survey-form.png)

![Adding a question](../screenshots/demo-add-question.gif)

### Which question types are there?

| Type | On screen | Counts in the score? |
|---|---|---|
| **NPS** | 0 to 10 (or 0 to 5), as numbers | Yes, and in the NPS |
| **Emoticons** | 3 or 5 faces | Yes (converted to 0–10) |
| **Stars** | 3, 5 or 10 stars | Yes (converted to 0–10) |
| **Yes / No** | Two buttons, optionally "I don't know" | No |
| **Single choice** | One option from a list | No |
| **Multiple choice** | Several options from a list | No |
| **Text** | Short or long free text | No |

### How do I show a question only for some answers?

When editing a question, use **Conditional display** *(Exibição condicional)*: pick an earlier question,
an operator (=, ≠, <, ≤, >, ≥) and a value. Example: show "What can we improve?" only when the NPS is
**≤ 6**. Hidden questions are skipped and don't count as unanswered. The same rule is checked again
on the server.

### How do I check what respondents will see?

Open the **Preview** tab *(Visualizar)*. It shows the survey exactly as respondents see it, conditional
questions included. Nothing answered there is recorded.

![Preview](../screenshots/preview.png)

---

## Who answers

### How do I survey requesters after every ticket?

1. Create a survey of type **Ticket**, add the questions and activate it.
2. Open the entity (**Administration › Entities › your entity › Assistance**). Under **Satisfaction
   survey**, choose **External survey** and paste the URL shown by NPSphere in the survey's own tab,
   for example:
   ```
   https://YOUR-GLPI/marketplace/npsphere/front/ticket.php?tickets_id=[TICKET_ID]&surveys_id=1
   ```
3. In the same place, GLPI keeps its own settings: the delay after closing, the share of tickets
   that get a survey and how long the survey stays open.
4. In the survey's **Template › Invitation** tab, choose how long to wait for an answer inside the
   ticket before emailing *(Esperar a avaliação no chamado…)*: 0, 5, 10, **15** (default), 30 or 60
   minutes.

From then on, when a ticket is closed:
- the survey appears **inside the ticket** for the requester;
- if they don't answer there, the invitation email goes out after the wait;
- GLPI's native satisfaction email is cancelled, so they're not asked twice;
- the score is also saved in GLPI's satisfaction table.

The **Template › Invitation** tab shows a warning if the entity isn't set up correctly or if the
automatic action is disabled.

![Survey inside the ticket](../screenshots/ticket-survey.png)

### How do I survey my staff?

1. Create a survey of type **Internal**.
2. In the **Target audience** tab *(Público-alvo)*, choose who answers: entities, profiles, groups,
   specific users, or **everyone**. Sub-entities are taken into account.
   - To leave someone out, add them under **Exceptions** *(Exceções)* on the same tab, for example
     "the Finance group, except its manager" or "everyone, except the IT group". An exception always
     wins: that person doesn't see the survey, isn't asked by the mandatory pop-up and doesn't get the
     invitation email.
3. Activate it. Users with the **My surveys** right *(Minhas pesquisas)* see it under **Tools › My
   surveys**, or **Plug-ins › My surveys** in the self-service portal.
4. To also email them, go to **Template › Invitation** and click **Send invitations** *(Enviar
   convites)*. The card at the top shows who will receive it, who already answered and who has no email
   address.

### How do I make a survey mandatory?

In the survey settings, set **Mandatory answer** *(Resposta obrigatória)* to yes and choose an **end date**. Every user in
the target audience who hasn't answered gets a pop-up right after login:

- until the day before the end date, they can postpone it (**Answer later**);
- on the last day, the pop-up can't be closed until they answer.

![Mandatory survey after login](../screenshots/selfservice-mandatory.png)

### How do I survey customers without a GLPI account?

1. Create a survey of type **External**.
2. Choose whether it's **anonymous** *(Pesquisa anônima)*:
   - **Anonymous**: everyone uses the same link, and no identity is stored.
   - **Identified**: the respondent fills in name, email and phone before sending, and the same email
     or phone can't answer twice.
3. Share it:
   - through the **public link** shown in the survey, on your site, WhatsApp or a QR code;
   - or by email: in **Template › Invitation › Recipients** *(Destinatários)*, add people one by one or
     import a CSV (`email;name` per line), then click **Send invitations**.

The answer page works on phones too.

<p align="center"><img src="../screenshots/mobile-public.png" width="300" alt="Public survey on a phone"></p>

### Does it work in the self-service portal?

Yes. Users with the simplified interface can do everything a respondent needs:

- **Internal surveys** are listed under **Plug-ins › My surveys** *(Minhas pesquisas)*, with the
  deadline of each one.
- **Mandatory surveys** pop up after login (see above).
- **Ticket surveys** appear inside the requester's own ticket.
- **Links from the email** work too.

Managing surveys needs the central interface: the self-service profile can only be given the right
to *answer*.

| Plug-ins menu | My surveys |
|---|---|
| ![Plug-ins menu](../screenshots/selfservice-menu.png) | ![My surveys](../screenshots/selfservice-my-surveys.png) |

![Login, mandatory survey, answered](../screenshots/demo-selfservice.gif)

---

## Emails

### How does answering from the email work?

The invitation shows the first rating question with clickable options.

- **The click records the answer.** Clicking a score opens the survey and records that answer right
  away, without logging in. The respondent then answers the remaining questions and clicks **Send**.
- **Each link is personal.** It's signed for that person or ticket; a tampered link is rejected.
- **Opening a link saves nothing.** A script in the respondent's browser saves the answer. That way,
  email security scanners that open every link don't create fake answers.
- **Clicking another score changes the answer** instead of creating a second one, unless the survey
  allows several answers per person.

![Answering from the email](../screenshots/demo-email-one-click.gif)

### How do I customize the emails?

Open the survey's **Template** tab. Each email has its own sub-tab: **Invitation** *(Convite)*,
**Reminder** *(Lembrete)*, **Report** *(Relatório)* and **Alert** *(Aviso)*. The **Help** sub-tab
*(Ajuda)* explains the rules of each one.

- **Subject:** leave it blank to use the default (shown in grey).
- **Message:** the HTML is on the right, and a **live preview** on the left uses real data (the
  latest closed ticket, or the first recipient).
- **Insert a variable:** click it under the editor; hover to see what it means.
- **↺** restores the default template.
- The **ⓘ** icons explain each field, and **Como funciona** opens the Help sub-tab on the right topic.

![Template tab](../screenshots/demo-template.gif)

### Which variables can I use?

Write them in the subject or the message; each one is replaced for every recipient. The full list,
with an example from your own survey, is in **Template › Help › Variables**.

| Variable | Invitation and reminder |
|---|---|
| `{{pesquisa}}` | Survey name |
| `{{destinatario}}` | Recipient's name (internal and external) |
| `{{link}}` | Survey address (text) |
| `{{botao}}` | "Answer the survey" button |
| `{{chamado_id}}`, `{{chamado_titulo}}` | Ticket number and title (ticket surveys) |
| `{{requerente}}`, `{{tecnico}}` | Requester and assigned technician (ticket surveys) |
| One per question | The question with clickable options, named after its text (e.g. `{{de_0_a_10_quanto_recomendaria}}`) |

The report and the alert have their own variables, including ready-made blocks such as
`{{numeros}}`, `{{notas_baixas}}` and `{{respostas_por_pessoa}}`. A variable written in the wrong email
stays as plain text.

### Why do I see the warning "Perguntas dentro do e-mail"?

It means the survey has **more than one question** and the email shows one of them with clickable
options. Many people click the score in the email and stop there, so the other questions go
unanswered. With several questions, prefer the `{{botao}}` button, which takes the person straight to
the full survey. The built-in ticket template shows the first question, so the warning appears as
soon as a second question is added.

### How do reminders work?

Turn them on in **Template › Reminder**:

- **Who gets them:** whoever received the invitation and hasn't answered yet.
- **How often:** every *N* days without an answer, counted from the invitation and then from the
  last reminder, up to *N* reminders per person.
- **Ticket surveys:** they also stop when the survey expires (GLPI's "survey duration" in the entity).
- **Not available:** in anonymous surveys, because the plugin can't know who answered.
- **Same link:** the reminder uses the invitation's link, so a click in the reminder records the
  answer the same way.

### How does the daily report work?

Turn it on in **Template › Report** and pick:
- the **sending hour**;
- the **low-score cut-off** (default: up to 6);
- the **users and groups** who receive it.

Once a day it sends, first, the answers with a low score, then every answer grouped by person, each
with a link to open it in GLPI. Days without answers send nothing.

![Daily report template](../screenshots/template-report.png)

### How does the low-score alert work?

Turn it on in **Template › Alert**. When an answer at or below the cut-off arrives, the chosen people
get a short email, so someone can call the customer the same day.

- **When it's sent:** a few minutes after the answer (5 to 60 minutes, default 10), so the comment
  typed after the click is included.
- **What it uses:** its own recipients and cut-off, independent from the report.
- **How many:** at most one alert per answer.
- **Which answers:** only answers given after the alert was turned on.

### Who gets the links to the results?

Reports and alerts go to everyone selected. The **View answer** and **Open results** links go only to
people who can open the survey in GLPI: a central-interface profile covering the survey's entity,
read access to satisfaction surveys, and being the survey's creator or a general administrator. The
others get the same email without those links. Each card in the **Report** and **Alert** sub-tabs
shows who gets them.

---

## Results

### Where do I see the results?

- **One survey:** the **Results** tab *(Resultados)* shows:
  - the NPS, the average score and the distribution;
  - the list of respondents, with search and filters by date, score and entity;
  - the full detail of each answer.
- **All surveys:** **Management › Satisfaction dashboard** *(Gerência › Painel de satisfação)* shows:
  - the NPS and its monthly trend;
  - a comparison with the previous period;
  - breakdowns by survey, entity, requester, technician, technician group and category.
  
  Click a row to filter the whole dashboard by it.

![Survey results](../screenshots/results.png)

![Filtering the dashboard](../screenshots/demo-dashboard.gif)

### How are the NPS and the score calculated?

- **NPS** uses the NPS questions: 9–10 are promoters, 7–8 passives, 0–6 detractors. NPS = % promoters
  − % detractors, from −100 to +100.
- **Score of an answer** is the average of its rated questions (NPS, stars and emoticons, all
  converted to 0–10). It's the "Nota" column in Results and the value compared with the cut-offs.
- The Results tab and the dashboard use the same calculation, so the numbers always match.
- Fewer than 5 answers are flagged as a small sample.

### How do I export the answers?

In **Results**, click **Export** *(Exportar)*. The active filters (dates, score, entity) are applied
to every format:

- **Excel (.xlsx)** or **LibreOffice (.ods)**: one file with two sheets, **Overview** and **Answers**.
  Scores are real numbers and dates are real dates, so you can sort, filter and build charts.
- **CSV**: overview or answers, separated by the delimiter set in your GLPI preferences.
- **Print / PDF** of the overview.

The **Satisfaction dashboard** has the same Excel, LibreOffice and CSV options, with the dashboard's
filters.

### Where do I see what changed in a survey?

In the survey's **Historical** tab *(Histórico)*, the same one every GLPI item has. It lists who changed
what and when:

- the survey's own settings and email templates;
- questions added, changed, deleted or reordered;
- people, groups, profiles and entities added to or removed from the target audience and the
  exceptions.

---

## Other questions

### How do I clone (duplicate) a survey?

The same way as anything else in GLPI:

- **From the list:** tick the survey in **Administration › Satisfaction surveys**, click **Actions**
  *(Ações)*, choose **Clone** *(Clonar)* and confirm.
- **From the survey itself:** click **Actions** *(Ações)* at the top of the survey and choose **Clone**.

The copy is named "*… (cópia)*".
- **It gets:** the settings, the questions (with their conditional rules) and the email templates.
- **It doesn't get:** the target audience, the answers, the invitations and the sending history.
- It starts **inactive** with a new public link. Review it, set who answers, and activate it.

### Can a person answer more than once?

Only if **Allow several answers** *(Permitir múltiplas respostas)* is on. Otherwise a second click
in the email replaces the first answer. A ticket can always be rated only once, and only by its
requester.

### What happens when a ticket is reopened?

GLPI deletes the pending satisfaction survey, so a scheduled invitation for that ticket is cancelled.
When the ticket is closed again, the cycle starts over.

### Can I have different ticket surveys per entity?

Yes. Each entity points to one survey with `&surveys_id=N` in its satisfaction URL. A survey applies
to its own entity and, when recursive, to its sub-entities.

### Who can see and edit a survey?

Whoever has the **Satisfaction surveys** right manages the surveys **they created**. General
administrators (with the right to change GLPI's configuration) manage all of them. Answer pages
aren't restricted this way, since respondents aren't the survey's creators.

### Can I use it in English?

Yes. The plugin follows each user's language in GLPI (**My settings › Language**). Brazilian Portuguese
and English are included. Pages opened without login (the public link and the email links) follow the
visitor's browser language.

What you write yourself (survey names, questions and email templates) stays as you wrote it. Dates and
numbers follow the date and number formats in GLPI's preferences.

Emails that still use the built-in template come in the language GLPI is using when they're built:
the automatic actions (ticket invitations, reminders, report, alert) use GLPI's default language
(**Setup › General › Default language**), and **Send invitations** uses yours.

---

Still stuck? Check [Troubleshooting](../README.md#troubleshooting) or
[open an issue](https://github.com/MichaelRodriguesOficial/NPS-GLPi/issues).
