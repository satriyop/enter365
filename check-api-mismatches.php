<?php

/**
 * Script to check for mismatches between API Resources and OpenAPI schema
 *
 * Usage: php check-api-mismatches.php
 */

require __DIR__.'/vendor/autoload.php';

$apiJson = json_decode(file_get_contents(__DIR__.'/api.json'), true);
$schemas = $apiJson['components']['schemas'] ?? [];

$resourceDir = __DIR__.'/app/Http/Resources/Api/V1';
$resourceFiles = glob($resourceDir.'/*Resource.php');

$mismatches = [];

foreach ($resourceFiles as $file) {
    $className = basename($file, '.php');
    $schemaName = $className;

    if (! isset($schemas[$schemaName])) {
        continue;
    }

    $schema = $schemas[$schemaName]['properties'] ?? [];
    $resourceCode = file_get_contents($file);

    // Extract fields from toArray method
    if (preg_match('/public function toArray.*?\{.*?return \[(.*?)\];/s', $resourceCode, $matches)) {
        $toArrayContent = $matches[1];

        // Extract field names from array keys
        preg_match_all("/'([^']+)'\s*=>/", $toArrayContent, $fieldMatches);
        $resourceFields = array_unique($fieldMatches[1]);

        // Compare with schema
        $schemaFields = array_keys($schema);

        $missingInSchema = array_diff($resourceFields, $schemaFields);
        $missingInResource = array_diff($schemaFields, $resourceFields);

        if (! empty($missingInSchema) || ! empty($missingInResource)) {
            $mismatches[$className] = [
                'resource_fields' => $resourceFields,
                'schema_fields' => $schemaFields,
                'missing_in_schema' => $missingInSchema,
                'missing_in_resource' => $missingInResource,
            ];
        }
    }
}

// Output results
echo "=== API Resource vs OpenAPI Schema Mismatches ===\n\n";

if (empty($mismatches)) {
    echo "✅ No mismatches found!\n";
    exit(0);
}

foreach ($mismatches as $resource => $data) {
    echo "📋 {$resource}\n";
    echo str_repeat('-', 80)."\n";

    if (! empty($data['missing_in_schema'])) {
        echo "❌ Fields in Resource but NOT in Schema:\n";
        foreach ($data['missing_in_schema'] as $field) {
            echo "   - {$field}\n";
        }
    }

    if (! empty($data['missing_in_resource'])) {
        echo "⚠️  Fields in Schema but NOT in Resource:\n";
        foreach ($data['missing_in_resource'] as $field) {
            echo "   - {$field}\n";
        }
    }

    echo "\n";
}

echo "\nTotal resources with mismatches: ".count($mismatches)."\n";
