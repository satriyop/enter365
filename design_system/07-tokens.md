# 07 - Design Tokens

Exportable design tokens for implementation in code.

---

## Tailwind CSS Configuration

```javascript
// tailwind.config.js
const colors = require('tailwindcss/colors')

module.exports = {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        // Brand Colors
        primary: {
          50: '#FFF7ED',
          100: '#FFEDD5',
          200: '#FED7AA',
          300: '#FDBA74',
          400: '#FB923C',
          500: '#F97316', // Base
          600: '#EA580C',
          700: '#C2410C',
          800: '#9A3412',
          900: '#7C2D12',
        },
        secondary: colors.blue,

        // Semantic Colors
        success: colors.green,
        warning: colors.amber,
        error: colors.red,
        info: colors.blue,
      },

      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
      },

      fontSize: {
        'xs': ['0.75rem', { lineHeight: '1rem' }],      // 12px
        'sm': ['0.875rem', { lineHeight: '1.25rem' }],  // 14px
        'base': ['1rem', { lineHeight: '1.5rem' }],     // 16px
        'lg': ['1.125rem', { lineHeight: '1.75rem' }],  // 18px
        'xl': ['1.25rem', { lineHeight: '1.75rem' }],   // 20px
        '2xl': ['1.5rem', { lineHeight: '2rem' }],      // 24px
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }], // 30px
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],   // 36px
        '5xl': ['3rem', { lineHeight: '1' }],           // 48px
      },

      spacing: {
        // Extended spacing for larger gaps
        '18': '4.5rem',  // 72px
        '22': '5.5rem',  // 88px
      },

      borderRadius: {
        'sm': '0.25rem',   // 4px
        'DEFAULT': '0.375rem', // 6px
        'md': '0.5rem',    // 8px
        'lg': '0.75rem',   // 12px
        'xl': '1rem',      // 16px
        '2xl': '1.5rem',   // 24px
      },

      boxShadow: {
        'sm': '0 1px 2px 0 rgb(0 0 0 / 0.05)',
        'DEFAULT': '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
        'md': '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
        'lg': '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
        'xl': '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
      },

      zIndex: {
        'dropdown': '10',
        'sticky': '20',
        'fixed': '30',
        'modal-backdrop': '40',
        'modal': '50',
        'toast': '60',
        'tooltip': '70',
      },

      animation: {
        'fade-in': 'fadeIn 150ms ease-out',
        'fade-out': 'fadeOut 150ms ease-in',
        'slide-in': 'slideIn 300ms ease-out',
        'slide-out': 'slideOut 300ms ease-in',
        'scale-in': 'scaleIn 150ms ease-out',
        'spin-slow': 'spin 2s linear infinite',
      },

      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        fadeOut: {
          '0%': { opacity: '1' },
          '100%': { opacity: '0' },
        },
        slideIn: {
          '0%': { transform: 'translateX(100%)' },
          '100%': { transform: 'translateX(0)' },
        },
        slideOut: {
          '0%': { transform: 'translateX(0)' },
          '100%': { transform: 'translateX(100%)' },
        },
        scaleIn: {
          '0%': { transform: 'scale(0.95)', opacity: '0' },
          '100%': { transform: 'scale(1)', opacity: '1' },
        },
      },

      transitionDuration: {
        'instant': '0ms',
        'fast': '75ms',
        'normal': '150ms',
        'slow': '300ms',
        'slower': '500ms',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
}
```

---

## CSS Custom Properties

