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
const DERIVED = 'tests/cypress/fixtures/derived.zip';
// Unique per run. The spec creates a generator rather than touching the seeded
// one, so without this the list accumulates rows with one name and finding it
// again by name picks whichever came first - which on the second run is not
// the one this test just saved.
const NAME = `Written for Testlang ${Date.now()}`;

describe('metalanguages', () => {
  before(() => {
    // Nothing left over from an earlier run. These tests name the generator
    // they create with a timestamp, so every run used to leave a new one and
    // the site had reached seventeen against one real generator.
    cy.exec('php tools/forget-test-generators.php');

    cy.exec('php tools/make-test-package.php');

    // And one built on it: step 4.5. Derived from that package rather than
    // written by hand, so it cannot drift from the concepts the import guard
    // asks about.
    cy.exec('php tools/make-derived-package.php');
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

      // By name, which is what the vocabulary is keyed by and what the
      // validator will check the rule against. 3.4 offered the concept *key*
      // here and nothing added it to the vocabulary, so a rule written that way
      // was refused when it ran.
      expect(html, "the language's concepts, by name").to.contain('Thing');
      expect(html, 'and its other one').to.contain('Part');

      // The target's own selectors are offered too, in the same list: a
      // generator written for a language still generates for a target.
      expect(html, "the target's selectors as well").to.contain('backendPages');
    });
  });

  /**
   * A rule may select a concept of the language, or of one it derives from: 4.5.
   *
   * Which is the whole point of deriving. A generator written for a parent runs
   * over a child's models - the import refuses a child that renamed or dropped
   * one of the parent's concepts, so every name a parent's rule selects is
   * still there - and a generator modelled here is one of those. A selector
   * list that offered only the child's concepts would make the derivation
   * something you could declare and not use.
   *
   * The fixture adds a concept of its own for exactly this assertion: with no
   * addition, "the parent's concepts" and "the child's" would look the same.
   */
  it('offers a parent language\'s concepts as well as the child\'s', () => {
    cy.visit('/administrator/index.php?option=com_gengen&view=metalanguages');

    cy.get('input[name="package"]').selectFile(DERIVED);
    cy.get('button[type="submit"]').click();

    cy.get('#system-message-container', { timeout: 30000 }).should('contain.text', 'DerivedTestlang');

    cy.visit('/administrator/index.php?option=com_gengen&view=generator&layout=edit&id=0');

    cy.get('#jform_generator_name', { timeout: 20000 }).clear();
    cy.get('#jform_generator_name').type(`${NAME}Derived`);
    cy.get('#jform_output_path').clear();
    cy.get('#jform_output_path').type('administrator/components/com_example/src/Generator');
    cy.get('#jform_php_namespace').clear();
    cy.get('#jform_php_namespace').type('Example');

    cy.get('#jform_group__group0__class').clear({ force: true });
    cy.get('#jform_group__group0__class').type('ExampleGroup', { force: true });
    cy.get('#jform_group__group0__prefix').clear({ force: true });
    cy.get('#jform_group__group0__prefix').type('example', { force: true });

    cy.get('#jform_metalanguage').select('DerivedTestlang 1.0');

    cy.window().then((w) => w.Joomla.submitbutton('generator.save'));

    cy.get('#system-message-container', { timeout: 30000 }).should('contain.text', 'saved');

    cy.get('#generatorList').contains('a', `${NAME}Derived`).click();

    cy.get('joomla-field-subform template', { timeout: 20000 }).then(($templates) => {
      const html = [...$templates].map((t) => t.innerHTML).join('');

      expect(html, "the child's own concept").to.contain('Extra');
      expect(html, "and its parent's").to.contain('Thing');
      expect(html, "and its parent's other one").to.contain('Part');
      expect(html, "and the target's selectors").to.contain('backendPages');
    });
  });

  /**
   * And the list shows what was written for a parent when asked about a child.
   *
   * "The generators I could run over a model in this language" is the question
   * somebody is actually asking of that filter, and a list that answered only
   * with the ones bound to that exact language would answer a narrower one.
   */
  it('lists a parent language\'s generators under the child', () => {
    // Driven by the request rather than by a control, because this screen has
    // no filter bar: the filter form has existed since 0.1 and the template has
    // never rendered it, so `target` is reachable the same way and by nothing
    // else. A gap this step found rather than made, written down in the plan -
    // what is asserted here is the filtering, which is the part 4.5 changed.
    const list = '/administrator/index.php?option=com_gengen&view=generators&filter_metalanguage=';

    cy.visit(list + 'DerivedTestlang|1.0');

    // The one bound to the child, and the one bound to its parent.
    cy.get('#generatorList', { timeout: 20000 }).should('contain.text', NAME + 'Derived');
    cy.get('#generatorList').should('contain.text', NAME);

    // And not everything: asked about the parent, the child's generator is not
    // an answer - deriving points one way.
    cy.visit(list + 'Testlang|1.0');

    cy.get('#generatorList', { timeout: 20000 }).should('contain.text', NAME);
    cy.get('#generatorList').should('not.contain.text', NAME + 'Derived');

    // Put it back: a list filter is user state, and a spec that left one set
    // would filter every list the specs after it look at.
    cy.visit(list);
  });
});
