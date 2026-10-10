<?php

declare(strict_types=1);

namespace Yepr\Component\Gengen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The update server says what this component is.
 *
 * `build/update-xml.php` came over from Exten-gen with Exten-gen's description
 * written into it, so Joomla's update screen described Gen-gen as "Model a
 * Joomla extension, and generate it". It is read from the language key the
 * manifest holds now, the same sentence the extension manager shows.
 */
final class UpdateServerTest extends TestCase
{
    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function testItDescribesThisComponent(): void
    {
        $manifest = simplexml_load_file($this->root() . '/src/gengen.xml');
        $updates  = simplexml_load_file($this->root() . '/updates.xml');

        $this->assertNotFalse($manifest);
        $this->assertNotFalse($updates);

        $key = trim((string) $manifest->description);
        $ini = parse_ini_file(
            $this->root() . '/src/administrator/components/com_gengen/language/en-GB/com_gengen.sys.ini'
        ) ?: [];

        $this->assertArrayHasKey($key, $ini, 'Nothing defines ' . $key . ', so the extension manager shows the key.');
        $this->assertSame($ini[$key], trim((string) $updates->update->description));
    }

    public function testItIsNamedAsTheAdministratorMenuNamesIt(): void
    {
        $updates = simplexml_load_file($this->root() . '/updates.xml');
        $ini     = parse_ini_file(
            $this->root() . '/src/administrator/components/com_gengen/language/en-GB/com_gengen.sys.ini'
        ) ?: [];

        $this->assertNotFalse($updates);
        $this->assertSame($ini['COM_GENGEN'] ?? null, trim((string) $updates->update->name));
    }
}
