<?php

/**
 * Is the installed component actually wired up?
 *
 *   php tools/smoke.php
 *   php tools/smoke.php ../some/site
 *
 * Everything the suite checks is true of the working copy. This asks about the
 * install: whether Joomla registered the namespace, whether the classes the
 * package shipped resolve through the site's own autoloader, whether the
 * library the install script was supposed to put there is there and new enough,
 * whether the table exists, and whether Joomla can build each form.
 *
 * All of which is what a component gets wrong when it gets anything wrong. A
 * missing folder in `<files>`, a namespace that does not match the manifest, a
 * field class Joomla cannot resolve - none of it shows up until somebody opens
 * the screen, and then it shows up as a blank page.
 *
 * It needs no login, because none of this is behind one.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);
$site = $argv[1] ?? $root . '/../Exten-gen/joomla';

// Resolved, and with native separators. Joomla's defines.php derives
// JPATH_ROOT by exploding JPATH_BASE on DIRECTORY_SEPARATOR and popping the
// last part - so on Windows a single forward slash in the path leaves
// `joomla/administrator` as one element, and popping it removes both. It
// looked for the libraries one directory above the site.
$site = realpath($site) ?: $site;

if (!is_file($site . '/configuration.php')) {
    fwrite(STDERR, "No Joomla at {$site}.\n");
    exit(2);
}

\defined('_JEXEC') || \define('_JEXEC', 1);

// Booted the way cli/joomla.php boots: JPATH_BASE is the site root and the
// console application is the one that runs without a browser. An application
// is needed at all because Form and Text both reach for one - building a form
// without it fails with "Failed to start application", which is true and says
// nothing about the form.
\define('JPATH_BASE', $site);

require_once $site . \DIRECTORY_SEPARATOR . 'includes' . \DIRECTORY_SEPARATOR . 'defines.php';
require_once $site . \DIRECTORY_SEPARATOR . 'includes' . \DIRECTORY_SEPARATOR . 'framework.php';

$container = Joomla\CMS\Factory::getContainer();

$container->alias('session', 'session.cli')
    ->alias(Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(Joomla\Session\Session::class, 'session.cli')
    ->alias(Joomla\Session\SessionInterface::class, 'session.cli');

Joomla\CMS\Factory::$application = $container->get(Joomla\Console\Application::class);

// An extension's namespace is registered from a map the installer writes, and
// it is an application that loads it. Loading it here is also the check: if
// com_gengen is not in that file, nothing the package shipped can be found,
// however correct the files are.
$map = JPATH_ADMINISTRATOR . '/cache/autoload_psr4.php';

if (!is_file($map)) {
    fwrite(STDERR, "This site has no autoload_psr4.php. Reinstall an extension to have it rebuilt.
");
    exit(2);
}

$namespaces = (array) require $map;

// Registered the way an application registers them, so what is checked below is
// the map the installer wrote and not this script's idea of where files are.
$autoload = JPATH_LIBRARIES . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "This site has no libraries/vendor/autoload.php." . PHP_EOL);
    exit(2);
}

$loader = include $autoload;

foreach ($namespaces as $prefix => $paths) {
    $loader->setPsr4($prefix, $paths);
}


/**
 * What has been asked so far.
 *
 * A holder rather than two globals, because `global` makes a variable
 * invisible to anything reading the file - including the analyser, which
 * concluded that the list of problems was always empty and so that the exit
 * code below could only ever be zero. It was right about what it could see.
 */
final class Report
{
    /** @var string[] */
    public static array $problems = [];

    public static int $checks = 0;
}

/**
 * Report one check.
 */
function check(string $what, bool $ok, string $detail = ''): void
{
    Report::$checks++;

    printf("  %-4s %s%s\n", $ok ? 'ok' : 'FAIL', $what, $detail === '' ? '' : '  (' . $detail . ')');

    if (!$ok) {
        Report::$problems[] = $what . ($detail === '' ? '' : ': ' . $detail);
    }
}

echo "The namespace map the installer wrote\n";

check("the component's namespace is in it", isset($namespaces['Yepr\\Component\\Gengen\\Administrator\\']));
check("the library's namespace is in it", isset($namespaces['Yepr\\Gen\\']));

echo "\nThe component's own classes\n";

