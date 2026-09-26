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
 * A prefix rather than exact names, which is the one thing here worth being
 * careful about: the timestamp means no two runs agree on the name, so there is
 * nothing exact to match. The prefix is the spec's own literal, and it names a
 * language that only exists as a test fixture - a real generator called
 * "Written for Testlang ..." would be one somebody wrote for the test language
 * on purpose. Everything else on the site is untouched, and what goes is
 * printed.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);

// The specs run against Exten-gen's site, which is where this component is
// installed; Gen-gen's own `joomla/` is a clean tree for the analyser.
$site = $argv[1] ?? $root . '/../Exten-gen/joomla';

if (!is_file($site . '/configuration.php')) {
    fwrite(STDERR, "No site at {$site}: this is for the development install.\n");
    exit(1);
}

\defined('_JEXEC') || \define('_JEXEC', 1);

require $site . '/configuration.php';

/**
 * How the browser specs name the generators they create.
 *
 * `tests/cypress/e2e/metalanguages.cy.js` builds both the plain and the derived
 * one from this, so one prefix covers both.
 */
const SPEC_GENERATOR_PREFIX = 'Written for Testlang ';

$config = new JConfig();

try {
    $database = new PDO(
        'mysql:host=' . $config->host . ';dbname=' . $config->db,
        $config->user,
        $config->password
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'Cannot reach the site database: ' . $e->getMessage() . "\n");
    exit(1);
}

$table = $config->dbprefix . 'gengen_generators';

// Said out loud before anything is removed. A cleanup that silently took more
// than it should would be indistinguishable from one that worked.
$listing = $database->prepare("SELECT id, name FROM `{$table}` WHERE name LIKE ? ESCAPE '!'");

// `_` and `%` mean something in LIKE and nothing in the prefix.
$pattern = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], SPEC_GENERATOR_PREFIX) . '%';

$listing->execute([$pattern]);

$doomed = $listing->fetchAll(PDO::FETCH_ASSOC);

if ($doomed === []) {
    echo "nothing to forget\n";

    exit(0);
}

foreach ($doomed as $row) {
    printf("  forgetting %d: %s\n", $row['id'], $row['name']);
}

$delete = $database->prepare("DELETE FROM `{$table}` WHERE name LIKE ? ESCAPE '!'");

$delete->execute([$pattern]);

printf("%d row(s) removed\n", $delete->rowCount());
