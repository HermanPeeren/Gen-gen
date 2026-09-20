<?php

/**
 * Put a generator into the installed component, and run it there.
 *
 *   php tools/seed-generator.php
 *   php tools/seed-generator.php ../some/site
 *
 * Two things at once, because they are only worth knowing together: the site
 * gets a row to look at, and running it proves that the component's own
 * generate path works where it will actually run - through the installed
 * library, the installed templates and the installed vocabulary, none of which
 * the test suite touches.
 *
 * The generator seeded is Exten-gen's own, the same one the acceptance check
 * compares against the hand-written generator. So what comes out here is
 * output that has already been compared, file for file, with the approved one.
 *
 * Idempotent: running it twice updates the row rather than adding a second.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);
$site = $argv[1] ?? $root . '/../Exten-gen/joomla';
$site = realpath($site) ?: $site;

if (!is_file($site . '/configuration.php')) {
    fwrite(STDERR, "No Joomla at {$site}.\n");
    exit(2);
}

\defined('_JEXEC') || \define('_JEXEC', 1);
\define('JPATH_BASE', $site);

require_once $site . \DIRECTORY_SEPARATOR . 'includes' . \DIRECTORY_SEPARATOR . 'defines.php';
require_once $site . \DIRECTORY_SEPARATOR . 'includes' . \DIRECTORY_SEPARATOR . 'framework.php';

$container = Joomla\CMS\Factory::getContainer();

$container->alias('session', 'session.cli')
    ->alias(Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(Joomla\Session\Session::class, 'session.cli')
    ->alias(Joomla\Session\SessionInterface::class, 'session.cli');

Joomla\CMS\Factory::$application = $container->get(Joomla\Console\Application::class);

$loader = include JPATH_LIBRARIES . '/vendor/autoload.php';

$map = JPATH_ADMINISTRATOR . '/cache/autoload_psr4.php';

if (!is_file($map)) {
    fwrite(STDERR, "This site has no autoload_psr4.php. Reinstall an extension to have it rebuilt.
");
    exit(2);
}

foreach ((array) require $map as $prefix => $paths) {
    $loader->setPsr4($prefix, $paths);
}

// --- Seed ------------------------------------------------------------------

$formData = (string) file_get_contents($root . '/tests/Fixtures/joomla6.generator.json');
$decoded  = json_decode($formData, true, 512, \JSON_THROW_ON_ERROR);

$definition = Yepr\Component\Gengen\Administrator\Generator\GeneratorDefinition::fromFormData($decoded);

$db   = $container->get(Joomla\Database\DatabaseInterface::class);
$name = $definition->name;

$existing = $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__gengen_generators'))
        ->where($db->quoteName('name') . ' = :name')
        ->bind(':name', $name)
)->loadResult();

$row = (object) [
    'name'      => $definition->name,
    'target'    => $definition->target,
    'form_data' => $formData,
    'published' => 1,
    'access'    => 1,
    'modified'  => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
];

if ($existing !== null) {
    $row->id = (int) $existing;

    $db->updateObject('#__gengen_generators', $row, 'id');

    printf("updated generator %d: %s\n", $row->id, $row->name);
} else {
    $row->created = $row->modified;

    $db->insertObject('#__gengen_generators', $row, 'id');

    printf("created generator %d: %s\n", (int) $row->id, $row->name);
}

// --- And run it, where it is installed -------------------------------------

// Constructed directly rather than through the MVC factory: outside a
// dispatched request there is no component factory registered, and this model
// needs neither - it takes a definition and returns files.
$model = new Yepr\Component\Gengen\Administrator\Model\GenerateModel();

$result = $model->generatePackage($definition);

printf("\ngenerated %d file(s):\n", \count($result['files']));

foreach ($result['files'] as $path => $contents) {
    printf("  %-72s %6d bytes\n", $path, \strlen($contents));
}

printf("\npackaged at %s (%d bytes)\n", str_replace(JPATH_ROOT, '', $result['path']), (int) filesize($result['path']));