foreach (
    [
    'Yepr\Component\Gengen\Administrator\Extension\GengenComponent',
    'Yepr\Component\Gengen\Administrator\Controller\DisplayController',
    'Yepr\Component\Gengen\Administrator\Controller\GeneratorController',
    'Yepr\Component\Gengen\Administrator\Controller\GeneratorsController',
    'Yepr\Component\Gengen\Administrator\Model\GeneratorModel',
    'Yepr\Component\Gengen\Administrator\Model\GeneratorsModel',
    'Yepr\Component\Gengen\Administrator\Model\GenerateModel',
    'Yepr\Component\Gengen\Administrator\Table\GeneratorTable',
    'Yepr\Component\Gengen\Administrator\View\Generator\HtmlView',
    'Yepr\Component\Gengen\Administrator\View\Generators\HtmlView',
    'Yepr\Component\Gengen\Administrator\Generator\GeneratorDefinition',
    'Yepr\Component\Gengen\Administrator\Generator\Target\JoomlaGeneratorTarget',
    ] as $class
) {
    check($class, class_exists($class));
}

echo "\nThe form fields, resolved the way Joomla resolves them\n";

foreach (['GeneratorTarget', 'RuleSelector', 'RuleTemplate', 'Derivation', 'Operator', 'BindingKind'] as $type) {
    $class = 'Yepr\Component\Gengen\Administrator\Field\\' . ucfirst(ucwords($type)) . 'Field';

    check('type="' . $type . '"', class_exists($class), $class);
}

echo "\nThe component, built the way Joomla builds it\n";

// Not class_exists(): the provider is a closure in a file Joomla includes at
// run time, and it calls methods on the component that nothing static checks.
// It called setRegistry() on a class that did not have it, which looks like a
// working component right up until the first request reaches it.
try {
    $component = Joomla\CMS\Factory::getApplication()->bootComponent('com_gengen');

    // MVCComponent rather than ComponentInterface: the interface does not
    // promise getMVCFactory(), and a component that cannot hand one over
    // cannot dispatch a controller, which is all this component does.
    check('services/provider.php builds it', $component instanceof Joomla\CMS\Extension\MVCComponent);

    if ($component instanceof Joomla\CMS\Extension\MVCComponent) {
        // Asking the factory for a model rather than for itself: the factory
        // is always there, and whether it can find this component's classes
        // under the namespace the manifest declared is the actual question.
        $model = $component->getMVCFactory()->createModel('Generators', 'Administrator', ['ignore_request' => true]);

        check(
            'its MVC factory finds the models',
            $model instanceof Yepr\Component\Gengen\Administrator\Model\GeneratorsModel
        );
    }
} catch (\Throwable $e) {
    check('services/provider.php builds it', false, $e->getMessage());
}

echo "\nThe shared library\n";

check('Yepr\Gen\Core\Rule\RuleEngine', class_exists('Yepr\Gen\Core\Rule\RuleEngine'));
check('Yepr\Gen\Core\Rule\Vocabulary', class_exists('Yepr\Gen\Core\Rule\Vocabulary'));

echo "\nWhat the install script was meant to leave behind\n";

$db = Joomla\CMS\Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class);

$tables = $db->setQuery('SHOW TABLES LIKE ' . $db->quote($db->replacePrefix('#__gengen_generators')))->loadColumn();

check('the generators table exists', $tables !== []);

$installed = $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('manifest_cache'))
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_gengen'))
)->loadResult();

check('the component is registered', \is_string($installed) && $installed !== '');

echo "\nThe forms Joomla will have to build\n";

Joomla\CMS\Form\FormHelper::addFieldPrefix('Yepr\Component\Gengen\Administrator\Field');

$forms = $site . '/administrator/components/com_gengen/forms';

foreach (['generator', 'rule', 'condition', 'binding', 'group', 'filter_generators'] as $name) {
    $path = $forms . '/' . $name . '.xml';

    if (!is_file($path)) {
        check($name . '.xml shipped', false, $path);

        continue;
    }

    try {
        // Through the form factory, not Form::getInstance(), which Joomla
        // deprecated. Same result, and one fewer thing that goes away in 7.
        $form = Joomla\CMS\Factory::getContainer()
            ->get(Joomla\CMS\Form\FormFactoryInterface::class)
            ->createForm('smoke.' . $name, ['control' => 'jform']);

        $form->loadFile($path);

        check($name . '.xml builds', $form instanceof Joomla\CMS\Form\Form, \count($form->getFieldset()) . ' fields');
    } catch (\Throwable $e) {
        check($name . '.xml builds', false, $e->getMessage());
    }
}

echo "\nAnd what a target published, which is what the dropdowns offer\n";

$library = new Yepr\Component\Gengen\Administrator\Generator\VocabularyLibrary(
    ...(glob(JPATH_ADMINISTRATOR . '/components/*/src/Generator/Rules', \GLOB_ONLYDIR) ?: [])
);

$targets = $library->targets();

check('at least one target is installed', $targets !== [], implode(', ', $targets));

printf("\n%d checks, %d problem(s)\n", Report::$checks, \count(Report::$problems));

if (Report::$problems !== []) {
    fwrite(STDERR, "  " . implode("\n  ", Report::$problems) . "\n");
}

exit(Report::$problems === [] ? 0 : 1);
