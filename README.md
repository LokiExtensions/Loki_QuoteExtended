# Loki_QuoteExtended

<!-- badges.specs.start -->
![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://gitlab.yireo.com/loki-checkout/Loki_QuoteExtended/-/badges/release.svg)
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
[![Static Tests](https://gitlab.yireo.com/loki-checkout/Loki_QuoteExtended/badges/main/pipeline.svg?job=static-tests)](https://gitlab.yireo.com/loki-checkout/Loki_QuoteExtended/-/pipelines)
[![Unit Tests](https://gitlab.yireo.com/loki-checkout/Loki_QuoteExtended/badges/main/pipeline.svg?job=unit-tests)](https://gitlab.yireo.com/loki-checkout/Loki_QuoteExtended/-/pipelines)
[![Integration Tests](https://gitlab.yireo.com/loki-checkout/Loki_QuoteExtended/badges/main/pipeline.svg?job=integration-tests)](https://gitlab.yireo.com/loki-checkout/Loki_QuoteExtended/-/pipelines)
[![Playwright](https://gitlab.yireo.com/loki-checkout/Loki_QuoteExtended/badges/main/pipeline.svg?job=playwright)](https://gitlab.yireo.com/loki-checkout/Loki_QuoteExtended/-/pipelines)
[![DI Compilation](https://gitlab.yireo.com/loki-checkout/Loki_QuoteExtended/badges/main/pipeline.svg?job=compile)](https://gitlab.yireo.com/loki-checkout/Loki_QuoteExtended/-/pipelines)
<!-- badges.test.end -->
