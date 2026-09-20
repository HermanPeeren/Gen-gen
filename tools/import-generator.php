<?php

/**
 * Build a modelled generator out of an existing rule file.
 *
 *   php tools/import-generator.php
 *   php tools/import-generator.php tests/Fixtures/joomla6.manifest.json
 *
 * This is an import, and saying so is the point. The model is meant to be the
 * source: somebody fills in the forms and a generator comes out. Exten-gen's
 * generator already exists, written by hand and then turned into rules at 2.1,
 * and the only way to check that a modelled generator is the same generator is
 * to start from the one that is already known to work.
 *
 * So the rules come from the target's committed rule file and the rest - what
 * the generator is called, where its source goes, which classes it is made of -
 * comes from a small manifest beside it, because none of that is recoverable
 * from a rule file. A rule id says `admin.mvc.index.model`; nothing in it says
 * where the prefix stops.
 *
 * The result is committed, and `GeneratedGeneratorTest` fails when running this
 * again would change it. That is what keeps the fixture from quietly ageing
 * into a simpler generator than the one Exten-gen actually runs.
 */

declare(strict_types=1);

require_once \dirname(__DIR__) . '/tests/bootstrap.php';

use Yepr\Component\Gengen\Administrator\Generator\GeneratorDefinition;
use Yepr\Gen\Core\Rule\RuleSet;

$root     = \dirname(__DIR__);
$manifest = $argv[1] ?? $root . '/tests/Fixtures/joomla6.manifest.json';

$declared = json_decode((string) file_get_contents($manifest), true, 512, \JSON_THROW_ON_ERROR);

if (!\is_array($declared)) {
    fwrite(STDERR, "The manifest at {$manifest} is not an object.\n");
    exit(1);
}

$rulesPath = \dirname($manifest) . '/' . $declared['target'] . '.rules.json';

if (!is_file($rulesPath)) {
    fwrite(STDERR, "No rule file at {$rulesPath}. Run composer import-vocabularies first.\n");
    exit(1);
}

$definition = GeneratorDefinition::fromRuleSet(
    (string) $declared['generator_name'],
    (string) $declared['target'],
    RuleSet::fromFile($rulesPath),
    (array) $declared['group'],
    (string) $declared['output_path'],
    (string) $declared['php_namespace']
);

$out = \dirname($manifest) . '/' . $declared['target'] . '.generator.json';

file_put_contents(
    $out,
    json_encode(
        $definition->toFormData(),
        \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR
    ) . "\n"
);

printf(
    "%s: %d rules in %d groups -> %s\n",
    $definition->name,
    \count($definition->rules()),
    \count($definition->groups()),
    basename($out)
);
