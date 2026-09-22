<?php

declare(strict_types=1);

namespace Yepr\Component\Gengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Gengen\Administrator\Metalanguage\MetalanguageContext;
use Yepr\Component\Gengen\Administrator\Metalanguage\Metalanguages;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;

/**
 * A generator is written for a metalanguage: step 3.4.
 *
 * The step's own criterion is that *Gen-gen offers an imported language's
 * concepts when a rule names what to select*. What that comes down to is the
 * three things below: a language knows its concepts, the context carries which
 * one is bound, and a generator that names no language is a real state rather
 * than a missing value.
 *
 * Installing a package is the library's job and is tested there. What is here
 * is what this component does with one.
 *
 * @since  0.2.0
 */
final class MetalanguageTest extends TestCase
{
    protected function setUp(): void
    {
        MetalanguageContext::reset();
    }

    protected function tearDown(): void
    {
        MetalanguageContext::reset();
    }

    /**
     * One imported language, as a row of the table would produce it.
     */
    private function language(string $manifest = null): MetalanguageEntry
    {
        return MetalanguageEntry::fromRow((object) [
            'id'            => 3,
            'lang_key'      => 'ER1',
            'version'       => '0.1',
            'name'          => 'ER1',
            'root'          => 'Project',
            'form_root'     => 'media/yepr_metalanguages/ER1/0.1/',
            'language_file' => 'language/en-GB/er1.ini',
            'manifest'      => $manifest ?? json_encode([
                'format'   => 1,
                'name'     => 'ER1',
                'concepts' => [
                    ['key' => 'c-entity', 'name' => 'Entity'],
                    ['key' => 'c-field', 'name' => 'Field'],
                ],
            ]),
        ]);
    }

    /**
     * A rule picks a concept by name and stores its key.
     *
     * Both halves matter and they are not the same. Storing the name would
     * come unpicked the moment somebody renamed a concept in Meta-gen -
     * silently, because the rule would still be a string and still look like
     * one. Showing the key would make the form unreadable.
     */
    public function testAConceptIsOfferedByNameAndStoredByKey(): void
    {
        $this->assertSame(
            ['c-entity' => 'Entity', 'c-field' => 'Field'],
            $this->language()->conceptChoices()
        );
    }

    /**
     * A language imported before packages named concepts offers none.
     *
     * An empty list rather than an error: the package is otherwise complete,
     * and every package built before that field existed is one of these. What
     * must not happen is falling back to the target's ER1-shaped selectors,
     * which would offer one language's names for another.
     */
    public function testALanguageWhoseManifestNamesNoConceptsOffersNone(): void
    {
        $this->assertSame([], $this->language('{"format":1,"name":"ER1"}')->conceptChoices());
        $this->assertSame([], $this->language('not json at all')->conceptChoices());
        $this->assertSame([], $this->language('')->conceptChoices());
    }

    /**
     * The context carries which language the form being rendered is for.
     *
     * The same seam `VocabularyContext` uses, and for the same reason: Joomla
     * builds form fields from XML with no constructor arguments, and the field
     * that needs this is nested two subforms below where the choice was made.
     */
    public function testTheContextCarriesTheLanguageBeingRendered(): void
    {
        $this->assertNull(MetalanguageContext::current(), 'nothing bound to start with');

        MetalanguageContext::useLanguage($this->language());

        $this->assertNotNull(MetalanguageContext::current());
        $this->assertSame('ER1', MetalanguageContext::current()->name);

        MetalanguageContext::reset();

        $this->assertNull(MetalanguageContext::current(), 'reset means reset');
    }

    /**
     * No language is an answer, not a blank.
     *
     * A generator written before 3.4 names selectors from its target's own
     * vocabulary. Saying "no language" is exactly true of one of those, and it
     * is why this component passes no built-in to the catalogue the way
     * Exten-gen passes ER1 - Exten-gen ships one because it has to open the
     * projects already in its database, and this component ships nothing.
     */
    public function testNoLanguageIsARealState(): void
    {
        MetalanguageContext::useLanguage(null);

        $this->assertNull(MetalanguageContext::current());
        $this->assertSame('#__gengen_metalanguages', Metalanguages::TABLE);
    }

    /**
     * A binding is a key and a version, because a version is half the identity.
     *
     * Two versions of one language sit beside each other, so a generator that
     * recorded only the key would not say which concepts it was written
     * against.
     */
    public function testABindingCarriesBothHalvesOfTheIdentity(): void
    {
        $this->assertSame('ER1|0.1', $this->language()->binding());
        $this->assertTrue($this->language()->answersTo('ER1', '0.1'));
        $this->assertFalse($this->language()->answersTo('ER1', '0.2'));
    }
}
