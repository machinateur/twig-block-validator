<?php

declare(strict_types=1);

return [
    'prefix'          => 'Isolated',
    'exclude-classes' => [\Composer\InstalledVersions::class],
    'exclude-files'   => ['vendor/composer/InstalledVersions.php'],
    'patchers'        => [
        static function (string $filePath, string $prefix, string $content): string {
            //
            // PHP-Parser patch for yaml/yml files (redundant).
            //
            //if (\str_ends_with($filePath, '.yaml')
            //    || \str_ends_with($filePath, '.yml')
            //) {
            //    return \preg_replace(
            //        "%Machinateur\\\\TwigBlockValidator\\\\%",
            //        $prefix . '\\Machinateur\\TwigBlockValidator\\',
            //        $content,
            //    );
            //}

            return $content;
        },
    ],
];
