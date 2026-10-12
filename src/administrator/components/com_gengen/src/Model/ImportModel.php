<?php

/**
 * @package     Gengen
 * @subpackage  Model
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Gengen\Administrator\Model;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Yepr\Component\Gengen\Administrator\Generator\ImportedGenerator;
use Yepr\Component\Gengen\Administrator\Generator\VocabularyLibraryAwareInterface;
use Yepr\Component\Gengen\Administrator\Generator\VocabularyLibraryAwareTrait;
use Yepr\Component\Gengen\Administrator\Metalanguage\Metalanguages;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Importing a generator package as a model to edit: the Import button.
 *
 * Read, check, store, in that order, so a refused package leaves nothing
 * behind. Reading is `ImportedGenerator`'s; this adds what needs the site:
 *
 * - **The check** is against the targets this site publishes, through the
 *   vocabulary library the component's MVC factory hands over. A rule naming
 *   a selector, derivation or template the installed target does not have is
 *   refused, with the list of what is wrong.
 * - **The binding.** A package says which metalanguage its rules are written
 *   for. When that language is imported here, the generator is bound to it,
 *   and its rules may select the language's concepts. When it is not, the
 *   generator is imported unbound and the result says so. A package that
 *   names no version (the Extension Generator's own ship that way) binds to
 *   the newest version of the language this site has.
 * - **The same generator twice** replaces the row with the same name, the way
 *   importing a metalanguage or an Extension Generator package again replaces
 *   it there. A row is found by its name because a name is what a package's
 *   key is made from (`GeneratorManifest::slug()`), and the Generators list
 *   shows names. The row keeps its id, its published state and, when the
 *   package names no language this site has, the binding it had.
 * - **A generator that extends another** is imported as it is. It holds only
 *   the rules it adds, and the Extension Generator lays them over its
 *   parent's when it runs it. The parent is a generator on that site, named
 *   by its id there (`joomla6`), so it need not be modelled here at all; the
 *   result says that the parent has to be on the Extension Generator.
 *
 * @since  0.6.0
 */
class ImportModel extends BaseDatabaseModel implements VocabularyLibraryAwareInterface
{
    use VocabularyLibraryAwareTrait;

    /**
     * Where the generators are.
     *
     * @since  0.6.0
     */
    private const TABLE = '#__gengen_generators';

    /**
     * Import a package, or say why not.
     *
     * @param   string  $zip  Path to the uploaded file.
     *
     * @return  array{
     *     id: int,
     *     name: string,
     *     rules: int,
     *     replaced: bool,
     *     bound: string,
     *     unboundLanguage: string,
     *     extends: string,
     *     notes: string[]
     * }
     *
     * @throws  \RuntimeException  With a sentence a person can act on.
     *
     * @since   0.6.0
     */
    public function import(string $zip): array
    {
        $package    = ImportedGenerator::fromZip($zip);
        $definition = $package->definition;
        $language   = $this->languageFor($package);

        $problems = $package->problems($this->getVocabularyLibrary(), $this->conceptsOf($language));

        if ($problems !== []) {
            $missing = $package->metalanguageKey !== '' && $language === null
                ? ' It is written for ' . $package->metalanguageKey . ', which is not imported under'
                    . ' Metalanguages; a rule may select that language\'s concepts once it is.'
                : '';

            throw new \RuntimeException(
                'The generator was not imported: ' . implode(' ', \array_slice($problems, 0, 5))
                . (\count($problems) > 5 ? ' (and ' . (\count($problems) - 5) . ' more)' : '')
                . $missing
            );
        }

        $database = $this->getDatabase();
        $existing = $this->rowNamed($definition->name);
        $now      = gmdate('Y-m-d H:i:s');
        $user     = (int) $this->getCurrentUser()->id;

        $row = (object) [
            'name'        => $definition->name,
            'target'      => $definition->target,
            'form_data'   => json_encode($definition->toFormData(), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            'modified'    => $now,
            'modified_by' => $user,
        ];

        if ($language !== null) {
            $row->metalanguage_key     = $language->key;
            $row->metalanguage_version = $language->version;
        }

        if ($existing !== null) {
            $row->id = (int) $existing->id;

            $database->updateObject(self::TABLE, $row, 'id');
        } else {
            $row->published  = 1;
            $row->access     = 1;
            $row->created    = $now;
            $row->created_by = $user;

            $row->metalanguage_key     ??= '';
            $row->metalanguage_version ??= '';

            $database->insertObject(self::TABLE, $row, 'id');
        }

        return [
            'id'              => (int) $row->id,
            'name'            => $definition->name,
            'rules'           => \count($definition->rules()),
            'replaced'        => $existing !== null,
            'bound'           => $language?->label() ?? '',
            'unboundLanguage' => $package->metalanguageKey !== '' && $language === null
                ? trim($package->metalanguageKey . ' ' . $package->metalanguageVersion)
                : '',
            'extends'         => $definition->extends,
            'notes'           => $package->notes,
        ];
    }

    /**
     * The imported language the package says it is for, or null.
     *
     * The exact version when the package names one. When it names none, the
     * newest version of that language here, because "for LedgerLang" without
     * a version means any LedgerLang.
     *
     * @param   ImportedGenerator  $package  The package.
     *
     * @return  ?MetalanguageEntry
     *
     * @since   0.6.0
     */
    private function languageFor(ImportedGenerator $package): ?MetalanguageEntry
    {
        if ($package->metalanguageKey === '') {
            return null;
        }

        $found = null;

        foreach (Metalanguages::catalogue($this->getDatabase())->all() as $entry) {
            if ($entry->key !== $package->metalanguageKey) {
                continue;
            }

            if ($package->metalanguageVersion !== '') {
                if ($entry->version === $package->metalanguageVersion) {
                    return $entry;
                }

                continue;
            }

            if ($found === null || version_compare($entry->version, $found->version, '>')) {
                $found = $entry;
            }
        }

        return $found;
    }

    /**
     * The concept names a rule may select when the generator is bound to this language.
     *
     * The language's own and those of every language it derives from, the same
     * list the rule form's "what to select" dropdown offers (see
     * `RuleSelectorField`), so the import accepts what the form lets somebody
     * write.
     *
     * @param   ?MetalanguageEntry  $language  The language, or null.
     *
     * @return  string[]
     *
     * @since   0.6.0
     */
    private function conceptsOf(?MetalanguageEntry $language): array
    {
        if ($language === null) {
            return [];
        }

        $concepts = [];

        foreach (Metalanguages::ancestry($this->getDatabase())->withSelf($language) as $entry) {
            $concepts = array_merge($concepts, array_values($entry->conceptChoices()));
        }

        return $concepts;
    }

    /**
     * The stored generator with this name, or null.
     *
     * @param   string  $name  The generator's name.
     *
     * @return  ?object
     *
     * @since   0.6.0
     */
    private function rowNamed(string $name): ?object
    {
        $database = $this->getDatabase();

        $query = $database->getQuery(true)
            ->select($database->quoteName(['id', 'name']))
            ->from($database->quoteName(self::TABLE))
            ->where($database->quoteName('name') . ' = :name')
            ->order($database->quoteName('id') . ' ASC')
            ->bind(':name', $name);

        return $database->setQuery($query, 0, 1)->loadObject() ?: null;
    }
}
