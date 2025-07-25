<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/database/migrations',
        __DIR__.'/tests',
    ])
    // uncomment to reach your current PHP version
    // ->withPhpSets()
    ->withPreparedSets(
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        carbon: true,
    )
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(9)
    ->withTypeCoverageLevel(9)
    ->withCodingStyleLevel(9)
    ->withIndent()
    ->withIndent()
    ->withPhpSets(
        php82: true,
    );
