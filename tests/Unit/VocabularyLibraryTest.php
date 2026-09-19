<?php

declare(strict_types=1);

namespace Yepr\Component\Gengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Gengen\Administrator\Generator\VocabularyContext;
use Yepr\Component\Gengen\Administrator\Generator\VocabularyLibrary;
use Yepr\Gen\Core\Rule\RuleException;

/**
 * Finding the targets this site can model a generator for.
 *
 * Gen-gen must not depend on the components it models generators for - that is
 * the whole reason a target publishes its vocabulary as data rather than as
 * PHP. So discovery is a glob, and what it finds is whatever is installed.
 */
final class VocabularyLibraryTest extends TestCase
{
    protected function tearDown(): void
    {
        VocabularyContext::reset();
    }

    private function fixtures(): string
    {
        return \dirname(__DIR__) . '/Fixtures';
    }

    private function library(): VocabularyLibrary
    {
        return new VocabularyLibrary($this->fixtures());
    }

    public function testItFindsWhatIsPublished(): void
    {
        $this->assertSame(['joomla6'], $this->library()->targets());
        $this->assertTrue($this->library()->has('joomla6'));
        $this->assertSame('joomla6', $this->library()->get('joomla6')->target);
    }

    public function testItSaysWhatItHasWhenAskedForSomethingElse(): void
    {
        $this->expectException(RuleException::class);
        $this->expectExceptionMessage('nothing on this site publishes a vocabulary for "drupal11". Found: joomla6.');

        $this->library()->get('drupal11');
    }

    public function testAnEmptyLibraryIsNotAnError(): void
    {
        $library = new VocabularyLibrary(\dirname(__DIR__) . '/Fixtures/nothing-here');

        $this->assertSame([], $library->targets());
        $this->assertNull($library->only());
    }

    /**
     * With one target installed, nothing has to be chosen.
     *
     * Which is the usual case and the one where making somebody pick from a
     * list of one would be silly.
     */
    public function testOneTargetNeedsNoChoosing(): void
    {
        VocabularyContext::useLibrary($this->library());

        $vocabulary = VocabularyContext::current();

        $this->assertNotNull($vocabulary);
        $this->assertSame('joomla6', $vocabulary->target);
    }

    /**
     * A chosen target wins, and a chosen target that is not installed offers
     * nothing rather than falling back.
     *
     * Falling back would mean a form quietly offering one target's derivations
     * for another target's rules, which produces a rule set that validates
     * against nothing and fails only when somebody runs it.
     */
    public function testAChosenTargetIsNotSecondGuessed(): void
    {
        VocabularyContext::useLibrary($this->library());

        VocabularyContext::useTarget('joomla6');
        $chosen = VocabularyContext::current();
        $this->assertNotNull($chosen);
        $this->assertSame('joomla6', $chosen->target);

        VocabularyContext::useTarget('drupal11');
        $this->assertNull(VocabularyContext::current());

        VocabularyContext::useTarget(null);
        $fallback = VocabularyContext::current();
        $this->assertNotNull($fallback);
        $this->assertSame('joomla6', $fallback->target);
    }

    /**
     * One badly installed extension does not stop the others being modelled.
     */
    public function testADescriptorThatDoesNotParseIsSkipped(): void
    {
        $directory = sys_get_temp_dir() . '/gengen-vocab-' . getmypid();

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($directory . '/broken.vocabulary.json', '{ not json');
        copy($this->fixtures() . '/joomla6.vocabulary.json', $directory . '/joomla6.vocabulary.json');

        try {
            $this->assertSame(['joomla6'], (new VocabularyLibrary($directory))->targets());
        } finally {
            array_map(unlink(...), glob($directory . '/*') ?: []);
            rmdir($directory);
        }
    }
}
