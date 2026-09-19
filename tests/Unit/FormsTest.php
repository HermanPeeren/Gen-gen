<?php

declare(strict_types=1);

namespace Yepr\Component\Gengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Gen\Core\Rule\Binding;
use Yepr\Gen\Core\Rule\Condition;

/**
 * The forms describe the rule language, and keep describing it.
 *
 * Forms are XML that nothing compiles. A field type with no class behind it is
 * a blank space on the screen; a `showon` naming a value the field cannot
 * produce is a box that never appears; a `formsource` pointing at a file that
 * was renamed is a subform that silently holds nothing. None of it is visible
 * until somebody opens the form, and some of it is not visible then either.
 *
 * The rule language itself is defined in the library - four operators, five
 * binding kinds - and the forms repeat it, because a `showon` cannot read a PHP
 * constant. Repeating it is the risk these tests exist for: add a binding kind
 * in the library and the form still offers five, with the sixth arriving as a
 * kind whose value box never shows.
 */
final class FormsTest extends TestCase
{
    private function formsRoot(): string
    {
        return \dirname(__DIR__, 2) . '/src/administrator/components/com_gengen/forms/';
    }

    private function fieldRoot(): string
    {
        return \dirname(__DIR__, 2) . '/src/administrator/components/com_gengen/src/Field/';
    }

    private function form(string $name): \SimpleXMLElement
    {
        $xml = simplexml_load_file($this->formsRoot() . $name);

        $this->assertNotFalse($xml, $name . ' is not valid XML.');

        return $xml;
    }

    /**
     * @return string[]
     */
    private function formNames(): array
    {
        return ['generator.xml', 'rule.xml', 'condition.xml', 'binding.xml'];
    }

    public function testEveryFormParses(): void
    {
        foreach ($this->formNames() as $name) {
            $this->assertInstanceOf(\SimpleXMLElement::class, $this->form($name));
        }
    }

    /**
     * Every subform points at a form that is there.
     *
     * A `formsource` Joomla cannot resolve does not raise anything; the subform
     * renders with no fields in it, which looks like a feature nobody filled
     * in.
     */
    public function testEverySubformSourceExists(): void
    {
        $root = \dirname(__DIR__, 2) . '/src/';

        foreach ($this->formNames() as $name) {
            foreach ($this->form($name)->xpath('//field[@formsource]') ?: [] as $field) {
                $source = (string) $field['formsource'];

                $this->assertFileExists($root . $source, $name . ' points at ' . $source);
            }
        }
    }

    /**
     * Every custom field type a form uses resolves to a class that is there.
     *
     * Resolved the way Joomla resolves it, which is the only version of the
     * question worth asking. `FormHelper::loadClass()` builds the class name as
     * `ucfirst(ucwords($type)) . 'Field'` under the form's `addfieldprefix`, and
     * `ucwords` only touches letters after whitespace - so `ruleselector`
     * becomes `RuleselectorField`, not `RuleSelectorField`.
     *
     * That is one letter, and on Windows it is no letters at all, because the
     * filesystem does not care. On the Linux server this will run on, the class
     * is simply not found and Joomla falls back to something that is not a
     * closed list - which turns "pick one of the target's selectors" into "type
     * anything you like". It failed in CI on the first push, which is what CI
     * is for; it had passed locally for the whole of its short life.
     */
    public function testEveryCustomFieldTypeResolvesTheWayJoomlaResolvesIt(): void
    {
        $builtIn = ['text', 'number', 'hidden', 'list', 'subform'];
        $missing = [];

        foreach ($this->formNames() as $name) {
            foreach ($this->form($name)->xpath('//field[@type]') ?: [] as $field) {
                $type = (string) $field['type'];

                if (\in_array($type, $builtIn, true)) {
                    continue;
                }

                // FormHelper::loadClass(), minus the parts that do not apply here.
                $class = ucfirst(ucwords($type)) . 'Field.php';

                if (!\in_array($class, scandir($this->fieldRoot()) ?: [], true)) {
                    $missing[] = $name . ': type="' . $type . '" wants ' . $class;
                }
            }
        }

        $this->assertSame([], $missing, implode(', ', $missing));
    }

