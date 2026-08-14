<?php

declare(strict_types=1);

it('does not keep MovementRecorder as a production stock-write path', function () {
    expect(file_exists(app_path('Domain/Inventory/Movements/MovementRecorder.php')))->toBeFalse()
        ->and(class_exists(\App\Domain\Inventory\Movements\MovementRecorder::class, false))->toBeFalse();
});

it('has no production caller of MovementRecorder', function () {
    $matches = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if ($contents !== false && str_contains($contents, 'MovementRecorder')) {
            $matches[] = $file->getPathname();
        }
    }

    expect($matches)->toBe([]);
});
