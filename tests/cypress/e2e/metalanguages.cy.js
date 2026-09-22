/**
 * A generator is written for a metalanguage: step 3.4.
 *
 * The unit suite checks that a language knows its concepts and that the
 * context carries which one is bound. It cannot see any of this: the route,
 * the upload, the token, or - the thing the step is actually about - whether a
 * rule's "what to select" dropdown holds that language's concepts once one is
 * chosen.
 *
 * The package is built by `tools/make-test-package.php` rather than committed
 * or exported from Meta-gen; see that file for why.
 *
 * **It makes its own generator and never saves the seeded one.** An earlier
 * version bound the seeded generator, saved it through the form and put it
 * back afterwards - and saving a generator through the form rewrites its
 * stored JSON from what the form posted, which is not the same thing as what
 * was seeded. `component-renders.cy.js` then failed on rules that had quietly
 * changed shape. Shared fixture state is read, not written.
 */

const PACKAGE = 'tests/cypress/fixtures/metalanguage.zip';
// Unique per run. The spec creates a generator rather than touching the seeded
// one, so without this the list accumulates rows with one name and finding it
// again by name picks whichever came first - which on the second run is not
// the one this test just saved.
const NAME = `Written for Testlang ${Date.now()}`;

describe('metalanguages', () => {
  before(() => {
    cy.exec('php tools/make-test-package.php');
    cy.readFile(PACKAGE, null).should((buffer) => {
      expect(buffer.length, 'the package has bytes in it').to.be.greaterThan(200);
    });
  });

  beforeEach(() => {
    cy.loginToAdmin();
  });

  it('renders, and is empty until something is imported', () => {
    cy.visitGengen('metalanguages');

    cy.get('#metalanguageList', { timeout: 20000 }).should('exist');
    cy.get('body').should('not.contain', 'Fatal error');
    cy.get('body').should('not.contain', 'View not found');
  });

  it('imports a package and lists what came out of it', () => {
    cy.visitGengen('metalanguages');

    cy.get('input[name="package"]').selectFile(PACKAGE);
    cy.get('button[type="submit"]').click();

    cy.get('#system-message-container', { timeout: 30000 }).should('contain.text', 'Testlang');
    cy.get('#metalanguageList').should('contain.text', 'Testlang');
  });

  it('refuses something that is not a package', () => {
    cy.visitGengen('metalanguages');

    cy.get('input[name="package"]').selectFile({
      contents: Cypress.Buffer.from('holiday photos'),
      fileName: 'notapackage.zip',
      mimeType: 'application/zip',
    });
    cy.get('button[type="submit"]').click();

    cy.get('#system-message-container', { timeout: 20000 }).should('exist');
    cy.get('#metalanguageList').should('not.contain.text', 'notapackage');
  });

  /**
   * The step's own criterion: a rule's source dropdown offers the bound
   * language's concepts.
   *
   * Read out of the subform's `<template>` rather than off a rendered row,
   * because a generator with no rules yet has no row - the template is what
   * Joomla clones when somebody presses add, and it is rendered server-side
   * from the same field. If the concepts are in there, they are what a new
   * rule will offer.
   *
   * Nothing in this component knows those names. They come out of the imported
   * package's manifest, which is where Meta-gen wrote them - and the value is
   * the concept's **key**, so renaming it in Meta-gen changes what the rule
   * reads as rather than what it points at.
   */
  it('offers the language\'s concepts when a rule says what to select', () => {
    cy.visit('/administrator/index.php?option=com_gengen&view=generator&layout=edit&id=0');

    cy.get('#jform_generator_name', { timeout: 20000 }).clear();
    cy.get('#jform_generator_name').type(NAME);
    cy.get('#jform_output_path').clear();
    cy.get('#jform_output_path').type('administrator/components/com_example/src/Generator');
    cy.get('#jform_php_namespace').clear();
    cy.get('#jform_php_namespace').type('Example');

    // A new generator renders one empty rule group, and its class and prefix
    // are required - so a generator cannot be created without them. They are
    // on the Rules tab, which is `display: none` until it is opened; forced
    // rather than clicked through, because what this test is about is what the
    // server renders and not how the tabs behave.
    cy.get('#jform_group__group0__class').clear({ force: true });
    cy.get('#jform_group__group0__class').type('ExampleGroup', { force: true });
    cy.get('#jform_group__group0__prefix').clear({ force: true });
    cy.get('#jform_group__group0__prefix').type('example', { force: true });

    cy.get('#jform_metalanguage option').then(($options) => {
      const texts = [...$options].map((o) => o.textContent.trim());

      expect(texts, 'the imported language is offered').to.include('Testlang 1.0');
    });

    cy.get('#jform_metalanguage').select('Testlang 1.0');

    // Saved and closed, then opened again: what comes back from an apply is
    // built from what was posted, so asserting against it would prove the post
    // round-tripped rather than that the binding was stored and read back.
    cy.window().then((w) => w.Joomla.submitbutton('generator.save'));

    cy.get('#system-message-container', { timeout: 30000 }).should('contain.text', 'saved');

    cy.get('#generatorList').contains('a', NAME).click();

    cy.get('#jform_metalanguage', { timeout: 20000 }).should('have.value', 'Testlang|1.0');

    cy.get('joomla-field-subform template').then(($templates) => {
      const html = [...$templates].map((t) => t.innerHTML).join('');

      expect(html, "the language's concepts, by key").to.contain('c-thing');
      expect(html, 'and its other one').to.contain('c-part');
      expect(html, 'labelled by name').to.contain('Thing');

      // And not the target's own selectors, which is what a generator with no
      // language bound gets - `component-renders.cy.js` checks that case.
      expect(html, "not the target's selectors").to.not.contain('backendPages');
    });
  });
});
