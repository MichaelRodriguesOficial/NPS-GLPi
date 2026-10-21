<p align="center">
  <img src="logo.png" alt="NPSphere" width="110">
</p>

<h1 align="center">NPSphere for GLPI</h1>

<p align="center">
  <b>Satisfaction and NPS surveys for GLPI 11, answered with one click from the email.</b><br>
  Ask after every ticket, survey your staff or your customers, and follow the results in one dashboard.
</p>

<p align="center">
  <a href="https://github.com/MichaelRodriguesOficial/NPS-GLPi/releases/latest"><img src="https://img.shields.io/github/v/release/MichaelRodriguesOficial/NPS-GLPi?label=release&color=1f93ff" alt="Latest release"></a>
  <img src="https://img.shields.io/badge/GLPI-11.x-2c9e6f" alt="GLPI 11.x">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777bb4" alt="PHP 8.1+">
  <img src="https://img.shields.io/badge/UI-pt__BR%20%7C%20en__GB-informational" alt="Interface languages">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPLv3%2B-blue" alt="License"></a>
  <a href="https://github.com/sponsors/MichaelRodriguesOficial"><img src="https://img.shields.io/badge/sponsor-%E2%9D%A4-db61a2?logo=githubsponsors" alt="Sponsor"></a>
</p>

<p align="center">
  <a href="#screenshots">Screenshots</a> ·
  <a href="#installation">Installation</a> ·
  <a href="#configuration">Configuration</a> ·
  <a href="docs/FAQ.md"><b>User guide &amp; FAQ</b></a> ·
  <a href="#troubleshooting">Troubleshooting</a> ·
  <a href="CHANGELOG.md">Changelog</a> ·
  <a href="#support">Support ☕</a>
</p>

<p align="center">
  <img src="screenshots/demo-email-one-click.gif" width="760" alt="Answering from the email with one click">
  <br><sub>From the email to a recorded answer: click the score, finish the rest, done. No login needed.</sub>
</p>

---

## ✨ Highlights

<table>
<tr>
<td width="50%" valign="top">

### 🎫 After every ticket
Replaces GLPI's native satisfaction survey. The survey shows up **inside the closed ticket**, and the
email only goes out if the requester hasn't answered there (15 minutes by default). The score is
also written to GLPI's own `glpi_ticketsatisfactions`, and GLPI's native email is cancelled, so
nobody gets asked twice.

</td>
<td width="50%" valign="top">

### 📨 One click from the email
The invitation shows the NPS question with clickable scores. The click **records the answer right
away**, without logging in, through a personal signed link. Clicking again changes the answer
instead of duplicating it. Reminders go to whoever hasn't answered yet.

</td>
</tr>
<tr>
<td width="50%" valign="top">

### 👥 Staff and customers too
**Internal** surveys for GLPI users (by entity, profile, group, user or everyone, with
**exceptions** such as "the Finance group, except its manager"), shown in
**My surveys** and, optionally, as a **mandatory** pop-up after login. **External** surveys for people
without an account, by public link or by an email list (CSV import).

</td>
<td width="50%" valign="top">

### 📊 Results that lead to action
NPS, average score, monthly trend and breakdowns by survey, entity, requester, technician, group and
category, exported to **Excel, LibreOffice or CSV**. Managers get a **daily report** and an
**immediate low-score alert**, so they can call the customer the same day.

</td>
</tr>
<tr>
<td width="50%" valign="top">

### 🧩 7 question types, with logic
NPS (0–10), emoticons, stars, yes/no, single choice, multiple choice and free text. **Conditional
questions** appear only for some answers (e.g. "What can we improve?" only for scores up to 6).

</td>
<td width="50%" valign="top">

### ✉️ Emails you can edit
Invitation, reminder, report and alert each have their own template: subject and HTML body, a
**live preview**, ready-made variables and a button to restore the default. Everything goes through
GLPI's own mail queue.

</td>
</tr>
</table>

<a id="screenshots"></a>

## 📸 Screenshots

> The screenshots show the **Brazilian Portuguese** interface; **English** is included too and follows
> each user's language in GLPI. They come from a demo GLPI 11.0.11 with made-up data.

### Building a survey

| Survey list | Survey settings |
|---|---|
| ![Survey list](screenshots/survey-list.png) | ![Survey settings](screenshots/survey-form.png) |
| **Questions**: drag to reorder, edit, duplicate a question | **Preview**: exactly what respondents see, nothing is recorded |
| ![Questions](screenshots/questions.png) | ![Preview](screenshots/preview.png) |