```css
/* styles/tokens.css */

:root {
  /* ============================================
     COLOR TOKENS
     ============================================ */

  /* Primary (Solar Orange) */
  --color-primary-50: #FFF7ED;
  --color-primary-100: #FFEDD5;
  --color-primary-200: #FED7AA;
  --color-primary-300: #FDBA74;
  --color-primary-400: #FB923C;
  --color-primary-500: #F97316;
  --color-primary-600: #EA580C;
  --color-primary-700: #C2410C;
  --color-primary-800: #9A3412;
  --color-primary-900: #7C2D12;

  /* Secondary (Blue) */
  --color-secondary-50: #EFF6FF;
  --color-secondary-100: #DBEAFE;
  --color-secondary-200: #BFDBFE;
  --color-secondary-300: #93C5FD;
  --color-secondary-400: #60A5FA;
  --color-secondary-500: #3B82F6;
  --color-secondary-600: #2563EB;
  --color-secondary-700: #1D4ED8;
  --color-secondary-800: #1E40AF;
  --color-secondary-900: #1E3A8A;

  /* Neutral (Slate) */
  --color-neutral-50: #F8FAFC;
  --color-neutral-100: #F1F5F9;
  --color-neutral-200: #E2E8F0;
  --color-neutral-300: #CBD5E1;
  --color-neutral-400: #94A3B8;
  --color-neutral-500: #64748B;
  --color-neutral-600: #475569;
  --color-neutral-700: #334155;
  --color-neutral-800: #1E293B;
  --color-neutral-900: #0F172A;
  --color-neutral-950: #020617;

  /* Semantic */
  --color-success: #22C55E;
  --color-warning: #F59E0B;
  --color-error: #EF4444;
  --color-info: #3B82F6;

  /* Status */
  --color-status-draft: #94A3B8;
  --color-status-pending: #F59E0B;
  --color-status-in-progress: #3B82F6;
  --color-status-approved: #22C55E;
  --color-status-completed: #16A34A;
  --color-status-on-hold: #8B5CF6;
  --color-status-cancelled: #EF4444;
  --color-status-overdue: #DC2626;

  /* ============================================
     TYPOGRAPHY TOKENS
     ============================================ */

  --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
  --font-mono: 'JetBrains Mono', ui-monospace, monospace;

  --font-size-xs: 0.75rem;
  --font-size-sm: 0.875rem;
  --font-size-base: 1rem;
  --font-size-lg: 1.125rem;
  --font-size-xl: 1.25rem;
  --font-size-2xl: 1.5rem;
  --font-size-3xl: 1.875rem;
  --font-size-4xl: 2.25rem;
  --font-size-5xl: 3rem;

  --line-height-xs: 1rem;
  --line-height-sm: 1.25rem;
  --line-height-base: 1.5rem;
  --line-height-lg: 1.75rem;
  --line-height-xl: 1.75rem;
  --line-height-2xl: 2rem;
  --line-height-3xl: 2.25rem;
  --line-height-4xl: 2.5rem;
  --line-height-5xl: 1;

  --font-weight-normal: 400;
  --font-weight-medium: 500;
  --font-weight-semibold: 600;
  --font-weight-bold: 700;

  /* ============================================
     SPACING TOKENS
     ============================================ */

  --space-0: 0;
  --space-0-5: 0.125rem;  /* 2px */
  --space-1: 0.25rem;     /* 4px */
  --space-1-5: 0.375rem;  /* 6px */
  --space-2: 0.5rem;      /* 8px */
  --space-2-5: 0.625rem;  /* 10px */
  --space-3: 0.75rem;     /* 12px */
  --space-4: 1rem;        /* 16px */
  --space-5: 1.25rem;     /* 20px */
  --space-6: 1.5rem;      /* 24px */
  --space-8: 2rem;        /* 32px */
  --space-10: 2.5rem;     /* 40px */
  --space-12: 3rem;       /* 48px */
  --space-16: 4rem;       /* 64px */
  --space-20: 5rem;       /* 80px */
  --space-24: 6rem;       /* 96px */

  /* ============================================
     BORDER RADIUS TOKENS
     ============================================ */

  --radius-none: 0;
  --radius-sm: 0.25rem;   /* 4px */
  --radius-base: 0.375rem; /* 6px */
  --radius-md: 0.5rem;    /* 8px */
  --radius-lg: 0.75rem;   /* 12px */
  --radius-xl: 1rem;      /* 16px */
  --radius-2xl: 1.5rem;   /* 24px */
  --radius-full: 9999px;

  /* ============================================
     SHADOW TOKENS
     ============================================ */

  --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  --shadow-base: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
  --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
  --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
  --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);

  /* ============================================
     Z-INDEX TOKENS
     ============================================ */

  --z-base: 0;
  --z-dropdown: 10;
  --z-sticky: 20;
  --z-fixed: 30;
  --z-modal-backdrop: 40;
  --z-modal: 50;
  --z-toast: 60;
  --z-tooltip: 70;

  /* ============================================
     ANIMATION TOKENS
     ============================================ */

  --duration-instant: 0ms;
  --duration-fast: 75ms;
  --duration-normal: 150ms;
  --duration-slow: 300ms;
  --duration-slower: 500ms;

  --ease-default: cubic-bezier(0.4, 0, 0.2, 1);
  --ease-in: cubic-bezier(0.4, 0, 1, 1);
  --ease-out: cubic-bezier(0, 0, 0.2, 1);
  --ease-bounce: cubic-bezier(0.68, -0.55, 0.265, 1.55);

  /* ============================================
     COMPONENT-SPECIFIC TOKENS
     ============================================ */

  /* Sidebar */
  --sidebar-width: 240px;
  --sidebar-width-collapsed: 64px;

  /* Header */
  --header-height: 64px;

  /* Card */
  --card-padding: var(--space-6);
  --card-radius: var(--radius-lg);
  --card-shadow: var(--shadow-base);

  /* Button */
  --button-height-xs: 1.75rem;  /* 28px */
  --button-height-sm: 2rem;     /* 32px */
  --button-height-md: 2.5rem;   /* 40px */
  --button-height-lg: 2.75rem;  /* 44px */
  --button-height-xl: 3rem;     /* 48px */

  /* Input */
  --input-height-sm: 2rem;      /* 32px */
  --input-height-md: 2.5rem;    /* 40px */
  --input-height-lg: 3rem;      /* 48px */

  /* Modal */
  --modal-width-sm: 24rem;      /* 384px */
  --modal-width-md: 28rem;      /* 448px */
  --modal-width-lg: 32rem;      /* 512px */
  --modal-width-xl: 36rem;      /* 576px */
  --modal-width-2xl: 42rem;     /* 672px */
  --modal-width-3xl: 48rem;     /* 768px */
  --modal-width-4xl: 56rem;     /* 896px */
}
```

