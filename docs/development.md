# Developing Gen-gen

A Joomla component that models generators. What a generator *is* — the rule
vocabulary, and the engine that runs it — lives in the shared library; this
repository is the modelling of one.

## Layout

```
src/administrator/components/com_gengen/
  forms/
    generator.xml              a generator: which target, then its rules
    rule.xml                   one rule: source, conditions, template, output, bindings
    condition.xml              one condition: operator, path, value
    binding.xml                one binding: variable, kind, and the boxes that kind uses
  src/
    Generator/
      GeneratorDefinition.php  form shape <-> rule shape, in one place
      VocabularyLibrary.php    which targets this site can model a generator for
      VocabularyContext.php    which one the fields are currently offering
    Field/
      VocabularyListField.php  a dropdown whose choices come from the target
      *Field.php               the six of them
tests/
  Fixtures/                    Exten-gen's real rule set and vocabulary
  Unit/                        the suite
tools/
  import-vocabularies.php      refresh those fixtures from a sibling checkout
  phpstan-bootstrap.php        the Joomla constants this component reads
```

## The shared library

`Yepr\Gen\Core` comes from
[generator-core](https://github.com/HermanPeeren/generator-core), through
composer for the tests and through the installed `lib_yepr_gen` at run time.
Nothing here re-implements a rule: `RuleSet`, `Rule`, `Condition`, `Binding` and
`Vocabulary` are all the library's, and this component arranges them.

## Two shapes for one generator

The thing that runs is a `RuleSet`: an ordered list of rules, each binding named
variables under a single-key kind.

```json
{
    "id": "admin.entity.table",
    "for": "entities",
    "when": [{ "operator": "missing", "path": "isvalueobject" }],
    "template": ".../src/Table/Table.php.twig",
    "target": "administrator/components/com_{componentName|lower}/src/Table/{entityName}Table.php",
    "bind": { "entityName": { "derive": "entityNameUcfirst" }, "getFK": { "literal": "" } }
}
```

Joomla's form layer cannot hold that. A repeating group is stored as an object
keyed `binding0`, `binding1`; a field holds one scalar, not a key that is itself
the meaning. So the form shape is flat — every binding carries a `kind` and the
fields that kind uses:

```json
{ "variable": "entityName", "kind": "derive", "derivation": "entityNameUcfirst",
  "value": "", "fragment_template": "", "glue": "" }
```

**`GeneratorDefinition` is the translation, and it is the only one.** A generator
modelled here and a generator committed as a rule file have to be the same
generator or step 2.3 has nothing to compare, so the proof is a round trip:
Exten-gen's own twenty-seven rules, through the form shape and back, identical —
and then checked against the target's vocabulary, because two arrays matching
would prove nothing if both were nonsense.

Three things that translation gets right and a naive one would not:

- **An empty literal is a value.** `getFK` is bound to `""` and the template
  reads it. Treating empty as absent drops the binding, and the generated Table
  class dies on a strict-variables error naming a variable nobody can find in
  any model.
- **A valueless operator writes no value.** The form's value box is hidden for
  `has` and `missing`, but hidden is not empty — it still holds whatever was
  typed before the operator changed. `{"operator":"missing","path":"x","value":""}`
  reads exactly like a deliberate comparison with the empty string.
- **Order survives.** Later rules overwrite earlier ones at the same output
  path, and consecutive rules over one source pattern run node-major. Reordering
  rules in the form changes what a generated language file contains.

## Why the dropdowns are the point

A `type="RuleSelector"` field offers the target's selectors, `type="Derivation"`
its derivations, `type="RuleTemplate"` its templates. `type="Operator"` and
`type="BindingKind"` come from the library's own constants, because a target may
add selectors and derivations but does not get to add operators.

**The capitals are load-bearing.** `FormHelper::loadClass()` builds the class
name as `ucfirst(ucwords($type)) . 'Field'`, and `ucwords` only touches letters
after whitespace - so `type="ruleselector"` asks for `RuleselectorField`, which
is one letter away from the class and, on a case-insensitive filesystem, no
letters away at all. It passed on Windows and failed on the first CI run, which
is what CI is for. On a Linux server the class is simply not found and the
closed list degrades into a text box.

That is what makes this a modelling tool rather than a JSON editor with rounded
corners. The engine enforces a closed vocabulary; these fields *offer* it, so a
rule naming something that does not exist cannot be written in the first place.

**A target publishes its vocabulary; Gen-gen finds it.** Exten-gen's
`build/vocabulary.php` writes `joomla6.vocabulary.json` from its live registries
and its template directory, and ships it inside its own component.
`VocabularyLibrary` globs for `*.vocabulary.json` under the installed
components. So Gen-gen depends on nothing it models generators for, and a new
target appears in the list by being installed.

**`VocabularyContext` is ambient state, which deserves an explanation rather
than an apology.** Joomla constructs form fields itself, from XML, with no
constructor arguments and no container — a field's only inputs are its own
attributes and whatever it can reach. The target is chosen once at the top of the
generator form and needed by fields two subforms deep, where `$this->form` is the
subform's own `Form` and knows nothing about it. So the edit form says which
target it is for, once, before rendering, and the fields read it there. It is
small, it is named, and it is resettable, which is what keeps it testable.

With exactly one target installed nothing has to say anything. With none or
several and none chosen, the fields offer an empty list and say why — a list
assembled from whichever target was found first would let somebody build a rule
set that validates against nothing.

## Quality gates

```bash
composer test      # phpunit
composer analyse   # phpstan, level 5
composer cs        # phpcs: PSR-12, minus what contradicts Joomla
```

`composer analyse` wants a Joomla to resolve `ListField` against. Unpack a
Joomla 6 package into `/joomla`, which is git-ignored; CI fetches its own.

**What the tests can and cannot see.** Everything here reads XML or converts
arrays; nothing boots Joomla. `FormsTest` is the substitute for the compiler a
form file does not have: every subform source exists, every custom field type has
a class, every class declares the type its form asks for, and every `showon`
names a value the library actually has. A `formsource` Joomla cannot resolve
raises nothing — the subform renders with no fields in it, which looks like a
feature somebody forgot to fill in. A field type it cannot resolve falls back to
a plain text box, which silently turns a closed list of the target's selectors
into a place to type anything at all.

What none of it can see is whether the form is usable. That needs the component
to exist, which is step 2.4.

## The fixtures

`tests/Fixtures/joomla6.*.json` are copies of the files Exten-gen runs in
production. The round trip is only a proof while they are — a fixture that ages
into a simpler rule set than the real one still passes and stops meaning
anything.

```bash
composer import-vocabularies        # from ../Exten-gen
```

Nothing imports automatically: a fixture that changed underneath a test run
would turn "this still works" into "this works today". Run it, read the diff,
commit it like any other change.

## Not here yet

The component's MVC, its manifest, its `script.php`, its package and its release
workflow. That is step 2.4 of the rework plan, and step 2.3 comes first: generate
a generator, and check its output byte for byte against the hand-written one it
replaces.
