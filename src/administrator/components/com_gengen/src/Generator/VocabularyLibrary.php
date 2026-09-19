<?php

/**
 * @package     Gengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Generator;

use Yepr\Gen\Core\Rule\RuleException;
use Yepr\Gen\Core\Rule\Vocabulary;

/**
 * The targets this site can model a generator for.
 *
 * A target publishes what its rules may say - its selectors, its derivations,
 * its templates - as a `*.vocabulary.json` beside its rule file. Gen-gen finds
 * those rather than being told about them, which is what keeps it from
 * depending on any of the components it models generators for. Install
 * Exten-gen and `joomla6` appears; install something else and its target
 * appears too.
 *
 * Discovery is a glob over directories the caller names. Nothing here knows
 * where a Joomla keeps its components; the one class that does passes the
 * paths in.
 *
 * @since  0.1.0
 */
final class VocabularyLibrary
{
    /**
     * Vocabularies by target name, loaded lazily.
     *
     * @var    ?array<string, Vocabulary>
     * @since  0.1.0
     */
    private ?array $vocabularies = null;

    /**
     * The directories to search.
     *
     * @var    string[]
     * @since  0.1.0
     */
    private array $directories;

    /**
     * Constructor.
     *
     * @param   string  ...$directories  Searched, each one level deep, for `*.vocabulary.json`.
     *
     * @since   0.1.0
     */
    public function __construct(string ...$directories)
    {
        $this->directories = $directories;
    }

    /**
     * The targets found, sorted.
     *
     * @return  string[]
     *
     * @since   0.1.0
     */
    public function targets(): array
    {
        $targets = array_keys($this->load());
        sort($targets);

        return $targets;
    }

    /**
     * Whether a target was found.
     *
     * @param   string  $target  The target's name.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function has(string $target): bool
    {
        return isset($this->load()[$target]);
    }

    /**
     * One target's vocabulary.
     *
     * @param   string  $target  The target's name.
     *
     * @return  Vocabulary
     *
     * @throws  RuleException  When nothing published that target.
     *
     * @since   0.1.0
     */
    public function get(string $target): Vocabulary
    {
        $found = $this->load();

        if (!isset($found[$target])) {
            throw new RuleException(
                'nothing on this site publishes a vocabulary for "' . $target . '". Found: '
                . ($found === [] ? '(none)' : implode(', ', $this->targets())) . '.'
            );
        }

        return $found[$target];
    }

    /**
     * The one target, when there is exactly one.
     *
     * A form field has to offer something before anybody has chosen a target,
     * and with a single target installed the answer is obvious. With none or
     * several it is not, and guessing would mean offering one target's
     * derivations for another's rules.
     *
     * @return  ?Vocabulary
     *
     * @since   0.1.0
     */
    public function only(): ?Vocabulary
    {
        $found = $this->load();

        return \count($found) === 1 ? reset($found) : null;
    }

    /**
     * Find and read every descriptor, once.
     *
     * A descriptor that does not parse is skipped rather than fatal: one
     * badly installed extension should not stop Gen-gen from modelling
     * generators for the targets that are fine.
     *
     * @return  array<string, Vocabulary>
     *
     * @since   0.1.0
     */
    private function load(): array
    {
        if ($this->vocabularies !== null) {
            return $this->vocabularies;
        }

        $found = [];

        foreach ($this->directories as $directory) {
            foreach (glob(rtrim($directory, '/\\') . '/*.vocabulary.json') ?: [] as $path) {
                try {
                    $vocabulary = Vocabulary::fromFile($path);
                } catch (RuleException) {
                    continue;
                }

                $found[$vocabulary->target] = $vocabulary;
            }
        }

        return $this->vocabularies = $found;
    }
}
