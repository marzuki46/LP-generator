<?php
/**
 * Tiny test runner. Usage: php tests/run.php
 *
 * @package LPG
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$GLOBALS['lpg_tests_passed'] = 0;
$GLOBALS['lpg_tests_failed'] = 0;

/**
 * Assert equality.
 *
 * @param mixed  $expected Expected.
 * @param mixed  $actual   Actual.
 * @param string $label    Test label.
 */
function lpg_assert_same( $expected, $actual, string $label ): void {
	if ( $expected === $actual ) {
		++$GLOBALS['lpg_tests_passed'];
		echo "  PASS  {$label}\n";
		return;
	}
	++$GLOBALS['lpg_tests_failed'];
	echo "  FAIL  {$label}\n";
	echo '        expected: ' . var_export( $expected, true ) . "\n";
	echo '        actual:   ' . var_export( $actual, true ) . "\n";
}

/**
 * Assert truthiness.
 *
 * @param mixed  $condition Condition.
 * @param string $label     Test label.
 */
function lpg_assert_true( $condition, string $label ): void {
	lpg_assert_same( true, (bool) $condition, $label );
}

foreach ( glob( __DIR__ . '/test-*.php' ) ?: array() as $file ) {
	echo "\n== " . basename( $file ) . " ==\n";
	require_once $file;
}

$passed = (int) $GLOBALS['lpg_tests_passed'];
$failed = (int) $GLOBALS['lpg_tests_failed'];
echo "\nResult: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
