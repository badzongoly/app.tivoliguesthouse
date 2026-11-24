# Shared workspace navigation

Date — 23 November 2026

Tivoli uses one shared tab design for workspace and section navigation. On wider screens the available sections appear in a muted green strip with a pale raised selection. On phones, the current section appears beside More. Each More control reveals that strip’s own destinations. Opening another strip closes the previous one, and Escape closes the open strip and returns focus to its More button.

The shared styling covers Check-In & Occupancy, Property & Audits, Extras & Stock, Finance & Cash and corrections, Shift Handover, Purchasing, Payroll, Staff & HR, System Access, Settings, Reminders, Owner Reports and Owner Exceptions. Workflow action links, report pagination and wizard steps keep their navigation purpose. The app preserves the existing server-rendered destinations, filters and permission gates. Links remain available with JavaScript disabled, and a strip with one available destination does not need a menu.

The update changes presentation only. It introduces no schema, account, approval or financial changes. A compatible source rollback restores the earlier navigation.

The isolated browser passed 73 shared navigation assertions across 24 phone and desktop views, including the System Access hash-tab update. Five permission and navigation contracts and the confirmation asset contract passed 213 assertions. PHP and JavaScript syntax and Git whitespace checks passed.
