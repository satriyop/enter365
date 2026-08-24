<?php

declare(strict_types=1);

/**
 * Browser smoke for feature-pack / industry routes.
 *
 * Visits manufacturing, solar, and electrical-panel SPA pages under the
 * current FEATURE_PRESET. Pages that require a disabled pack should still
 * load without JS errors (app may redirect or show empty gated state).
 *
 * Prerequisites: admin@example.com / password, SPA_URL, seeded DB.
 */
it('loads work orders list without JS errors', function () {
    skipUnlessLiveFeature('work_orders');
    $page = loginAndVisit('/work-orders');

    $page->assertNoJavascriptErrors();
});

it('loads boms list without JS errors', function () {
    skipUnlessLiveFeature('bom');
    $page = loginAndVisit('/boms');

    $page->assertNoJavascriptErrors();
});

it('loads material requisitions list without JS errors', function () {
    skipUnlessLiveFeature('material_requisitions');
    $page = loginAndVisit('/manufacturing/material-requisitions');

    $page->assertNoJavascriptErrors();
});

it('loads mrp runs list without JS errors', function () {
    skipUnlessLiveFeature('mrp');
    $page = loginAndVisit('/manufacturing/mrp');

    $page->assertNoJavascriptErrors();
});

it('loads solar calculator without JS errors', function () {
    skipUnlessLiveFeature('solar_proposals');
    $page = loginAndVisit('/solar-calculator');

    $page->assertNoJavascriptErrors();
});

it('loads electrical panel cost optimization page without JS errors', function () {
    skipUnlessLiveFeature('electrical_panel');
    $page = loginAndVisit('/addons/electrical-panel/cost-optimization');

    $page->assertNoJavascriptErrors();
});

it('loads bom templates settings without JS errors', function () {
    skipUnlessLiveFeature('bom');
    $page = loginAndVisit('/settings/bom-templates');

    $page->assertNoJavascriptErrors();
});
