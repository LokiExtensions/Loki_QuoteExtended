%title: Keep custom values when logging in during checkout
%status: complete
---

**As a** guest customer,  
**I want to** keep the custom values I entered when I log in during checkout,  
**So that** I do not need to enter them again.

## Acceptance Criteria

### when-customer-has-no-values-yet

**Given** I entered a custom value as a guest  
**And** my customer account has no custom values for its cart  
**When** I log in  
**Then** my cart still contains the value I entered as a guest.

### when-customer-already-has-values

**Given** I entered a custom value as a guest  
**And** my customer account cart already has custom values  
**When** I log in  
**Then** the values I entered as a guest replace the conflicting values  
**And** other values of my customer cart are kept.

### when-guest-value-is-empty

**Given** I left a custom field empty as a guest  
**And** my customer account cart has a value for that field  
**When** I log in  
**Then** the value of my customer cart is kept.

### when-cleanup-runs-after-login

**Given** I logged in after entering custom values as a guest  
**When** the cleanup cron job runs  
**Then** my custom values are still there.

## Tests
- `Test/Unit/Observer/MergeQuoteExtendedTest.php`
- `Test/Integration/Observer/MergeQuoteExtendedTest.php`
- `Loki_QuoteExtendedExample/Test/Playwright/quote-extended-example-login-test.spec.ts`
