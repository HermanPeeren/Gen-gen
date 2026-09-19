<?php

declare(strict_types=1);

namespace Yepr\Component\Gengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Gengen\Administrator\Generator\GeneratorDefinition;
use Yepr\Gen\Core\Rule\RuleSet;
use Yepr\Gen\Core\Rule\Vocabulary;

/**
 * A generator modelled in a form is the same generator as one written as rules.
 *
 * This is the load-bearing test of the whole step. Gen-gen is only worth
 * building if what comes out of its forms is what the engine runs, and the
 * cheapest way to be wrong about that is a translation that loses something
 * small - a literal empty string, a condition's value, the order of bindings -
 * in a way nobody notices until a generated file is missing a variable.
 *
 * So the fixture is not a toy. It is Exten-gen's own rule set, all
 * twenty-seven rules of it, copied from the component that runs it in
 * production: every binding kind, every operator, fragments with templates,
 * conditions with and without values. Through the form shape and back, it has
 * to be identical.
 */
final class GeneratorDefinitionTest extends TestCase
{
    private function fixture(string $name): string
    {
        return \dirname(__DIR__) . '/Fixtures/' . $name;
    }

    private function rules(): RuleSet
    {
        return RuleSet::fromFile($this->fixture('joomla6.rules.json'));
    }

    /**
     * The round trip, on a real rule set.
     */
    public function testARealRuleSetSurvivesTheFormShape(): void
    {
        $rules = $this->rules();

        $modelled = GeneratorDefinition::fromRuleSet('Joomla 6 component', 'joomla6', $rules);
        $saved    = GeneratorDefinition::fromFormData($modelled->toFormData());

        $this->assertSame($rules->toArray(), $saved->rules()->toArray());
    }

    /**
     * And it is not a trivially small thing that survived.
     */
    public function testTheFixtureIsTheWholeGenerator(): void
    {
        $this->assertCount(27, $this->rules());
    }

