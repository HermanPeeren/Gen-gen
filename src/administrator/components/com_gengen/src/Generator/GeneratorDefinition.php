<?php

/**
 * @package     Gengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Generator;

use Yepr\Gen\Core\Rule\Binding;
use Yepr\Gen\Core\Rule\RuleSet;

/**
 * A modelled generator, as a form holds it and as the engine runs it.
 *
 * Two shapes for one thing. `RuleSet` is what the engine executes and what a
 * target's repository commits: a list of rules, each binding named variables
 * under a single-key kind. Joomla's form layer cannot express that - a subform
 * stores a repeating group as an object keyed `binding0`, `binding1`, and a
 * field holds one scalar, not a key that is itself the meaning. So the form
 * shape is flat: every binding has a `kind` and the fields that kind uses.
 *
 * This is the translation, and it exists in one place on purpose. A generator
 * modelled in Gen-gen and a generator committed as a rule file have to be the
 * same generator, or step 2.3 has nothing to compare. The proof is a round
 * trip: Exten-gen's own twenty-seven rules, through the form shape and back,
 * identical.
 *
 * It does not know what a Joomla is. The form layer hands it arrays.
 *
 * @since  0.1.0
 */
final class GeneratorDefinition
{
    /**
     * Constructor.
     *
     * @param   string                            $name    What this generator is called.
     * @param   string                            $target  Which target's vocabulary its rules are written in.
     * @param   array<int, array<string, mixed>>  $rules   The rules, in order, in the stored rule shape.
     * @param   array<int, array<string, mixed>>  $groups  The generator classes, in the order they run.
     * @param   string                            $outputPath    Where in the package the generator's own source goes.
     * @param   string                            $phpNamespace  The namespace its classes live in.
     *
     * @since   0.1.0
     */
    public function __construct(
        public readonly string $name,
        public readonly string $target,
        private readonly array $rules,
        private readonly array $groups = [],
        public readonly string $outputPath = '',
        public readonly string $phpNamespace = ''
    ) {
    }

    /**
     * Read a generator out of what a form saved.
     *
     * @param   array<string, mixed>  $data  The decoded form data.
     *
     * @return  self
     *
     * @since   0.1.0
     */
    public static function fromFormData(array $data): self
    {
        $rules = [];

        foreach (self::repeated($data, 'rule') as $rule) {
            $rules[] = self::ruleFromFormData($rule);
        }

        $groups = [];

        foreach (self::repeated($data, 'group') as $group) {
            $groups[] = [
                'class'   => (string) ($group['class'] ?? ''),
                'prefix'  => (string) ($group['prefix'] ?? ''),
                'summary' => (string) ($group['summary'] ?? ''),
                'emits'   => (bool) ($group['emits'] ?? false),
            ];
        }

        return new self(
            (string) ($data['generator_name'] ?? ''),
            (string) ($data['target'] ?? ''),
            $rules,
            $groups,
            (string) ($data['output_path'] ?? ''),
            (string) ($data['php_namespace'] ?? '')
        );
    }

    /**
     * Read a generator out of a committed rule file.
     *
     * @param   string                            $name    What to call it.
     * @param   string                            $target  Which target its rules are written in.
     * @param   RuleSet                           $rules   The rules.
     * @param   array<int, array<string, mixed>>  $groups  The generator classes, in the order they run.
     * @param   string                            $outputPath    Where the generator's own source goes.
     * @param   string                            $phpNamespace  The namespace its classes live in.
     *
     * @return  self
     *
     * @since   0.1.0
     */
    public static function fromRuleSet(
        string $name,
        string $target,
        RuleSet $rules,
        array $groups = [],
        string $outputPath = '',
        string $phpNamespace = ''
    ): self {
        return new self($name, $target, $rules->toArray(), $groups, $outputPath, $phpNamespace);
    }

    /**
     * The rules, ready for the engine.
     *
     * @return  RuleSet
     *
     * @since   0.1.0
     */
    public function rules(): RuleSet
    {
        return RuleSet::fromArray($this->rules);
    }

    /**
     * The generator classes this generator is made of, in the order they run.
     *
     * @return  array<int, array<string, mixed>>
     *
     * @since   0.2.0
     */
    public function groups(): array
    {
        return $this->groups;
    }

    /**
     * The rules one group claims: those whose ids start with its prefix.
     *
     * @param   array<string, mixed>  $group  The group.
     *
     * @return  RuleSet
     *
     * @since   0.2.0
     */
    public function rulesOf(array $group): RuleSet
    {
        return $this->rules()->withPrefix((string) ($group['prefix'] ?? ''));
    }

    /**
     * What a form should be filled with to show this generator.
     *
     * @return  array<string, mixed>
     *
     * @since   0.1.0
     */
    public function toFormData(): array
    {
        $rules = [];

        foreach (array_values($this->rules) as $index => $rule) {
            $rules['rule' . $index] = self::ruleToFormData($rule);
        }

        $groups = [];

        foreach (array_values($this->groups) as $index => $group) {
            $groups['group' . $index] = [
                'class'   => (string) ($group['class'] ?? ''),
                'prefix'  => (string) ($group['prefix'] ?? ''),
                'summary' => (string) ($group['summary'] ?? ''),
                'emits'   => ($group['emits'] ?? false) ? '1' : '0',
            ];
        }

        return [
            'generator_name' => $this->name,
            'target'         => $this->target,
            'output_path'    => $this->outputPath,
            'php_namespace'  => $this->phpNamespace,
            'group'          => $groups,
            'rule'           => $rules,
        ];
    }

