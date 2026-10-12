<?php

declare(strict_types=1);

namespace Yepr\Component\Gengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Gengen\Administrator\Generator\GeneratorDefinition;
use Yepr\Component\Gengen\Administrator\Generator\GeneratorManifest;
use Yepr\Component\Gengen\Administrator\Generator\ImportedGenerator;
use Yepr\Component\Gengen\Administrator\Generator\Model\ModelledGenerator;
use Yepr\Component\Gengen\Administrator\Generator\Target\JoomlaGeneratorTarget;
use Yepr\Component\Gengen\Administrator\Generator\VocabularyLibrary;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Pipeline;

/**
 * The Import button reads each kind of package it is offered: 0.6.0.
 *
 * One test per input shape. A package this component generated holds
 * `model.json` and comes back exactly. A package without it is rebuilt from
 * the rule file, with the rest taken from a modelling manifest if one came
 * with it, from `generator.json` if not, and guessed if there is neither. A
 * generator that extends another comes in as it is. Anything else is refused,
 * and so is a generator naming what the target does not publish.
 *
 * The site half - the row, the binding to a metalanguage - needs a database
 * and is what `generator-import.cy.js` checks through the button.
 *
 * @since  0.6.0
 */
final class ImportedGeneratorTest extends TestCase
{
    private ?string $zip = null;

    protected function tearDown(): void
    {
        if ($this->zip !== null && is_file($this->zip)) {
            unlink($this->zip);
        }
    }

