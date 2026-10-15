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

## Endpoint

```
GET /api/translations/{locale}/{domain}[.json]
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

* **Full access** — users with the `full_access_role` may fetch every
  domain and every well-formed locale, see [Full access](#full-access).
* **Suffix** — the `.json` suffix is optional, so the i18next default
  `loadPath` (`…/{{lng}}/{{ns}}.json`) works as well.
* **Domain** — only domains listed in `domains` are delivered, everything
  else responds with `404`. A domain corresponds to an i18next namespace.
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
* **Caching** — `Cache-Control: public, max-age=<cache_max_age>` and an
  `ETag`; `If-None-Match` is answered with `304`.
* **CORS** — `Access-Control-Allow-Origin` is set for origins listed in
  `cors_allow_origins`. No preflight is needed, as `i18next-http-backend`
  sends simple GET requests.

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
  domains: [frontend]          # exposed domains, empty = nothing is exposed
  locales: [de, en]            # allowed locales, default: framework.enabled_locales
  full_access_role: ~          # e.g. ROLE_TRANSLATOR, see "Full access"
  cache_max_age: 300           # seconds
  cors_allow_origins: []       # e.g. ['https://www.example.com'] or ['*']
```

The bundle uses the `translator` service and thus sees everything the
translator knows: `translations/` of the application, bundle translations
and translations added at runtime (in SiteKit: the Infosite language packages
from `var/infosite_translations`, see `SP\SiteKit\Translation\InfositeTranslationSupplier`).

**Never expose domains that contain internal texts** — every message of an
exposed domain is publicly readable.

## Full access

Users granted the `full_access_role` may fetch every domain and every
well-formed locale, regardless of `domains` and `locales` (e.g. for
translation tools that need all catalogues):

* The role is checked with `isGranted()`, so a voter attribute works as well.
  It is only checked if the request is not allowed anyway.
* Locales that are not allowed are delivered in their ICU spelling (`pt-BR` →
  `pt_BR`), allowed locales keep their configured spelling.
* These responses are `Cache-Control: private` and thus not stored by shared
  caches (reverse proxies, CDN).

The bundle does not authenticate users. This is up to the firewall of the
application (`symfony/security-bundle`), which must cover the path
`/api/translations`. Without the security bundle or without
`full_access_role` nobody has full access. Example with HTTP Basic:

```yaml
# config/packages/security.yaml
security:
  firewalls:
    translation_api:
      pattern: ^/api/translations/
      stateless: true
      http_basic: true
```

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
    loadPath: 'https://www.example.com/api/translations/{{lng}}/{{ns}}',
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
curl -i http://localhost:8000/api/translations/de/frontend
# full access: user "translator", password "translator"
curl -i -u translator:translator http://localhost:8000/api/translations/en/internal
```

It allows every CORS origin, so e.g. the i18next demo can use
`http://localhost:8000/api/translations/{{lng}}/{{ns}}` as API URL.
The functional tests run against the same application.
