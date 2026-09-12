# PHPUnit

Integration tests for the WooCommerce addon. Locally they run against the shared `iwp-dev` stack (ImportWP + Pro + WooCommerce + this addon). On GitHub Actions they use `.wp-env.ci.json` with sibling checkouts.

## Local (`iwp-dev`)

```bash
cd ../iwp-dev
npm run test:start
npm run test:woocommerce:install   # first time / after composer.json changes
npm run test:woocommerce
```

## GitHub Actions

Workflow: `.github/workflows/phpunit.yml`

- Checks out `importwp/importwp` and (optionally) `jcollings/importwp-pro`
- Starts wp-env from `.wp-env.ci.json`
- Runs this repo’s PHPUnit suite

Optional repo configuration:

| Name | Type | Purpose |
| --- | --- | --- |
| `IMPORTWP_PRO_TOKEN` | secret | PAT with read access to `jcollings/importwp-pro` |
| `IMPORTWP_REF` | variable | ImportWP branch/tag (default `master`) |
| `IMPORTWP_PRO_REF` | variable | ImportWP Pro branch/tag (default `master`) |

Without `IMPORTWP_PRO_TOKEN`, CI still runs against free ImportWP + WooCommerce.

Premium third-party plugins (ACF Pro, WPML, etc.) are not installed in CI — tag those tests `@group premium` and skip when missing.

## Suites

| Path | Coverage |
| --- | --- |
| `Bootstrap/DependenciesTest.php` | Stack smoke (WC, ImportWP, addon classes) |
| `Importer/TemplateRegistrationTest.php` | Template/mapper registration + filters |
| `Importer/Template/ProductTemplateTest.php` | Variation/global attributes, product lookup, upsells, field registration |
| `Importer/Template/CustomerTemplateTest.php` | Customer field groups + billing/shipping address |
| `Importer/Template/OrderTemplateTest.php` | Order field groups, addresses, line items, customer link |
| `Importer/Mapper/ProductMapperTest.php` | Product create by type |
| `Importer/Mapper/CustomerMapperTest.php` | Customer create defaults to customer role |
| `Importer/Mapper/OrderMapperTest.php` | Order create + exists by order key |
| `Importer/PostProcessTest.php` | Default `product_cat` cleanup after import |

Shared helpers live in `Utils/` (`ProductTestTrait`, `CustomerTestTrait`, `OrderTestTrait`, `ProtectedPropertyTrait`).

## Sample data

| File | Purpose |
| --- | --- |
| `samples/products.csv` / `samples/products.xml` | Product examples |
| `samples/customers.csv` / `samples/customers.xml` | Customer examples |
| `samples/orders.csv` / `samples/orders.xml` | Order examples (SKU refs match product samples) |
