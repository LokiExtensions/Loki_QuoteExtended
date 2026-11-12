%title: Show custom values in the admin
%status: complete
---

**As a** shop administrator,  
**I want to** see the custom values on the admin sales pages,  
**So that** I can process the order with this information.

## Acceptance Criteria

### when-column-is-registered-with-a-label

**Given** a column is registered with a label in `Loki\QuoteExtended\Config\LabelledColumns`  
**And** the order has a value for this column  
**When** I open the order, invoice, shipment or credit memo page in the admin  
**Then** the label and the value are shown in the account information.

### when-column-is-not-registered

**Given** a column is not registered with a label  
**When** I open an admin sales page  
**Then** its value is not shown.

### when-value-is-empty

**Given** a registered column has no value for the order  
**When** I open an admin sales page  
**Then** the label is not shown.

## Tests
- `Test/Unit/Config/LabelledColumnsTest.php`
- `Test/Unit/ViewModel/Adminhtml/OrderQuoteExtendedViewTest.php`
- `Test/Integration/ViewModel/Adminhtml/OrderQuoteExtendedViewTest.php`
- `Test/Integration/Config/LabelledColumnsTest.php`
- `Test/Integration/Adminhtml/OrderInfoBlockTest.php`
- `Loki_QuoteExtendedExample/Test/Integration/Adminhtml/SalesViewTest.php`
