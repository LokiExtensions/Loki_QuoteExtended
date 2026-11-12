%title: Save a custom checkout value
%status: complete
---

**As a** customer,  
**I want to** fill in a custom field in the checkout,  
**So that** the shop receives this information with my order.

## Acceptance Criteria

### when-value-is-entered

**Given** the shop has added a column and a matching checkout field  
**When** I enter a value in the field  
**Then** the value is stored for my current cart.

### when-several-fields-are-saved-at-the-same-time

**Given** the checkout has two custom fields  
**When** both fields are saved shortly after each other  
**Then** both values are stored for my cart, in a single row.

### when-column-does-not-exist

**Given** a checkout field refers to a column that does not exist  
**When** a value is saved  
**Then** nothing is stored  
**And** an error is logged.

### when-reserved-column-is-used

**Given** a checkout field refers to `entity_id`, `quote_id`, `order_id`, `created_at` or `updated_at`  
**When** a value is saved  
**Then** nothing is stored.

### when-value-is-too-long

**Given** a text column with a maximum length  
**When** a longer value is saved  
**Then** the value is cut to the maximum length.

## Tests
- `Test/Unit/Repository/QuoteExtendedRepositoryTest.php`
- `Test/Unit/Component/QuoteExtendedField/QuoteExtendedFieldRepositoryTest.php`
- `Test/Integration/Repository/QuoteExtendedRepositorySaveValueTest.php`
- `Test/Integration/Model/ResourceModel/QuoteExtendedTest.php`
- `Test/Integration/Component/QuoteExtendedFieldRepositoryTest.php`
- `Test/Playwright/quote-extended-test.spec.ts`
