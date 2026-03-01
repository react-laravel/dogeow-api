<?php

/**
 * Coverage Check Script
 * Checks if code coverage meets the minimum threshold
 */
$coverageFile = $argv[1] ?? 'coverage/clover.xml';
$minCoverage = $argv[2] ?? 80; // Default 80% coverage

if (! file_exists($coverageFile)) {
    echo "Error: Coverage file not found at {$coverageFile}\n";
    exit(1);
}

$xml = simplexml_load_file($coverageFile);
if ($xml === false) {
    echo "Error: Could not parse coverage file\n";
    exit(1);
}

// Get metrics from clover XML. PHPUnit 11 writes camel-less attribute names
// like "coveredstatements", while older generators used "covered-statements".
$metrics = $xml->project->metrics;
$totalStatements = (int) ($metrics['statements'] ?? 0);
$coveredStatements = (int) ($metrics['coveredstatements'] ?? $metrics['covered-statements'] ?? 0);

if ($totalStatements === 0) {
    echo "Error: No statements found in coverage data\n";
    exit(1);
}

$percentage = round(($coveredStatements / $totalStatements) * 100, 2);

echo "Code Coverage: {$percentage}%\n";
echo "Total Statements: {$totalStatements}\n";
echo "Covered Statements: {$coveredStatements}\n";
echo "Minimum Required: {$minCoverage}%\n";

if ($percentage < $minCoverage) {
    echo "\nError: Coverage {$percentage}% is below minimum {$minCoverage}%\n";
    exit(1);
}

echo "\nCoverage check passed!\n";
exit(0);
