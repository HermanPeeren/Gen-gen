/**
 * A saved generator survives being opened and saved again.
 *
 * `save()` already carries a note about this gap: Gen-gen 0.1.0 could not save
 * a generator from the UI at all, *"and nothing had noticed because nothing had
 * ever saved one: the browser spec opened the screens and read them, and every
 * other test works on stored JSON directly."* That was fixed. The gap in the
 * testing was not - saving is still something no spec does.
 *
 * It matters here more than almost anywhere, because a generator is stored as
 * one JSON blob and `save()` serialises what the form posted. Anything the form
 * did not render is not merely absent from the screen; it is gone from the
 * record the moment somebody presses Save. Exten-gen had exactly that and lost
 * a project from 18941 bytes to 63 on one click, with a success message on top.
 *
 * The record this opens is the one that would hurt to lose: Exten-gen's own
 * generator, twenty-seven rules and five groups, the same one
 * `check-against-extengen.php` runs to reproduce the approved output file for
 * file. If a save quietly drops a rule, the acceptance check is the next thing
 * to fail and it will be a long way from the cause.
 *
 * Seeded by `tools/seed-generator.php`, which is idempotent and which
 * `beforeEach` runs - a spec about losing data has to own its data.
 */

const RULES = 27;
const GROUPS = 5;

// Every binding of Exten-gen's generator that names no derivation, which is
// most of them: a binding of kind `path` reads a value, it does not compute
// one. The number is the point rather than the identity of any one of them -
// it went to zero on a single Save, and nothing said so.
const BINDINGS_WITHOUT_A_DERIVATION = 115;

const openTheGenerator = () => {
  cy.visit('/administrator/index.php?option=com_gengen&task=generator.edit&id=1');
  cy.get('#generator-form', { timeout: 20000 }).should('exist');
};

/**
 * How many binding rows are showing with no derivation chosen.
 *
 * This is the assertion the row counts could not make. Saving used to keep all
 * twenty-seven rules and quietly rewrite what was inside them: `derivation` and
 * `fragment_template` are lists with no empty entry, so Joomla rendered the
 * first option as selected wherever the stored value was empty, `showon` hid
 * the field without stopping it posting, and the save wrote that first option
 * back. Every path binding came back deriving `adminLinkPageName` from a
 * fragment template called `LICENSE`.
 *
 * Nothing in Gen-gen would have noticed. The rule count is the same, the names
 * are the same, and the first thing to fail is the acceptance check that
 * reproduces Exten-gen's output - a long way from the cause.
 */
const bindingsWithoutADerivation = (doc) =>
  [...doc.querySelectorAll('#generator-form select[name$="[derivation]"]')]
    .filter((select) => select.value === '').length;

/**
 * How many rows of each kind the form is showing.
 *
 * Counted off live inputs, never out of a `<template>`: a repeatable subform
 * keeps one row's markup in one, and what Joomla clones says nothing at all
 * about what it bound.
 */
const rowsOnScreen = (doc, group) =>
  new Set(
    [...doc.querySelectorAll(`#generator-form [name^="jform[${group}]["]`)]
      .map((input) => (input.getAttribute('name') || '').match(/^jform\[\w+\]\[(\w+)\]/))
      .filter(Boolean)
      .map((match) => match[1])
  ).size;

describe('a saved generator', () => {
  beforeEach(() => {
    cy.loginToAdmin();
    cy.exec('php tools/seed-generator.php');
  });

  it('opens with its rules and groups on the screen', () => {
    openTheGenerator();
    cy.shouldHaveRendered();

    cy.get('#jform_generator_name').should('have.value', 'Exten-gen: a Joomla 6 component');

    cy.document().then((doc) => {
      expect(rowsOnScreen(doc, 'rule'), 'the stored rules').to.equal(RULES);
      expect(rowsOnScreen(doc, 'group'), 'the stored groups').to.equal(GROUPS);
      expect(bindingsWithoutADerivation(doc), 'bindings that derive nothing')
        .to.equal(BINDINGS_WITHOUT_A_DERIVATION);
    });
  });

  /**
   * And still has them afterwards.
   *
   * The wait is for a marker on `window` to disappear rather than for a
   * selector, because a selector that exists on both pages is satisfied by the
   * document that has not navigated yet - which is how the equivalent test in
   * Exten-gen passed while the save was destroying the record behind it. The
   * model is then read back from a fresh request, not from the posted page.
   */
  it('still has them after being opened and saved', () => {
    openTheGenerator();

    cy.window().then((win) => {
      win.__beforeSave = true;
      win.Joomla.submitbutton('generator.apply');
    });

    cy.window({ timeout: 20000 }).should((win) => {
      expect(win.__beforeSave, 'the save has reloaded the page').to.be.undefined;
    });

    cy.get('body').should('not.contain', 'Fatal error');

    openTheGenerator();

    cy.document().then((doc) => {
      expect(rowsOnScreen(doc, 'rule'), 'the rules survived the round trip').to.equal(RULES);
      expect(rowsOnScreen(doc, 'group'), 'the groups survived the round trip').to.equal(GROUPS);
      expect(bindingsWithoutADerivation(doc), 'and so did what was inside them')
        .to.equal(BINDINGS_WITHOUT_A_DERIVATION);
    });
  });
});