    private function fixtures(): string
    {
        return \dirname(__DIR__) . '/Fixtures';
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents($this->fixtures() . '/' . $name);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(string $fixture): array
    {
        return json_decode($this->fixture($fixture), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function library(): VocabularyLibrary
    {
        return new VocabularyLibrary($this->fixtures());
    }

    /**
     * What the Generate button puts in the zip: the pipeline's files, the manifest and the model.
     *
     * @return array<string, string>
     */
    private function generated(string $fixture, bool $withModel = true, string $key = '', string $version = ''): array
    {
        $generator = ModelledGenerator::fromFile($this->fixtures() . '/' . $fixture);
        $files     = (new Pipeline())->run(
            $generator,
            (new JoomlaGeneratorTarget(JoomlaGeneratorTarget::defaultTemplateRoot()))->target()
        );

        $files->add(GeneratorManifest::FILE, GeneratorManifest::toJson($generator->definition, $key, $version));

        if ($withModel) {
            $files->add(GeneratorManifest::MODEL, GeneratorManifest::modelJson($generator->definition));
        }

        return $files->all();
    }

    public function testAPackageGeneratedHereComesBackExactly(): void
    {
        foreach (['joomla6.generator.json', 'ledgerlang.generator.json'] as $fixture) {
            $imported = ImportedGenerator::fromFiles($this->generated($fixture, true, 'LedgerLang', '0.1'));

            $this->assertSame(ImportedGenerator::FROM_MODEL, $imported->source, $fixture);
            $this->assertSame($this->formData($fixture), $imported->definition->toFormData(), $fixture);
            $this->assertSame([], $imported->notes, $fixture);
            $this->assertSame('LedgerLang', $imported->metalanguageKey);
            $this->assertSame('0.1', $imported->metalanguageVersion);
        }
    }

    public function testTheGenerateButtonsZipIsReadThroughTheZip(): void
    {
        $this->zip = tempnam(sys_get_temp_dir(), 'gengen') . '.zip';

        $files = new \Yepr\Gen\Core\Output\FileCollection();

        foreach ($this->generated('ledgerlang.generator.json') as $path => $contents) {
            $files->add($path, $contents);
        }

        (new ZipWriter())->write($files, $this->zip);

        $imported = ImportedGenerator::fromZip($this->zip);

        $this->assertSame($this->formData('ledgerlang.generator.json'), $imported->definition->toFormData());
    }

    public function testAnOlderGenGenPackageIsRebuiltFromItsRuleFileAndManifest(): void
    {
        // Generated before 0.6: no model.json. The groups come from
        // generator.json, without their summaries, and the output path is read
        // back off the rule file's path. The namespace is nowhere, and the
        // notes say so.
        $imported = ImportedGenerator::fromFiles($this->generated('joomla6.generator.json', false));
        $expected = GeneratorDefinition::fromFormData($this->formData('joomla6.generator.json'));

        $this->assertSame(ImportedGenerator::FROM_RULES, $imported->source);
        $this->assertSame($expected->name, $imported->definition->name);
        $this->assertSame($expected->rules()->toArray(), $imported->definition->rules()->toArray());
        $this->assertSame($expected->outputPath, $imported->definition->outputPath);
        $this->assertSame(
            array_column($expected->groups(), 'prefix'),
            array_column($imported->definition->groups(), 'prefix')
        );
        $this->assertSame('', $imported->definition->phpNamespace);
        $this->assertCount(1, $imported->notes);
    }

    public function testTheExtensionGeneratorsLedgerLangPackageWithItsModellingManifest(): void
    {
        // The files the Extension Generator ships, zipped, with the manifest
        // `import-generator.php` builds the committed model from. That is the
        // committed model, exactly.
        $imported = ImportedGenerator::fromFiles([
            'ledgerlang.package.json'  => $this->fixture('ledgerlang.package.json'),
            'ledgerlang.rules.json'    => $this->fixture('ledgerlang.rules.json'),
            'ledgerlang.manifest.json' => $this->fixture('ledgerlang.manifest.json'),
        ]);

        $this->assertSame($this->formData('ledgerlang.generator.json'), $imported->definition->toFormData());
        $this->assertSame('LedgerLang', $imported->metalanguageKey);
        $this->assertSame('', $imported->metalanguageVersion, 'the shipped package names no version');
        $this->assertSame([], $imported->notes);
    }

    public function testTheJoomla6RulesWithTheirModellingManifest(): void
    {
        // The Joomla 6 generator has no package on the Extension Generator: it
        // is its target's own rule file. With the manifest beside it, it is
        // the committed model.
        $imported = ImportedGenerator::fromFiles([
            'joomla6.rules.json'    => $this->fixture('joomla6.rules.json'),
            'joomla6.manifest.json' => $this->fixture('joomla6.manifest.json'),
        ]);

        $this->assertSame($this->formData('joomla6.generator.json'), $imported->definition->toFormData());
    }

    public function testARuleFileOnItsOwnGetsOneGroupPerPrefix(): void
    {
        $imported = ImportedGenerator::fromFiles(['joomla6.rules.json' => $this->fixture('joomla6.rules.json')]);

        $this->assertSame('joomla6', $imported->definition->target, 'the target is the rule file\'s name');

        // Which is how the Extension Generator slices them.
        $manifest = json_decode($this->fixture('joomla6.manifest.json'), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(
            array_column($manifest['group'], 'prefix'),
            array_column($imported->definition->groups(), 'prefix')
        );
        // The class is the prefix in StudlyCase: close to the real names, and a
        // guess the notes say is one.
        $this->assertSame('AdminMvc', $imported->definition->groups()[3]['class']);
        $this->assertNotEmpty($imported->notes, 'a guess is said');
        $this->assertSame([], $imported->problems($this->library()));
    }

    public function testAChildThatExtendsAnotherIsImportedAsItIs(): void
    {
        // Without the modelling manifest: the shipped package and its rules.
        $imported = ImportedGenerator::fromFiles([
            'ledgerlang.package.json' => $this->fixture('ledgerlang.package.json'),
            'ledgerlang.rules.json'   => $this->fixture('ledgerlang.rules.json'),
        ]);

        $this->assertSame('joomla6', $imported->definition->extends);
        $this->assertSame('Joomla component with posting rules', $imported->definition->name);
        $this->assertCount(3, $imported->definition->rules(), 'only the rules it adds');
        $this->assertSame(['admin.general.'], array_column($imported->definition->groups(), 'prefix'));

        // Its parent's rules are not here and need not be: the Extension
        // Generator lays these over them. What it names is still checked.
        $this->assertSame([], $imported->problems($this->library()));
    }

    public function testSomethingThatIsNotAZipIsRefused(): void
    {
        $this->zip = tempnam(sys_get_temp_dir(), 'gengen');
        file_put_contents($this->zip, 'holiday photos');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not a zip');

        ImportedGenerator::fromZip($this->zip);
    }

    public function testAZipWithoutRulesIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not a generator package');

        ImportedGenerator::fromFiles(['README.txt' => 'hello']);
    }

    public function testAManifestNamingARuleFileThatIsNotThereIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ledgerlang.rules.json');

        ImportedGenerator::fromFiles(['ledgerlang.package.json' => $this->fixture('ledgerlang.package.json')]);
    }

    public function testANewerFormatIsRefused(): void
    {
        $manifest = json_decode($this->fixture('ledgerlang.package.json'), true, 512, \JSON_THROW_ON_ERROR);

        $manifest['format'] = GeneratorManifest::FORMAT + 1;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('format');

        ImportedGenerator::fromFiles([
            'generator.json'        => json_encode($manifest, \JSON_THROW_ON_ERROR),
            'ledgerlang.rules.json' => $this->fixture('ledgerlang.rules.json'),
        ]);
    }

    public function testRulesThatAreNotRulesAreRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be read');

        ImportedGenerator::fromFiles(['joomla6.rules.json' => '[{"id": "x"}]']);
    }

    public function testARuleNamingWhatTheTargetDoesNotPublishIsAProblem(): void
    {
        $rules = json_decode($this->fixture('ledgerlang.rules.json'), true, 512, \JSON_THROW_ON_ERROR);

        $rules[0]['for'] = 'balloons';
        $rules[1]['template'] = '../../../../configuration.php';

        $imported = ImportedGenerator::fromFiles([
            'ledgerlang.package.json' => $this->fixture('ledgerlang.package.json'),
            'ledgerlang.rules.json'   => json_encode($rules, \JSON_THROW_ON_ERROR),
        ]);

        $problems = implode("\n", $imported->problems($this->library()));

        $this->assertStringContainsString('"balloons" is not a selector of joomla6', $problems);
        $this->assertStringContainsString('"../../../../configuration.php" is not a template of joomla6', $problems);
    }

    public function testATargetThisSiteDoesNotHaveIsAProblem(): void
    {
        $model = $this->formData('joomla6.generator.json');

        $model['target'] = 'wordpress';

        $imported = ImportedGenerator::fromFiles([
            GeneratorManifest::MODEL => json_encode($model, \JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame(
            [
                'It is for the target "wordpress", and nothing on this site publishes a vocabulary'
                . ' for it. Found: joomla6.',
            ],
            $imported->problems($this->library())
        );
    }

    public function testARuleMaySelectAConceptOfTheLanguageItIsBoundTo(): void
    {
        $rules = json_decode($this->fixture('ledgerlang.rules.json'), true, 512, \JSON_THROW_ON_ERROR);

        $rules[0]['for'] = 'AccountRole';

        $imported = ImportedGenerator::fromFiles([
            'ledgerlang.package.json' => $this->fixture('ledgerlang.package.json'),
            'ledgerlang.rules.json'   => json_encode($rules, \JSON_THROW_ON_ERROR),
        ]);

        $this->assertNotSame([], $imported->problems($this->library()), 'not without the language');
        $this->assertSame([], $imported->problems($this->library(), ['AccountRole']));
    }
}
