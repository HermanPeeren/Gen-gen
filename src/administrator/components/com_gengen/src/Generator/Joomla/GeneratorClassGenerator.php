<?php

/**
 * @package     Gengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Generator\Joomla;

use Yepr\Component\Gengen\Administrator\Generator\Model\ModelledGenerator;
use Yepr\Gen\Core\GeneratorInterface;
use Yepr\Gen\Core\Model\ModelInterface;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Template\RendererInterface;

/**
 * The classes that carry the rule file into a Joomla component.
 *
 * A rule file does not run by itself. Joomla asks a component for a list of
 * generators and runs them in order, so something has to be a class, name a
 * slice of the rules, and sit in that order. That is all these classes are -
 * two methods and a docblock - and generating them is what moves "which
 * generator runs which rules, in what order" out of hand-written PHP and into
 * the model.
 *
 * **It does not generate the groups that emit**, and that is the same line 2.1
 * drew. A generator that builds form XML through DOM, assembles a language
 * file, or writes sql for a schema only knowable once every entity has been
 * seen is doing something no rule expresses; overwriting it with a generated
 * class would delete that and leave something that looks complete. The group
 * says so in the model, and this skips it.
 *
 * @since  0.2.0
 */
final class GeneratorClassGenerator implements GeneratorInterface
{
    /**
     * Constructor.
     *
     * @param   RendererInterface  $renderer  Renders the class template.
     *
     * @since   0.2.0
     */
    public function __construct(private readonly RendererInterface $renderer)
    {
    }

    /**
     * Only a modelled generator has groups.
     *
     * @param   ModelInterface  $model  The source model.
     *
     * @return  boolean
     *
     * @since   0.2.0
     */
    public function supports(ModelInterface $model): bool
    {
        return $model instanceof ModelledGenerator;
    }

    /**
     * Write one class per group that is only rules.
     *
     * @param   ModelInterface  $model  The source model.
     * @param   FileCollection  $files  The collection to add to.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function generate(ModelInterface $model, FileCollection $files): void
    {
        if (!$model instanceof ModelledGenerator) {
            throw new \InvalidArgumentException(
                'A generator class is generated from a modelled generator, got ' . get_debug_type($model) . '.'
            );
        }

        $definition = $model->definition;
        $directory  = ucfirst($definition->target);

        foreach ($definition->groups() as $group) {
            if ($group['emits'] ?? false) {
                continue;
            }

            $class = (string) $group['class'];

            $files->add(
                rtrim($definition->outputPath, '/') . '/' . $directory . '/' . $class . '.php',
                $this->renderer->render('Joomla/GeneratorClass.php.twig', [
                    'namespace'     => rtrim($definition->phpNamespace, '\\') . '\\' . $directory,
                    'baseNamespace' => rtrim($definition->phpNamespace, '\\'),
                    'class'         => $class,
                    'prefix'        => (string) $group['prefix'],
                    'summary'       => (string) ($group['summary'] ?? ''),
                    'ruleFile'      => '/../Rules/' . $definition->target . '.rules.json',
                    'generatorName' => $definition->name,
                    'ruleCount'     => \count($definition->rulesOf($group)),
                ])
            );
        }
    }
}
