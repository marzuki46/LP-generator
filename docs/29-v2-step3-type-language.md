# V2 Step 3 — Page type registry & language system

Status: implemented locally, not yet committed/released.

## Implemented

### Page type registry

- `includes/class-lp-type-strategy.php`
  - `LpTypeStrategy` interface (extensible)
  - `AbstractLpTypeStrategy`
  - `ServiceLpType`, `ProductLpType`, `AmazonAffiliateLpType`
  - `LpTypeRegistry`
- 3 types:
  - `service`: schema `Service`, `BreadcrumbList`; `local_value_gate`; consultation CTA
  - `product`: `Product`/`SoftwareApplication`; no fake reviews/scarcity; checkout/support CTA
  - `amazon_affiliate`: `Article`/`Review`; mandatory Amazon Associate disclosure, ASIN/tag/source requirements, compliance-first
- `offer_type = affiliate` is mapped to `amazon_affiliate` for backward compatibility.
- New campaign field `lp_type`, normalized in `ContentRepository::get_campaign()`.
- Campaign UI select **Tipe Landing Page**, localized labels.

### Language system

- `includes/class-language-service.php`
  - supported: `id`, `en`
  - normalization, labels, `html_lang`
  - planned slug prefixes `/id/`, `/en/` (planning only; existing URLs untouched)
  - `planned_path()` example: `jasa/website/bali` → `/en/services/website/bali/`
  - `hreflang_plan()` only returns pairs for existing language URLs; `x-default` only when both id+en exist
  - full localized strings for nav, breadcrumb, related, FAQ, CTA, validation messages
- Language select now uses `LanguageService::SUPPORTED` and localized labels.
- `ContentRepository::get_campaign()` normalizes language.

## Tests

```text
php tests\run.php → 931 passed, 0 failed
php -l → No syntax errors
```

Regression added:
- 3 registered types
- affiliate mapping
- unknown type fallback
- service schema + quality gate
- Amazon disclosure present
- language normalization/fallback
- planned EN path
- self-only hreflang
- x-default only when both languages exist
- localized CTA string

## Not yet in Step 3

Steps 4–8 remain:
- service/product/affiliate templates and brief generators
- SEO metadata + schema + internal-link engines
- duplicate/doorway/affiliate gates + quality score
- experiment mode + publishing workflow
- automated integration/E2E tests

No production content was changed. No version bump/release was made for this step.
