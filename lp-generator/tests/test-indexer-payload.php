<?php
/**
 * Indexer payload and delay tests.
 *
 * @package LPG
 */

declare(strict_types=1);

use LPG\Indexer;
use LPG\Utils;

$push = Indexer::build_push_body( 'https://example.test/feed/' );
lpg_assert_same(
	array( 'hub.mode' => 'publish', 'hub.url' => 'https://example.test/feed/' ),
	$push,
	'PuSH publish body'
);
lpg_assert_true( in_array( Indexer::PUSH_HUB, Indexer::RSS_ENDPOINTS, true ), 'Google WebSub tetap menjadi provider RSS' );
lpg_assert_true( in_array( 'https://pubsubhubbub.superfeedr.com/', Indexer::RSS_ENDPOINTS, true ), 'Superfeedr ditambahkan sebagai provider RSS gratis' );
lpg_assert_true( in_array( 'https://websubhub.com/hub', Indexer::RSS_ENDPOINTS, true ), 'WebSubHub ditambahkan' );
lpg_assert_true( in_array( 'http://rpc.pingomatic.com/', Indexer::RSS_ENDPOINTS, true ), 'Ping-o-Matic ditambahkan' );
lpg_assert_true( in_array( 'http://rpc.weblogs.com/RPC2', Indexer::RSS_ENDPOINTS, true ), 'Weblogs.com ditambahkan' );
lpg_assert_true( in_array( 'http://ping.blo.gs/', Indexer::RSS_ENDPOINTS, true ), 'Blo.gs ditambahkan' );
lpg_assert_true( in_array( 'http://blogsearch.google.com/ping/RPC2', Indexer::RSS_ENDPOINTS, true ), 'Google Blog Search Ping ditambahkan' );

$invalid_ping = Indexer::ping_rss_endpoint( 'not-a-url', 'https://example.test/feed/' );
lpg_assert_same( 'invalid', $invalid_ping['type'], 'ping menolak URL invalid' );

$payload = Indexer::build_indexnow_payload(
	'https://example.test/lp/',
	'lpgabcdef0123456789abcdef01234567',
	'https://example.test/lpgabcdef0123456789abcdef01234567.txt',
	'example.test'
);
lpg_assert_same( 'example.test', $payload['host'], 'IndexNow host' );
lpg_assert_same( array( 'https://example.test/lp/' ), $payload['urlList'], 'IndexNow urlList' );
lpg_assert_same( 'https://example.test/lpgabcdef0123456789abcdef01234567.txt', $payload['keyLocation'], 'IndexNow keyLocation eksplisit' );

lpg_assert_true( Indexer::is_valid_key( 'lpgabcdef0123456789abcdef01234567' ), 'key valid' );
lpg_assert_true( ! Indexer::is_valid_key( '../evil' ), 'path traversal ditolak' );
lpg_assert_true( ! Indexer::is_valid_key( 'short' ), 'key terlalu pendek ditolak' );

lpg_assert_same( 10, Utils::index_delay( array( 'index_delay' => 10 ) ), 'jeda default 10 detik' );
lpg_assert_same( 300, Utils::index_delay( array( 'index_delay' => 9999 ) ), 'jeda di-clamp 300' );
lpg_assert_same( 10, Utils::index_delay( array() ), 'jeda fallback 10' );
lpg_assert_same( 30, Utils::index_delay( array( 'index_delay' => 30 ) ), 'jeda custom dipakai' );

// Simulasi penjadwalan: publish pada T, index pada T + 10.
$published_at = 1_700_000_000;
$scheduled_at = $published_at + Utils::index_delay( array( 'index_delay' => 10 ) );
lpg_assert_same( 10, $scheduled_at - $published_at, 'index job tepat +10 detik setelah publish' );