    /**
     * What comes back still satisfies the target it was written for.
     *
     * The round trip above compares two arrays, which would pass just as well
     * if both were nonsense. This asks the vocabulary whether the result is a
     * generator that target could actually run.
     */
    public function testWhatComesBackIsStillValidForTheTarget(): void
    {
        $vocabulary = Vocabulary::fromFile($this->fixture('joomla6.vocabulary.json'));

        $saved = GeneratorDefinition::fromFormData(
            GeneratorDefinition::fromRuleSet('Joomla 6 component', 'joomla6', $this->rules())->toFormData()
        );

        $problems = $vocabulary->problems($saved->rules());

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function testItKeepsWhatTheGeneratorIsCalledAndWhatItTargets(): void
    {
        $form = GeneratorDefinition::fromRuleSet('Joomla 6 component', 'joomla6', $this->rules())->toFormData();

        $this->assertSame('Joomla 6 component', $form['generator_name']);
        $this->assertSame('joomla6', $form['target']);

        $back = GeneratorDefinition::fromFormData($form);

        $this->assertSame('Joomla 6 component', $back->name);
        $this->assertSame('joomla6', $back->target);
    }

    /**
     * Subforms are stored keyed, in order, which is how Joomla writes them and
     * how a rule set means them.
     */
    public function testRepeatingGroupsAreKeyedInOrder(): void
    {
        $form = GeneratorDefinition::fromRuleSet('g', 'joomla6', $this->rules())->toFormData();

        $this->assertSame('rule0', array_key_first($form['rule']));
        $this->assertSame('component.manifest', $form['rule']['rule0']['rule_id']);
        $this->assertSame('root', $form['rule']['rule0']['source']);

        $bindings = array_keys($form['rule']['rule0']['binding']);

        $this->assertSame(['binding0', 'binding1', 'binding2'], \array_slice($bindings, 0, 3));
    }

    /**
     * An empty literal is a value, not an absence.
     *
     * `getFK` is bound to the empty string, and the template reads it. Treating
     * "" as "nothing was filled in" would drop the binding, and the generated
     * Table class would fail to render with a strict-variables error that names
     * a variable nobody can find in the model.
     */
    public function testAnEmptyLiteralSurvives(): void
    {
        $form = GeneratorDefinition::fromRuleSet('g', 'joomla6', $this->rules())->toFormData();

        $table = null;

        foreach ($form['rule'] as $rule) {
            if ($rule['rule_id'] === 'admin.entity.table') {
                $table = $rule;
            }
        }

        $this->assertNotNull($table);

        $getFk = null;

        foreach ($table['binding'] as $binding) {
            if ($binding['variable'] === 'getFK') {
                $getFk = $binding;
            }
        }

        $this->assertNotNull($getFk);
        $this->assertSame('literal', $getFk['kind']);
        $this->assertSame('', $getFk['value']);

        $back = GeneratorDefinition::fromFormData($form)->rules()->toArray();
        $rule = array_values(array_filter($back, static fn (array $r): bool => $r['id'] === 'admin.entity.table'))[0];

        $this->assertSame(['literal' => ''], $rule['bind']['getFK']);
    }

    /**
     * A condition that compares with nothing writes no value.
     *
     * The form's value box is hidden for `has` and `missing`, but hidden is not
     * empty - it still holds whatever was typed before the operator changed.
     * Writing that into the rule would make the file say something the rule
     * does not mean, and `{"operator":"missing","path":"x","value":""}` reads
     * exactly like a deliberate comparison with the empty string.
     */
    public function testAValuelessOperatorWritesNoValue(): void
    {
        $rules = GeneratorDefinition::fromFormData([
            'generator_name' => 'g',
            'target'         => 'joomla6',
            'rule'           => [
                'rule0' => [
                    'rule_id'   => 'a.b',
                    'source'    => 'entities',
                    'template'  => 't.twig',
                    'output'    => 'a.php',
                    'condition' => [
                        'condition0' => ['operator' => 'missing', 'path' => 'isvalueobject', 'value' => 'left over'],
                        'condition1' => ['operator' => 'equals', 'path' => 'page_type', 'value' => 'indexpage'],
                    ],
                ],
            ],
        ])->rules()->toArray();

        $this->assertSame(
            [
                ['operator' => 'missing', 'path' => 'isvalueobject'],
                ['operator' => 'equals', 'path' => 'page_type', 'value' => 'indexpage'],
            ],
            $rules[0]['when']
        );
    }

    /**
     * Fragments carry a derivation, a template and an optional glue.
     */
    public function testFragmentsKeepTheirTemplate(): void
    {
        $rules = GeneratorDefinition::fromFormData([
            'generator_name' => 'g',
            'target'         => 'joomla6',
            'rule'           => [[
                'rule_id'  => 'a.b',
                'source'   => 'entities',
                'template' => 't.twig',
                'output'   => 'a.php',
                'binding'  => [[
                    'variable'          => 'm2m_bind',
                    'kind'              => 'fragments',
                    'derivation'        => 'manyToMany',
                    'fragment_template' => 'f.twig',
                    'glue'              => '',
                ]],
            ]],
        ])->rules()->toArray();

        $this->assertSame(
            ['fragments' => 'manyToMany', 'template' => 'f.twig'],
            $rules[0]['bind']['m2m_bind']
        );
    }

    /**
     * A binding with no variable name is a row somebody added and left blank,
     * not a binding of the empty string.
     */
    public function testAnUnnamedBindingIsDropped(): void
    {
        $rules = GeneratorDefinition::fromFormData([
            'generator_name' => 'g',
            'target'         => 'joomla6',
            'rule'           => [[
                'rule_id'  => 'a.b',
                'source'   => 'entities',
                'template' => 't.twig',
                'output'   => 'a.php',
                'binding'  => [
                    ['variable' => 'x', 'kind' => 'path', 'value' => 'name'],
                    ['variable' => '', 'kind' => 'path', 'value' => ''],
                ],
            ]],
        ])->rules()->toArray();

        $this->assertSame(['x' => ['path' => 'name']], $rules[0]['bind']);
    }

    /**
     * Joomla hands a subform back as objects when it came from JSON and as
     * arrays when it came from the request. Both are the same generator.
     */
    public function testItReadsSubformsWhicheverWayJoomlaHandsThemOver(): void
    {
        $asObjects = json_decode(json_encode([
            'generator_name' => 'g',
            'target'         => 'joomla6',
            'rule'           => ['rule0' => [
                'rule_id'  => 'a.b',
                'source'   => 'entities',
                'template' => 't.twig',
                'output'   => 'a.php',
            ]],
        ]), true);

        $asObjects['rule'] = (object) ['rule0' => (object) $asObjects['rule']['rule0']];

        $this->assertSame(
            [['id' => 'a.b', 'for' => 'entities', 'template' => 't.twig', 'target' => 'a.php']],
            GeneratorDefinition::fromFormData($asObjects)->rules()->toArray()
        );
    }
}
