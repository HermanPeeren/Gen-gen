<?php

/**
 * @package     Gengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Generator\Model;

use Yepr\Component\Gengen\Administrator\Generator\GeneratorDefinition;
use Yepr\Gen\Core\Model\ModelInterface;

/**
 * A generator, as the thing being generated.
 *
 * This is where the recursion becomes concrete and stops being a slogan. The
 * core's pipeline takes a model and produces files; up to now the model has
 * always been a description of an extension. Here it is a description of a
 * generator, and the files it produces are a generator - a rule file and the
 * classes that carry it.
 *
 * Nothing in the core changed to allow that, which is the interesting part.
 * `ModelInterface` is a marker, `Pipeline` never looks inside a model, and a
 * target is a structure plus emitters plus a template set. A generator happens
 * to be describable that way, so it is.
 *
 * @since  0.2.0
 */
final class ModelledGenerator implements ModelInterface
{
    /**
     * Constructor.
     *
     * @param   GeneratorDefinition  $definition  The modelled generator.
     *
     * @since   0.2.0
     */
    public function __construct(public readonly GeneratorDefinition $definition)
    {
    }

    /**
     * Read one from what a form saved.
     *
     * @param   string  $json  The stored form data.
     *
     * @return  self
     *
     * @since   0.2.0
     */
    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return new self(GeneratorDefinition::fromFormData($data));
    }

    /**
     * Read one from a file.
     *
     * @param   string  $path  Absolute path to the stored form data.
     *
     * @return  self
     *
     * @since   0.2.0
     */
    public static function fromFile(string $path): self
    {
        $json = @file_get_contents($path);

        if ($json === false) {
            throw new \RuntimeException('Cannot read the modelled generator at ' . $path . '.');
        }

        return self::fromJson($json);
    }
}
