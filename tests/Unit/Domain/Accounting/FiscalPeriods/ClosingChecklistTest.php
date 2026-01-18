<?php

use App\Domain\Accounting\FiscalPeriods\ValueObjects\ClosingChecklist;

describe('ClosingChecklist', function () {

    describe('Creation', function () {

        it('can be created from array', function () {
            $checks = [
                'unposted_journals' => [
                    'status' => 'ok',
                    'count' => 0,
                    'message' => 'All journals posted',
                ],
                'draft_invoices' => [
                    'status' => 'warning',
                    'count' => 2,
                    'message' => '2 draft invoices',
                ],
            ];

            $checklist = ClosingChecklist::fromArray($checks);

            expect($checklist->items)->toHaveCount(2);
            expect($checklist->items['unposted_journals']->isOk())->toBeTrue();
            expect($checklist->items['draft_invoices']->isWarning())->toBeTrue();
        });

    });

    describe('canClose', function () {

        it('returns true when no blocking errors', function () {
            $checks = [
                'check1' => ['status' => 'ok', 'count' => 0, 'message' => 'OK'],
                'check2' => ['status' => 'warning', 'count' => 1, 'message' => 'Warning'],
            ];

            $checklist = ClosingChecklist::fromArray($checks);

            expect($checklist->canClose())->toBeTrue();
        });

        it('returns false when blocking errors exist', function () {
            $checks = [
                'check1' => ['status' => 'ok', 'count' => 0, 'message' => 'OK'],
                'check2' => ['status' => 'error', 'count' => 5, 'message' => 'Blocking error'],
            ];

            $checklist = ClosingChecklist::fromArray($checks);

            expect($checklist->canClose())->toBeFalse();
        });

    });

    describe('getBlockingErrors', function () {

        it('returns only error messages', function () {
            $checks = [
                'check1' => ['status' => 'ok', 'count' => 0, 'message' => 'OK'],
                'check2' => ['status' => 'error', 'count' => 5, 'message' => 'Error 1'],
                'check3' => ['status' => 'warning', 'count' => 2, 'message' => 'Warning'],
                'check4' => ['status' => 'error', 'count' => 3, 'message' => 'Error 2'],
            ];

            $checklist = ClosingChecklist::fromArray($checks);
            $errors = $checklist->getBlockingErrors();

            expect($errors)->toBe(['Error 1', 'Error 2']);
        });

        it('returns empty array when no errors', function () {
            $checks = [
                'check1' => ['status' => 'ok', 'count' => 0, 'message' => 'OK'],
                'check2' => ['status' => 'warning', 'count' => 2, 'message' => 'Warning'],
            ];

            $checklist = ClosingChecklist::fromArray($checks);

            expect($checklist->getBlockingErrors())->toBe([]);
        });

    });

    describe('getWarnings', function () {

        it('returns only warning messages', function () {
            $checks = [
                'check1' => ['status' => 'ok', 'count' => 0, 'message' => 'OK'],
                'check2' => ['status' => 'warning', 'count' => 2, 'message' => 'Warning 1'],
                'check3' => ['status' => 'error', 'count' => 5, 'message' => 'Error'],
                'check4' => ['status' => 'warning', 'count' => 1, 'message' => 'Warning 2'],
            ];

            $checklist = ClosingChecklist::fromArray($checks);
            $warnings = $checklist->getWarnings();

            expect($warnings)->toBe(['Warning 1', 'Warning 2']);
        });

    });

    describe('Counts', function () {

        it('correctly counts ok items', function () {
            $checks = [
                'check1' => ['status' => 'ok', 'count' => 0, 'message' => 'OK 1'],
                'check2' => ['status' => 'ok', 'count' => 0, 'message' => 'OK 2'],
                'check3' => ['status' => 'warning', 'count' => 2, 'message' => 'Warning'],
            ];

            $checklist = ClosingChecklist::fromArray($checks);

            expect($checklist->getOkCount())->toBe(2);
        });

        it('correctly counts warning items', function () {
            $checks = [
                'check1' => ['status' => 'ok', 'count' => 0, 'message' => 'OK'],
                'check2' => ['status' => 'warning', 'count' => 2, 'message' => 'Warning 1'],
                'check3' => ['status' => 'warning', 'count' => 1, 'message' => 'Warning 2'],
            ];

            $checklist = ClosingChecklist::fromArray($checks);

            expect($checklist->getWarningCount())->toBe(2);
        });

        it('correctly counts error items', function () {
            $checks = [
                'check1' => ['status' => 'error', 'count' => 5, 'message' => 'Error 1'],
                'check2' => ['status' => 'ok', 'count' => 0, 'message' => 'OK'],
                'check3' => ['status' => 'error', 'count' => 3, 'message' => 'Error 2'],
            ];

            $checklist = ClosingChecklist::fromArray($checks);

            expect($checklist->getErrorCount())->toBe(2);
        });

        it('correctly counts total items', function () {
            $checks = [
                'check1' => ['status' => 'ok', 'count' => 0, 'message' => 'OK'],
                'check2' => ['status' => 'warning', 'count' => 2, 'message' => 'Warning'],
                'check3' => ['status' => 'error', 'count' => 5, 'message' => 'Error'],
            ];

            $checklist = ClosingChecklist::fromArray($checks);

            expect($checklist->getTotalCount())->toBe(3);
        });

    });

    describe('toArray', function () {

        it('converts back to array format', function () {
            $checks = [
                'check1' => ['status' => 'ok', 'count' => 0, 'message' => 'OK'],
                'check2' => ['status' => 'warning', 'count' => 2, 'message' => 'Warning'],
            ];

            $checklist = ClosingChecklist::fromArray($checks);
            $array = $checklist->toArray();

            expect($array)->toBe($checks);
        });

    });

    describe('getSummary', function () {

        it('returns ready message when all ok', function () {
            $checks = [
                'check1' => ['status' => 'ok', 'count' => 0, 'message' => 'OK 1'],
                'check2' => ['status' => 'ok', 'count' => 0, 'message' => 'OK 2'],
            ];

            $checklist = ClosingChecklist::fromArray($checks);
            $summary = $checklist->getSummary();

            expect($summary['can_close'])->toBeTrue();
            expect($summary['errors'])->toBe([]);
            expect($summary['warnings'])->toBe([]);
            expect($summary['summary'])->toBe('Siap untuk ditutup');
        });

        it('returns warning summary when warnings exist', function () {
            $checks = [
                'check1' => ['status' => 'ok', 'count' => 0, 'message' => 'OK'],
                'check2' => ['status' => 'warning', 'count' => 2, 'message' => 'Warning 1'],
                'check3' => ['status' => 'warning', 'count' => 1, 'message' => 'Warning 2'],
            ];

            $checklist = ClosingChecklist::fromArray($checks);
            $summary = $checklist->getSummary();

            expect($summary['can_close'])->toBeTrue();
            expect($summary['warnings'])->toHaveCount(2);
            expect($summary['summary'])->toContain('2 peringatan');
        });

        it('returns error summary when errors exist', function () {
            $checks = [
                'check1' => ['status' => 'error', 'count' => 5, 'message' => 'Error 1'],
                'check2' => ['status' => 'error', 'count' => 3, 'message' => 'Error 2'],
            ];

            $checklist = ClosingChecklist::fromArray($checks);
            $summary = $checklist->getSummary();

            expect($summary['can_close'])->toBeFalse();
            expect($summary['errors'])->toHaveCount(2);
            expect($summary['summary'])->toContain('2 masalah');
        });

    });

});
