<?php

/**
 * Write the LedgerLang generator package for the browser spec to import.
 *
 *   php tools/make-generator-package.php      # tests/cypress/fixtures/ledgerlang-generator.zip
 *
 * The Extension Generator ships its LedgerLang generator as two files,
 * `ledgerlang.package.json` and `ledgerlang.rules.json`. Zipped together with
 * the modelling manifest `import-generator.php` reads (`ledgerlang.manifest.json`,
 * which holds the groups, the output path and the namespace a rule file cannot
 * say), that is what somebody hands the Import button to get the model back.
 *
 * Built from the committed fixtures rather than committed as a binary, for the
 * reason `make-test-package.php` gives: a zip nobody can read in a diff goes
 * stale without a word. The fixtures are copies of the Extension Generator's
 * files, kept current by `composer import-vocabularies`.
 */

declare(strict_types=1);

\defined('_JEXEC') || \define('_JEXEC', 1);

require __DIR__ . '/../vendor/autoload.php';

use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Output\ZipWriter;

$root     = \dirname(__DIR__);
$fixtures = $root . '/tests/Fixtures';
$out      = $root . '/tests/cypress/fixtures/ledgerlang-generator.zip';

$files = new FileCollection();

foreach (['ledgerlang.package.json', 'ledgerlang.rules.json', 'ledgerlang.manifest.json'] as $name) {
    $files->add($name, (string) file_get_contents($fixtures . '/' . $name));
}

if (is_file($out)) {
    unlink($out);
}

(new ZipWriter())->write($files, $out);

printf("%s: %d files, %d bytes\n", basename($out), \count($files), (int) filesize($out));

// And the same package with one rule selecting something Joomla 6 does not
// publish, for the refusal: `ledgerlang-generator-broken.zip`. Same name, so
// the spec can check that a refused import left the stored one alone.
$rules = json_decode((string) file_get_contents($fixtures . '/ledgerlang.rules.json'), true, 512, \JSON_THROW_ON_ERROR);

$rules[0]['for'] = 'balloons';

$broken = new FileCollection();

$broken->add('ledgerlang.package.json', (string) file_get_contents($fixtures . '/ledgerlang.package.json'));
$broken->add('ledgerlang.rules.json', json_encode($rules, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n");
$broken->add('ledgerlang.manifest.json', (string) file_get_contents($fixtures . '/ledgerlang.manifest.json'));

$brokenOut = \dirname($out) . '/ledgerlang-generator-broken.zip';

if (is_file($brokenOut)) {
    unlink($brokenOut);
}

(new ZipWriter())->write($broken, $brokenOut);

printf("%s: %d files, %d bytes\n", basename($brokenOut), \count($broken), (int) filesize($brokenOut));
