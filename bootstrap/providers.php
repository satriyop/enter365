<?php

return [
    App\Providers\AccountingServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\StrategyServiceProvider::class,
    // Industry add-ons (conditional internal registration)
    App\Providers\Addons\ElectricalPanelServiceProvider::class,
    App\Providers\Addons\SolarServiceProvider::class,
];
