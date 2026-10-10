<?php

declare(strict_types=1);

namespace Yepr\Component\Gengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Gengen\Administrator\Generator\GeneratorDefinition;
use Yepr\Component\Gengen\Administrator\Generator\GeneratorManifest;
use Yepr\Component\Gengen\Administrator\Generator\Model\ModelledGenerator;
use Yepr\Component\Gengen\Administrator\Generator\Target\JoomlaGeneratorTarget;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Pipeline;
use Yepr\Gen\Core\Rule\Vocabulary;

/**
 * A generator that extends another: the LedgerLang generator, modelled here (step 5.9).
 *
 * The Extension Generator ships a generator for LedgerLang that extends its
 * Joomla 6 generator: every rule of the parent, plus three of its own for the
 * account roles and the posting rules. Modelled here it is those three rules
 * and the word `extends`. The claim is the one 2.3 made for the Joomla 6
 * generator: the modelled one and the shipped one are the same generator,
 * rule for rule, and the Extension Generator reads the package as what it is.
 *
 * `ledgerlang.rules.json` and `ledgerlang.package.json` are the Extension
 * Generator's, copied by `composer import-vocabularies`. The model is
 * `ledgerlang.generator.json`, made from `ledgerlang.manifest.json` by
 * `php tools/import-generator.php tests/Fixtures/ledgerlang.manifest.json`.
 */
final class ExtendedGeneratorTest extends TestCase
{
    private ?string $zip = null;

    protected function tearDown(): void
    {
        if ($this->zip !== null && is_file($this->zip)) {
            unlink($this->zip);
        }
    }

    private function fixture(string $name): string
    {
        return \dirname(__DIR__) . '/Fixtures/' . $name;
    }

    private function generator(): ModelledGenerator
    {
        return ModelledGenerator::fromFile($this->fixture('ledgerlang.generator.json'));
    }

    private function package(): FileCollection
    {
        $generator = $this->generator();
        $files     = (new Pipeline())->run(
            $generator,
            (new JoomlaGeneratorTarget(JoomlaGeneratorTarget::defaultTemplateRoot()))->target()
        );

        $files->add(GeneratorManifest::FILE, GeneratorManifest::toJson($generator->definition, 'LedgerLang', ''));

        return $files;
    }

    private function extengen(): string
    {
        return (string) getenv('EXTENGEN_PATH') ?: \dirname(__DIR__, 2) . '/../Exten-gen';
    }

    public function testTheFormKeepsWhatAGeneratorExtends(): void
    {
        $definition = GeneratorDefinition::fromFormData(
            ['generator_name' => 'X', 'target' => 'joomla6', 'extends' => ' joomla6 ']
        );

        $this->assertSame('joomla6', $definition->extends);
        $this->assertSame('joomla6', $definition->toFormData()['extends']);
        $this->assertArrayNotHasKey(
            'extends',
            GeneratorDefinition::fromFormData(['generator_name' => 'Y', 'target' => 'joomla6'])->toFormData(),
            'a generator that extends nothing saves what it saved before'
        );
    }

    public function testItsManifestSaysWhatItExtendsAndAsksForFormatTwo(): void
    {
        $manifest = json_decode($this->package()->all()[GeneratorManifest::FILE], true, 512, \JSON_THROW_ON_ERROR);
        $shipped  = json_decode(
            (string) file_get_contents($this->fixture('ledgerlang.package.json')),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $this->assertSame(GeneratorManifest::FORMAT, $manifest['format']);
        $this->assertSame('joomla6', $manifest['extends']);

        // What the Extension Generator says about the one it ships.
        foreach (['format', 'name', 'target', 'extends', 'metalanguage'] as $key) {
            $this->assertSame($shipped[$key], $manifest[$key], $key);
        }
    }

    /**
     * Only the rules it adds: the parent's are not copied into it.
     */
    public function testItWritesTheRuleFileTheExtensionGeneratorShips(): void
    {
        $rules = $this->package()->get($this->generator()->definition->ruleFilePath());

        $this->assertSame((string) file_get_contents($this->fixture('ledgerlang.rules.json')), $rules);
    }

    public function testTheModelledGeneratorIsCurrent(): void
    {
        $committed = (string) file_get_contents($this->fixture('ledgerlang.generator.json'));

        exec(
            'php ' . escapeshellarg(\dirname(__DIR__, 2) . '/tools/import-generator.php') . ' '
            . escapeshellarg($this->fixture('ledgerlang.manifest.json')) . ' 2>&1',
            $output,
            $status
        );

        $this->assertSame(0, $status, implode("\n", $output));
        $this->assertSame(
            $committed,
            (string) file_get_contents($this->fixture('ledgerlang.generator.json')),
            'Run php tools/import-generator.php tests/Fixtures/ledgerlang.manifest.json and commit the result.'
        );
    }

    public function testExtenGenReadsItAsAGeneratorThatExtendsJoomla6(): void
    {
        $reader = $this->extengen()
            . '/src/administrator/components/com_extengen/src/Generator/Imported/GeneratorPackage.php';

        if (!is_file($reader)) {
            $this->markTestSkipped(
                'Exten-gen is not available to read the package with.'
                . ' Clone it beside this repository, or set EXTENGEN_PATH.'
            );
        }

        require_once $reader;

        $this->zip = tempnam(sys_get_temp_dir(), 'gengen') . '.zip';

        (new ZipWriter())->write($this->package(), $this->zip);

        $class   = 'Yepr\\Component\\Extengen\\Administrator\\Generator\\Imported\\GeneratorPackage';
        $package = $class::fromZip($this->zip);

        $this->assertSame('joomla6', $package->extends);
        $this->assertSame('LedgerLang', $package->metalanguageKey);
        $this->assertSame(
            (string) file_get_contents($this->fixture('ledgerlang.rules.json')),
            $package->rules->toJson() . "\n"
        );

        $vocabulary = Vocabulary::fromFile(
            $this->extengen() . '/src/administrator/components/com_extengen/src/Generator/Rules/joomla6.vocabulary.json'
        );
        $prefixes = array_map(static fn (array $group): string => $group['prefix'], $package->groups);

        $this->assertSame([], $package->problems($vocabulary, $prefixes));
    }
}
