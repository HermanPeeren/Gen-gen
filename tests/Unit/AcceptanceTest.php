<?php

declare(strict_types=1);

namespace Yepr\Component\Gengen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Step 2.3's acceptance criterion, which is not a judgement call.
 *
 * The generator Gen-gen produces is run over Exten-gen's three golden models,
 * and every file it produces is compared with the output Stage 1 approved for
 * the hand-written generator it replaces. Both directions: nothing missing,
 * nothing extra, 228 files.
 *
 * That is the only claim worth making about a generated generator. "The rules
 * round-trip" and "the classes parse" are necessary and neither of them would
 * notice a binding that resolved to the wrong thing.
 *
 * **In a separate process**, because the generated classes have the same fully
 * qualified names as Exten-gen's hand-written ones - that is the point, they
 * are meant to be the same classes - so they are required before anything can
 * autoload the originals. A class is defined once per process, so doing this
 * inside the suite would make the answer depend on what had already been
 * loaded.
 */
final class AcceptanceTest extends TestCase
{
    /**
     * The generated generator produces the approved output, file for file.
     */
    public function testTheGeneratedGeneratorProducesTheApprovedOutput(): void
    {
        $root = \dirname(__DIR__, 2);

        exec(
            'php ' . escapeshellarg($root . '/tools/check-against-extengen.php') . ' 2>&1',
            $output,
            $status
        );

        $report = implode("\n", $output);

        // 2 means the target is not here to check against. On a machine with
        // only this repository cloned that is a fact about the checkout, not a
        // failure - but it must not pass silently either, or the one test that
        // proves the step would be the one test nobody notices is not running.
        // CI checks Exten-gen out beside this one so that it always runs there.
        if ($status === 2) {
            $this->markTestSkipped(
                "Exten-gen is not available to check against:\n" . $report
                . "\nClone it beside this repository, or set EXTENGEN_PATH."
            );
        }

        $this->assertSame(0, $status, $report);
        $this->assertStringContainsString('all identical to the approved output', $report);
        $this->assertStringContainsString('rule file identical to the committed one', $report);
    }
}
