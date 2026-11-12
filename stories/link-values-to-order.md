%title: Link custom values to the order
%status: complete
---

**As a** shop owner,  
**I want** the custom values of a cart to be linked to the order,  
**So that** I can see them after the order has been placed.

## Acceptance Criteria

### when-order-is-placed

**Given** a cart with custom values  
**When** the order is placed  
**Then** the custom values are linked to the order.

### when-same-cart-is-used-for-another-order

**Given** the custom values of a cart are already linked to an order  
**When** a value of the same cart is changed  
**Then** the values of the existing order are not changed.

### when-order-is-edited-in-the-admin

**Given** an order with custom values  
**When** an admin edits the order, which creates a new order  
**Then** the new order gets a copy of the custom values  
**And** the original order keeps its values.

### when-order-is-reordered

**Given** an order with custom values  
**When** the order is placed again via a normal reorder  
**Then** no custom values are copied.

## Tests
- `Test/Unit/Observer/LinkOrderToQuoteExtendedTest.php`
- `Test/Unit/Repository/QuoteExtendedRepositoryTest.php`
- `Test/Integration/Observer/LinkOrderToQuoteExtendedTest.php`
- `Test/Integration/Repository/QuoteExtendedRepositoryTest.php`
- `Test/Playwright/quote-extended-test.spec.ts`
