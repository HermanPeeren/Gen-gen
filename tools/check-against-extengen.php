<?php

/**
 * Run the generated generator, and compare its output with the hand-written one's.
 *
 *   php tools/check-against-extengen.php
 *   php tools/check-against-extengen.php ../Exten-gen
 *
 * This is step 2.3's acceptance criterion, and it is not a judgement call. The
 * generator Gen-gen produces is run over Exten-gen's three golden models, and
 * every file it produces is compared byte for byte with the output Stage 1
 * approved for the hand-written generator. Both directions: nothing missing and
 * nothing extra.
 *
 * **Why a separate process.** The generated classes have the same fully
 * qualified names as Exten-gen's hand-written ones - that is the point, they
 * are meant to be the same classes - so they are required before anything can
 * autoload the originals. A class is defined once per process, so doing this
 * inside the suite would make the result depend on what had already been
 * loaded. A process of its own is the only honest way to ask.
 *
 * The two generators that also emit - AdminGeneral writes language strings,
 * AdminEntities writes the sql - are not generated and are not replaced. They
 * read the committed rule file, which this also checks is byte-identical to the
 * generated one, so it makes no difference which they read.
 */

declare(strict_types=1);

require_once \dirname(__DIR__) . '/tests/bootstrap.php';

use Yepr\Component\Gengen\Administrator\Generator\Model\ModelledGenerator;
use Yepr\Component\Gengen\Administrator\Generator\Target\JoomlaGeneratorTarget;
use Yepr\Gen\Core\Pipeline;
use Yepr\Gen\Core\Testing\GoldenFiles;

$root     = \dirname(__DIR__);
$extengen = $argv[1] ?? ((string) getenv('EXTENGEN_PATH') ?: $root . '/../Exten-gen');

if (!is_file($extengen . '/vendor/autoload.php')) {
    fwrite(STDERR, "No Exten-gen with dependencies installed at {$extengen}.\n");
    fwrite(STDERR, "This check needs the target it generates for: clone it beside this one,\n");
    fwrite(STDERR, "run composer install in it, or point EXTENGEN_PATH at it.\n");
    exit(2);
}

// --- 1. Generate the generator. -------------------------------------------

$generated = sys_get_temp_dir() . '/gengen-' . getmypid();

$files = (new Pipeline())->run(
    ModelledGenerator::fromFile($root . '/tests/Fixtures/joomla6.generator.json'),
    (new JoomlaGeneratorTarget(JoomlaGeneratorTarget::defaultTemplateRoot()))->target()
);

foreach ($files as $path => $contents) {
    $destination = $generated . '/' . $path;

    if (!is_dir(\dirname($destination))) {
        mkdir(\dirname($destination), 0777, true);
    }

    file_put_contents($destination, $contents);
}

printf("generated %d file(s)\n", \count($files));

$componentRoot = $generated . '/administrator/components/com_extengen/src/Generator';

// --- 2. The rule file has to be the one the target committed. -------------

$committedRules = $extengen . '/src/administrator/components/com_extengen/src/Generator/Rules/joomla6.rules.json';
$generatedRules = $componentRoot . '/Rules/joomla6.rules.json';

if (file_get_contents($committedRules) !== file_get_contents($generatedRules)) {
    fwrite(STDERR, "The generated rule file is not the one Exten-gen committed.\n\n");
    fwrite(STDERR, "  generated from  " . $root . "/tests/Fixtures/joomla6.generator.json\n");
    fwrite(STDERR, "  committed at    " . $committedRules . "\n\n");

    // Three causes, in the order they actually happen. The third is the one
    // that is easy to mistake for a real difference: this check reads the
    // target's *current* main, so pushing a change to the rule file after
    // pushing the fixture that carries it fails here until the other side
    // lands. That happened on the first CI run of this check.
    fwrite(STDERR, "Either the model changed and the target has not been regenerated,\n");
    fwrite(STDERR, "or the target's rule file was edited by hand instead of regenerated,\n");
    fwrite(STDERR, "or the fixture here is stale: run composer import-vocabularies,\n");
    fwrite(STDERR, "then composer import-generator, and commit the result.\n");

    exit(1);
}

echo "rule file identical to the committed one\n";

// --- 3. Load the generated classes before the hand-written ones. ----------

require_once $extengen . '/vendor/autoload.php';

if (!\defined('JPATH_ROOT')) {
    \define('JPATH_ROOT', $extengen . '/src');
}

if (!\defined('JPATH_LIBRARIES')) {
    \define('JPATH_LIBRARIES', $extengen . '/src/libraries');
}

foreach (glob($componentRoot . '/Joomla6/*.php') ?: [] as $class) {
    require_once $class;

    printf("  running the generated %s\n", basename($class, '.php'));
}

// --- 4. Run it over every golden model. -----------------------------------

$target = new Yepr\Component\Extengen\Administrator\Generator\Target\Joomla6Target(
    $extengen . '/src/administrator/components/com_extengen/generator_templates'
);

$pipeline = new Pipeline();
$problems = [];
$compared = 0;

foreach (glob($extengen . '/tests/Fixtures/golden/models/*.json') ?: [] as $modelPath) {
    $name     = basename($modelPath, '.json');
    $expected = $extengen . '/tests/Fixtures/golden/expected/' . $name;

    $produced = $pipeline->run(
        Yepr\Component\Extengen\Administrator\Generator\Model\Project::fromJson((string) file_get_contents($modelPath)),
        new Yepr\Gen\Core\Target\Target(
            $target->id(),
            $target->label(),
            $target->validator(),
            ...$target->generators()
        )
    );

    // Every file the generated generator produced is the approved one.
    foreach ($produced as $path => $contents) {
        $golden = $expected . '/' . $path;

        if (!is_file($golden)) {
            $problems[] = $name . ': ' . $path . ' was produced and nothing approved it';

            continue;
        }

        // Compared through the library's own normalisation, which is the
        // function Stage 1's golden test uses: line endings, because the
        // approved files are committed on Windows, and the trailing newline,
        // because a template's output ends with exactly one and a file
        // assembled by implode() ends with none. Comparing raw bytes reported
        // every sql and language file as different while every rendered file
        // matched - which is an accurate description of the normalisation and
        // a poor description of the generator.
        if (GoldenFiles::normalise((string) file_get_contents($golden)) !== GoldenFiles::normalise($contents)) {
            $problems[] = $name . ': ' . $path . ' differs from the approved output';
        }

        $compared++;
    }

    // And every approved file is still produced.
    $length = \strlen($expected) + 1;

    $approved = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($expected, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($approved as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), $length));

        if (!$produced->has($path)) {
            $problems[] = $name . ': ' . $path . ' was approved and is no longer produced';
        }
    }

    printf("  %-18s %d files\n", $name, \count($produced));
}

// --- 5. Clean up and report. ----------------------------------------------

$directory = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($generated, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($directory as $entry) {
    $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
}

rmdir($generated);

if ($problems !== []) {
    fwrite(STDERR, "\n" . \count($problems) . " difference(s):\n  " . implode("\n  ", $problems) . "\n");
    exit(1);
}

printf("\n%d files compared, all identical to the approved output.\n", $compared);
