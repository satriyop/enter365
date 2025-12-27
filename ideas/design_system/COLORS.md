# Enter365 Design System - Color Palette

## Brand Philosophy
Our color palette reflects the sustainability focus of our solar EPC customers (green) while maintaining professional credibility for electrical panel manufacturers.

---

## Primary Colors (Emerald Green - Sustainability)

| Name | Tailwind Class | Hex | Usage |
|------|----------------|-----|-------|
| Primary | `emerald-500` | `#10B981` | Primary actions, success states, growth indicators |
| Primary Dark | `emerald-600` | `#059669` | CTAs, buttons, emphasis, hover states |
| Primary Light | `emerald-400` | `#34D399` | Highlights, secondary indicators |

### CSS Custom Properties
```css
@theme {
  --color-primary: oklch(0.765 0.177 163);      /* emerald-500 */
  --color-primary-dark: oklch(0.696 0.17 162);  /* emerald-600 */
  --color-primary-light: oklch(0.82 0.19 165);  /* emerald-400 */
}
```

---

## Accent Colors (Amber - Electrical Industry Nod)

| Name | Tailwind Class | Hex | Usage |
|------|----------------|-----|-------|
| Accent | `amber-500` | `#F59E0B` | Alerts, electrical industry references, highlights |
| Accent Dark | `amber-600` | `#D97706` | Warning states, important notices |

---

## Neutral Colors

| Name | Tailwind Class | Hex | Usage |
|------|----------------|-----|-------|
| Dark | `gray-800` | `#1F2937` | Primary text |
| Medium | `gray-600` | `#4B5563` | Secondary text |
| Light | `gray-400` | `#9CA3AF` | Muted text, borders |
| Surface | `gray-100` | `#F3F4F6` | Card backgrounds |
| Background | `gray-50` | `#F9FAFB` | Page backgrounds |
| White | `white` | `#FFFFFF` | Card surfaces, contrast |

---

## Semantic Colors

| Purpose | Tailwind Class | Usage |
|---------|----------------|-------|
| Success | `emerald-500` | Positive actions, completion |
| Warning | `amber-500` | Cautions, attention needed |
| Error | `red-500` | Errors, destructive actions |
| Info | `blue-500` | Informational messages |

---

## Dark Mode Mappings

| Light Mode | Dark Mode |
|------------|-----------|
| `gray-50` bg | `gray-900` bg |
| `gray-800` text | `gray-100` text |
| `white` card | `gray-800` card |
| `gray-100` surface | `gray-700` surface |

---

## Usage Guidelines

### Do
- Use emerald-500/600 for primary CTAs and key actions
- Use amber-500 sparingly for alerts and electrical-themed highlights
- Maintain high contrast ratios (min 4.5:1 for text)

### Don't
- Mix too many accent colors
- Use emerald for error states
- Use pure black (#000) - prefer gray-800 or gray-900
