<?php

/**
 * Refresh the test fixtures from the targets they came from.
 *
 *   php tools/import-vocabularies.php
 *   php tools/import-vocabularies.php ../Exten-gen
 *
 * The suite's proof is a round trip of a real generator: Exten-gen's own rule
 * set, twenty-seven rules of it, through the form shape and back. That is only
 * a proof while the copy here is the file Exten-gen actually runs. A fixture
 * that quietly ages into a simpler rule set than the real one still passes, and
 * stops meaning anything.
 *
 * So it is copied by a command rather than by hand, and the command says what
 * it took and from where.
 *
 * Nothing imports automatically: a fixture that changed underneath a test run
 * would turn "this still works" into "this works today". Run it, look at the
 * diff, and commit it like any other change.
 */

declare(strict_types=1);

$root    = \dirname(__DIR__);
$sources = \array_slice($argv, 1);

if ($sources === []) {
    $sources = [$root . '/../Exten-gen'];
}

$imported = 0;

foreach ($sources as $source) {
    $rules = glob($source . '/src/administrator/components/*/src/Generator/Rules/*.json') ?: [];

    if ($rules === []) {
        fwrite(STDERR, "No rule files or vocabularies under {$source}.\n");

        continue;
    }

    foreach ($rules as $path) {
        $target = $root . '/tests/Fixtures/' . basename($path);

        if (!copy($path, $target)) {
            fwrite(STDERR, "Could not copy {$path}.\n");

            continue;
        }

        printf("%-32s from %s\n", basename($path), realpath($source) ?: $source);

        $imported++;
    }
}

if ($imported === 0) {
    fwrite(STDERR, "Nothing imported.\n");

    exit(1);
}

printf("\n%d file(s) imported. Review the diff before committing.\n", $imported);
