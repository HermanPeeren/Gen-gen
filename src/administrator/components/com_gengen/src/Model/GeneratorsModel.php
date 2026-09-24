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
use Yepr\Component\Gengen\Administrator\Metalanguage\Metalanguages;

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
	 * The language keys a filter should match: it, and what it derives from.
	 *
	 * A generator bound to a *parent* runs over a *child's* models, so
	 * choosing the child has to show the parent's generators - which is the
	 * ancestry of the chosen language, walked the ordinary way.
	 *
	 * Not the other direction, which is a different question nobody asked:
	 * "which languages could this one's generators run over" would be every
	 * language whose ancestry contains this one, and that is a scan of the
	 * catalogue rather than a walk from one node.
	 *
	 * @param   string  $binding  `key|version`, as the filter stores it.
	 *
	 * @return  string[]  Keys to match, never empty.
	 *
	 * @since   0.3.0
	 */
	private function keysFor(string $binding): array
	{
		[$key, $version] = array_pad(explode('|', $binding, 2), 2, '');

		$database = $this->getDatabase();
		$entry    = Metalanguages::catalogue($database)->forRecord($key, $version);

		if ($entry === null) {
			// A filter naming a language this site has not got matches that key
			// and nothing else, which is an empty list rather than every row.
			return [$key];
		}

		$keys = [];

		foreach (Metalanguages::ancestry($database)->withSelf($entry) as $node) {
			$keys[$node->key] = true;
		}

		return array_keys($keys);
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
		foreach (['search', 'published', 'target', 'metalanguage'] as $filter) {
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
			. ':' . $this->getState('filter.metalanguage')
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
			'a.id', 'a.name', 'a.target', 'a.published', 'a.ordering', 'a.metalanguage_key',
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

		// Which metalanguage, and everything that language derives from: 4.5.
		//
		// "The generators I could run over a model in this language" is the
		// question somebody is actually asking here, and the answer is not
		// only the ones written for it. A derived language adds and may not
		// remove or rename, so a generator written for a parent runs over a
		// child's models - and a list that hid those would make deriving a
		// thing you could declare and not use.
		$metalanguage = (string) $this->getState('filter.metalanguage');

		if ($metalanguage !== '') {
			$query->whereIn(
				$db->quoteName('a.metalanguage_key'),
				$this->keysFor($metalanguage),
				\Joomla\Database\ParameterType::STRING
			);
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
