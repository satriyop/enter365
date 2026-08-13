# Enter365

Indonesian SME ERP: sales, purchasing, inventory, accounting, manufacturing, and projects, with optional industry add-ons.

## Language

### Inventory

**Stock**:
Quantity of a product held in a warehouse. Always product × warehouse.
_Avoid_: Inventory balance (as a synonym for quantity), global stock without warehouse

**Free stock**:
Stock quantity not held for a work order; available for delivery, transfer, or unreserved issue.
_Avoid_: Available stock (ambiguous with on-hand including reserved)

**Reserved stock**:
Stock quantity earmarked for a work order so other documents cannot take it as free stock.
_Avoid_: Allocated stock, blocked stock, soft lock

**Stock reservation**:
The act of increasing reserved stock for a work order (and releasing it when cancelled or issued).
_Avoid_: Booking, hold, allocate (unless talking to finance)

**Stock mutation**:
Any change to on-hand quantity and/or cost at product × warehouse (receipt, issue, adjustment, transfer, production receipt).
_Avoid_: Stock movement (reserved for the audit/document trail of a mutation), inventory transaction

**Issue against reservation**:
An outbound stock mutation that consumes free stock only after releasing reserved stock belonging to the issuing work order.
_Avoid_: Force issue, override reserved

**Production receipt**:
Inbound stock mutation of finished goods from a completed work order, at a unit cost determined by manufacturing.
_Avoid_: FG stock-in as a separate business concept from stock mutation
