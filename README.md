# StuCvBuilder

**A multilingual Symfony platform to publish, customize, and operate a professional online CV — with recruiter tracking, secure admin tooling, and encrypted backup/restore.**

Not a static portfolio page: a full product with public CV rendering, employment targeting, visit analytics, i18n, SEO, and production-minded security.

| | |
|---|---|
| **Runtime** | PHP 8.2+, Symfony 8, Doctrine 3 |
| **Quality** | PHPUnit 11, PHPStan 2, compliance guardrails |
| **Docs** | Doxygen headers, service-oriented architecture |

---

## At a glance

<p align="center">
  <img src="docs/screenshots/home-landing.png" alt="Home landing page" width="900">
</p>

Customizable home landing with hero, quick tiles, and one-click access to the public CV.

---

## Why this project stands out

Most CV projects stop at a public page. StuCvBuilder goes further:

| Area | What it delivers |
|------|------------------|
| **Product** | Public CV, customizable home landing, admin dashboard, recruiter module |
| **Engineering** | Thin controllers, domain services, contracts, migrations, structured tests |
| **Security** | Email TOTP login, trusted devices, invitations, role governance, content sanitization |
| **Operations** | Encrypted `.cvbackup` export/import, reset with pre-backup, IP policy hooks |
| **Reach** | 5 locales (FR, EN, DE, LT, NO), SEO meta, sitemap, structured data |
| **Recruiter workflow** | Tracked companies, per-company CV overrides, stamped PDFs, visit analytics |

---

## Screenshots

### Public CV

Localized online CV with sidebar navigation, skills tree, downloadable PDF, and section-based layout.

<p align="center">
  <img src="docs/screenshots/cv-public.png" alt="Public CV" width="900">
</p>

### Admin onboarding & global CV editor

First-time setup checklist (5/6), tabbed CV sections, identity fields, and `[[cv.*]]` token-based page titles per language.

<p align="center">
  <img src="docs/screenshots/cv-customization.png" alt="Admin CV customization" width="900">
</p>

### Recruiter-targeted CV customization

Per-company Web CV overrides with section navigation, recruiter preview, and progress tracking (`0 / 12 customized sections`).

<p align="center">
  <img src="docs/screenshots/admin-dashboard.png" alt="Per-company CV customization" width="900">
</p>

### Visit analytics (connection log)

Filter visits by type, company, gate status, and date — distinguish random vs recruiter traffic and drill into each event.

<p align="center">
  <img src="docs/screenshots/recruiter-module.png" alt="Connection log and visit analytics" width="900">
</p>

### Encrypted backup & restore

Export or restore the full customization state (home, CV, languages, employment module, assets) as an encrypted `.cvbackup` archive. Sensitive actions require password + email TOTP.

<p align="center">
  <img src="docs/screenshots/backup-restore.png" alt="Backup and restore" width="900">
</p>

### Passwordless login (TOTP)

Email-based second-step verification — no classic password on the public login path.

<p align="center">
  <img src="docs/screenshots/login-totp.png" alt="TOTP second-step verification" width="420">
</p>

---

## Technical highlights

- **Service-oriented Symfony architecture** — business logic in `src/Service/`, thin controllers, Doctrine repositories
- **Multilingual by design** — configurable active locales, translated admin and public surfaces
- **Defense in depth** — HTML/CSS/SVG sanitization, CSRF on admin actions, rate limiting, captcha gate on CV access
- **Compliance tests** — guardrails against reintroducing legacy demo/personal seeds (`composer test:compliance`)
- **SEO-ready** — canonical URLs, hreflang, Open Graph, JSON-LD, `robots.txt`, dynamic sitemap
- **Document pipeline** — TCPDF/FPDI for stamped recruiter PDFs with caching and variant management

---

## Architecture