    /**
     * Every field class declares the type its form asks for.
     *
     * The class name is how Joomla finds the file; `$type` is what the field
     * calls itself afterwards. They are two spellings of one thing, and nothing
     * makes them agree.
     */
    public function testEveryFieldClassDeclaresItsOwnType(): void
    {
        foreach (glob($this->fieldRoot() . '*Field.php') ?: [] as $path) {
            $source = (string) file_get_contents($path);
            $class  = basename($path, 'Field.php');

            if (str_contains($source, 'abstract class')) {
                continue;
            }

            $this->assertStringContainsString(
                "protected \$type = '" . $class . "';",
                $source,
                basename($path) . ' does not declare $type = ' . $class
            );
        }
    }

    /**
     * The condition form offers exactly the operators the library accepts.
     *
     * Not more: an operator in the list that `Condition::fromArray` refuses
     * saves a rule that cannot be loaded again. Not fewer: one the library has
     * and the form hides is a rule that can be written by hand and not edited.
     */
    public function testTheValueBoxShowsForExactlyTheComparingOperators(): void
    {
        $value = $this->form('condition.xml')->xpath('//field[@name="value"]')[0];

        $shown = explode(',', explode(':', (string) $value['showon'])[1]);

        $comparing = array_values(array_filter(
            Condition::OPERATORS,
            static fn (string $o): bool => $o === Condition::EQUALS || $o === Condition::NOT_EQUALS
        ));

        $this->assertSame($comparing, $shown);
    }

    /**
     * The binding form's conditional boxes cover every kind the library has.
     *
     * Each kind has to show something, or choosing it leaves a binding with
     * nowhere to put its value.
     */
    public function testEveryBindingKindHasSomewhereToPutItsValue(): void
    {
        $covered = [];

        foreach ($this->form('binding.xml')->xpath('//field[@showon]') ?: [] as $field) {
            foreach (explode(',', explode(':', (string) $field['showon'])[1]) as $kind) {
                $covered[$kind] = true;
            }
        }

        $uncovered = array_values(array_diff(Binding::KINDS, array_keys($covered)));

        $this->assertSame([], $uncovered, 'Binding kinds no field shows for: ' . implode(', ', $uncovered));
    }

    /**
     * And it shows nothing for a kind that does not exist.
     */
    public function testNoBoxShowsForAKindTheLibraryDoesNotHave(): void
    {
        foreach ($this->form('binding.xml')->xpath('//field[@showon]') ?: [] as $field) {
            foreach (explode(',', explode(':', (string) $field['showon'])[1]) as $kind) {
                $this->assertContains($kind, Binding::KINDS, (string) $field['name'] . ' shows on ' . $kind);
            }
        }
    }

    /**
     * The field names in the forms are the ones the translation reads.
     *
     * `GeneratorDefinition` maps between the form's names and a rule's, and it
     * is the only thing that does. A field renamed in the XML and not here
     * arrives as an empty string, which for `rule_id` means a rule set that
     * will not load and for `output` means a file at the path `""`.
     */
    public function testTheFormFieldNamesAreTheOnesTheTranslationReads(): void
    {
        $translation = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/administrator/components/com_gengen/src/Generator/GeneratorDefinition.php'
        );

        $names = [
            'generator.xml' => ['generator_name', 'target', 'rule'],
            'rule.xml'      => ['rule_id', 'source', 'template', 'output', 'condition', 'binding'],
            'condition.xml' => ['operator', 'path', 'value'],
            'binding.xml'   => ['variable', 'kind', 'value', 'derivation', 'fragment_template', 'glue'],
        ];

        foreach ($names as $form => $expected) {
            $actual = [];

            foreach ($this->form($form)->xpath('//field[@name]') ?: [] as $field) {
                $actual[] = (string) $field['name'];
            }

            foreach ($expected as $name) {
                $this->assertContains($name, $actual, $form . ' no longer has a field called ' . $name);
                $this->assertStringContainsString(
                    "'" . $name . "'",
                    $translation,
                    'GeneratorDefinition does not mention ' . $name
                );
            }
        }
    }
}
