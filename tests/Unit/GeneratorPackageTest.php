<?php

declare(strict_types=1);

namespace Yepr\Component\Gengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Gengen\Administrator\Generator\GeneratorManifest;
use Yepr\Component\Gengen\Administrator\Generator\Model\ModelledGenerator;
use Yepr\Component\Gengen\Administrator\Generator\Target\JoomlaGeneratorTarget;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Pipeline;
use Yepr\Gen\Core\Rule\Vocabulary;

/**
 * The package says what it is, in the words Exten-gen reads: step 5.4 of Exten-gen's plan.
 *
 * `generator.json` is written here and read by Exten-gen's `GeneratorPackage`.
 * This component may not depend on Exten-gen, so the format has two homes,
 * and the second test is what keeps them agreeing: it builds the package the
 * way the Generate button does and hands it to Exten-gen's reader. Like the
 * acceptance check, it needs Exten-gen beside this repository and says so when
 * it is not there, and CI checks it out so that it always runs.
 *
 * @since  0.4.0
 */
final class GeneratorPackageTest extends TestCase
{
    private ?string $zip = null;

    protected function tearDown(): void
    {
        if ($this->zip !== null && is_file($this->zip)) {
            unlink($this->zip);
        }
    }

    private function generator(): ModelledGenerator
    {
        return ModelledGenerator::fromFile(\dirname(__DIR__) . '/Fixtures/joomla6.generator.json');
    }

    /**
     * What the Generate button puts in the zip.
     */
    private function package(string $key = 'ER1', string $version = '1.2'): FileCollection
    {
        $generator = $this->generator();
        $files     = (new Pipeline())->run(
            $generator,
            (new JoomlaGeneratorTarget(JoomlaGeneratorTarget::defaultTemplateRoot()))->target()
        );

        $files->add(GeneratorManifest::FILE, GeneratorManifest::toJson($generator->definition, $key, $version));

        return $files;
    }

    private function extengen(): string
    {
        return (string) getenv('EXTENGEN_PATH') ?: \dirname(__DIR__, 2) . '/../Exten-gen';
    }

    public function testTheManifestNamesTheRuleFileThePackageHolds(): void
    {
        $files    = $this->package()->all();
        $manifest = json_decode($files[GeneratorManifest::FILE], true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(GeneratorManifest::FORMAT, $manifest['format']);
        $this->assertSame('joomla6', $manifest['target']);
        $this->assertSame(['key' => 'ER1', 'version' => '1.2'], $manifest['metalanguage']);
        $this->assertArrayHasKey($manifest['rules'], $files, 'the rule file the manifest names is in the package');
        $this->assertNotEmpty($manifest['groups']);
    }

    public function testExtenGenReadsWhatThisWrites(): void
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

        // By name, because the class is Exten-gen's and only exists once the
        // line above has loaded it from the other checkout - this repository
        // does not depend on Exten-gen, and its analysis cannot see it.
        $class   = 'Yepr\\Component\\Extengen\\Administrator\\Generator\\Imported\\GeneratorPackage';
        $package = $class::fromZip($this->zip);

        $this->assertSame('joomla6', $package->target);
        $this->assertSame('ER1', $package->metalanguageKey);
        $this->assertSame('1.2', $package->metalanguageVersion);
        $this->assertSame(GeneratorManifest::slug($package->name), $package->key);
        $this->assertCount(\count($this->generator()->definition->rules()), $package->rules);

        // And it would be accepted: every rule names only what Exten-gen's
        // target publishes, and falls under a prefix one of its groups claims.
        $vocabulary = Vocabulary::fromFile(
            $this->extengen() . '/src/administrator/components/com_extengen/src/Generator/Rules/joomla6.vocabulary.json'
        );
        $prefixes = array_map(static fn (array $group): string => $group['prefix'], $package->groups);

        $this->assertSame([], $package->problems($vocabulary, $prefixes));
    }
}
