# Enter365 Design System - Components

## Overview
This document defines reusable Blade components for the Enter365 public-facing pages.

---

## Button Component

**File:** `resources/views/components/ui/button.blade.php`

### Variants
| Variant | Usage |
|---------|-------|
| `primary` | Main CTAs (Mulai Sekarang, Daftar) |
| `secondary` | Secondary actions (Pelajari Lebih Lanjut) |
| `outline` | Tertiary actions |
| `ghost` | Minimal emphasis actions |

### Sizes
| Size | Padding | Text |
|------|---------|------|
| `sm` | `px-3 py-1.5` | `text-sm` |
| `md` (default) | `px-4 py-2` | `text-base` |
| `lg` | `px-6 py-3` | `text-lg` |

### Usage
```blade
<x-ui.button variant="primary" size="lg">
    Mulai Sekarang
</x-ui.button>

<x-ui.button variant="secondary" href="/about">
    Pelajari Lebih Lanjut
</x-ui.button>
```

---

## Card Component

**File:** `resources/views/components/ui/card.blade.php`

### Variants
| Variant | Usage |
|---------|-------|
| `default` | Standard card with subtle shadow |
| `elevated` | More prominent shadow for featured items |
| `bordered` | Border instead of shadow |

### Usage
```blade
<x-ui.card>
    <x-slot:header>Card Title</x-slot:header>
    Card content here...
    <x-slot:footer>Card footer</x-slot:footer>
</x-ui.card>
```

---

## Section Component

**File:** `resources/views/components/ui/section.blade.php`

### Props
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `bg` | string | `white` | Background color class |
| `padding` | string | `py-16 md:py-24` | Vertical padding |
| `container` | bool | `true` | Whether to wrap in max-w container |

### Usage
```blade
<x-ui.section bg="gray-50">
    <x-slot:header>
        <h2>Section Title</h2>
        <p>Section description</p>
    </x-slot:header>

    Section content...
</x-ui.section>
```

---

## Feature Card Component

**File:** `resources/views/components/ui/feature-card.blade.php`

Specialized card for feature listings with icon support.

### Props
| Prop | Type | Description |
|------|------|-------------|
| `icon` | string | SVG icon or emoji |
| `title` | string | Feature title |
| `description` | string | Feature description |

### Usage
```blade
<x-ui.feature-card
    icon="chart"
    title="Real-time Analytics"
    description="Monitor profitability per project"
/>
```

---

## Pain Point Card Component

For displaying problems solved with before/after messaging.

### Structure
```blade
<x-ui.pain-point-card>
    <x-slot:problem>Manual spreadsheet tracking</x-slot:problem>
    <x-slot:solution>Integrated all-in-one system</x-slot:solution>
</x-ui.pain-point-card>
```

---

## Typography Scale

| Class | Size | Line Height | Usage |
|-------|------|-------------|-------|
| `text-display` | 3.75rem (60px) | 1 | Hero headlines |
| `text-headline` | 2.25rem (36px) | 1.2 | Section headlines |
| `text-title` | 1.5rem (24px) | 1.3 | Card titles |
| `text-body` | 1rem (16px) | 1.5 | Body text |
| `text-small` | 0.875rem (14px) | 1.5 | Captions, labels |

---

## Spacing System

Use Tailwind's default spacing scale. Key values:
- `4` (1rem) - Tight spacing within components
- `6` (1.5rem) - Medium spacing
- `8` (2rem) - Standard component gaps
- `12` (3rem) - Section internal spacing
- `16` (4rem) - Section padding (mobile)
- `24` (6rem) - Section padding (desktop)

---

## Grid Patterns

### Pain Points Grid (6 items)
```html
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
```

### Features Grid (2 columns)
```html
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
```

### Container Width
```html
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
```