```mermaid
flowchart LR
    subgraph Public
        Home[Landing]
        CV[CV pages]
        SEO[Sitemap / SEO]
    end

    subgraph Admin
        Dash[Dashboard]
        Editor[CV editor]
        Emp[Recruiter module]
        Backup[Backup / restore]
    end

    subgraph Core
        Ctrl[Controllers]
        Svc[Domain services]
        ORM[Doctrine ORM]
    end

    Home --> Ctrl
    CV --> Ctrl
    SEO --> Ctrl
    Dash --> Ctrl
    Editor --> Ctrl
    Emp --> Ctrl
    Backup --> Ctrl
    Ctrl --> Svc --> ORM
```

**Key domains**

| Path | Responsibility |
|------|----------------|
| `src/Service/Cv/` | CV sections, resolution, admin updates |
| `src/Service/Employment/` | Companies, documents, visits, PDF stamping |
| `src/Service/Customization/` | Backup/restore, home customization |
| `src/Service/Auth/` | TOTP, invitations, trusted devices |
| `src/Service/Admin/` | Users, roles, hard delete |
| `src/Service/Site/` | SEO, sitemap, mail templates, configuration |

---

## Stack

| Layer | Technology |
|-------|------------|
| Runtime | PHP 8.2+ |
| Framework | Symfony 8.0 |
| ORM | Doctrine 3 |
| Templates | Twig |
| PDF | TCPDF / FPDI |
| Security | Symfony Security, NelmioSecurityBundle, HTML sanitizer |
| Tests & static analysis | PHPUnit 11, PHPStan 2 |

---

## Quick start

### Requirements

- PHP 8.2+ (`ctype`, `iconv`, `gd`)
- Composer 2
- MariaDB 10.6+ or MySQL 8+
- Optional: SMTP or MailHog for TOTP / invitation emails

### Install

```bash
git clone <repository-url> StuCvBuilder
cd StuCvBuilder
composer install
cp .env.exemple .env
```

[`.env.exemple`](.env.exemple) is the committed template — copy it to `.env` (gitignored) and set at least:

| Variable | Action |
|----------|--------|
| `APP_SECRET` | Random string (Symfony secret) |
| `DATABASE_URL` | Your MariaDB/MySQL connection |
| `APP_TOTP_EMAIL_FROM` | Sender for auth emails |
| `APP_CV_CONTACT_TO` | CV contact form recipient |
| `APP_FILE_ENCRYPTION_KEY` | Long random string |
| `APP_CUSTOMIZATION_BACKUP_ENCRYPTION_KEY` | `openssl rand -base64 32` |

Minimal `.env` after copy:

```dotenv
APP_SECRET=your-secret
DATABASE_URL="mysql://user:pass@127.0.0.1:3306/stu_cv_builder?serverVersion=11.4.9-MariaDB&charset=utf8mb4"
MAILER_DSN=smtp://127.0.0.1:1025
APP_TOTP_EMAIL_FROM=no-reply@example.com
APP_CV_CONTACT_TO=you@example.com
APP_FILE_ENCRYPTION_KEY=your-file-encryption-key
APP_CUSTOMIZATION_BACKUP_ENCRYPTION_KEY=base64-key-from-openssl-rand
```

For MailHog or Mailpit in development, keep `MAILER_DSN=smtp://127.0.0.1:1025`. See [`.env.exemple`](.env.exemple) for the full variable list and comments.

Generate backup encryption key:

```bash
openssl rand -base64 32
```

Create database and run migrations:

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

Start the dev server:

```bash
symfony server:start
# or: php -S 127.0.0.1:8000 -t public
```

### Local Apache without TLS certificate

`public/.htaccess` enforces HTTPS on production hosts. Local dev hosts are excluded automatically:

- `localhost` (any port)
- `127.0.0.1` (any port)
- `*.loc` (example: `http://cv3.loc:90`)

Use plain HTTP locally: `http://cv3.loc:90` (not `https://`).

If Firefox still upgrades to HTTPS from a previous visit, clear HSTS for that host (`about:config` → delete `cv3.loc` entries, or test in a private window).

Optional full local override (disables HTTPS redirect on every host):

```bash
cp public/.htaccess.local public/.htaccess
```

Restore production rules:

```bash
git checkout -- public/.htaccess
```

Open **`/setup`** to create the first administrator account.

### Troubleshooting: `WebProfilerBundle` not found

