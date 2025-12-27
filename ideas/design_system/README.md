# Solar ERP Design System

A comprehensive design system for the Solar Panel & Electrical EPC accounting application.

## Overview

This design system is built for Indonesian EPC (Engineering, Procurement, Construction) companies specializing in:
- Solar panel installation
- Electrical panel manufacturing (LVMDP, MDP, etc.)
- Project-based contracting

## Design Philosophy

### 1. Value-First Design
Every screen emphasizes what matters most to the user. Dashboards show actionable metrics, not just data.

### 2. Project-Centric
EPC businesses revolve around projects. The UI reflects this by connecting all elements (costs, materials, labor, invoices) back to projects.

### 3. Action-Oriented
Alerts and notifications always include direct action buttons. Users should never see a problem without a clear path to resolution.

### 4. Progressive Disclosure
Show summary first, details on demand. Reduce cognitive load by revealing complexity only when needed.

### 5. Indonesian Context
- Currency: Rupiah (Rp) with dot separators (Rp 1.250.000)
- Tax: PPN 11% clearly displayed
- Dates: DD MMM YYYY format
- Mixed Indonesian/English terminology where appropriate

## User Roles & Views

| Role | Primary View | Key Features |
|------|-------------|--------------|
| Owner/Management | Executive Dashboard | KPIs, cash flow, project margins |
| Sales | Quotation Pipeline | Quote creation, conversion tracking |
| Project Manager | Project Dashboard | Progress, costs, work orders |
| Procurement | MRP Planning | Shortages, PO management |
| Warehouse | Stock Overview | Inventory, material requisitions |
| Finance | AR/AP Dashboards | Aging, payments, reports |
| Admin | Settings | Users, roles, system config |

## Accessibility

This design system follows WCAG 2.1 AA guidelines:
- Minimum contrast ratio 4.5:1 for text
- Focus indicators on all interactive elements
- Keyboard navigation support
- Screen reader friendly labels

## Responsive Breakpoints

| Breakpoint | Width | Use Case |
|------------|-------|----------|
| `sm` | 640px | Mobile landscape |
| `md` | 768px | Tablet portrait |
| `lg` | 1024px | Tablet landscape / Small laptop |
| `xl` | 1280px | Desktop |
| `2xl` | 1536px | Large desktop |

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | Dec 2025 | Initial design system |
