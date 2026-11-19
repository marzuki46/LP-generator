<?php
/**
 * Static guard for the admin runner's AJAX payload.
 *
 * The behavioral test lives in test-admin-runner.js. This guard keeps the
 * PHP suite honest in environments where Node is not installed.
 *
 * @package LPG
 */

declare(strict_types=1);

$source = (string) file_get_contents( dirname( __DIR__ ) . '/admin/js/admin.js' );
$handler = strpos( $source, "$(document).on('click', '[data-lpgen-action]'" );
$lpg_payload = strpos( $source, 'data.lpgen = formValues;' );

lpg_assert_true( false !== $handler && false !== $lpg_payload && $lpg_payload > $handler, 'payload formValues berada di dalam handler tombol' );
lpg_assert_true( false !== strpos( $source, 'var data = {};' ), 'objek payload diinisialisasi sebelum dipakai' );
lpg_assert_true( false === strpos( $source, 'var data = data || {};' ), 'deklarasi payload yang terkena hoisting tidak boleh kembali' );
