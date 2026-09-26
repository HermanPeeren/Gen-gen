<?php

/**
 * Remove the generators the browser specs create, from the development site.
 *
 *   php tools/forget-test-generators.php
 *   php tools/forget-test-generators.php ../some/site
 *
 * `metalanguages.cy.js` makes a generator through the screens, which is the
 * point of it: a generator written for an imported language has to be creatable
 * and openable by a component that has never heard of that language. What it
 * never did is take it away again, and because it names them with a timestamp -
 * `Written for Testlang ${Date.now()}` - every run left a new one rather than
 * reusing the last. The site had reached seventeen against one real generator.
 *
 * It boots Joomla and uses the site's own database driver, the way every other
 * tool here does. Not a preference: `JConfig` is a class Joomla writes at
 * install time and no source file declares, so a tool built on it cannot be
 * analysed, and `tools/` is analysed with everything else. The first version of
 * this file did read `configuration.php` into a `JConfig`; it passed on a
 * machine whose Joomla is an installed site and failed on CI, where the
 * fetched one never gets installed.
 *
 * A prefix rather than exact names, which is the one thing here worth being
 * careful about: the timestamp means no two runs agree on the name, so there is
 * nothing exact to match. The prefix is the spec's own literal, and it names a
 * language that exists only as a test fixture. Every row is printed before it
 * goes, because a cleanup that quietly took more than it should would look
 * exactly like one that worked.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);

// The specs run against Exten-gen's site, which is where this component is
// installed; Gen-gen's own `joomla/` is a clean tree for the analyser.
$site = $argv[1] ?? $root . '/../Exten-gen/joomla';
$site = realpath($site) ?: $site;

if (!is_file($site . '/configuration.php')) {
    fwrite(STDERR, "No Joomla at {$site}: this is for the development install.\n");
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

// --- Forget -----------------------------------------------------------------

/**
 * How the browser specs name the generators they create.
 *
 * `tests/cypress/e2e/metalanguages.cy.js` builds both the plain and the derived
 * one from this, so one prefix covers both.
 */
$prefix = 'Written for Testlang ';

/** @var Joomla\Database\DatabaseInterface $db */
$db = $container->get(Joomla\Database\DatabaseInterface::class);

$pattern = $db->escape($prefix, true) . '%';

$doomed = $db->setQuery(
    $db->getQuery(true)
        ->select([$db->quoteName('id'), $db->quoteName('name')])
        ->from($db->quoteName('#__gengen_generators'))
        ->where($db->quoteName('name') . ' LIKE ' . $db->quote($pattern, false))
)->loadObjectList();

if ($doomed === []) {
    echo "nothing to forget\n";

    exit(0);
}

foreach ($doomed as $row) {
    printf("  forgetting %d: %s\n", (int) $row->id, $row->name);
}

$db->setQuery(
    $db->getQuery(true)
        ->delete($db->quoteName('#__gengen_generators'))
        ->where($db->quoteName('name') . ' LIKE ' . $db->quote($pattern, false))
)->execute();

printf("%d row(s) removed\n", $db->getAffectedRows());
