<?php

declare(strict_types=1);

return [
    'prefix'          => 'Isolated',
    'exclude-classes' => [\Composer\InstalledVersions::class],
    'exclude-files'   => ['vendor/composer/InstalledVersions.php'],
];
