# Extras Catalogue optimisation

Date — 23 November 2026

The catalogue opens on active items and gives each item an accessible details control. Filters and sorting apply to the whole result set before pagination. Inactive and all-item views retain search, category and stock sort. Edit links carry the same filters to the form and back through Cancel or save.

Phones show item, price, category, available quantity and a plain-language stock label in a compact row. Wider screens use a table. Held and recorded stock remain in Details alongside the item actions permitted for that actor. Count Stock stays at workspace level.

The catalogue reads through the existing Stay service and shares the same stock and hold calculations as Occupancy. Its normal route skips unrelated room, booking, audit and checklist data. The existing writers, approval checks and historical rows are preserved. No schema or migration work was introduced.

Verification used synthetic fixtures. The catalogue selection contract passed 11 checks. The Extras & Stock navigation HTTP suite passed 87 checks. The isolated browser covered administrator and clerk behaviour, filters, paging, the empty search state, filtered Cancel and responsive layouts at 320, 390, 768 and 1440 CSS pixels.

Rollback is a compatible source rollback. It does not require database changes.
