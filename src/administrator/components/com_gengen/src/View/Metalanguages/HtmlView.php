<?php

/**
 * @package     Gengen
 * @subpackage  Gengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Gengen\Administrator\View\Metalanguages;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The metalanguages this site can write a project in: step 3.4.
 *
 * @since  0.2.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Every language this site has imported.
     *
     * @var MetalanguageEntry[]
     *
     * @since  0.2.0
     */
    public array $items = [];

    /**
     * How many generators are written for each, keyed by binding.
     *
     * @var array<string, int>
     *
     * @since  0.2.0
     */
    public array $counts = [];

    /**
     * @param   string|null  $tpl  The layout.
     *
     * @return  void
     *
     * @since  0.2.0
     */
    public function display($tpl = null): void
    {
        /** @var \Yepr\Component\Gengen\Administrator\Model\MetalanguagesModel $model */
        $model = $this->getModel();

        $this->items  = $model->getItems();
        $this->counts = $model->generatorCounts();

        ToolbarHelper::title(Text::_('COM_GENGEN_MANAGER_METALANGUAGES'), 'puzzle');

        parent::display($tpl);
    }
}
