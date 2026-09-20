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

/**
 * The rule file: the whole of the mapping, as the engine reads it.
 *
 * An emitter rather than a template, and for the reason emitters exist. The
 * rule file is one file assembled from the whole model, its format is JSON and
 * `RuleSet::toJson()` already knows how to write it correctly. Rendering it
 * from a template would mean reimplementing JSON quoting in Twig, which is the
 * sort of thing that works until somebody's derivation is called
 * `refDisplay"Name`.
 *
 * It is also the file that carries the proof. If what this writes is
 * byte-identical to the rule file a target committed by hand, then the modelled
 * generator and the hand-written one are the same generator - and the golden
 * files that pin the hand-written one pin this too.
 *
 * @since  0.2.0
 */
final class RuleFileGenerator implements GeneratorInterface
{
    /**
     * Only a modelled generator has rules to write.
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
     * Write the rule file.
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
                'A rule file is generated from a modelled generator, got ' . get_debug_type($model) . '.'
            );
        }

        $definition = $model->definition;

        $files->add(
            rtrim($definition->outputPath, '/') . '/Rules/' . $definition->target . '.rules.json',
            $definition->rules()->toJson() . "\n"
        );
    }
}
