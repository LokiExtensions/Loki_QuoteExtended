%title: Show custom values in sales emails
%status: complete
---

**As a** customer,  
**I want to** see my custom values in the sales emails,  
**So that** I can check that the shop received them.

## Acceptance Criteria

### when-sales-email-is-sent

**Given** an order with custom values  
**When** the order, invoice, shipment or credit memo email is sent  
**Then** the email template can show the values via `{{var loki_quote_extended.<column>}}`.

### when-order-has-no-values

**Given** an order without custom values  
**When** a sales email is sent  
**Then** the email is sent without custom values.

## Tests
- `Test/Unit/Observer/AddQuoteExtendedToEmailVarsTest.php`
- `Test/Integration/Observer/AddQuoteExtendedToEmailVarsTest.php`
- `Loki_QuoteExtendedExample/Test/Integration/Email/SalesEmailTest.php`
