<?php

declare(strict_types=1);

namespace Yepr\Component\Gengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Gengen\Administrator\Generator\GeneratorDefinition;
use Yepr\Gen\Core\Rule\Vocabulary;

/**
 * The generators `tools/seed-generator.php` puts on a site.
 *
 * It seeds every `tests/Fixtures/*.generator.json`, in name order, and finds
 * an existing row by the generator's name. The tool itself boots Joomla, so it
 * is not run here; what it relies on is: every fixture reads as a generator
 * and passes the target's vocabulary, no two share a name (a second would
 * overwrite the first's row), and a generator that extends another comes after
 * the one it extends.
 */
final class SeededGeneratorsTest extends TestCase
{
    /**
     * @return  array<string, GeneratorDefinition>  By fixture file name, in the order the tool seeds them.
     */
    private function seeded(): array
    {
        $fixtures = glob(\dirname(__DIR__) . '/Fixtures/*.generator.json') ?: [];
        sort($fixtures);

        $definitions = [];

        foreach ($fixtures as $fixture) {
            $definitions[basename($fixture)] = GeneratorDefinition::fromFormData(
                json_decode((string) file_get_contents($fixture), true, 512, \JSON_THROW_ON_ERROR)
            );
        }

        return $definitions;
    }

    public function testBothShippedRuleDrivenGeneratorsAreSeeded(): void
    {
        $this->assertSame(
            ['joomla6.generator.json', 'ledgerlang.generator.json'],
            array_keys($this->seeded())
        );
    }

    public function testEveryOneIsValidAgainstItsTarget(): void
    {
        $vocabulary = Vocabulary::fromFile(\dirname(__DIR__) . '/Fixtures/joomla6.vocabulary.json');

        foreach ($this->seeded() as $file => $definition) {
            $this->assertSame('joomla6', $definition->target, $file);

            $problems = $vocabulary->problems($definition->rules());

            $this->assertSame([], $problems, $file . ': ' . implode("\n", $problems));
        }
    }

    public function testNoTwoShareAName(): void
    {
        $names = array_map(static fn (GeneratorDefinition $d): string => $d->name, $this->seeded());

        $this->assertSame(array_values(array_unique($names)), array_values($names));
    }

    public function testAParentIsSeededBeforeTheGeneratorThatExtendsIt(): void
    {
        // On the Extension Generator a built-in generator's id is its target: `joomla6`.
        $seen = [];

        foreach ($this->seeded() as $definition) {
            if ($definition->extends !== '') {
                $this->assertContains($definition->extends, $seen, $definition->name);
            } else {
                $seen[] = $definition->target;
            }
        }

        $this->assertSame('joomla6', $this->seeded()['ledgerlang.generator.json']->extends);
    }
}
