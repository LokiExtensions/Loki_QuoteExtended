%title: Read custom values via the order API
%status: complete
---

**As a** developer of an integration (ERP, fulfilment),  
**I want to** read the custom values of an order via the order repository and REST API,  
**So that** I can process them in other systems.

## Acceptance Criteria

### when-single-order-is-loaded

**Given** an order with custom values  
**When** the order is loaded via `OrderRepositoryInterface::get()` or `GET /V1/orders/:id`  
**Then** the extension attribute `loki_quote_extended` contains a `code`/`value` pair per column.

### when-order-list-is-loaded

**Given** several orders with custom values  
**When** the orders are loaded via `OrderRepositoryInterface::getList()` or `GET /V1/orders`  
**Then** each order contains its own custom values.

### when-order-has-no-values

**Given** an order without custom values  
**When** the order is loaded  
**Then** the extension attribute `loki_quote_extended` is not set.

## Tests
- `Test/Unit/Plugin/Order/AddQuoteExtendedExtensionAttributeTest.php`
- `Test/Integration/Plugin/Order/AddQuoteExtendedExtensionAttributeTest.php`