`APP_ENV=dev` loads `WebProfilerBundle`, which lives in Composer **require-dev**. If you see this error, dev packages are missing on the machine.

```bash
composer install
```

Do **not** use `--no-dev` on a dev workstation. After install:

```bash
php bin/console cache:clear --env=dev
```

On a production server without dev tools, use `APP_ENV=prod` instead (you can still set `APP_DEBUG=1` for TOTP debug logs).

### Fresh install (generic content)

A new instance ships **without pre-filled CV business content**. Visitors see placeholders or empty sections until an administrator configures:

1. **Dashboard** — first-time setup checklist
2. **Admin → CV customization** — identity, About, experience, etc.
3. **Admin → Site identity** — favicon, colors, SEO meta descriptions
4. **Admin → Home customization** — intro text, signature photo, quick tiles

---

## Tests & quality

Prepare the test database:

```bash
APP_ENV=test php bin/console doctrine:database:create --if-not-exists
APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction
```

Run the suite:

```bash
APP_ENV=test ./vendor/bin/phpunit
composer test:compliance   # zero-legacy guardrails (translations, URLs, virgin CV)
composer analyse           # PHPStan level 6
```

---

## Configuration reference

Full template with inline comments: [`.env.exemple`](.env.exemple)

| Variable | Purpose |
|----------|---------|
| `APP_SECRET` | Symfony secret |
| `DATABASE_URL` | Database connection |
| `MAILER_DSN` | SMTP transport for TOTP, invitations, notifications |
| `DEFAULT_URI` | Public base URL for absolute links (emails, sitemap) |
| `SYMFONY_TRUSTED_PROXIES` | Trusted reverse-proxy IPs (comma-separated) |
| `APP_TOTP_CHALLENGE_TTL_SECONDS` | TOTP code lifetime |
| `APP_INVITATION_TOKEN_TTL_SECONDS` | Invitation token lifetime |
| `APP_TOTP_EMAIL_FROM` | Sender for auth emails |
| `APP_CV_CONTACT_TO` | CV contact form recipient |
| `APP_PUBLIC_DOWNLOAD_CHALLENGE_TTL_SECONDS` | TOTP resend window |
| `APP_PUBLIC_DOWNLOAD_CHALLENGE_COOLDOWN_SECONDS` | TOTP resend cooldown |
| `APP_PUBLIC_DOWNLOAD_CHALLENGE_MAX_RESEND_COUNT` | Max TOTP resends |
| `APP_FILE_ENCRYPTION_KEY` | Hard-delete vault encryption |
| `APP_CUSTOMIZATION_BACKUP_ENCRYPTION_KEY` | `.cvbackup` encryption key |
| `APP_BACKUP_CREATE_ENABLED` | Allow backup export |
| `APP_BACKUP_RESTORE_ENABLED` | Allow backup restore |
| `APP_BACKUP_RESET_ENABLED` | Allow customization reset |
| `APP_BACKUP_ALLOWED_IPS` | IP allowlist for backup operations |

---

## Backup and restore

1. Log in as admin → **Dashboard → Customization backup**
2. Export produces a `.cvbackup` file (encrypted ZIP, format version 2)
3. Restore uploads the same file on a fresh install (same `APP_CUSTOMIZATION_BACKUP_ENCRYPTION_KEY`)

Manifest JSON includes `appVersion: "StuCvBuilder"`.

---

## Code documentation (Doxygen)

Public APIs use Doxygen headers (`@brief`, `@param`, `@return`, `@date`, `@author`).

```bash
doxygen Doxyfile
```

Output: `var/doxygen/html/index.html`

---

## Project layout

```
StuCvBuilder/
├── .env.exemple         Environment template (copy to .env)
├── config/              Symfony configuration
├── docs/screenshots/    README screenshots (see screenshot.md)
├── migrations/          Doctrine migrations
├── public/              Web root (assets, uploads)
├── src/
│   ├── Controller/
│   ├── Entity/
│   ├── Service/
│   └── ...
├── templates/
├── tests/
├── translations/
├── Doxyfile
└── README.md
```

---

## License

Proprietary — see [LICENSE](LICENSE).
