<?php

/**
 * @package     Gengen
 * @subpackage  Model
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Model;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseQuery;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The list of generators.
 *
 * @since  0.1.0
 */
class GeneratorsModel extends ListModel
{
	/**
	 * Constructor.
	 *
	 * @param   array<string, mixed>  $config  Configuration array.
	 *
	 * @since   0.1.0
	 */
	public function __construct($config = [])
	{
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = [
				'id', 'a.id',
				'name', 'a.name',
				'target', 'a.target',
				'published', 'a.published',
			];
		}

		parent::__construct($config);
	}

	/**
	 * Remember what the list was filtered and ordered by.
	 *
	 * @param   string  $ordering   Column to order by.
	 * @param   string  $direction  Direction to order in.
	 *
	 * @return  void
	 *
	 * @since   0.1.0
	 */
	protected function populateState($ordering = 'a.name', $direction = 'asc')
	{
		foreach (['search', 'published', 'target'] as $filter) {
			$this->setState(
				'filter.' . $filter,
				$this->getUserStateFromRequest($this->context . '.filter.' . $filter, 'filter_' . $filter, '')
			);
		}

		parent::populateState($ordering, $direction);
	}

	/**
	 * Part of the cache key, so two different filters do not share one result.
	 *
	 * @param   string  $id  A prefix.
	 *
	 * @return  string
	 *
	 * @since   0.1.0
	 */
	protected function getStoreId($id = '')
	{
		return parent::getStoreId(
			$id . ':' . $this->getState('filter.search')
			. ':' . $this->getState('filter.published')
			. ':' . $this->getState('filter.target')
		);
	}

	/**
	 * The query behind the list.
	 *
	 * `form_data` is deliberately not selected. It is the whole modelled
	 * generator, tens of kilobytes of it, and a list showing twenty rows has
	 * no use for any of it.
	 *
	 * @return  DatabaseQuery
	 *
	 * @since   0.1.0
	 */
	protected function getListQuery()
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true);

		$columns = [
			'a.id', 'a.name', 'a.target', 'a.published', 'a.ordering',
			'a.checked_out', 'a.checked_out_time', 'a.modified',
		];

		$query->select($db->quoteName($columns))
			->from($db->quoteName('#__gengen_generators', 'a'));

		$published = (string) $this->getState('filter.published');

		if (is_numeric($published)) {
			$query->where($db->quoteName('a.published') . ' = :published')
				->bind(':published', $published, \Joomla\Database\ParameterType::INTEGER);
		} elseif ($published === '') {
			$query->whereIn($db->quoteName('a.published'), [0, 1]);
		}

		$target = (string) $this->getState('filter.target');

		if ($target !== '') {
			$query->where($db->quoteName('a.target') . ' = :target')
				->bind(':target', $target);
		}

		$search = (string) $this->getState('filter.search');

		if ($search !== '') {
			$like = '%' . str_replace(' ', '%', trim($search)) . '%';
			$query->where($db->quoteName('a.name') . ' LIKE :search')
				->bind(':search', $like);
		}

		$ordering  = $this->getState('list.ordering', 'a.name');
		$direction = $this->getState('list.direction', 'ASC');

		$query->order($db->escape($ordering) . ' ' . $db->escape($direction));

		return $query;
	}
}