---

## TypeScript Types

```typescript
// types/design-tokens.ts

export type ColorShade = 50 | 100 | 200 | 300 | 400 | 500 | 600 | 700 | 800 | 900;

export interface ColorPalette {
  50: string;
  100: string;
  200: string;
  300: string;
  400: string;
  500: string;
  600: string;
  700: string;
  800: string;
  900: string;
}

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'success';
export type ButtonSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl';

export type InputSize = 'sm' | 'md' | 'lg';

export type StatusType =
  | 'draft'
  | 'pending'
  | 'in_progress'
  | 'approved'
  | 'completed'
  | 'on_hold'
  | 'cancelled'
  | 'overdue'
  | 'rejected';

export type AlertVariant = 'info' | 'success' | 'warning' | 'error';

export type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl' | 'full';

export interface StatusConfig {
  label: string;
  color: string;
  bgColor: string;
  icon?: string;
}

export const STATUS_CONFIG: Record<StatusType, StatusConfig> = {
  draft: {
    label: 'Draft',
    color: 'text-slate-700',
    bgColor: 'bg-slate-100',
  },
  pending: {
    label: 'Pending',
    color: 'text-amber-700',
    bgColor: 'bg-amber-100',
    icon: 'clock',
  },
  in_progress: {
    label: 'In Progress',
    color: 'text-blue-700',
    bgColor: 'bg-blue-100',
    icon: 'arrow-path',
  },
  approved: {
    label: 'Approved',
    color: 'text-green-700',
    bgColor: 'bg-green-100',
    icon: 'check',
  },
  completed: {
    label: 'Completed',
    color: 'text-green-700',
    bgColor: 'bg-green-100',
    icon: 'check-circle',
  },
  on_hold: {
    label: 'On Hold',
    color: 'text-violet-700',
    bgColor: 'bg-violet-100',
    icon: 'pause',
  },
  cancelled: {
    label: 'Cancelled',
    color: 'text-red-700',
    bgColor: 'bg-red-100',
    icon: 'x-circle',
  },
  overdue: {
    label: 'Overdue',
    color: 'text-red-700',
    bgColor: 'bg-red-100',
    icon: 'exclamation-triangle',
  },
  rejected: {
    label: 'Rejected',
    color: 'text-red-700',
    bgColor: 'bg-red-100',
    icon: 'x-circle',
  },
};
```

