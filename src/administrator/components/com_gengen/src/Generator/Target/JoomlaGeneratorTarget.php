<?php

/**
 * @package     Gengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Generator\Target;

use Yepr\Component\Gengen\Administrator\Generator\Joomla\GeneratorClassGenerator;
use Yepr\Component\Gengen\Administrator\Generator\Joomla\RuleFileGenerator;
use Yepr\Gen\Core\GeneratorInterface;
use Yepr\Gen\Core\Model\ValidatorInterface;
use Yepr\Gen\Core\Target\Target;
use Yepr\Gen\Core\Target\TargetInterface;
use Yepr\Gen\Core\Template\TwigRenderer;

/**
 * What a modelled generator is generated into: a generator for a Joomla component.
 *
 * Two files' worth of output, and the split between them is the same one
 * everything else here rests on. The rule file is the mapping, emitted as data.
 * The classes are the wiring that lets Joomla run it, rendered from a template.
 *
 * A target is "a structure metamodel plus emitters plus a template set", and
 * nothing in the core had to learn what a generator is for this to be one.
 *
 * @since  0.2.0
 */
final class JoomlaGeneratorTarget implements TargetInterface
{
    /**
     * Constructor.
     *
     * @param   string   $templateRoot    Directory holding the template set.
     * @param   ?string  $cacheDirectory  Where Twig compiles to; null for none.
     *
     * @since   0.2.0
     */
    public function __construct(
        private readonly string $templateRoot,
        private readonly ?string $cacheDirectory = null
    ) {
    }

    /**
     * The stable identifier.
     *
     * @return  string
     *
     * @since   0.2.0
     */
    public function id(): string
    {
        return 'joomla-generator';
    }

    /**
     * What to call it on screen.
     *
     * @return  string
     *
     * @since   0.2.0
     */
    public function label(): string
    {
        return 'A rule-driven generator for a Joomla component';
    }

    /**
     * Nothing beyond what the model already refuses to be.
     *
     * A rule set that names a selector its target does not have is caught by
     * `Vocabulary::problems()` before it is ever saved, and a malformed rule is
     * caught by `Rule::fromArray()` while it is being read. There is nothing
     * left for a validator here to say that is not said earlier and better.
     *
     * @return  ?ValidatorInterface
     *
     * @since   0.2.0
     */
    public function validator(): ?ValidatorInterface
    {
        return null;
    }

    /**
     * The generators, in order.
     *
     * @return  GeneratorInterface[]
     *
     * @since   0.2.0
     */
    public function generators(): array
    {
        return [
            new RuleFileGenerator(),
            new GeneratorClassGenerator(
                TwigRenderer::forDirectories($this->templateRoot, $this->cacheDirectory)
            ),
        ];
    }

    /**
     * The whole thing, ready for the pipeline.
     *
     * @return  Target
     *
     * @since   0.2.0
     */
    public function target(): Target
    {
        return new Target($this->id(), $this->label(), $this->validator(), ...$this->generators());
    }

    /**
     * Where this component keeps its template set.
     *
     * @return  string
     *
     * @since   0.2.0
     */
    public static function defaultTemplateRoot(): string
    {
        return \dirname(__DIR__, 3) . '/generator_templates';
    }
}
