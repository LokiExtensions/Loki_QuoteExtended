%title: Show custom values after placing the order
%status: complete
---

**As a** customer,  
**I want to** see my custom values on the success page and in my account,  
**So that** I can check what I have entered.

## Acceptance Criteria

### when-success-page-is-shown

**Given** I placed an order with custom values  
**When** the checkout success page is shown  
**Then** the custom values of this order are available for display.

### when-order-is-viewed-in-customer-account

**Given** I placed an order with custom values as a customer  
**When** I view the order in my account  
**Then** the custom values of this order are available for display.

### when-there-is-no-order

**Given** there is no current or last placed order  
**When** a value is requested  
**Then** an empty value is returned.

## Tests
- `Test/Unit/ViewModel/OrderQuoteExtendedViewTest.php`
- `Test/Integration/ViewModel/OrderQuoteExtendedViewTest.php`
- `Loki_QuoteExtendedExample/Test/Playwright/quote-extended-example-order-test.spec.ts`
- `Loki_QuoteExtendedExample/Test/Integration/Frontend/OrderPageTest.php`
