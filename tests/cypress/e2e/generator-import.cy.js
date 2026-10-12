/**
 * The Import button on the Generators list: 0.6.0.
 *
 * `ImportedGeneratorTest` reads every kind of package without a site. What it
 * cannot see is the rest of the way: the toolbar button, the upload, the token,
 * the row the import stores, and the binding to a metalanguage this site has
 * imported. This imports the Extension Generator's LedgerLang generator ("Joomla
 * component with posting rules") through the button and opens it.
 *
 * The package is built by `tools/make-generator-package.php` from the committed
 * fixtures: the two files the Extension Generator ships, with the modelling
 * manifest beside them. What comes in is therefore the generator
 * `seed-generator.php` seeds, and importing it replaces that row with the same
 * generator - which is the "same generator twice" case, and leaves the shared
 * fixture as the other specs expect it.
 *
 * **The binding needs LedgerLang under Metalanguages.** The spec imports it
 * from the Metalanguage Generator's build beside this repository when that is
 * there, and says so and checks the rest when it is not (as on CI).
 */

const PACKAGE = 'tests/cypress/fixtures/ledgerlang-generator.zip';
const BROKEN = 'tests/cypress/fixtures/ledgerlang-generator-broken.zip';
const LEDGERLANG = '../Meta-gen/build/LedgerLang-0.1.zip';
const NAME = 'Joomla component with posting rules';

describe('importing a generator', () => {
  before(() => {
    cy.exec('php tools/make-generator-package.php');
  });

  beforeEach(() => {
    cy.loginToAdmin();
  });

  it('has an Import button that shows the file field', () => {
    cy.visitGengen('generators');

    cy.get('#gengen-import').should('not.be.visible');
    cy.get('#toolbar-import button').should('contain.text', 'Import').click();

    cy.get('#gengen-import').should('be.visible');
    cy.get('#gengen-import input[type="file"][name="package"]').should('be.visible');
    cy.shouldShowNoRawConstants();
  });

  it('imports the LedgerLang generator package, bound to LedgerLang when it is here', () => {
    cy.task('exists', LEDGERLANG).then((exists) => {
      if (exists) {
        // Importing a metalanguage again replaces it, so this is safe to repeat.
        cy.visitGengen('metalanguages');
        cy.get('input[name="package"]').selectFile(LEDGERLANG);
        cy.get('button[type="submit"]').click();
        cy.get('#system-message-container', { timeout: 30000 }).should('contain.text', 'LedgerLang');
      } else {
        cy.task('log', `No ${LEDGERLANG}: the binding is not checked.`);
      }

      cy.visitGengen('generators');
      cy.get('#toolbar-import button').click();
      cy.get('#gengen-import input[name="package"]').selectFile(PACKAGE);
      cy.get('#gengen-import-submit').click();

      cy.get('#system-message-container', { timeout: 30000 }).should('contain.text', `Imported ${NAME}: 3 rules`);

      // It extends the Joomla 6 generator, and the result says where that one has to be.
      cy.get('#system-message-container').should('contain.text', 'extends the generator joomla6');

      if (exists) {
        cy.get('#system-message-container').should('contain.text', 'bound to it');
      }

      // Imported twice (the seed tool put it here first): still one row.
      cy.get('#generatorList tbody tr th a').then((links) => {
        const named = [...links].filter((link) => link.textContent.trim() === NAME);

        expect(named, 'one row with that name').to.have.length(1);
      });

      cy.contains('#generatorList tbody tr th a', NAME).click();

      cy.shouldHaveRendered();
      cy.get('#jform_generator_name').should('have.value', NAME);
      cy.get('#jform_extends').should('have.value', 'joomla6');
      cy.get('#jform_output_path').should('have.value', 'administrator/components/com_extengen/src/Generator');
      cy.get('#generator-form input[name$="[rule_id]"]').then((inputs) => {
        expect([...inputs].map((input) => input.value)).to.deep.equal([
          'admin.general.ledger.roles',
          'admin.general.ledger.line',
          'admin.general.ledger.postingrules',
        ]);
      });

      if (exists) {
        cy.get('#jform_metalanguage').invoke('val').should('match', /^LedgerLang\|/);
      }

      // Closed rather than left checked out, so the next spec can open it.
      cy.window().then((w) => w.Joomla.submitbutton('generator.cancel'));
      cy.get('#generatorList', { timeout: 20000 }).should('exist');
    });
  });

  it('refuses a package whose rules name what the target does not publish', () => {
    cy.visitGengen('generators');
    cy.get('#toolbar-import button').click();
    cy.get('#gengen-import input[name="package"]').selectFile(BROKEN);
    cy.get('#gengen-import-submit').click();

    cy.get('#system-message-container', { timeout: 30000 })
      .should('contain.text', 'The generator was not imported')
      .and('contain.text', '"balloons" is not a selector of joomla6');

    // And the one already stored is still there.
    cy.get('#generatorList').should('contain.text', NAME);
  });

  it('refuses something that is not a package', () => {
    cy.visitGengen('generators');
    cy.get('#toolbar-import button').click();
    cy.get('#gengen-import input[name="package"]').selectFile({
      contents: Cypress.Buffer.from('holiday photos'),
      fileName: 'notapackage.zip',
      mimeType: 'application/zip',
    });
    cy.get('#gengen-import-submit').click();

    cy.get('#system-message-container', { timeout: 30000 }).should('contain.text', 'This is not a zip file');
  });
});
