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
    Generator/
      Model/ModelledGenerator.php   a generator, as the thing being generated
      Joomla/RuleFileGenerator.php  the mapping, emitted as data
      Joomla/GeneratorClassGenerator.php  the classes that carry it
      Target/JoomlaGeneratorTarget.php    what a modelled generator becomes
  generator_templates/
    Joomla/GeneratorClass.php.twig
tests/
  Fixtures/                    Exten-gen's real rule set, vocabulary and generator
  Unit/                        the suite
tools/
  import-vocabularies.php      refresh those fixtures from a sibling checkout
  import-generator.php         build the modelled generator from a rule file
  generate.php                 run a modelled generator through the pipeline
  check-against-extengen.php   the acceptance criterion
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

## Generating a generator

```bash
composer generate     # into build/generated/
```

Four files: the rule file, and one class per group that is only rules.

```
administrator/components/com_extengen/src/Generator/Rules/joomla6.rules.json
administrator/components/com_extengen/src/Generator/Joomla6/ComponentGeneral.php
administrator/components/com_extengen/src/Generator/Joomla6/AdminMVC.php
administrator/components/com_extengen/src/Generator/Joomla6/SiteMVC.php
```

**The model here is a description of a generator and the output is a
generator**, which sounds like a trick and is not: nothing in the core changed
to allow it. `ModelInterface` is a marker, `Pipeline` never looks inside a
model, and a target is a structure plus emitters plus a template set. A
generator happens to be describable that way, so it is one.

The two halves split the same way everything else here does. The rule file is
the mapping, **emitted** as data — `RuleSet::toJson()` already knows how to
write it, and rendering JSON from a Twig template would mean reimplementing
quoting in Twig, which works until somebody's derivation is called
`refDisplay"Name`. The classes are wiring, **rendered** from a template: a rule
file does not run by itself, so something has to be a class, claim a slice of
the rules and sit in the order Joomla runs them in.

**It does not generate the groups that emit.** `AdminGeneral` writes language
strings and `AdminEntities` writes sql for a schema only knowable once every
entity has been seen. Neither is expressible as a rule, so neither is generated
— a generated class replacing them would delete that code and leave something
that looks complete. The group says `emits` in the model and this skips it.
That is the same line 2.1 drew between rules and emitters, drawn once more.

## The acceptance criterion

```bash
composer acceptance
```

Not a judgement call, and not "the rules round-trip" either — that is necessary
and would not notice a binding resolving to the wrong thing. The generated
generator is **run**, over Exten-gen's three golden models, and every file it
produces is compared with the output Stage 1 approved for the hand-written
generator it replaces. Both directions: nothing missing, nothing extra.

> 228 files compared, all identical to the approved output.

**It runs in a process of its own**, because the generated classes have the same
fully qualified names as Exten-gen's hand-written ones — that is the point, they
are meant to be the same classes — so they are required before anything can
autoload the originals. A class is defined once per process, so doing this inside
the suite would make the answer depend on what had already been loaded.

**It compares through `GoldenFiles::normalise()`**, the library's own function,
which is what Stage 1's golden test uses: line endings, because the approved
files are committed on Windows, and the trailing newline, because a template's
output ends with exactly one and a file assembled by `implode()` ends with none.
Comparing raw bytes reported every sql and language file as different while
every rendered file matched — an accurate description of the normalisation and a
poor description of the generator.

**The rule file is byte-identical**, which is asserted separately and is where a
drift shows up first. That required Exten-gen's committed rule file to become
canonical `toJson()` output rather than the hand formatting it had: "Gen-gen
generates this file" is only checkable if there is exactly one way to write a
given rule set down. Exten-gen has a test that keeps it that way.

**CI checks Exten-gen out beside this repository** so the check actually runs
there. On a machine with only this repository cloned it skips with an
explanation — a skipped test that is the whole point of the repository is worse
than no test, so it must not pass silently.

## Not here yet

The component's MVC, its manifest, its `script.php`, its package and its release
workflow. That is step 2.4 of the rework plan. The forms exist and are checked,
and a generator can be generated and proven from the command line — what is
missing is a screen to open the forms on.
