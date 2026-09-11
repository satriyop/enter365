#!/usr/bin/env node
/**
 * Dual-site Odoo ↔ enter365 parity runner.
 *
 * Loads JourneyCatalog PagePairs in order, captures both sites, and writes
 * paired screenshots + DiffFinding JSON under storage/parity-runs/<run-id>/.
 *
 * Odoo is read-only always. Use --dry-run to validate the catalog without browsers.
 */

import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath, pathToFileURL } from 'node:url';

import {
    COMPARE_DIMENSIONS,
    REQUIRED_SEED_IDS,
    buildFindings,
    createRunId,
    ensureRunDir,
    pairScreenshotPaths,
    redactSecrets,
    relativeToRun,
    summarizeFindings,
    writePairNote,
    writeRunReport,
} from './lib/report.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '../..');
const DEFAULT_CATALOG = path.join(HERE, 'catalog.yaml');
const RUNS_ROOT = path.join(ROOT, 'storage', 'parity-runs');

const DEFAULTS = {
    ODOO_BASE_URL: 'https://enterkode.odoo.com',
    ENTER365_BASE_URL: 'https://enter365.pamungkas.org',
};

export function parseArgs(argv) {
    const args = { dryRun: false, limit: null, headed: false, help: false, catalog: DEFAULT_CATALOG };
    const rest = argv.slice(2);

    for (let i = 0; i < rest.length; i += 1) {
        const token = rest[i];
        if (token === '--dry-run') {
            args.dryRun = true;
        } else if (token === '--headed') {
            args.headed = true;
        } else if (token === '--help' || token === '-h') {
            args.help = true;
        } else if (token === '--limit') {
            args.limit = parseLimit(rest[i + 1]);
            i += 1;
        } else if (token.startsWith('--limit=')) {
            args.limit = parseLimit(token.slice('--limit='.length));
        } else if (token === '--catalog') {
            args.catalog = path.resolve(rest[i + 1] ?? '');
            i += 1;
        } else {
            throw new Error(`Unknown argument: ${token}`);
        }
    }

    return args;
}

function parseLimit(raw) {
    const value = Number(raw);
    if (!Number.isInteger(value) || value < 1) {
        throw new Error('--limit must be a positive integer');
    }

    return value;
}

function stripInlineComment(line) {
    let inSingle = false;
    let inDouble = false;
    for (let i = 0; i < line.length; i += 1) {
        const char = line[i];
        if (char === "'" && !inDouble) {
            inSingle = !inSingle;
        } else if (char === '"' && !inSingle) {
            inDouble = !inDouble;
        } else if (char === '#' && !inSingle && !inDouble) {
            return line.slice(0, i).trimEnd();
        }
    }

    return line;
}

function unquote(value) {
    const trimmed = value.trim();
    if (
        (trimmed.startsWith('"') && trimmed.endsWith('"'))
        || (trimmed.startsWith("'") && trimmed.endsWith("'"))
    ) {
        return trimmed.slice(1, -1);
    }

    return trimmed;
}

function parseInlineList(value) {
    const inner = value.trim().replace(/^\[/, '').replace(/\]$/, '');
    if (!inner.trim()) {
        return [];
    }

    return inner.split(',').map((item) => unquote(item.trim())).filter(Boolean);
}

function assignField(target, line) {
    const idx = line.indexOf(':');
    if (idx === -1) {
        return 'scalar';
    }

    const key = line.slice(0, idx).trim();
    const rawValue = line.slice(idx + 1).trim();

    if (key === 'compare') {
        if (!rawValue) {
            target.compare = [];

            return 'compare';
        }
        if (rawValue.startsWith('[')) {
            target.compare = parseInlineList(rawValue);

            return 'scalar';
        }
        target.compare = [];

        return 'compare';
    }

    target[key] = unquote(rawValue);

    return 'scalar';
}

function finalizePair(raw, index) {
    return {
        id: raw.id ?? '',
        journey: raw.journey ?? '',
        title: raw.title ?? '',
        odoo: { path: raw['odoo.path'] ?? '' },
        enter365: { path: raw['enter365.path'] ?? '' },
        compare: Array.isArray(raw.compare) ? raw.compare : [],
        role: raw.role || null,
        index,
    };
}

/**
 * Subset YAML loader for JourneyCatalog documents (sequence of mappings).
 */
