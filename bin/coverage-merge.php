<?php
/**
 * Merge the unit and functional coverage, print the line coverage and
 * fail below a floor.
 *
 *   php bin/coverage-merge.php <floor> <file.cov>...
 *
 * Each suite runs under its own bootstrap (Brain\Monkey stubs for the unit
 * suite, a real WordPress for the functional one), so neither alone measures
 * the plugin: the merged figure is the one the floor holds.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$floor = (float) ( $argv[1] ?? 0 );
$files = array_slice( $argv, 2 );
if ( array() === $files ) {
	fwrite( STDERR, "Usage: php bin/coverage-merge.php <floor> <file.cov>...\n" );
	exit( 2 );
}

$merged = null;
foreach ( $files as $file ) {
	$coverage = include $file;
	if ( null === $merged ) {
		$merged = $coverage;
	} else {
		$merged->merge( $coverage );
	}
}

$report  = $merged->getReport();
$covered = $report->numberOfExecutedLines();
$total   = $report->numberOfExecutableLines();
$percent = $total > 0 ? 100 * $covered / $total : 0.0;

printf( "Lines: %.2f%% (%d/%d), floor %.2f%%\n", $percent, $covered, $total, $floor );
if ( $total === 0 ) {
	fwrite( STDERR, "No executable line was measured: the coverage files are empty.\n" );
	exit( 1 );
}
exit( $percent + 1e-9 < $floor ? 1 : 0 );
