<?php

/**
 * The Joomla constants this component reads.
 *
 * Joomla defines these in `includes/defines.php` when an application boots, so
 * they exist at runtime but not in any file an analyser reads. Declaring them
 * here is the whole of the fix: without it every use is reported, which hides
 * the findings that mean something.
 *
 * Only the ones this component actually reads are here. A bootstrap that
 * defines everything is one nobody can read to answer "what does this depend
 * on".
 *
 * A bootstrap rather than a stub file: PHPStan's stubs describe classes and
 * functions, and a constant has to be defined by something that runs.
 */

declare(strict_types=1);

// Where components are installed, and so where a target's published vocabulary
// descriptor is found. See VocabularyContext.
\define('JPATH_ADMINISTRATOR', '');