<details>
<summary><b>▶ Adding a question (animated)</b></summary>
<br>
<p align="center"><img src="screenshots/demo-add-question.gif" width="880" alt="Adding a question"></p>
</details>

### Emails (Template tab)

| Invitation, with live preview | Daily report for managers |
|---|---|
| ![Invitation template](screenshots/template-invite.png) | ![Daily report template](screenshots/template-report.png) |
| **Low-score alert** | **Help**: how each email works, automatic actions and variables |
| ![Alert template](screenshots/template-alert.png) | ![Help tab](screenshots/template-help.png) |

<details>
<summary><b>▶ Tour of the Template tab (animated)</b></summary>
<br>
<p align="center"><img src="screenshots/demo-template.gif" width="880" alt="Template tab tour"></p>
</details>

### Answering

| The invitation email | Inside the closed ticket | On a phone (public link) |
|---|---|---|
| ![Invitation email](screenshots/invite-email.png) | ![Survey inside the ticket](screenshots/ticket-survey.png) | <img src="screenshots/mobile-public.png" width="260" alt="Public survey on a phone"> |

### Self-service portal

Users of the **simplified interface** (Self-Service profile) answer without ever leaving it. Internal
surveys are listed under **Plug-ins › My surveys** ("Minhas pesquisas"). A **mandatory** survey pops
up right after login: it can be postponed until the last day, then it must be answered. Ticket
surveys appear inside the requester's own ticket.

| Mandatory survey after login | "My surveys" in the Plug-ins menu |
|---|---|
| ![Mandatory survey pop-up](screenshots/selfservice-mandatory.png) | ![Plug-ins menu](screenshots/selfservice-menu.png) ![My surveys](screenshots/selfservice-my-surveys.png) |

<details>
<summary><b>▶ Login → mandatory survey → answered (animated)</b></summary>
<br>
<p align="center"><img src="screenshots/demo-selfservice.gif" width="880" alt="Self-service mandatory survey"></p>
</details>

### Results

| Survey results | Satisfaction dashboard |
|---|---|
| ![Results](screenshots/results.png) | ![Dashboard](screenshots/dashboard.png) |

<details>
<summary><b>▶ Clicking a row filters the dashboard (animated)</b></summary>
<br>
<p align="center"><img src="screenshots/demo-dashboard.gif" width="880" alt="Filtering the dashboard"></p>
</details>

## 🧭 How it works

The ticket flow, which is the most common one:

```mermaid
sequenceDiagram
    autonumber
    actor T as Technician
    participant G as GLPI
    participant N as NPSphere
    actor R as Requester
    actor M as Manager
    T->>G: Closes the ticket
    G->>N: Satisfaction created (entity set to "external survey")
    N-->>R: Survey shown inside the ticket
    Note over N: Waits 15 min (configurable)
    alt Requester already answered in the ticket
        N->>N: No email
    else Not answered yet
        N->>R: Invitation email (GLPI's native one is cancelled)
        R->>N: Clicks a score in the email (no login)
    end
    N->>G: Score saved in glpi_ticketsatisfactions
    N->>M: Low-score alert (minutes later) and daily report
```

1. When a ticket is closed, GLPI creates its satisfaction survey. With the entity set to **external
   survey** pointing to NPSphere, the survey is shown **inside the ticket**.
2. NPSphere waits a few minutes. If the requester answers in the ticket, no email is sent.
   Otherwise the invitation goes out, in place of GLPI's native notification.
3. The requester clicks a score in the email. The signed link records the answer without login and
   opens the rest of the survey.
4. The score is saved in NPSphere and in GLPI's own satisfaction table. Managers are alerted about
   low scores and get a daily summary.

<a id="installation"></a>

## 📦 Installation

**Requirements**

| | |
|---|---|
| **GLPI** | 11.x |
| **PHP** | 8.1+ |
| **Outgoing email** | Configured in GLPI (**Setup › Notifications**) |
| **Automatic actions** | Running, preferably in **CLI** mode (server cron) so schedules are met even when nobody is using GLPI |

**Steps**

