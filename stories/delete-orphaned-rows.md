%title: Delete orphaned custom values
%status: complete
---

**As a** shop owner,  
**I want** custom values that belong to neither a cart nor an order to be deleted,  
**So that** the database does not keep unused data.

## Acceptance Criteria

### when-cart-is-deleted-without-order

**Given** custom values of a cart without an order  
**When** the cart is deleted and the cleanup cron job runs  
**Then** the custom values are deleted.

### when-values-are-linked-to-an-order

**Given** custom values linked to an order  
**When** the cleanup cron job runs  
**Then** the custom values are kept.

## Tests
- `Test/Unit/Cron/DeleteOrphanedRowsTest.php`
- `Test/Integration/Cron/DeleteOrphanedRowsTest.php`