export function parseCatalogYaml(source) {
    const pairs = [];
    let current = null;
    let mode = null;
    let index = 0;

    for (const original of String(source).split(/\r?\n/)) {
        const line = stripInlineComment(original);
        const trimmed = line.trim();
        if (!trimmed) {
            continue;
        }

        const indent = line.length - line.trimStart().length;

        if (trimmed === 'pairs:') {
            mode = 'pairs';
            continue;
        }

        if (mode && trimmed.startsWith('- ') && indent <= 2) {
            if (current) {
                pairs.push(finalizePair(current, index));
                index += 1;
            }
            current = {};
            mode = 'pairs';
            const rest = trimmed.slice(2).trim();
            if (rest.includes(':')) {
                const next = assignField(current, rest);
                if (next === 'compare') {
                    mode = 'compare';
                }
            }
            continue;
        }

        if (!current) {
            continue;
        }

        if (mode === 'compare' && trimmed.startsWith('- ')) {
            current.compare = current.compare ?? [];
            current.compare.push(unquote(trimmed.slice(2).trim()));
            continue;
        }

        const next = assignField(current, trimmed);
        mode = next === 'compare' ? 'compare' : 'pairs';
    }

    if (current) {
        pairs.push(finalizePair(current, index));
    }

    return { pairs };
}

export function loadCatalog(catalogPath) {
    if (!fs.existsSync(catalogPath)) {
        throw new Error(`Catalog not found: ${catalogPath}`);
    }

    return parseCatalogYaml(fs.readFileSync(catalogPath, 'utf8'));
}

export function validateCatalog(catalog) {
    const errors = [];
    const pairs = catalog?.pairs ?? [];
    if (pairs.length === 0) {
        errors.push('catalog has no pairs');
    }

    const ids = new Set();
    pairs.forEach((pair, index) => {
        const label = pair.id || `pair[${index}]`;
        if (!pair.id) {
            errors.push(`pair[${index}] missing id`);
        }
        if (pair.id && ids.has(pair.id)) {
            errors.push(`duplicate id: ${pair.id}`);
        }
        if (pair.id) {
            ids.add(pair.id);
        }
        if (!pair.journey) {
            errors.push(`${label} missing journey`);
        }
        if (!pair.title) {
            errors.push(`${label} missing title`);
        }
        if (!pair.odoo?.path) {
            errors.push(`${label} missing odoo.path`);
        }
        if (!pair.enter365?.path) {
            errors.push(`${label} missing enter365.path`);
        }
        if (!Array.isArray(pair.compare) || pair.compare.length === 0) {
            errors.push(`${label} missing compare[]`);
        }
        for (const dimension of pair.compare ?? []) {
            if (!COMPARE_DIMENSIONS.includes(dimension)) {
                errors.push(`${label} unknown compare dimension: ${dimension}`);
            }
        }
    });

    if (pairs[0] && pairs[0].id !== 'post-login') {
        errors.push('first pair must be post-login (Odoo app switcher ↔ enter365 home/dashboard)');
    }

    for (const seedId of REQUIRED_SEED_IDS) {
        if (!ids.has(seedId)) {
            errors.push(`catalog missing required seed pair: ${seedId}`);
        }
    }

    return errors;
}

export function envConfig() {
    return {
        odooBaseUrl: process.env.ODOO_BASE_URL || DEFAULTS.ODOO_BASE_URL,
        odooEmail: process.env.ODOO_EMAIL || '',
        odooPassword: process.env.ODOO_PASSWORD || '',
        enter365BaseUrl: process.env.ENTER365_BASE_URL || DEFAULTS.ENTER365_BASE_URL,
        enter365Email: process.env.ENTER365_EMAIL || '',
        enter365Password: process.env.ENTER365_PASSWORD || '',
    };
}

export function missingLiveEnv(config) {
    const missing = [];
    if (!config.odooEmail) {
        missing.push('ODOO_EMAIL');
    }
    if (!config.odooPassword) {
        missing.push('ODOO_PASSWORD');
    }
    if (!config.enter365Email) {
        missing.push('ENTER365_EMAIL');
    }
    if (!config.enter365Password) {
        missing.push('ENTER365_PASSWORD');
    }

    return missing;
}

export function secretsFrom(config) {
    return [config.odooPassword, config.enter365Password, config.odooEmail, config.enter365Email].filter(Boolean);
}

function toEnvName(pairId) {
    return pairId.replace(/-/g, '_').toUpperCase();
}

