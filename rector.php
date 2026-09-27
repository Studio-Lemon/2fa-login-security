<?php

/**
 * Rector configuration.
 *
 * @package TFAuthLS
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rector_config): void {
	$rector_config->paths(
		array(
			__DIR__ . '/2fa-login-security.php',
			__DIR__ . '/classes',
		)
	);

	$rector_config->skip(
		array(
			__DIR__ . '/vendor',
			__DIR__ . '/node_modules',
			__DIR__ . '/css',
			__DIR__ . '/js',
			__DIR__ . '/languages',
			__DIR__ . '/views',
		)
	);

	$rector_config->sets(
		array(
			SetList::CODE_QUALITY,
			SetList::DEAD_CODE,
			SetList::EARLY_RETURN,
			SetList::TYPE_DECLARATION,
		)
	);
};