1. Download `npsphere-x.y.z.zip` from the [latest release](https://github.com/MichaelRodriguesOficial/NPS-GLPi/releases/latest).
2. Extract it into GLPI's `marketplace/` (or `plugins/`) folder. The zip contains an `npsphere/` directory.
3. Go to **Setup › Plugins**, then install and enable **NPSphere**.
4. Give the rights to the profiles (next section).

<details>
<summary><b>Installing from Git</b></summary>

The folder must be named `npsphere`:

```bash
cd /path/to/glpi/marketplace        # or /path/to/glpi/plugins
git clone https://github.com/MichaelRodriguesOficial/NPS-GLPi.git npsphere
```
</details>

<details>
<summary><b>Updating to a new version</b></summary>

Replace the folder in one step, so GLPI never sees it half-copied:

```bash
cd /path/to/glpi                         # the folder that contains marketplace/
unzip -q npsphere-x.y.z.zip -d ../npsphere-new
mv marketplace/npsphere ../npsphere-old && mv ../npsphere-new/npsphere marketplace/npsphere
rm -rf ../npsphere-old ../npsphere-new
```

Then open **Setup › Plugins** and click **Update**. Database migrations run by themselves. Upgrades
have been tested from 0.50.0 onwards: the resulting database is identical to a fresh install.

</details>

<a id="configuration"></a>

## ⚙️ Configuration

**1. Rights** (**Administration › Profiles**, NPSphere section)

| Right | What it allows |
|---|---|
| **Pesquisas de satisfação** (`plugin_npsphere_survey`) | Create and manage surveys. Each person manages the surveys they created; general administrators manage all of them. |
| **Painel de satisfação** (`plugin_npsphere_dashboard`) | See the consolidated dashboard (**Management › Painel de satisfação**). |
| **Minhas pesquisas** (`plugin_npsphere_answer`) | Answer internal surveys (**My surveys**). |

**2. Automatic actions** (**Setup › Automatic actions**)

| Automatic action | What it does | When it runs |
|---|---|---|
| `npsphereConvite` | Sends the ticket invitation after the wait | Every minute |
| `npsphereLembrete` | Sends the reminders | Hourly, 8 a.m. to 6 p.m. |
| `npsphereRelatorio` | Sends the daily report | Hourly |
| `npsphereAviso` | Sends the low-score alert | Every 5 minutes |
| `queuednotification` *(GLPI)* | Delivers the mail queue (every email above goes through it) | Set in GLPI |

**3. Ticket surveys only:** in the entity, **Assistance › Satisfaction survey**, choose **External
survey** and paste the URL shown in the survey's own tab:

```
https://YOUR-GLPI/marketplace/npsphere/front/ticket.php?tickets_id=[TICKET_ID]&surveys_id=N
```

The delay, the share of tickets that get a survey and the survey duration remain GLPI's own entity
settings.

<details>
<summary><b>All survey options</b></summary>

| Where | Option | Default |
|---|---|---|
| **Survey** | Type: internal, external or ticket | internal |
| | Active, start and end dates | inactive, no dates |
| | One page or one question per step | one page |
| | Allow several answers per person | no |
| | Mandatory (internal: pop-up after login) | no |
| | Anonymous (external: same link for everyone) | no |
| **Template › Invitation** | Wait for the in-ticket answer before emailing (0, 5, 10, 15, 30, 60 min) | 15 min |
| | Subject and HTML body | built-in template |
| **Template › Reminder** | On/off, every N days without an answer, at most N reminders | off, 3 days, 2 |
| **Template › Report** | On/off, sending hour, low-score cut-off, users and groups | off, 6 p.m., up to 6 |
| **Template › Alert** | On/off, wait (5–60 min), low-score cut-off, users and groups | off, 10 min, up to 6 |

</details>

<details>
<summary><b>Rate limits on the pages without login</b></summary>

Per IP address, with a sliding window. The defaults are generous, because a whole company often
answers from the same IP right after a mass invitation. To change them, add to GLPI's
`config/local_define.php`:

```php
define('NPSPHERE_LIMITE_PAGINA', 240); // opening a survey, per minute
define('NPSPHERE_LIMITE_ENVIO', 120);  // saving an answer (form or email click), per minute
define('NPSPHERE_LIMITE_FALHA', 20);   // invalid links/keys/tokens, per 10 minutes
```

`0` turns that limit off. Over the limit, the visitor sees "Please wait a moment" (HTTP 429). Behind
a reverse proxy, every visitor shares the proxy's IP: raise the limits, or have the web server pass
the real client IP in `REMOTE_ADDR`.

</details>

## 📖 User guide & FAQ

Step-by-step instructions for every task live in **[docs/FAQ.md](docs/FAQ.md)**, including:

- [Create a survey and its questions](docs/FAQ.md#how-do-i-create-a-survey)
- [Survey people after every ticket](docs/FAQ.md#how-do-i-survey-requesters-after-every-ticket)
- [Survey staff, and make it mandatory](docs/FAQ.md#how-do-i-survey-my-staff)
- [Survey customers who have no GLPI account](docs/FAQ.md#how-do-i-survey-customers-without-a-glpi-account)
- [Customize the emails and use variables](docs/FAQ.md#how-do-i-customize-the-emails)
- [Reminders, daily report and low-score alert](docs/FAQ.md#how-do-reminders-work)
- [Read the results and export them](docs/FAQ.md#where-do-i-see-the-results)

## 🔐 Security

| Concern | How it's handled |
|---|---|
| **Who manages what** | Each person sees and edits only the surveys they created (listing, form, questions, results, exports, previews). General administrators see all of them. |
| **Answering without login** | Every email link is personal and **signed** (survey + person or ticket). A tampered link is rejected. Opening a link records nothing: the answer is saved by a script in the respondent's browser, so email security scanners that open links don't create fake answers. |
| **Public link** | 256-bit token, compared in constant time. |
| **Rate limiting** | Every page that opens without login (public link and email links) has a per-IP limit, using the same Symfony RateLimiter GLPI uses for two-factor codes, stored in GLPI's cache. Invalid links, keys or tokens count separately: after 20 in 10 minutes the IP is blocked on all those pages until the window passes. Clearing cookies doesn't reset it. Limits can be changed in `config/local_define.php` (see [Configuration](#configuration)). |
| **History** | Survey, question and target audience changes are recorded in GLPI's own **Historical** tab. |
| **CSRF** | Every POST carries a fresh GLPI CSRF token, including the session-less answer pages. |
| **Anonymous surveys** | No identity is stored, and there are no reminders (finding out who answered would break anonymity). |
| **Links to results** | In reports and alerts, "View answer" and "Open results" go only to recipients who can open the survey in GLPI. Everyone else gets the same email without those links. |
| **Ticket answers** | Only the requester can rate a ticket, once per ticket. |
| **Uninstall** | Plugin tables, automatic actions, templates and settings are removed. Scores already written to GLPI's own satisfaction table stay. |

<a id="troubleshooting"></a>

## 🛠️ Troubleshooting

<details>
<summary><b>Ticket invitations are not sent</b></summary>

Check the entity (**Assistance › Satisfaction survey**). It must be set to **External survey** with the
NPSphere URL; with "Internal survey", GLPI sends its own email instead. With a wait configured, the
`npsphereConvite` automatic action must be enabled. The survey's **Template › Invitation** tab shows a
warning when any of this is wrong.
</details>

<details>
<summary><b>Emails stay in the queue</b></summary>

They are delivered by GLPI's `queuednotification` automatic action, using GLPI's mail settings. Check
**Setup › Notifications › Queued notifications** and run automatic actions in **CLI** mode.
</details>

<details>
<summary><b>Links in the email point to the wrong address</b></summary>

Links are built from GLPI's **URL of the application** (**Setup › General**, or the entity's own
URL). Set it to the address people use to reach GLPI.
</details>

<details>
<summary><b>The daily report didn't arrive</b></summary>

It is sent on the first run of `npsphereRelatorio` after the chosen hour, and **only on days with
answers**. Check that the recipients are active and have an email address. The "last sent" field only
changes when an email is actually sent.
</details>

<details>
<summary><b>The low-score alert didn't arrive</b></summary>

Only answers given **after** the alert was turned on count. The alert arrives between the chosen wait
and 5 minutes after it. Each answer triggers at most one alert.
</details>

<details>
<summary><b>"We couldn't record your answer automatically"</b></summary>

Update to 0.64.1 or later. In earlier versions, people who weren't logged in could lose the click
from the second answer on in the same browser.
</details>

<details>
<summary><b>Where are the errors logged?</b></summary>

In GLPI's `files/_log/` folder (`php-errors.log` and `npsphere.log`). Each automatic action also logs
what it did in **Setup › Automatic actions › (action) › Logs**.
</details>

<details>
<summary><b>Why GLPI 11 only?</b></summary>

GLPI 11 changed the plugin bootstrap, routing and firewall (needed for the session-less answer
pages). Supporting GLPI 10 too would mean two plugin architectures, so installation requires 11.0.0
or later.
</details>

## 🧪 Development

<details>
<summary><b>File structure</b></summary>

```
npsphere/
├── setup.php           Plugin metadata, hooks, public (session-less) routes
├── hook.php            Install, migrations, uninstall
├── src/                Classes (namespace GlpiPlugin\Npsphere)
│   └── QuestionType/   One class per question type
├── front/              Pages: forms, dashboard, answer pages
├── ajax/               Previews, sending, exports, one-click answers
├── templates/          Twig
├── public/             CSS and JavaScript
├── locales/            Translations (.pot, .po, .mo)
├── tools/              locales.py: extract, update and compile translations (not in the release zip)
├── vendor/             Composer autoload (no external dependencies)
├── docs/               User guide, project status, Pix QR code
└── screenshots/        Images used in this README (not in the release zip)
```

Database tables: `glpi_plugin_npsphere_surveys`, `_questions`, `_answers`, `_answervalues`,
`_targets`, `_completions`, `_invites`, `_deliveries`, plus the score written to GLPI's
`glpi_ticketsatisfactions`.

</details>

The project status, decisions and roadmap are in [docs/NPSPHERE-STATUS.md](docs/NPSPHERE-STATUS.md)
(in Portuguese).

<details>
<summary><b>Translations</b></summary>

The source texts are in Brazilian Portuguese, and English (`en_GB`, `en_US`) is included. GLPI picks
the language from each user's preferences; the public link follows the visitor's browser.

To add a language, copy `locales/en_GB.po` to `locales/<code>.po` (for example `es_ES.po`), translate
the `msgstr` lines and compile:

```bash
python3 tools/locales.py extract   # refresh locales/npsphere.pot from the code
python3 tools/locales.py update    # add new texts to every .po
python3 tools/locales.py compile   # build the .mo files GLPI reads
```

`pt_BR.po` and `pt_PT.po` stay untranslated on purpose: the code already is the Portuguese text, and
the files only exist so GLPI doesn't fall back to English for Portuguese users.

</details>

<a id="support"></a>

## ☕ Support the project

<div align="center">

**Enjoying the plugin?** It's an independent, open-source project, built and maintained with care so
that IT teams can hear from the people they serve without leaving GLPI. If it saves your team time,
you can buy me a coffee: it helps keep the plugin up to date with new GLPI versions. It's completely
optional. A ⭐ on the repository, a bug report, an idea or a pull request help just as much.

**Curtiu o plugin?** É um projeto independente e de código aberto. Se ele facilita o dia a dia da sua
equipe, você pode me pagar um café 💚. Isso ajuda a manter o plugin atualizado com as novas versões
do GLPI. É totalmente opcional: uma ⭐ no repositório, um relato de bug, uma ideia ou um PR ajudam
tanto quanto.

<table>
<tr>
<th width="50%">🌎 From anywhere</th>
<th width="50%">🇧🇷 Do Brasil, via Pix</th>
</tr>
<tr>
<td align="center" valign="middle">

<a href="https://github.com/sponsors/MichaelRodriguesOficial"><img src="https://img.shields.io/badge/Sponsor%20on%20GitHub-%E2%9D%A4-db61a2?style=for-the-badge&logo=githubsponsors&logoColor=white" alt="Sponsor on GitHub"></a>

<sub>One-time or monthly, any amount.<br>GitHub charges no fees on personal sponsorships.</sub>

</td>
<td align="center" valign="middle">

<img src="docs/pix-qrcode.png" alt="QR code Pix" width="180"><br>
<sub>Aponte a câmera do app do seu banco</sub>

<details>
<summary><sub>Pix copia e cola</sub></summary>

```
00020126890014br.gov.bcb.pix01363d43ffaa-4b56-45ed-b038-ba7407c0a9750227Cafe para o plugin NPSphere5204000053039865802BR5917MICHAEL G R SILVA6009SAO PAULO62070503***6304201C
```

</details>

</td>
</tr>
</table>

</div>

## 📄 License

[GPL-3.0-or-later](LICENSE). Made by **Michael Rodrigues** ·
[github.com/MichaelRodriguesOficial](https://github.com/MichaelRodriguesOficial)
