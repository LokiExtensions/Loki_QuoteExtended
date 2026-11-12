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

Values are saved with a single `INSERT ... ON DUPLICATE KEY UPDATE` query, so parallel saves of different fields do not overwrite each other. A value is only saved when its column exists in the table; strings that are longer than the column are cut to fit. The columns `entity_id`, `quote_id`, `order_id`, `created_at` and `updated_at` are reserved and can not be written via the checkout.


## Displaying the values

- Checkout (current quote): `Loki\QuoteExtended\ViewModel\CheckoutQuoteExtendedView::getValue('example_field')`
- Customer account order view and checkout success page: `Loki\QuoteExtended\ViewModel\OrderQuoteExtendedView::getValue('example_field')` (uses the registry `current_order`, otherwise the last placed order of the checkout session)
- Admin order, invoice, shipment and credit memo pages: `Loki\QuoteExtended\ViewModel\Adminhtml\OrderQuoteExtendedView::getValue('example_field')`
- Order, invoice, shipment and credit memo emails: `{{var loki_quote_extended.example_field}}`

### Generic admin display
Instead of adding your own admin templates, you can register columns with a label. These are shown (when not empty) in the "Account Information" table of the admin order, invoice, shipment and credit memo pages:

```xml
<type name="Loki\QuoteExtended\Config\LabelledColumns">
    <arguments>
        <argument name="columns" xsi:type="array">
            <item name="example_field" xsi:type="string">Example field</item>
        </argument>
    </arguments>
</type>
```

Columns that are not registered are not shown. There is no column in the admin order grid.


## API
The values are added to the order as the read-only extension attribute `loki_quote_extended` (a list of `code`/`value` pairs, see `Loki\QuoteExtended\Api\Data\QuoteExtendedValueInterface`). This applies to `OrderRepositoryInterface::get()` and `getList()`, and therefore also to the REST endpoints `GET /V1/orders/:id` and `GET /V1/orders`. There is no GraphQL support and no write API.


## Behaviour

- **Login during checkout:** When a guest logs in and Magento merges the guest cart into the customer cart, the guest row is moved to the customer cart. If the customer cart already has a row, the non-empty guest values are copied into it (the guest values win) and the guest row is deleted.
- **Placing an order:** The row is linked to the order. When the same quote is used for another order, the values are copied to a new row for the quote first.
- **Admin order edit:** When an order is edited in the admin (which creates a new order), the values of the original order are copied to the new order. A normal reorder copies nothing.
- **Cleanup:** A cron job deletes rows that are neither linked to a quote nor to an order.


## Personal data (GDPR)
This module does not know what is stored in your custom columns. If you store personal data in `loki_quote_extended`, you (the integrator) are responsible for handling it according to the GDPR: informing customers, exporting, anonymizing and deleting this data (for instance when a customer or order is deleted or anonymized). Rows are deleted together with their order (foreign key with `ON DELETE CASCADE`).

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