export function resolvePath(urlPath, pairId, site) {
    if (!urlPath.includes('{id}')) {
        return { path: urlPath, unresolved: false };
    }

    const specific = process.env[`PARITY_${toEnvName(pairId)}_${site}_ID`];
    const generic = process.env[`PARITY_${toEnvName(pairId)}_ID`];
    const id = specific || generic;
    if (!id) {
        return { path: urlPath, unresolved: true };
    }

    return { path: urlPath.split('{id}').join(id), unresolved: false };
}

function pad(value, width) {
    const text = String(value);

    return text.length >= width ? text : `${text}${' '.repeat(width - text.length)}`;
}

export function formatPlan(pairs, config, { limit = null } = {}) {
    const selected = limit ? pairs.slice(0, limit) : pairs;
    const lines = [
        'Odoo ↔ enter365 parity compare',
        '',
        `Odoo:      ${config.odooBaseUrl}  (read-only)`,
        `enter365:  ${config.enter365BaseUrl}`,
        `Catalog:   ${pairs.length} pairs${limit ? ` (limit ${selected.length})` : ''}`,
        '',
        `${pad('#', 3)} ${pad('id', 22)} ${pad('journey', 12)} ${pad('odoo.path', 42)} enter365.path`,
    ];

    selected.forEach((pair, index) => {
        lines.push(
            `${pad(index + 1, 3)} ${pad(pair.id, 22)} ${pad(pair.journey, 12)} ${pad(pair.odoo.path, 42)} ${pair.enter365.path}`,
        );
    });

    return lines.join('\n');
}

function usage() {
    return `Usage: node scripts/parity/compare.mjs [--dry-run] [--limit N] [--headed]

Load JourneyCatalog PagePairs and capture Odoo + enter365 side by side.

  --dry-run     Validate catalog.yaml and print the plan (no browsers)
  --limit N     Only the first N pairs
  --headed      Show the browser windows (default is headless)

Environment:
  ODOO_BASE_URL          default ${DEFAULTS.ODOO_BASE_URL}
  ODOO_EMAIL             required for a live run
  ODOO_PASSWORD          required for a live run
  ENTER365_BASE_URL      default ${DEFAULTS.ENTER365_BASE_URL}
  ENTER365_EMAIL         required for a live run
  ENTER365_PASSWORD      required for a live run

Odoo is read-only always. Writes (save/POST/create/write/unlink) are blocked
in the Odoo Playwright context. See scripts/parity/README.md.
`;
}