---

## Utility Functions

```typescript
// utils/format.ts

/**
 * Format number as Indonesian Rupiah
 */
export function formatCurrency(value: number): string {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value);
}

/**
 * Format number with Indonesian thousand separators
 */
export function formatNumber(value: number): string {
  return new Intl.NumberFormat('id-ID').format(value);
}

/**
 * Format percentage
 */
export function formatPercent(value: number, decimals: number = 1): string {
  return `${(value * 100).toFixed(decimals).replace('.', ',')}%`;
}

/**
 * Format date in Indonesian format
 */
export function formatDate(date: Date | string): string {
  const d = new Date(date);
  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(d);
}

/**
 * Format date and time in Indonesian format
 */
export function formatDateTime(date: Date | string): string {
  const d = new Date(date);
  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d);
}

/**
 * Format relative time (e.g., "2 hours ago")
 */
export function formatRelativeTime(date: Date | string): string {
  const d = new Date(date);
  const now = new Date();
  const diffMs = now.getTime() - d.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
  if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
  if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;

  return formatDate(d);
}

/**
 * Calculate days remaining/overdue
 */
export function daysRemaining(dueDate: Date | string): { days: number; isOverdue: boolean } {
  const due = new Date(dueDate);
  const now = new Date();
  const diffMs = due.getTime() - now.getTime();
  const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

  return {
    days: Math.abs(diffDays),
    isOverdue: diffDays < 0,
  };
}

/**
 * Truncate text with ellipsis
 */
export function truncate(text: string, maxLength: number): string {
  if (text.length <= maxLength) return text;
  return text.slice(0, maxLength - 3) + '...';
}

/**
 * Get initials from name
 */
export function getInitials(name: string): string {
  return name
    .split(' ')
    .map(word => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
}
```

---

## Component Class Utilities

```typescript
// utils/cn.ts
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Merge Tailwind classes with conflict resolution
 */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}
```

```typescript
// utils/variants.ts

/**
 * Button variant classes
 */
export const buttonVariants = {
  primary: 'bg-orange-500 text-white hover:bg-orange-600 active:bg-orange-700',
  secondary: 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 active:bg-slate-100',
  ghost: 'bg-transparent text-slate-600 hover:bg-slate-100 active:bg-slate-200',
  danger: 'bg-red-500 text-white hover:bg-red-600 active:bg-red-700',
  success: 'bg-green-500 text-white hover:bg-green-600 active:bg-green-700',
};

export const buttonSizes = {
  xs: 'h-7 px-2.5 text-xs',
  sm: 'h-8 px-3 text-sm',
  md: 'h-10 px-4 text-sm',
  lg: 'h-11 px-5 text-base',
  xl: 'h-12 px-6 text-base',
};

/**
 * Badge variant classes
 */
export const badgeVariants = {
  default: 'bg-slate-100 text-slate-700',
  primary: 'bg-orange-100 text-orange-700',
  success: 'bg-green-100 text-green-700',
  warning: 'bg-amber-100 text-amber-700',
  error: 'bg-red-100 text-red-700',
  info: 'bg-blue-100 text-blue-700',
};

/**
 * Alert variant classes
 */
export const alertVariants = {
  info: 'bg-blue-50 border-l-4 border-blue-500 text-blue-700',
  success: 'bg-green-50 border-l-4 border-green-500 text-green-700',
  warning: 'bg-amber-50 border-l-4 border-amber-500 text-amber-700',
  error: 'bg-red-50 border-l-4 border-red-500 text-red-700',
};

/**
 * Input state classes
 */
export const inputStates = {
  default: 'border-slate-300 focus:border-orange-500 focus:ring-1 focus:ring-orange-500',
  error: 'border-red-500 focus:border-red-500 focus:ring-1 focus:ring-red-500',
  disabled: 'bg-slate-50 cursor-not-allowed',
};
```
