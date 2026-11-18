<?php
/**
 * Test AI client helper functions.
 *
 * @package LPG
 */

declare(strict_types=1);

use LPG\AiClient;

lpg_assert_same(
	'{"status":"ok"}',
	AiClient::strip_fences( "```json\n{\"status\":\"ok\"}\n```" ),
	'strip_fences membuang ```json blok markdown'
);

lpg_assert_same(
	'{"status":"ok"}',
	AiClient::strip_fences( "```\n{\"status\":\"ok\"}\n```" ),
	'strip_fences membuang ``` polos'
);

lpg_assert_same(
	'{"status":"ok"}',
	AiClient::strip_fences( '  {"status":"ok"}  ' ),
	'strip_fences mempertahankan JSON bersih'
);
