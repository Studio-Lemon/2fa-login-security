<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
   $rectorConfig->paths([
      __DIR__ . '/2fa-login-security.php',
      __DIR__ . '/classes',
   ]);

   $rectorConfig->skip([
      __DIR__ . '/vendor',
      __DIR__ . '/node_modules',
      __DIR__ . '/css',
      __DIR__ . '/js',
      __DIR__ . '/languages',
      __DIR__ . '/views',
   ]);

   $rectorConfig->sets([
      SetList::CODE_QUALITY,
      SetList::DEAD_CODE,
      SetList::EARLY_RETURN,
      SetList::TYPE_DECLARATION,
   ]);
};
