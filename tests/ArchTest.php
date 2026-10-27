<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('it declares strict types')
    ->expect('AbeTwoThree\LaravelLangExport')
    ->toUseStrictTypes();
