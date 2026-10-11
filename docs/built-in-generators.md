# The Extension Generator's built-in generators, as models here

The Extension Generator ships four generators. Its Generators page lists them as
"Built in": ready to run, not to edit. This page says which of them the Generator
Generator holds as editable models, how they get here, and what it would take
for the rest.

## Where each one stands

| Generator on the Extension Generator | Rule-driven | Model here | How it gets here |
|---|---|---|---|
| Joomla 6 component (`joomla6`) | yes, `joomla6.rules.json` | `tests/Fixtures/joomla6.generator.json` | `php tools/seed-generator.php` |
| Joomla component with posting rules (`joomla6-ledgerlang`, extends `joomla6`) | yes, `ledgerlang.rules.json` | `tests/Fixtures/ledgerlang.generator.json` | `php tools/seed-generator.php` |
| WordPress plugin (`wordpress`) | no, PHP classes | none | not possible yet |
| Drupal module (`drupal`) | no, PHP classes | none | not possible yet |

`seed-generator.php` seeds every `*.generator.json` fixture onto the development
site, parent first, and runs each one there. Open **Components > Generator
Generator > Generators** and both are on the list.

A model can only be made from rules. The Generator Generator edits rules: for
each, when, render, binding. The WordPress and Drupal generators are hand-written
`Generator` classes with no rule file and no published vocabulary, so there is
nothing to read into a form.

## Proposal 1: import a generator from the screen

Today a model reaches the site through a command-line tool and a committed
fixture. An **Import** button on the Generators list would let anybody do it:

- **Input:** a zip this component generated. It already carries the rule file
  and `generator.json` (target, metalanguage, `extends`). Add the form data to the
  zip as `model.json` (`GeneratorDefinition::toFormData()`), and an import is a
  read plus an insert.
- **Without `model.json`** (a package the Extension Generator ships, such as
  `ledgerlang.package.json` with its rule file): build the model with
  `GeneratorDefinition::fromRuleSet()`, as `import-generator.php` does. The groups,
  output path and namespace are not in a rule file. Take them from a manifest if
  one comes with it, or else default to one group per rule-id prefix.
- **Validation:** the same vocabulary check a save runs. A rule naming something
  the installed target does not publish is refused with the list of problems.
- **Wiring:** `GeneratorsController::import()`, an `ImportModel` taking a
  `VocabularyLibrary` via the MVC factory, a file field on the list toolbar, and a
  Cypress spec that imports the LedgerLang package and opens it.

Size: one controller task, one model, one toolbar button, a test per input shape.

## Proposal 2: WordPress and Drupal as rules

This is the same step 2.1 took for Joomla 6, once per target:

1. Move each file the target writes into a template plus a rule. The selectors and
   derivations go into `WordPressSelectors` / `WordPressDerivations` (and the same
   for Drupal), and the parts that are not rules stay as emitters (`emits` groups).
2. Publish `wordpress.vocabulary.json` and `drupal.vocabulary.json` from
   `build/vocabulary.php`. `VocabularyLibrary` finds them without a change here.
3. Capture golden output first, and require the rule-driven version to reproduce
   it byte for byte, the way `composer acceptance` does for Joomla 6.
4. Add manifests and run `import-generator.php`. The seed tool then picks the new
   models up.

Most of this work is in the Extension Generator. It is a step of its own in
`rework-plan.md`, not a change to this component.

## Open on the LedgerLang model

The seeded LedgerLang generator is not bound to the LedgerLang metalanguage,
because that language is not imported into this component on the development
site. Its package therefore says no metalanguage, where the one the Extension
Generator ships says `LedgerLang`. To bind it, import LedgerLang under
**Metalanguages** and choose it on the generator.
