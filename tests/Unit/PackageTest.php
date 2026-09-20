<?php

declare(strict_types=1);

namespace Yepr\Component\Gengen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The manifest describes the package, and the package contains what it says.
 *
 * Every rule here exists because Exten-gen shipped without it and somebody
 * found out by installing: a folder missing from `<files>` so the generator had
 * no templates, a `<schemapath>` pointing at a directory that was not there so
 * the install failed outright, a media file left out so a form died with 500.
 * None of it is visible in a working copy, where development runs off symlinks
 * and every file is present whatever the manifest says.
 *
 * These read the manifest rather than a list written beside it, so adding a
 * folder to the component without listing it fails here.
 */
final class PackageTest extends TestCase
{
    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    private function manifest(): \SimpleXMLElement
    {
        $xml = simplexml_load_file($this->root() . '/src/gengen.xml');

        $this->assertNotFalse($xml, 'The manifest is not valid XML.');

        return $xml;
    }

    private function componentRoot(): string
    {
        return $this->root() . '/src/administrator/components/com_gengen/';
    }

    public function testEveryFileAndFolderTheManifestClaimsIsThere(): void
    {
        $missing = [];

        foreach ($this->manifest()->administration->files->children() as $entry) {
            $path = $this->componentRoot() . (string) $entry;

            $exists = $entry->getName() === 'folder' ? is_dir($path) : is_file($path);

            if (!$exists) {
                $missing[] = (string) $entry;
            }
        }

        $this->assertSame([], $missing, 'The manifest names these and they are not there: ' . implode(', ', $missing));
    }

    /**
     * And every folder in the component is named by the manifest.
     *
     * The other direction, which is the one that actually bites: a folder added
     * to the working copy and not to `<files>` is present for everybody
     * developing and absent for everybody installing.
     */
    public function testEveryFolderInTheComponentIsListed(): void
    {
        $listed = [];

        foreach ($this->manifest()->administration->files->children() as $entry) {
            $listed[] = (string) $entry;
        }

        $unlisted = [];

        foreach (glob($this->componentRoot() . '*', \GLOB_ONLYDIR) ?: [] as $directory) {
            $name = basename($directory);

            // Products, not sources. build.php leaves both out of the package.
            if (\in_array($name, ['generated', 'compilation_cache'], true)) {
                continue;
            }

            if (!\in_array($name, $listed, true)) {
                $unlisted[] = $name;
            }
        }

        $this->assertSame([], $unlisted, 'In the component and not in the manifest: ' . implode(', ', $unlisted));
    }

    /**
     * The update path has somewhere to start from.
     *
     * An empty `sql/updates/mysql` is not an empty set of updates, it is an
     * install that stops with "Path is not a folder" and rolls back.
     */
    public function testTheSchemaPathHasAVersionToStartFrom(): void
    {
        $path = $this->componentRoot() . (string) $this->manifest()->update->schemas->schemapath;

        $this->assertDirectoryExists($path);
        $this->assertNotEmpty(glob($path . '/*.sql') ?: [], 'The schema path holds no versions.');
    }

    public function testTheInstallAndUninstallSqlAreThere(): void
    {
        foreach (
            [
            (string) $this->manifest()->install->sql->file,
            (string) $this->manifest()->uninstall->sql->file,
            ] as $file
        ) {
            $this->assertFileExists($this->componentRoot() . $file);
        }
    }

    public function testTheInstallScriptIsNamedAndPresent(): void
    {
        $this->assertSame('script.php', (string) $this->manifest()->scriptfile);
        $this->assertFileExists($this->root() . '/src/script.php');
    }

    /**
     * The install script's class name is the one Joomla will look for.
     *
     * Joomla resolves it from the element name: `com_gengen` gives
     * `Com_GengenInstallerScript`. Anything else is never called, and nothing
     * says so - the component installs and the library it depends on does not.
     */
    public function testTheInstallScriptIsNamedTheWayJoomlaLooksForIt(): void
    {
        $script = (string) file_get_contents($this->root() . '/src/script.php');

        $this->assertStringContainsString('class Com_GengenInstallerScript', $script);
    }

    /**
     * Every language key the component uses is translated.
     *
     * An untranslated key renders as the key, in capitals, in the middle of a
     * sentence. It is the most visible kind of unfinished and the easiest to
     * miss while writing the thing that uses it.
     */
    public function testEveryLanguageKeyIsTranslated(): void
    {
        $translated = parse_ini_file($this->componentRoot() . 'language/en-GB/com_gengen.ini') ?: [];
        $used       = [];

        foreach ($this->sources() as $file) {
            preg_match_all('/COM_GENGEN_[A-Z0-9_]+/', (string) file_get_contents($file), $matches);

            foreach ($matches[0] as $key) {
                $used[$key] = true;
            }
        }

        $missing = array_values(array_diff(array_keys($used), array_keys($translated)));

        $this->assertSame([], $missing, 'Used and not translated: ' . implode(', ', $missing));
    }

    /**
     * And the component's own name is in the sys file.
     *
     * A different file, read at a different time: `com_gengen.sys.ini` is what
     * the extension manager and the admin menu read, before the component's own
     * language file is loaded.
     */
    public function testTheExtensionManagerHasSomethingToShow(): void
    {
        $sys = parse_ini_file($this->componentRoot() . 'language/en-GB/com_gengen.sys.ini') ?: [];

        $this->assertArrayHasKey('COM_GENGEN', $sys);
        $this->assertArrayHasKey((string) $this->manifest()->description, $sys);
    }

    /**
     * The manifest points sites at this repository's update server.
     */
    public function testTheUpdateServerIsThisRepositorys(): void
    {
        $server = trim((string) $this->manifest()->updateservers->server);

        $this->assertStringContainsString('HermanPeeren/Gen-gen', $server);
        $this->assertStringEndsWith('updates.xml', $server);
        $this->assertFileExists($this->root() . '/updates.xml');
    }

    /**
     * The committed update server is what the script writes.
     */
    public function testTheUpdateServerIsCurrent(): void
    {
        $committed = (string) file_get_contents($this->root() . '/updates.xml');

        exec('php ' . escapeshellarg($this->root() . '/build/update-xml.php') . ' 2>&1', $output, $status);

        $this->assertSame(0, $status, implode("\n", $output));

        $this->assertSame(
            $committed,
            (string) file_get_contents($this->root() . '/updates.xml'),
            'updates.xml is out of date. Run php build/update-xml.php and commit the result.'
        );
    }

    /**
     * composer.json and the install script ask for the same library.
     *
     * Two places name a version and only one is checked at run time.
     */
    public function testTheDeclaredDependencyMatchesTheInstallScript(): void
    {
        $script = (string) file_get_contents($this->root() . '/src/script.php');

        preg_match("/LIBRARY_MINIMUM\s*=\s*'([^']+)'/", $script, $minimum);

        $composer = json_decode(
            (string) file_get_contents($this->root() . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $constraint = $composer['require']['yepr/generator-core'];

        $this->assertSame(
            '^' . implode('.', \array_slice(explode('.', $minimum[1]), 0, 2)),
            $constraint,
            'composer.json asks for ' . $constraint . ' but script.php insists on ' . $minimum[1] . '.'
        );
    }

    /**
     * Every source file the component ships.
     *
     * @return string[]
     */
    private function sources(): array
    {
        $files = [];

        $tree = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->componentRoot(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($tree as $file) {
            if ($file->isFile() && \in_array($file->getExtension(), ['php', 'xml'], true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
