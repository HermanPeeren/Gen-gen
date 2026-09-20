<?php

declare(strict_types=1);

namespace Yepr\Component\Gengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Gengen\Administrator\Generator\Model\ModelledGenerator;
use Yepr\Component\Gengen\Administrator\Generator\Target\JoomlaGeneratorTarget;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Pipeline;
use Yepr\Gen\Core\Rule\RuleSet;

/**
 * Generating a generator.
 *
 * The model here is a description of a generator and the output is a generator,
 * which sounds like a trick and is not: nothing in the core changed to allow
 * it. `ModelInterface` is a marker, the pipeline never looks inside a model,
 * and a target is a structure plus emitters plus a template set. A generator
 * happens to be describable that way.
 *
 * What this file checks is the shape of what comes out. Whether it *works* is
 * `AcceptanceTest`, which runs it.
 */
final class GeneratedGeneratorTest extends TestCase
{
    private function fixture(string $name): string
    {
        return \dirname(__DIR__) . '/Fixtures/' . $name;
    }

    private function generate(): FileCollection
    {
        return (new Pipeline())->run(
            ModelledGenerator::fromFile($this->fixture('joomla6.generator.json')),
            (new JoomlaGeneratorTarget(JoomlaGeneratorTarget::defaultTemplateRoot()))->target()
        );
    }

    /**
     * The rule file, and one class per group that is only rules.
     */
    public function testItProducesARuleFileAndTheClassesThatCarryIt(): void
    {
        $paths = $this->generate()->paths();

        sort($paths);

        $this->assertSame(
            [
                'administrator/components/com_extengen/src/Generator/Joomla6/AdminMVC.php',
                'administrator/components/com_extengen/src/Generator/Joomla6/ComponentGeneral.php',
                'administrator/components/com_extengen/src/Generator/Joomla6/SiteMVC.php',
                'administrator/components/com_extengen/src/Generator/Rules/joomla6.rules.json',
            ],
            $paths
        );
    }

    /**
     * The groups that emit are not generated, and that is the same line 2.1 drew.
     *
     * `AdminGeneral` writes language strings and `AdminEntities` writes the sql
     * for a schema that can only be known once every entity has been seen.
     * Neither is expressible as a rule, so neither is generated - and a
     * generated class that replaced them would delete that code and leave
     * something that looks complete.
     */
    public function testItDoesNotGenerateOverTheGeneratorsThatEmit(): void
    {
        $paths = $this->generate()->paths();

        foreach (['AdminGeneral', 'AdminEntities'] as $handWritten) {
            $this->assertNotContains(
                'administrator/components/com_extengen/src/Generator/Joomla6/' . $handWritten . '.php',
                $paths
            );
        }
    }

    /**
     * The generated rule file is the whole rule set, canonically written.
     */
    public function testTheRuleFileIsTheWholeRuleSet(): void
    {
        $generated = $this->generate()->get(
            'administrator/components/com_extengen/src/Generator/Rules/joomla6.rules.json'
        );

        $rules = RuleSet::fromJson($generated);

        $this->assertCount(27, $rules);
        $this->assertSame($rules->toJson() . "\n", $generated);
    }

    /**
     * It is byte-identical to the rule file the target committed.
     *
     * The heart of the step, in one assertion. If what Gen-gen writes is the
     * file Exten-gen runs, then the modelled generator and the hand-written one
     * are the same generator - and the golden files that pin one pin the other.
     * `AcceptanceTest` then proves the rest by running it, but this is where a
     * drift would show up first and most clearly.
     */
    public function testItIsByteIdenticalToTheRuleFileTheTargetCommitted(): void
    {
        $this->assertSame(
            (string) file_get_contents($this->fixture('joomla6.rules.json')),
            $this->generate()->get(
                'administrator/components/com_extengen/src/Generator/Rules/joomla6.rules.json'
            )
        );
    }

    /**
     * A generated class names its prefix and its own rule file.
     *
     * Those two lines are the whole class. The rule file is relative to the
     * class, so the generated generator runs wherever it is put - which is what
     * lets the acceptance check run it out of a temp directory beside the
     * hand-written one.
     */
    public function testAGeneratedClassNamesItsPrefixAndItsRuleFile(): void
    {
        $class = $this->generate()->get(
            'administrator/components/com_extengen/src/Generator/Joomla6/AdminMVC.php'
        );

        $this->assertStringContainsString('namespace Yepr\Component\Extengen\Administrator\Generator\Joomla6;', $class);
        $this->assertStringContainsString('class AdminMVC extends RuleDrivenGenerator', $class);
        $this->assertStringContainsString("return 'admin.mvc.';", $class);
        $this->assertStringContainsString("return __DIR__ . '/../Rules/joomla6.rules.json';", $class);
    }

    /**
     * Every generated class is PHP that parses.
     *
     * Cheap, and it is the difference between "a template rendered" and "a
     * class exists". `token_get_all` with TOKEN_PARSE raises on a syntax error,
     * which is `php -l` without leaving the process.
     */
    public function testEveryGeneratedClassParses(): void
    {
        $parsed = 0;

        foreach ($this->generate() as $path => $contents) {
            if (!str_ends_with($path, '.php')) {
                continue;
            }

            try {
                $tokens = token_get_all($contents, \TOKEN_PARSE);
            } catch (\ParseError $e) {
                $this->fail($path . ' does not parse: ' . $e->getMessage());
            }

            $this->assertNotEmpty($tokens, $path . ' parsed to nothing.');

            $parsed++;
        }

        // Counted, so that a run producing no PHP at all cannot pass this by
        // having nothing to look at.
        $this->assertSame(3, $parsed);
    }

    /**
     * The committed fixture is what importing Exten-gen's rule file produces.
     *
     * Without this the model quietly ages into a simpler generator than the one
     * Exten-gen runs, and every check above keeps passing against it.
     */
    public function testTheModelledGeneratorIsCurrent(): void
    {
        $committed = (string) file_get_contents($this->fixture('joomla6.generator.json'));

        exec(
            'php ' . escapeshellarg(\dirname(__DIR__, 2) . '/tools/import-generator.php') . ' 2>&1',
            $output,
            $status
        );

        $this->assertSame(0, $status, implode("\n", $output));

        $this->assertSame(
            $committed,
            (string) file_get_contents($this->fixture('joomla6.generator.json')),
            'The modelled generator is out of date. Run php tools/import-generator.php and commit the result.'
        );
    }
}
