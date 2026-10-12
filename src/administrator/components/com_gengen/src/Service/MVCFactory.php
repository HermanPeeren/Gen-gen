<?php

/**
 * @package     Gengen
 * @subpackage  Service
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Gengen\Administrator\Service;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactory as JoomlaMVCFactory;
use Joomla\Input\Input;
use Psr\Container\ContainerInterface;
use Yepr\Component\Gengen\Administrator\Generator\VocabularyLibrary;
use Yepr\Component\Gengen\Administrator\Generator\VocabularyLibraryAwareInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Joomla's MVC factory, handing this component's own services to what it makes.
 *
 * Joomla's factory builds every controller and model itself, with the
 * constructors Joomla decides, and then hands over the services it knows about
 * through `*AwareInterface` setters - the database, the form factory, the
 * dispatcher. This does the same for the services this component registers in
 * `services/provider.php`, which today is one: the vocabulary library, which
 * the import checks a package against. The Extension Generator's factory is
 * the same shape.
 *
 * A service is taken from the container when an object asks for it, not when
 * the factory is made, so a request that never imports anything never looks
 * for a vocabulary.
 *
 * @since  0.6.0
 */
class MVCFactory extends JoomlaMVCFactory
{
    /**
     * @param  string              $namespace  The component's namespace.
     * @param  ContainerInterface  $services   The component's container, which holds the services.
     *
     * @since  0.6.0
     */
    public function __construct(string $namespace, private readonly ContainerInterface $services)
    {
        parent::__construct($namespace);
    }

    /**
     * @since  0.6.0
     */
    public function createController($name, $prefix, array $config, CMSApplicationInterface $app, Input $input)
    {
        return $this->handOver(parent::createController($name, $prefix, $config, $app, $input));
    }

    /**
     * @since  0.6.0
     */
    public function createModel($name, $prefix = '', array $config = [])
    {
        return $this->handOver(parent::createModel($name, $prefix, $config));
    }

    /**
     * Give an object the services it says it needs.
     *
     * @template T
     *
     * @param   T  $object  What the parent factory made, or null.
     *
     * @return  T
     *
     * @since   0.6.0
     */
    private function handOver($object)
    {
        if ($object instanceof VocabularyLibraryAwareInterface) {
            $object->setVocabularyLibrary($this->services->get(VocabularyLibrary::class));
        }

        return $object;
    }
}
