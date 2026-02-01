<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Centralized validation rules constants to eliminate duplication
 * across Form Request classes.
 */
class ValidationRules
{
    // ========================================
    // CONTACT VALIDATION
    // ========================================

    public const CONTACT_REQUIRED = ['required', 'exists:contacts,id'];

    public const CONTACT_OPTIONAL = ['nullable', 'exists:contacts,id'];

    public const CONTACT_SOMETIMES_REQUIRED = ['sometimes', 'required', 'integer', 'exists:contacts,id'];

    public const CONTACT_SOMETIMES_OPTIONAL = ['sometimes', 'nullable', 'integer', 'exists:contacts,id'];

    // ========================================
    // COMMON FIELD PATTERNS
    // ========================================

    // Names and identifiers
    public const NAME_REQUIRED = ['required', 'string', 'max:255'];

    public const NAME_OPTIONAL = ['nullable', 'string', 'max:255'];

    public const CODE_REQUIRED = ['required', 'string', 'max:50'];

    public const CODE_OPTIONAL = ['nullable', 'string', 'max:50'];

    // Descriptions and notes
    public const SUBJECT_OPTIONAL = ['nullable', 'string', 'max:255'];

    public const DESCRIPTION_OPTIONAL = ['nullable', 'string', 'max:500'];

    public const NOTES_OPTIONAL = ['nullable', 'string', 'max:2000'];

    public const REFERENCE_OPTIONAL = ['nullable', 'string', 'max:100'];

    public const TERMS_OPTIONAL = ['nullable', 'string', 'max:5000'];

    // ========================================
    // NUMERIC PATTERNS
    // ========================================

    // Financial values
    public const CURRENCY_OPTIONAL = ['nullable', 'string', 'size:3'];

    public const EXCHANGE_RATE_OPTIONAL = ['nullable', 'numeric', 'min:0', 'max:100000'];

    // Tax and discount
    public const TAX_RATE_NULLABLE = ['nullable', 'numeric', 'min:0', 'max:100'];

    public const DISCOUNT_PERCENT_NULLABLE = ['nullable', 'numeric', 'min:0', 'max:100'];

    public const DISCOUNT_TYPE_OPTIONAL = ['nullable', 'in:percentage,fixed'];

    public const DISCOUNT_VALUE_OPTIONAL = ['nullable', 'numeric', 'min:0', 'max:2000000000'];

    // Quantities and prices (max ~Rp 2 trillion for monetary, 999999 for quantities)
    public const QUANTITY_REQUIRED = ['required', 'numeric', 'min:0.0001', 'max:999999'];

    public const QUANTITY_OPTIONAL = ['nullable', 'numeric', 'min:0', 'max:999999'];

    public const UNIT_PRICE_REQUIRED = ['required', 'integer', 'min:0', 'max:2000000000'];

    public const UNIT_PRICE_OPTIONAL = ['nullable', 'integer', 'min:0', 'max:2000000000'];

    public const TOTAL_AMOUNT_OPTIONAL = ['nullable', 'integer', 'min:0', 'max:2000000000'];

    // Percentages
    public const RETENTION_PERCENT = ['nullable', 'numeric', 'min:0', 'max:100'];

    public const COMPLETION_PERCENTAGE = ['nullable', 'integer', 'min:0', 'max:100'];

    // ========================================
    // DATE PATTERNS
    // ========================================

    public const DATE_REQUIRED = ['required', 'date', 'before:+5 years'];

    public const DATE_OPTIONAL = ['nullable', 'date', 'before:+5 years'];

    public const DATE_FUTURE = ['nullable', 'date', 'after:today', 'before:+5 years'];

    // ========================================
    // ARRAY PATTERNS
    // ========================================

    // Item collections
    public const ITEMS_ARRAY = ['required', 'array', 'min:1'];

    public const ITEMS_ARRAY_OPTIONAL = ['nullable', 'array'];

    // Item fields
    public const ITEM_DESCRIPTION = ['required', 'string', 'max:500'];

    public const ITEM_UNIT_OPTIONAL = ['nullable', 'string', 'max:20'];

    public const ITEM_NOTES = ['nullable', 'string', 'max:500'];

    public const ITEM_SORT_ORDER = ['nullable', 'integer', 'min:0'];

    // ========================================
    // STATUS AND TYPE PATTERNS
    // ========================================

    public const STATUS_OPTIONAL = ['nullable', 'string', 'max:20'];

    public const PRIORITY_OPTIONAL = ['nullable', 'in:low,normal,high,urgent'];

    public const TYPE_OPTIONAL = ['nullable', 'string', 'max:20'];

    // ========================================
    // BOOLEAN PATTERNS
    // ========================================

    public const BOOLEAN_OPTIONAL = ['nullable', 'boolean'];

    // ========================================
    // FILE AND IMAGE PATTERNS
    // ========================================

    public const IMAGE_OPTIONAL = ['nullable', 'image', 'max:2048'];

    public const FILE_OPTIONAL = ['nullable', 'file', 'max:10240'];
}