async function runLive(pairs, config, { headed, runDir }) {
    const { launchDualBrowser, loginOdoo, loginEnter365, capturePage, joinUrl } = await import('./lib/capture.mjs');
    const session = await launchDualBrowser({ headed });
    const secrets = secretsFrom(config);
    const pairNotes = [];

    try {
        await loginOdoo(session.odooPage, {
            baseUrl: config.odooBaseUrl,
            email: config.odooEmail,
            password: config.odooPassword,
        });
        await loginEnter365(session.enter365Page, {
            baseUrl: config.enter365BaseUrl,
            email: config.enter365Email,
            password: config.enter365Password,
        });

        for (const pair of pairs) {
            const files = pairScreenshotPaths(runDir, pair.id);
            const odooResolved = resolvePath(pair.odoo.path, pair.id, 'ODOO');
            const enter365Resolved = resolvePath(pair.enter365.path, pair.id, 'ENTER365');
            const extraFindings = [];

            if (odooResolved.unresolved || enter365Resolved.unresolved) {
                extraFindings.push({
                    severity: 'gap',
                    dimension: 'labels',
                    expected: odooResolved.unresolved
                        ? `Odoo path still contains {id}: ${pair.odoo.path}`
                        : `Odoo: ${odooResolved.path}`,
                    actual: enter365Resolved.unresolved
                        ? `enter365 path still contains {id}: ${pair.enter365.path}`
                        : `enter365: ${enter365Resolved.path}`,
                    evidence: [],
                });
            }

            const [odooCapture, enter365Capture] = await Promise.all([
                capturePage(session.odooPage, {
                    url: joinUrl(config.odooBaseUrl, odooResolved.path),
                    screenshotPath: files.odoo,
                }),
                capturePage(session.enter365Page, {
                    url: joinUrl(config.enter365BaseUrl, enter365Resolved.path),
                    screenshotPath: files.enter365,
                }),
            ]);

            const evidence = [
                relativeToRun(runDir, files.odoo),
                relativeToRun(runDir, files.enter365),
            ];
            extraFindings.forEach((finding) => {
                finding.evidence = evidence;
            });

            const findings = [
                ...extraFindings,
                ...buildFindings(pair, odooCapture.signals, enter365Capture.signals, evidence),
            ];

            const note = {
                pair: {
                    id: pair.id,
                    journey: pair.journey,
                    title: pair.title,
                    'odoo.path': pair.odoo.path,
                    'enter365.path': pair.enter365.path,
                    compare: pair.compare,
                    role: pair.role,
                },
                capture: {
                    odoo: {
                        url: odooCapture.meta.url,
                        title: odooCapture.meta.title,
                        timestamp: odooCapture.meta.timestamp,
                        screenshot: evidence[0],
                    },
                    enter365: {
                        url: enter365Capture.meta.url,
                        title: enter365Capture.meta.title,
                        timestamp: enter365Capture.meta.timestamp,
                        screenshot: evidence[1],
                    },
                },
                findings,
                errors: [odooCapture.navigationError, enter365Capture.navigationError]
                    .filter(Boolean)
                    .map((error) => redactSecrets(error.message ?? error, secrets)),
            };

            writePairNote(files.note, note);
            pairNotes.push(note);
            process.stdout.write(`captured ${pair.id}\n`);
        }

        const reportPath = writeRunReport(runDir, {
            runId: path.basename(runDir),
            startedAt: pairNotes[0]?.capture?.odoo?.timestamp ?? new Date().toISOString(),
            odoo: { baseUrl: config.odooBaseUrl, readOnly: true },
            enter365: { baseUrl: config.enter365BaseUrl },
            blockedOdooWrites: session.blockedWrites,
            pairs: pairNotes.map((note) => ({
                id: note.pair.id,
                journey: note.pair.journey,
                counts: summarizeFindings(note.findings),
                findings: note.findings,
            })),
            totals: summarizeFindings(pairNotes.flatMap((note) => note.findings)),
        });

        return { reportPath, blockedWrites: session.blockedWrites, pairNotes };
    } finally {
        await session.close();
    }
}

export async function main(argv = process.argv) {
    const args = parseArgs(argv);
    if (args.help) {
        process.stdout.write(usage());

        return 0;
    }

    const catalog = loadCatalog(args.catalog);
    const errors = validateCatalog(catalog);
    if (errors.length > 0) {
        process.stderr.write(`Catalog is invalid:\n${errors.map((error) => `  - ${error}`).join('\n')}\n`);

        return 1;
    }

    const config = envConfig();
    const pairs = args.limit ? catalog.pairs.slice(0, args.limit) : catalog.pairs;
    process.stdout.write(`${formatPlan(catalog.pairs, config, { limit: args.limit })}\n`);

    if (args.dryRun) {
        process.stdout.write('\nOK. Catalog is valid. No browsers launched.\n');

        return 0;
    }

    const missing = missingLiveEnv(config);
    if (missing.length > 0) {
        process.stderr.write(
            `Missing required environment variables for a live run:\n${missing.map((name) => `  - ${name}`).join('\n')}\n\n`
            + 'Set them in the environment (never commit secrets). See scripts/parity/README.md.\n'
            + 'Use --dry-run to validate the catalog without browsers.\n',
        );

        return 1;
    }

    const runId = createRunId();
    const runDir = ensureRunDir(RUNS_ROOT, runId);
    process.stdout.write(`\nRun directory: ${runDir}\nOdoo context is read-only (writes are blocked).\n\n`);

    try {
        const result = await runLive(pairs, config, { headed: args.headed, runDir });
        process.stdout.write(`\nWrote ${result.pairNotes.length} pair notes + ${path.basename(result.reportPath)}\n`);
        if (result.blockedWrites.length > 0) {
            process.stdout.write(`Blocked ${result.blockedWrites.length} Odoo write request(s).\n`);
        }

        return 0;
    } catch (error) {
        const message = redactSecrets(error?.message ?? error, secretsFrom(config));
        process.stderr.write(`Parity run failed: ${message}\n`);

        return 1;
    }
}

const invokedDirectly = process.argv[1]
    && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href;

if (invokedDirectly) {
    main().then((code) => process.exit(code)).catch((error) => {
        process.stderr.write(`${error?.stack ?? error}\n`);
        process.exit(1);
    });
}
