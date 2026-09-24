<?php

/**
 * Write a metalanguage package that derives from the test one.
 *
 *   php tools/make-derived-package.php     # tests/cypress/fixtures/derived.zip
 *
 * Step 4.5's fixture. A language may say it is built on another, and two things
 * here have to know it: a rule may be written for a concept of either, and the
 * generators list has to show what was written for the parent when somebody
 * asks about the child.
 *
 * Built from the Testlang package rather than by hand, so it cannot drift from
 * the concepts the import guard is going to ask about - a derived language that
 * dropped or renamed one of its parent's is refused, which is the whole reason
 * merging their concepts is safe.
 *
 * It adds one concept of its own, which Testlang's fixture does not have. That
 * is what makes the union visible: a selector list that offered only the
 * parent's concepts and one that offered only the child's would both be wrong,
 * and with no addition they would look the same.
 */

declare(strict_types=1);

\defined('_JEXEC') || \define('_JEXEC', 1);

require __DIR__ . '/../vendor/autoload.php';

use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Package\MetalanguagePackage;
use Yepr\Gen\Core\Package\PackageManifest;
use Yepr\Gen\Core\Package\PackageReader;

$source = \dirname(__DIR__) . '/tests/cypress/fixtures/metalanguage.zip';

if (!is_file($source)) {
    fwrite(STDERR, "Run php tools/make-test-package.php first: this derives from what it writes.\n");
    exit(1);
}

$reader   = PackageReader::fromZip($source);
$problems = $reader->problems();

if ($problems !== []) {
    fwrite(STDERR, "The package to derive from is not readable:\n  " . implode("\n  ", $problems) . "\n");
    exit(1);
}

$parent = $reader->manifest();

$name    = 'DerivedTestlang';
$version = '1.0';
$oldRoot = $parent->formRoot;
$newRoot = MetalanguagePackage::installRoot($name, $version);

$files = new FileCollection();

// Every file, with the install root rewritten inside it: a subform's
// `formsource` is resolved against the site root, so a form copied without this
// would load the parent's file instead of this language's.
foreach ($reader->files() as $path => $contents) {
    if ($path === MetalanguagePackage::MANIFEST) {
        continue;
    }

    $files->add($path, str_replace($oldRoot, $newRoot, $contents));
}

// The one thing it adds, and the reason this fixture exists.
$files->add(
    MetalanguagePackage::formPath('Extra'),
    '<?xml version="1.0" encoding="utf-8"?>' . "\n"
    . '<form><fieldset addfieldprefix="Yepr\Gen\Joomla\Form\Field">'
    . '<field name="extraName" type="text" label="YEPR_DERIVEDTESTLANG_EXTRA_FIELD_EXTRANAME_LABEL" />'
    . '</fieldset></form>' . "\n"
);

$files->replace(MetalanguagePackage::MODEL, json_encode(
    [
        'name'      => $name,
        'version'   => $version,
        'dependsOn' => ['dependsOn0' => ['language' => $parent->key . '|' . $parent->version]],
    ],
    \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR
) . "\n");

$hashes = [];

foreach ($files as $path => $contents) {
    $hashes[$path] = hash('sha256', $contents);
}

ksort($hashes);

$files->add(MetalanguagePackage::MANIFEST, (new PackageManifest(
    $name,
    $name,
    $version,
    $parent->root,
    $newRoot,
    $parent->language,
    $parent->tag,
    // Its parent's, unchanged - a child that renamed one is what the import
    // refuses - plus its own.
    [...$parent->concepts, ['key' => 'c-extra', 'name' => 'Extra']],
    $hashes,
    MetalanguagePackage::FORMAT,
    gmdate('c'),
    [['key' => $parent->key, 'version' => $parent->version]]
))->toJson());

$target = \dirname(__DIR__) . '/tests/cypress/fixtures/derived.zip';

(new ZipWriter())->write($files, $target);

printf(
    "wrote %s: %s %s deriving from %s %s (%d files)\n",
    $target,
    $name,
    $version,
    $parent->key,
    $parent->version,
    \count($files)
);
