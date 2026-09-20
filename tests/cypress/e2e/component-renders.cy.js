/**
 * The screens exist and can be used.
 *
 * `tools/smoke.php` goes a long way without a browser: it boots the component
 * through its own provider, resolves every class the package shipped, and
 * builds all six forms. It still cannot tell you whether a screen renders.
 * Exten-gen's views passed everything it had and returned 200 with an empty
 * body, for six steps, because `die` is not an error.
 *
 * So these are the questions only a browser answers: does the list come up,
 * does the edit form come up with its subforms in it, and do the dropdowns hold
 * the target's actual vocabulary rather than being empty boxes.
 */

describe('the component', () => {
  it('shows the list of generators', () => {
    cy.visitGengen('generators');

    cy.shouldHaveRendered();
    cy.get('h1, .page-title').should('contain.text', 'Generators');
  });

  it('knows which targets this site can generate for', () => {
    cy.visitGengen('generators');

    // Seeded by tools/seed-generator.php. Without it the list is correctly
    // empty and says so, which is a different screen.
    cy.get('#generatorList').should('exist');
    cy.get('#generatorList tbody tr').should('have.length.greaterThan', 0);
    cy.get('#generatorList').should('contain.text', 'joomla6');
  });

  it('opens a stored generator for editing', () => {
    cy.visitGengen('generators');

    cy.get('#generatorList tbody tr th a').first().click();

    cy.shouldHaveRendered();
    cy.get('#generator-form').should('exist');
    cy.get('#jform_generator_name').should('have.value', 'Exten-gen: a Joomla 6 component');
  });

  /**
   * The point of the whole thing: a rule is filled in from a list, not typed.
   *
   * An unresolved field type does not fail in Joomla, it silently becomes a
   * text box - which is how "pick one of the target's selectors" turns into
   * "type anything you like". That already happened once, in the field type's
   * capitalisation, and CI caught it only because a test asked.
   */
  it('offers the target\'s vocabulary rather than empty boxes', () => {
    cy.visitGengen('generators');
    cy.get('#generatorList tbody tr th a').first().click();

    // The rules live on their own tab. Joomla 6 renders a <joomla-tab> custom
    // element with a <joomla-tab-element> per panel and builds the clickable
    // nav from those in JavaScript - not the .tab-pane divs an older Joomla
    // produced, which is what this asked for first.
    cy.get('joomla-tab#generatorTab joomla-tab-element#rules').should('exist');
    cy.get('joomla-tab#generatorTab').should('contain.text', 'Rules');

    // The fields themselves are read where they are. A tab hides its panel
    // with CSS, so the selects are in the DOM either way, and asserting on
    // what they hold is the question - whether the panel is on screen at this
    // moment is not.
    // The first rule's "for each" is a select holding this target's selectors.
    cy.get('select[name*="[source]"]').first().as('source');

    cy.get('@source').find('option').then((options) => {
      const values = [...options].map((o) => o.value).filter(Boolean);

      expect(values, 'the selectors joomla6 published').to.include.members([
        'root',
        'entities',
        'backendPages',
        'frontendPages',
      ]);
    });

    cy.get('@source').should('have.value', 'root');

    // And a derivation dropdown holds names the target registered.
    cy.get('select[name*="[derivation]"]').first().find('option').then((options) => {
      const values = [...options].map((o) => o.value).filter(Boolean);

      expect(values, 'the derivations joomla6 published').to.include('componentNameUcfirst');
    });
  });

  it('renders without a JavaScript error', () => {
    cy.visit('/administrator/index.php?option=com_gengen&view=generators', {
      onBeforeLoad(win) {
        cy.spy(win.console, 'error').as('consoleError');
      },
    });

    cy.get('body').should('not.be.empty');
    cy.get('@consoleError').should('not.have.been.called');
  });
});
