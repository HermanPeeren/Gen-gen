<?php

/**
 * @package     Gengen
 * @subpackage  Model
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Gengen\Administrator\Model;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Yepr\Component\Gengen\Administrator\Generator\GeneratorDefinition;
use Yepr\Component\Gengen\Administrator\Generator\GeneratorManifest;
use Yepr\Component\Gengen\Administrator\Generator\Model\ModelledGenerator;
use Yepr\Component\Gengen\Administrator\Generator\Target\JoomlaGeneratorTarget;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Pipeline;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Running a modelled generator, and handing back what it produced.
 *
 * Four lines of pipeline and a zip. Everything interesting happened before
 * this: the rules were checked against the target's vocabulary when they were
 * saved, and what comes out has been compared with the hand-written generator's
 * output file for file. This is the button.
 *
 * @since  0.1.0
 */
class GenerateModel extends BaseDatabaseModel
{
	/**
	 * Where a generated generator is written.
	 *
	 * Inside the component rather than anywhere else, because the component is
	 * the one directory a Joomla install is certain to let it write to.
	 *
	 * @return  string
	 *
	 * @since   0.1.0
	 */
	public function outputDirectory(): string
	{
		return JPATH_ADMINISTRATOR . '/components/com_gengen/generated';
	}

	/**
	 * Generate, and return the files in memory.
	 *
	 * Nothing is written here. A run that fails part way through leaves
	 * nothing behind, which is the whole reason the core hands back a
	 * collection rather than writing as it goes.
	 *
	 * @param   GeneratorDefinition  $definition  The modelled generator.
	 *
	 * @return  FileCollection
	 *
	 * @since   0.1.0
	 */
	public function generate(GeneratorDefinition $definition): FileCollection
	{
		return (new Pipeline())->run(
			new ModelledGenerator($definition),
			(new JoomlaGeneratorTarget(
				JPATH_ADMINISTRATOR . '/components/com_gengen/generator_templates',
				JPATH_ADMINISTRATOR . '/components/com_gengen/compilation_cache'
			))->target()
		);
	}

	/**
	 * Generate and write a zip of the result.
	 *
	 * The zip carries `generator.json` beside what the pipeline produced, since
	 * 0.4: which target, which metalanguage, and where the rule file is. That is
	 * what lets Exten-gen import the package and offer it only for projects in
	 * that language. It is added here rather than by a generator in the target,
	 * because the binding is on the row and not in the model - and because the
	 * acceptance check compares what the pipeline produces, which this leaves
	 * exactly as it was.
	 *
	 * @param   GeneratorDefinition  $definition           The modelled generator.
	 * @param   string               $metalanguageKey      The language it is bound to, '' for none.
	 * @param   string               $metalanguageVersion  That language's version.
	 *
	 * @return  array{path: string, files: FileCollection}
	 *
	 * @since   0.1.0
	 */
	public function generatePackage(
		GeneratorDefinition $definition,
		string $metalanguageKey = '',
		string $metalanguageVersion = ''
	): array {
		$files     = $this->generate($definition);

		$files->add(
			GeneratorManifest::FILE,
			GeneratorManifest::toJson($definition, $metalanguageKey, $metalanguageVersion)
		);
		$directory = $this->outputDirectory() . '/' . $this->slug($definition->name);

		if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
			throw new \RuntimeException('Cannot create ' . $directory . '.');
		}

		$path = $directory . '/' . $this->slug($definition->name) . '-' . $definition->target . '.zip';

		(new ZipWriter())->write($files, $path);

		return ['path' => $path, 'files' => $files];
	}

	/**
	 * A generator's name, as a directory can hold it.
	 *
	 * @param   string  $name  The generator's name.
	 *
	 * @return  string
	 *
	 * @since   0.1.0
	 */
	private function slug(string $name): string
	{
		$slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name));

		return trim($slug, '-') ?: 'generator';
	}
}
