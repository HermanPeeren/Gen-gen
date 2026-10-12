# The Extension Generator's built-in generators, as models here

The Extension Generator ships four generators. Its Generators page lists them as
"Built in": ready to run, not to edit. This page says which of them the Generator
Generator holds as editable models, how they get here, and what it would take
for the rest.

## Where each one stands

| Generator on the Extension Generator | Rule-driven | Model here | How it gets here |
|---|---|---|---|
| Joomla 6 component (`joomla6`) | yes, `joomla6.rules.json` | `tests/Fixtures/joomla6.generator.json` | `php tools/seed-generator.php`, or the Import button |
| Joomla component with posting rules (`joomla6-ledgerlang`, extends `joomla6`) | yes, `ledgerlang.rules.json` | `tests/Fixtures/ledgerlang.generator.json` | `php tools/seed-generator.php`, or the Import button |
| WordPress plugin (`wordpress`) | no, PHP classes | none | not possible yet |
| Drupal module (`drupal`) | no, PHP classes | none | not possible yet |

`seed-generator.php` seeds every `*.generator.json` fixture onto the development
site, parent first, and runs each one there. Open **Components > Generator
Generator > Generators** and both are on the list. Without the command line, the
Import button does the same from a zip: see below.

A model can only be made from rules. The Generator Generator edits rules: for
each, when, render, binding. The WordPress and Drupal generators are hand-written
`Generator` classes with no rule file and no published vocabulary, so there is
nothing to read into a form.

## Import a generator from the screen (done in 0.6.0)

This was proposal 1. A model no longer needs the command line and a committed
fixture to reach a site: the **Import** button on the Generators list does it.

### How to use it

1. Open **Components > Generator Generator > Generators** and press **Import**
   in the toolbar. A panel with a file field opens above the list.
2. Choose a zip and press **Import** in the panel.
3. The generator is on the list. Open it to edit it like any other.

Importing needs the *Create* permission on the component, the same as **New**.

### What it reads

- **A zip this component generated.** Since 0.6.0 the Generate button puts
  `model.json` in the zip beside the rule file and `generator.json`. It is the
  form data (`GeneratorDefinition::toFormData()`), so the import gives back
  exactly the generator that was generated: groups, summaries, output path and
  namespace included.
- **A zip without `model.json`**: one generated before 0.6.0, or the Extension
  Generator's own files zipped up. The rules come from the rule file
  (`GeneratorDefinition::fromRuleSet()`). The rest comes from the first of these
  that the zip holds:
  - a modelling manifest, the `*.manifest.json` that `import-generator.php`
    reads. It knows the groups with their summaries, the output path and the
    namespace;
  - `generator.json` or the Extension Generator's `*.package.json`. They know the
    groups, and Gen-gen's names the rule file under the output path;
  - nothing: then there is one group per rule-id prefix (`admin.mvc.index.model`
    is in `admin.mvc.`), and the screen says that this is a guess.

  So for the two rule-driven generators the Extension Generator ships:

  | Generator | Zip these files together |
  |---|---|
  | Joomla 6 component | `joomla6.rules.json`, `joomla6.manifest.json` |
  | Joomla component with posting rules | `ledgerlang.package.json`, `ledgerlang.rules.json`, `ledgerlang.manifest.json` |

  The rule files and the package file are in the Extension Generator's
  `src/Generator/Rules`; copies of all of them are in `tests/Fixtures`.
  `php tools/make-generator-package.php` writes the second zip, which is what
  the browser spec imports. A rule file on its own also imports: the target is
  its file name, and the groups are guessed.

### What it checks

The rules are checked against the vocabulary the installed target publishes,
through `VocabularyLibrary`, the same list the rule form's dropdowns offer. A rule
naming a selector, derivation or template the target does not have is refused
with the list of problems, and nothing is stored. When the generator is bound to
a metalanguage (see below), a rule may also select that language's concepts.

Something that is not a zip, a zip without a rule file, a manifest in a newer
format and a rule file that is not rules are refused with a sentence that says so.

### Three decisions

- **The same generator twice replaces it.** A generator with the same name is
  replaced, keeping its id and its published state. The metalanguage import and
  the Extension Generator's generator import both replace by key, and a
  package's key is made from the name. The import panel says so before you
  choose a file, and the message after it says which generator was replaced.
- **A generator that extends another imports without its parent.** `extends`
  names a generator on the *Extension Generator*, by its id there (`joomla6`),
  and that is where the parent's rules are laid under the child's when it runs.
  The Generator Generator does not compose them, so it does not need the parent
  modelled here. The import says that the generator extends `joomla6` and that
  `joomla6` has to be on the Extension Generator's site, where its own import
  refuses a child whose parent is missing.
- **The binding follows the package.** A package says which metalanguage its
  rules are for. When that language is imported here, the generator is bound to
  it. A package that names no version, as the Extension Generator's
  `ledgerlang.package.json` does, binds to the newest version here. When the
  language is not here, the generator is imported unbound with a warning that
  says to import the language and choose it. A replaced generator keeps the
  binding it had unless the package binds it.

### Wiring

- `ImportedGenerator` reads the zip, without touching the site.
- `ImportModel` checks, binds and stores. It gets the `VocabularyLibrary` from the
  component's own MVC factory (`Service\MVCFactory`), which hands it to every
  model or controller that implements `VocabularyLibraryAwareInterface`. The
  library is registered once, lazily, in `services/provider.php`
  (`Service\Provider\Vocabularies`), and the component gives the same one to the
  form fields when it boots.
- `GeneratorsController::import()` checks the token and the permission and says
  what happened.
- Tests: `ImportedGeneratorTest`, one per input shape, and
  `generator-import.cy.js`, which imports the LedgerLang package through the
  button and opens it.

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

## The LedgerLang model is bound

The seeded LedgerLang generator was not bound to the LedgerLang metalanguage,
because that language had not been imported into this component on the
development site, so its package said no metalanguage. On 12 October 2026
LedgerLang 0.1 was imported under **Metalanguages** and the LedgerLang generator
package imported through the Import button, which bound it. Its package now says
`LedgerLang` 0.1, as the one the Extension Generator ships says `LedgerLang`.

On a new site, import LedgerLang under **Metalanguages** first (the Metalanguage
Generator's `build/LedgerLang-0.1.zip`), then import the generator package.
`seed-generator.php` seeds the generator unbound.
