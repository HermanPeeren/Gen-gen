<?php

/**
 * @package     Gengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Gengen\Administrator\Generator;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What a generator package says about itself: step 5.4 of Exten-gen's plan.
 *
 * Until now the zip held a rule file and the classes that carry it, and said
 * nothing about which metalanguage its rules are written for - which is what a
 * site importing it needs most, because a project may only be generated with a
 * generator for its own language. So the package gains `generator.json`.
 *
 * Exten-gen reads it with `GeneratorPackage`. This component depends on none of
 * the components it generates for, so the format cannot live in one place;
 * `GeneratorPackageTest` reads what this writes with Exten-gen's reader, which
 * is what keeps the two from drifting apart.
 *
 * @since  0.4.0
 */
final class GeneratorManifest
{
    /**
     * The manifest's name inside the zip.
     *
     * @since  0.4.0
     */
    public const FILE = 'generator.json';

    /**
     * The model's name inside the zip: the form data, as the Import button reads it back.
     *
     * The rule file is the generator as it runs. It does not say which classes
     * slice it, where their source goes or what each group is for, and those
     * are what a person fills in on the form. So the package carries the form
     * data as well, and importing a package this component wrote gives back
     * exactly the generator that was generated. The Extension Generator reads
     * `generator.json` and the rule file and leaves this alone.
     *
     * @since  0.6.0
     */
    public const MODEL = 'model.json';

    /**
     * The newest format written: 2, which added `extends` (step 5.9).
     *
     * A generator that extends nothing is still written as format 1, which is
     * exactly what it is. Only a package that needs `extends` asks the reader
     * for format 2, so an Extension Generator from before 5.9 refuses that one
     * - rather than importing a child as if it stood alone, with a handful of
     * rules and none of its parent's - and goes on reading every other one.
     *
     * @since  0.4.0
     */
    public const FORMAT = 2;

    /**
     * The format of a generator that extends nothing.
     *
     * @since  0.5.0
     */
    public const FORMAT_WITHOUT_EXTENDS = 1;

    /**
     * The manifest for one generator.
     *
     * @param  GeneratorDefinition  $definition           The modelled generator.
     * @param  string               $metalanguageKey      The language it is bound to, '' for the target's own.
     * @param  string               $metalanguageVersion  That language's version.
     *
     * @return array<string, mixed>
     *
     * @since  0.4.0
     */
    public static function for(
        GeneratorDefinition $definition,
        string $metalanguageKey = '',
        string $metalanguageVersion = ''
    ): array {
        return [
            'format'       => $definition->extends === '' ? self::FORMAT_WITHOUT_EXTENDS : self::FORMAT,
            'key'          => self::slug($definition->name),
            'name'         => $definition->name,
            'target'       => $definition->target,
            'metalanguage' => ['key' => $metalanguageKey, 'version' => $metalanguageVersion],
        ] + ($definition->extends === '' ? [] : ['extends' => $definition->extends]) + [
            'rules'        => $definition->ruleFilePath(),
            'groups'       => array_map(
                static fn (array $group): array => [
                    'class'  => (string) ($group['class'] ?? ''),
                    'prefix' => (string) ($group['prefix'] ?? ''),
                    'emits'  => (bool) ($group['emits'] ?? false),
                ],
                $definition->groups()
            ),
        ];
    }

    /**
     * The manifest as the file it is written to.
     *
     * @since  0.4.0
     */
    public static function toJson(
        GeneratorDefinition $definition,
        string $metalanguageKey = '',
        string $metalanguageVersion = ''
    ): string {
        return json_encode(
            self::for($definition, $metalanguageKey, $metalanguageVersion),
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR
        ) . "\n";
    }

    /**
     * The model as the file it is written to: `GeneratorDefinition::toFormData()`.
     *
     * @since  0.6.0
     */
    public static function modelJson(GeneratorDefinition $definition): string
    {
        return json_encode(
            $definition->toFormData(),
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR
        ) . "\n";
    }

    /**
     * A key out of a name: lower case, digits and hyphens.
     *
     * @since  0.4.0
     */
    public static function slug(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'generator';
    }
}