    /**
     * A repeating subform's entries, in order, whatever shape it arrived in.
     *
     * Joomla stores a multiple subform as an object keyed `rule0`, `rule1`, and
     * hands it back as an object when it came from JSON and as an array when it
     * came from the request. A caller assembling one by hand writes a plain
     * list. All three mean the same thing, and none of them is worth a branch
     * at every call site.
     *
     * @param   array<string, mixed>  $data  The containing data.
     * @param   string                $name  The subform's field name.
     *
     * @return  array<int, array<string, mixed>>
     *
     * @since   0.1.0
     */
    private static function repeated(array $data, string $name): array
    {
        $value = $data[$name] ?? [];

        if (\is_object($value)) {
            $value = get_object_vars($value);
        }

        if (!\is_array($value)) {
            return [];
        }

        $entries = [];

        foreach ($value as $entry) {
            if (\is_object($entry)) {
                $entry = get_object_vars($entry);
            }

            if (\is_array($entry)) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * One rule, from what a form saved.
     *
     * @param   array<string, mixed>  $data  The form's rule entry.
     *
     * @return  array<string, mixed>  The rule, in the stored rule shape.
     *
     * @since   0.1.0
     */
    private static function ruleFromFormData(array $data): array
    {
        $rule = [
            'id'       => (string) ($data['rule_id'] ?? ''),
            'for'      => (string) ($data['source'] ?? ''),
            'template' => (string) ($data['template'] ?? ''),
            'target'   => (string) ($data['output'] ?? ''),
        ];

        $when = [];

        foreach (self::repeated($data, 'condition') as $condition) {
            $operator = (string) ($condition['operator'] ?? '');
            $entry    = ['operator' => $operator, 'path' => (string) ($condition['path'] ?? '')];

            // `has` and `missing` compare with nothing, and the form's value
            // box is hidden for them. Writing the empty string it still holds
            // would make the rule file say something the rule does not mean.
            if ($operator === 'equals' || $operator === 'notEquals') {
                $entry['value'] = $condition['value'] ?? null;
            }

            $when[] = $entry;
        }

        if ($when !== []) {
            $rule['when'] = $when;
        }

        $bind = [];

        foreach (self::repeated($data, 'binding') as $binding) {
            $variable = (string) ($binding['variable'] ?? '');

            if ($variable === '') {
                continue;
            }

            $bind[$variable] = self::bindingFromFormData($binding);
        }

        if ($bind !== []) {
            $rule['bind'] = $bind;
        }

        return $rule;
    }

    /**
     * One binding, from what a form saved.
     *
     * @param   array<string, mixed>  $data  The form's binding entry.
     *
     * @return  array<string, mixed>  The binding, in the stored rule shape.
     *
     * @since   0.1.0
     */
    private static function bindingFromFormData(array $data): array
    {
        $kind = (string) ($data['kind'] ?? Binding::LITERAL);

        // Which field carries the value depends on the kind, because a
        // derivation is picked from a list and a literal is typed. One field
        // could not be both a dropdown and a text box.
        if ($kind === Binding::DERIVE) {
            return [Binding::DERIVE => (string) ($data['derivation'] ?? '')];
        }

        if ($kind !== Binding::FRAGMENTS) {
            return [$kind => (string) ($data['value'] ?? '')];
        }

        $binding = [
            Binding::FRAGMENTS => (string) ($data['derivation'] ?? ''),
            'template'         => (string) ($data['fragment_template'] ?? ''),
        ];

        $glue = (string) ($data['glue'] ?? '');

        if ($glue !== '') {
            $binding['glue'] = $glue;
        }

        return $binding;
    }

    /**
     * One rule, as a form wants it.
     *
     * @param   array<string, mixed>  $rule  The rule, in the stored rule shape.
     *
     * @return  array<string, mixed>
     *
     * @since   0.1.0
     */
    private static function ruleToFormData(array $rule): array
    {
        $conditions = [];

        foreach (array_values((array) ($rule['when'] ?? [])) as $index => $condition) {
            $condition = (array) $condition;

            $conditions['condition' . $index] = [
                'operator' => (string) ($condition['operator'] ?? ''),
                'path'     => (string) ($condition['path'] ?? ''),
                'value'    => $condition['value'] ?? '',
            ];
        }

        $bindings = [];
        $index    = 0;

        foreach ((array) ($rule['bind'] ?? []) as $variable => $binding) {
            $bindings['binding' . $index++] = ['variable' => (string) $variable]
                + self::bindingToFormData((array) $binding);
        }

        return [
            'rule_id'   => (string) ($rule['id'] ?? ''),
            'source'    => (string) ($rule['for'] ?? ''),
            'template'  => (string) ($rule['template'] ?? ''),
            'output'    => (string) ($rule['target'] ?? ''),
            'condition' => $conditions,
            'binding'   => $bindings,
        ];
    }

    /**
     * One binding, as a form wants it.
     *
     * @param   array<string, mixed>  $binding  The binding, in the stored rule shape.
     *
     * @return  array<string, mixed>
     *
     * @since   0.1.0
     */
    private static function bindingToFormData(array $binding): array
    {
        $kinds = array_values(array_intersect(Binding::KINDS, array_keys($binding)));
        $kind  = $kinds[0] ?? Binding::LITERAL;
        $value = $binding[$kind] ?? '';

        $form = [
            'kind'              => $kind,
            'value'             => '',
            'derivation'        => '',
            'fragment_template' => '',
            'glue'              => '',
        ];

        if ($kind === Binding::DERIVE || $kind === Binding::FRAGMENTS) {
            $form['derivation'] = (string) $value;
        } else {
            $form['value'] = $value;
        }

        if ($kind === Binding::FRAGMENTS) {
            $form['fragment_template'] = (string) ($binding['template'] ?? '');
            $form['glue']              = (string) ($binding['glue'] ?? '');
        }

        return $form;
    }
}
