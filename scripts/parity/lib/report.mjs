import fs from 'node:fs';
import path from 'node:path';

export const COMPARE_DIMENSIONS = Object.freeze([
    'chrome',
    'filters',
    'columns',
    'badges',
    'actions',
    'status_workflow',
    'labels',
]);

export const REQUIRED_SEED_IDS = Object.freeze([
    'post-login',
    'chart-of-accounts',
    'customer-invoices',
    'quotation-detail',
    'purchase-rfq',
    'vendor-bills',
    'stock-report',
    'accounting-reports',
]);

/**
 * @typedef {'match' | 'mismatch' | 'gap'} DiffSeverity
 *
 * @typedef {{
 *   severity: DiffSeverity,
 *   dimension: string,
 *   expected: string,
 *   actual: string,
 *   evidence: string[],
 * }} DiffFinding
 *
 * @typedef {{
 *   url: string,
 *   title: string,
 *   timestamp: string,
 *   screenshot: string,
 * }} PageMeta
 *
 * @typedef {{
 *   odoo: PageMeta,
 *   enter365: PageMeta,
 * }} CaptureResult
 */

export function createRunId(now = new Date()) {
    const iso = now.toISOString().replace(/[-:]/g, '').replace(/\.\d+Z$/, 'Z');

    return iso;
}

export function ensureRunDir(runsRoot, runId) {
    const dir = path.join(runsRoot, runId);
    fs.mkdirSync(dir, { recursive: true });

    return dir;
}

export function pairScreenshotPaths(runDir, pairId) {
    return {
        odoo: path.join(runDir, `${pairId}-odoo.png`),
        enter365: path.join(runDir, `${pairId}-enter365.png`),
        note: path.join(runDir, `${pairId}.json`),
    };
}

export function relativeToRun(runDir, absolutePath) {
    return path.relative(runDir, absolutePath).split(path.sep).join('/');
}

function normalize(value) {
    return String(value ?? '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

function summarize(items, limit = 8) {
    const list = (items ?? []).map((item) => String(item).trim()).filter(Boolean);
    if (list.length === 0) {
        return '(none observed)';
    }

    const head = list.slice(0, limit);

    return head.length < list.length
        ? `${head.join(' | ')} (+${list.length - head.length} more)`
        : head.join(' | ');
}

function overlapScore(expected, actual) {
    const expectedNorm = (expected ?? []).map(normalize).filter(Boolean);
    if (expectedNorm.length === 0) {
        return 0;
    }

    const actualNorm = (actual ?? []).map(normalize).filter(Boolean);
    let hits = 0;
    for (const expectedItem of expectedNorm) {
        if (actualNorm.some((actualItem) => actualItem.includes(expectedItem) || expectedItem.includes(actualItem))) {
            hits += 1;
        }
    }

    return hits / expectedNorm.length;
}

/**
 * Structured dimension compare. Pixel-diff is intentionally not used.
 *
 * @param {string} dimension
 * @param {string[]} expected
 * @param {string[]} actual
 * @param {string[]} evidence
 * @returns {DiffFinding}
 */
export function compareDimension(dimension, expected, actual, evidence) {
    const expectedItems = expected ?? [];
    const actualItems = actual ?? [];
    const payload = {
        dimension,
        expected: summarize(expectedItems),
        actual: summarize(actualItems),
        evidence,
    };

    if (expectedItems.length === 0 && actualItems.length === 0) {
        return {
            severity: 'gap',
            ...payload,
            expected: 'Odoo: (none observed)',
            actual: 'enter365: (none observed)',
        };
    }

    if (expectedItems.length > 0 && actualItems.length === 0) {
        return {
            severity: 'gap',
            ...payload,
            expected: `Odoo: ${payload.expected}`,
            actual: 'enter365: (none observed)',
        };
    }

    const score = overlapScore(expectedItems, actualItems);
    if (score >= 0.35) {
        return {
            severity: 'match',
            ...payload,
            expected: `Odoo: ${payload.expected}`,
            actual: `enter365: ${payload.actual}`,
        };
    }

    return {
        severity: 'mismatch',
        ...payload,
        expected: `Odoo: ${payload.expected}`,
        actual: `enter365: ${payload.actual}`,
    };
}

/**
 * @param {{ compare: string[] }} pair
 * @param {Record<string, string[]>} odooSignals
 * @param {Record<string, string[]>} enter365Signals
 * @param {string[]} evidence
 * @returns {DiffFinding[]}
 */
export function buildFindings(pair, odooSignals, enter365Signals, evidence) {
    const dimensions = pair.compare ?? [];

    return dimensions.map((dimension) => compareDimension(
        dimension,
        odooSignals?.[dimension] ?? [],
        enter365Signals?.[dimension] ?? [],
        evidence,
    ));
}

export function writePairNote(filePath, note) {
    fs.writeFileSync(filePath, `${JSON.stringify(note, null, 2)}\n`, 'utf8');
}

export function writeRunReport(runDir, report) {
    const filePath = path.join(runDir, 'report.json');
    fs.writeFileSync(filePath, `${JSON.stringify(report, null, 2)}\n`, 'utf8');

    return filePath;
}

export function summarizeFindings(findings) {
    const counts = { match: 0, mismatch: 0, gap: 0 };
    for (const finding of findings) {
        if (counts[finding.severity] !== undefined) {
            counts[finding.severity] += 1;
        }
    }

    return counts;
}

/**
 * Strip known secret values from a string so logs/reports never echo credentials.
 *
 * @param {unknown} value
 * @param {string[]} secrets
 */
export function redactSecrets(value, secrets) {
    let text = String(value ?? '');
    for (const secret of secrets) {
        if (!secret) {
            continue;
        }
        text = text.split(secret).join('[redacted]');
    }

    return text;
}
