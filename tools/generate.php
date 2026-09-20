<?php

/**
 * Generate a generator.
 *
 *   php tools/generate.php                                  # into build/generated/
 *   php tools/generate.php tests/Fixtures/joomla6.generator.json /tmp/out
 *
 * Runs a modelled generator through the core's pipeline and writes what comes
 * out: a rule file and the classes that carry it. Nothing here is Joomla-aware
 * beyond the shape of what it produces, which is why this is a command rather
 * than a screen - the component that puts a button on it is step 2.4.
 */

declare(strict_types=1);

require_once \dirname(__DIR__) . '/tests/bootstrap.php';

use Yepr\Component\Gengen\Administrator\Generator\Model\ModelledGenerator;
use Yepr\Component\Gengen\Administrator\Generator\Target\JoomlaGeneratorTarget;
use Yepr\Gen\Core\Pipeline;

$root  = \dirname(__DIR__);
$model = $argv[1] ?? $root . '/tests/Fixtures/joomla6.generator.json';
$into  = $argv[2] ?? $root . '/build/generated';

if (!is_file($model)) {
    fwrite(STDERR, "No modelled generator at {$model}.\n");
    exit(1);
}

$files = (new Pipeline())->run(
    ModelledGenerator::fromFile($model),
    (new JoomlaGeneratorTarget(JoomlaGeneratorTarget::defaultTemplateRoot()))->target()
);

foreach ($files as $path => $contents) {
    $destination = $into . '/' . $path;
    $directory   = \dirname($destination);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        fwrite(STDERR, "Cannot create {$directory}.\n");
        exit(1);
    }

    file_put_contents($destination, $contents);

    printf("  %s (%d bytes)\n", $path, \strlen($contents));
}

printf("\n%d file(s) into %s\n", \count($files), $into);
