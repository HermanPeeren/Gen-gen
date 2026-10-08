/**
 * Every string on screen is translated: step 5.1.
 *
 * `LanguageStringsTest` reads the source. This reads what is rendered, which is
 * the only place a constant built at run time shows - and the menu, which reads
 * the `.sys.ini` alone. The Metalanguages entry was raw there before this spec.
 */

describe('language strings', () => {
  ['generators', 'metalanguages'].forEach((view) => {
    it(`shows the ${view} page with every string translated`, () => {
      cy.visitGengen(view);
      cy.shouldHaveRendered();
      cy.shouldShowNoRawConstants();
    });
  });

  it('shows a generator with every string translated', () => {
    cy.visitGengen('generators');
    cy.get('#generatorList tbody tr th a').first().click();
    cy.shouldHaveRendered();
    cy.shouldShowNoRawConstants();
  });

  it('shows the menu entries translated', () => {
    cy.visitGengen('generators');
    cy.get('#sidebarmenu a[href*="option=com_gengen"]').each(($link) => {
      expect($link.text().trim(), 'a menu entry').to.not.match(/^COM_GENGEN_/);
    });
  });
});
