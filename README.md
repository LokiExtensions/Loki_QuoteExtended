# Loki_QuoteExtended

<!-- badges.specs.start -->
<div class="github-specs-badges">

![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://img.shields.io/packagist/v/loki-checkout/magento2-quote-extended)

</div>
<!-- badges.specs.end -->


## Installation
Install this package via composer:
```bash
composer require loki-checkout/magento2-quote-extended
```

Next, enable this module:
```bash
bin/magento module:enable Loki_QuoteExtended
```


## Usage
This module adds a table `loki_quote_extended` with one row per quote. When the order is placed, the row is linked to the order (`order_id`).

To store a custom value:

1. Add a column to `loki_quote_extended` via your own `etc/db_schema.xml` (plus `db_schema_whitelist.json`).
2. Add a field block to the Loki Checkout whose name ends with the column name (e.g. `loki-checkout.customer.example_field`), using the template `Loki_FieldComponents::form/field.phtml`.
3. Register the block in your `etc/loki_components.xml` with the group `quoteExtendedFields`.

To display the value:

- Frontend (current quote): `Loki\QuoteExtended\ViewModel\CheckoutQuoteExtendedView::getValue('example_field')`
- Admin order and invoice pages: `Loki\QuoteExtended\ViewModel\Adminhtml\OrderQuoteExtendedView::getValue('example_field')`
- Order and invoice emails: `{{var loki_quote_extended.example_field}}`

See `Loki_QuoteExtendedExample` for a complete example.


## Current status

<!-- badges.test.start -->
<div class="github-pipeline-badges">

![Static Tests](https://img.shields.io/github/actions/workflow/status/LokiExtensions/Loki_QuoteExtended/static-tests.yml?label=static-tests)
![Unit Tests](https://img.shields.io/github/actions/workflow/status/LokiExtensions/Loki_QuoteExtended/unit-tests.yml?label=unit-tests)
![Integration Tests](https://img.shields.io/github/actions/workflow/status/LokiExtensions/Loki_QuoteExtended/integration-tests.yml?label=integration-tests)
![Playwright](https://img.shields.io/github/actions/workflow/status/LokiExtensions/Loki_QuoteExtended/playwright.yml?label=playwright)
![DI Compilation](https://img.shields.io/github/actions/workflow/status/LokiExtensions/Loki_QuoteExtended/compile.yml?label=compile)

</div>
<!-- badges.test.end -->
