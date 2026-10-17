# atoolo/translation-api-bundle

Symfony bundle that delivers the messages of the Symfony translator as JSON
over HTTP — one request per locale and translation domain. The first use
case is [i18next](https://www.i18next.com/) with
[`i18next-http-backend`](https://github.com/i18next/i18next-http-backend):
labels are maintained in Symfony translation files (e.g. Infosite language
packages in SiteKit) instead of being hard-coded in the frontend.

This bundle only **delivers** existing translations. It does not create
them — for machine translation of translation files see `translate-api`.

> **Status:** prototype

## Endpoints

### Messages

```
GET /api/translations/{locale}/domains/{domain}[.json]
```

```json
{
  "greeting": "Hallo",
  "welcome": "Willkommen, {name}!",
  "cart": {
    "items": "{count, plural, =0 {Ihr Warenkorb ist leer.} one {# Artikel} other {# Artikel}}"
  }
}
```

* **Full access** — users with one of the `full_access_roles` may fetch every
  domain and every well-formed locale, see [Full access](#full-access).
* **Suffix** — the `.json` suffix is optional, so a `loadPath` ending in
  `{{ns}}.json` (as the i18next default) works as well.
* **Domain** — only domains listed in `domains` are delivered (`*` exposes
  every domain), everything else responds with `404`. A domain corresponds to an i18next namespace.
  It may be requested with or without `+intl-icu` suffix (`frontend` or
  `frontend+intl-icu`), both deliver the ICU and the plain messages.
* **Locale** — Symfony compares locales as exact strings, while i18next
  requests BCP 47 tags (`en-GB`). The requested locale is therefore matched
  against `locales` (default: `framework.enabled_locales`) ignoring case and
  `-`/`_`, and the configured spelling is used (`en-GB` → `en_GB` or
  `en-gb`). Without any allowed locales, every well-formed locale is accepted
  and converted to its ICU spelling (`en-GB` → `en_GB`, `zh-hans` →
  `zh_Hans`). Malformed or not allowed locales respond with `404` (i18next
  does not retry on 4xx).
* **Messages** are delivered **unchanged**, i.e. in ICU message format for
  `+intl-icu` domains. The messages of the fallback locales of the Symfony
  translator are included, the more specific locale wins.
* **Keys** — the dot separated keys of the catalogue become nested objects.
  If a key is both a message and the prefix of other keys (`a` and `a.b`),
  the longer key is kept flat (`{"a": "…", "a.b": "…"}`); i18next resolves
  this as well.
* **Caching** — `Cache-Control: public, max-age=<cache_max_age>`,
  `Vary: Origin` (CORS headers may depend on it) and an `ETag`;
  `If-None-Match` is answered with `304`.
* **CORS** — is up to the application, see [CORS](#cors).

### Domains of a locale

```
GET /api/translations/{locale}/domains[.json]
```

```json
["frontend+intl-icu", "legacy"]
```

Lists the domains that have messages in the locale or one of its fallback
locales, sorted and named in full: with `+intl-icu` suffix if the domain
contains ICU messages. Each listed domain can be fetched as is.

* The same restrictions as for the messages apply: only domains listed in
  `domains` of allowed locales, otherwise `404`. With full access, all
  domains of every well-formed locale are listed.
* The response is `Cache-Control: private`, as it depends on the user.

### All domains of a locale

```
GET /api/translations/{locale}[.json]
```

```json
{
  "frontend+intl-icu": {
    "greeting": "Hallo",
    "cart": { "items": "{count, plural, one {# Artikel} other {# Artikel}}" }
  },
  "legacy": { "farewell": "Auf Wiedersehen" }
}
```

Delivers the [listed domains](#domains-of-a-locale) together with their
messages in one request, e.g. for tools that need the whole catalogue of a
locale. The messages of each domain are the same as when fetched
individually. The same restrictions apply, and the response is private as
well.

### Sources

All endpoints take the optional query parameter `source`:

```
GET /api/translations/de/domains/frontend?source=overrides
```

Without it, the messages come from the translator of the application. An
application can register further sources, e.g. the messages of one kind of
translation resources only, or a translator without them. A source is a
`Symfony\Component\Translation\TranslatorBagInterface` service tagged with
`atoolo_translation_api.source`, its name is the attribute `source`:

```yaml
# config/services.yaml
App\Translation\OverridesSource:
  tags:
    - { name: atoolo_translation_api.source, source: overrides }
```

The bundle knows nothing about what a source holds. An unknown source
responds with `404`; the same restrictions apply as without source.

## Installation

```php
// config/bundles.php
Atoolo\TranslationApi\AtooloTranslationApiBundle::class => ['all' => true],
```

```yaml
# config/routes/atoolo_translation_api.yaml
atoolo_translation_api:
  resource: '@AtooloTranslationApiBundle/config/routes.yaml'
```

```yaml
# config/packages/atoolo_translation_api.yaml
atoolo_translation_api:
  domains: [frontend]          # exposed domains, empty = nothing, '*' = every domain
  locales: [de, en]            # allowed locales, default: framework.enabled_locales
  full_access_roles: []        # e.g. [ROLE_API, ROLE_ADMIN], see "Full access"
  cache_max_age: 300           # seconds
```

The bundle uses the `translator` service and thus sees everything the
translator knows: `translations/` of the application, bundle translations
and translations added at runtime (in SiteKit: the Infosite language packages
from `var/infosite_translations`, see `SP\SiteKit\Translation\InfositeTranslationSupplier`).

**Never expose domains that contain internal texts** — every message of an
exposed domain is publicly readable.

`domains: ['*']` exposes every domain the translator knows — including
those of other bundles (e.g. `validators`, `security`), runtime resources
(e.g. the Infosite language packages) and every domain added by a future
update. Use it only if all of them are meant to be public, e.g. because they
only contain texts shown to visitors anyway.

## Full access

Users granted one of the `full_access_roles` may fetch every domain and every
well-formed locale and list all domains of a locale, regardless of
`domains` and `locales` (e.g. for translation tools that need all
catalogues):

* The roles are checked with `isGranted()`, so voter attributes work as
  well. They are only checked if the request is not allowed anyway.
* Locales that are not allowed are delivered in their ICU spelling (`pt-BR` →
  `pt_BR`), allowed locales keep their configured spelling.
* These responses are `Cache-Control: private` and thus not stored by shared
  caches (reverse proxies, CDN).

The bundle does not authenticate users. This is up to the firewall of the
application (`symfony/security-bundle`), which must cover the path
`/api/translations`. Without the security bundle or without
`full_access_roles` nobody has full access. Example with HTTP Basic:

```yaml
# config/packages/security.yaml
security:
  firewalls:
    translation_api:
      pattern: ^/api/translations/
      stateless: true
      http_basic: true
```

### With atoolo/security-bundle

Applications using `atoolo/security-bundle` need no additional firewall: the
`api` firewall of its recipe (`^/api`, stateless, JWT) already covers
`/api/translations`.

* Clients fetch a token via `POST /api/login_check` and send it as
  `Authorization: Bearer <token>`. Requests without token stay anonymous,
  an invalid or expired token responds with `401`.
* Roles of IES and SiteKit users are mapped to `ROLE_<NAME>` in upper case
  (`api` → `ROLE_API`).
* The IES already uses the user `api` with `ROLE_API`, which also grants
  access to `/api/admin/`. Reusing it needs no additional user:

  ```yaml
  atoolo_translation_api:
    full_access_roles: [ROLE_API, ROLE_ADMIN]
  ```
* Cross-origin requests with token need `Authorization` in the allowed
  headers of the CORS configuration, see [CORS](#cors).

## CORS

As the other atoolo bundles, this bundle sets no CORS headers. Cross-origin
access is configured in the application with
[`nelmio/cors-bundle`](https://github.com/nelmio/NelmioCorsBundle), which
also answers preflight requests (`OPTIONS`). Without matching `paths`, nelmio
applies its `defaults` to every path — and thus to `/api/translations` as
well. Anonymous i18next requests are simple GET requests without preflight,
requests with an `Authorization` header (full access) need one.

```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
  paths:
    '^/api/translations/':
      allow_origin: ['*']
      forced_allow_origin_value: '*'
      allow_methods: ['GET']
      allow_headers: ['Authorization']
      max_age: 3600
```

* `forced_allow_origin_value: '*'` sets the header on every response, even
  without `Origin`. Otherwise nelmio echoes the requesting origin, so every
  origin gets its own cache entry (`Vary: Origin`).
* `allow_origin: ['*']` suffices for bearer tokens, as they are no
  credentials in terms of CORS (unlike cookies).

## i18next setup

Since the messages are ICU, i18next needs the ICU plugin:

```sh
pnpm add i18next-icu intl-messageformat
```

```ts
import i18n from 'i18next'
import ICU from 'i18next-icu'
import HttpBackend from 'i18next-http-backend'

i18n.use(ICU).use(HttpBackend).init({
  fallbackLng: 'en',
  ns: ['frontend'],
  defaultNS: 'frontend',
  backend: {
    loadPath: 'https://www.example.com/api/translations/{{lng}}/domains/{{ns}}',
  },
})

i18n.t('cart.items', { count: 3 })
```

**Important:** `i18next-icu` applies to *all* namespaces. Local resource
files in i18next format must be converted to ICU as well:

| i18next format                         | ICU                                            |
|----------------------------------------|------------------------------------------------|
| `"welcome": "Hello {{name}}"`          | `"welcome": "Hello {name}"`                    |
| `"items_one": "{{count}} item"`<br>`"items_other": "{{count}} items"` | `"items": "{count, plural, one {# item} other {# items}}"` |

## Development

```sh
composer install
composer test
composer analyse
```

`dev/` contains a minimal application with sample translations
(`dev/translations`, domains `frontend` and the not exposed `internal`):

```sh
composer serve-dev
curl -i http://localhost:8000/api/translations/de/domains/frontend
# full access: user "translator", password "translator"
curl -i -u translator:translator http://localhost:8000/api/translations/en/domains/internal
```

It allows every CORS origin (via `nelmio/cors-bundle`), so e.g. the i18next
demo can use `http://localhost:8000/api/translations/{{lng}}/domains/{{ns}}` as API
URL.
The functional tests run against the same application.
