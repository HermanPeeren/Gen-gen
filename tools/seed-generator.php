<?php

/**
 * Put the modelled generators into the installed component, and run them there.
 *
 *   php tools/seed-generator.php
 *   php tools/seed-generator.php ../some/site
 *
 * Two things at once, because they are only worth knowing together: the site
 * gets rows to look at, and running them proves that the component's own
 * generate path works where it will actually run - through the installed
 * library, the installed templates and the installed vocabulary, none of which
 * the test suite touches.
 *
 * The generators seeded are every `tests/Fixtures/*.generator.json`: the ones
 * the Extension Generator ships, each imported from its committed rule file by
 * `import-generator.php`. Exten-gen's own Joomla 6 generator is the one the
 * acceptance check compares against the hand-written generator, so what comes
 * out of it here has already been compared, file for file, with the approved
 * output. The LedgerLang one ("Joomla component with posting rules") extends it
 * and holds only its three rules (step 5.9).
 *
 * In name order, which puts the parent first. A row is found by its name.
 *
 * Idempotent: running it twice updates the rows rather than adding more.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);
$site = $argv[1]
    ?? ((string) getenv('EXTENGEN_PATH') ? getenv('EXTENGEN_PATH') . '/joomla' : $root . '/../Exten-gen/joomla');
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

$autoload = JPATH_LIBRARIES . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "This site has no libraries/vendor/autoload.php." . PHP_EOL);
    exit(2);
}

$loader = include $autoload;

$map = JPATH_ADMINISTRATOR . '/cache/autoload_psr4.php';

if (!is_file($map)) {
    fwrite(STDERR, "This site has no autoload_psr4.php. Reinstall an extension to have it rebuilt.
");
    exit(2);
}

foreach ((array) require $map as $prefix => $paths) {
    $loader->setPsr4($prefix, $paths);
}

// --- Seed, and run each where it is installed --------------------------------

$fixtures = glob($root . '/tests/Fixtures/*.generator.json') ?: [];
sort($fixtures);

if ($fixtures === []) {
    fwrite(STDERR, "No tests/Fixtures/*.generator.json to seed. Run composer import-generator first.\n");
    exit(1);
}

$db = $container->get(Joomla\Database\DatabaseInterface::class);

// Constructed directly rather than through the MVC factory: outside a
// dispatched request there is no component factory registered, and this model
// needs neither - it takes a definition and returns files.
$model = new Yepr\Component\Gengen\Administrator\Model\GenerateModel();

foreach ($fixtures as $fixture) {
    $formData = (string) file_get_contents($fixture);
    $decoded  = json_decode($formData, true, 512, \JSON_THROW_ON_ERROR);

    $definition = Yepr\Component\Gengen\Administrator\Generator\GeneratorDefinition::fromFormData($decoded);

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

        printf("updated generator %d: %s (%s)\n", $row->id, $row->name, basename($fixture));
    } else {
        $row->created = $row->modified;

        $db->insertObject('#__gengen_generators', $row, 'id');

        printf("created generator %d: %s (%s)\n", (int) $row->id, $row->name, basename($fixture));
    }

    $result = $model->generatePackage($definition);

    printf("  generated %d file(s):\n", \count($result['files']));

    foreach ($result['files'] as $path => $contents) {
        printf("    %-70s %6d bytes\n", $path, \strlen($contents));
    }

    printf(
        "  packaged at %s (%d bytes)\n\n",
        str_replace(JPATH_ROOT, '', $result['path']),
        (int) filesize($result['path'])
    );
}
