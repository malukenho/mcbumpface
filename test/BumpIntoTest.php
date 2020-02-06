<?php

declare(strict_types=1);

namespace MalukenhoTest\McBumpface;

use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\Locker;
use Composer\Script\Event;
use Malukenho\McBumpface\BumpInto;
use Malukenho\McBumpface\ComposerOptions;
use PHPUnit\Framework\TestCase;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function mkdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

final class BumpIntoTest extends TestCase
{
    /**
     * @param string[]            $expected expected end structure
     * @param array<string,mixed> $options  optional composer options provided via `extra`
     *
     * @test
     * @dataProvider providerVersions
     */
    public function updateVersions(string $requiredPackage, string $requiredVersion, string $installedVersion, array $expected, array $options = []) : void
    {
        $directory = sys_get_temp_dir() . '/' . uniqid('test-composer', false);

        mkdir($directory);

        file_put_contents($directory . '/composer.json', $this->createComposerFile($requiredPackage, $requiredVersion, $options));

        file_put_contents($directory . '/composer.lock', sprintf('{
            "content-hash": "fake-hash",
            "packages": [{
                "name": "%s",
                "version": "%s"
            }]
        }', $requiredPackage, $installedVersion));

        $composerEvent = $this->createMock(Event::class);
        $IOInterface   = $this->createMock(IOInterface::class);
        $composer      = (new Factory())
            ->createComposer($IOInterface, $directory . '/composer.json', false, $directory);

        $composerEvent
            ->expects(self::once())
            ->method('getIO')
            ->willReturn($IOInterface);

        $composerEvent
            ->expects(self::once())
            ->method('getComposer')
            ->willReturn($composer);

        BumpInto::versions($composerEvent);

        $composerFinalContent     = file_get_contents($directory . '/composer.json');
        $composerLockFinalContent = file_get_contents($directory . '/composer.lock');

        self::assertSame(
            Locker::getContentHash($composerFinalContent),
            json_decode($composerLockFinalContent, true)['content-hash']
        );
        self::assertSame($expected, json_decode($composerFinalContent, true)['require'] ?? []);
    }

    /**
     * @return string[][]|iterable
     */
    public function providerVersions() : iterable
    {
        yield '^1.0' => [
            'package' => 'malukenho/docheader',
            'required_version' => '^1.0',
            'installed_version' => '1.0.0',
            'expected' => ['malukenho/docheader' => '^1.0.0'],
        ];

        yield 'version with leading "v" char' => [
            'package' => 'malukenho/docheader',
            'required_version' => '^1.0',
            'installed_version' => 'v1.0.1',
            'expected' => ['malukenho/docheader' => '^v1.0.1'],
        ];

        yield 'locked versions should not be marked for updated' => [
            'package' => 'malukenho/docheader',
            'required_version' => '1.0',
            'installed_version' => 'v1.0.0',
            'expected' => ['malukenho/docheader' => 'v1.0.0'],
        ];

        yield '^1.3' => [
            'package' => 'malukenho/docheader',
            'required_version' => '^1.3',
            'installed_version' => '1.9.6',
            'expected' => ['malukenho/docheader' => '^1.9.6'],
        ];

        yield 'dev-master-bits' => [
            'package' => 'malukenho/zend-framework',
            'required_version' => 'dev-master-bits',
            'installed_version' => 'dev-master-bits',
            'expected' => ['malukenho/zend-framework' => 'dev-master-bits'],
        ];

        yield 'dev-master#4e4cd83e1bc67fef9efca32f30648011d6d319cb' => [
            'package' => 'malukenho/zend-framework',
            'required_version' => 'dev-master#4e4cd83e1bc67fef9efca32f30648011d6d319cb',
            'installed_version' => 'dev-master',
            'expected' => ['malukenho/zend-framework' => 'dev-master#4e4cd83e1bc67fef9efca32f30648011d6d319cb'],
        ];

        yield 'dev-hackfix-composite-key-serialization-2.7@dev' => [
            'package' => 'malukenho/zend-framework',
            'required_version' => 'dev-hackfix-composite-key-serialization-2.7@dev',
            'installed_version' => 'dev-hackfix-composite-key-serialization-2.7',
            'expected' => ['malukenho/zend-framework' => 'dev-hackfix-composite-key-serialization-2.7@dev'],
        ];

        yield 'dev-hackfix-composite-key-serialization as 1.1.1' => [
            'package' => 'malukenho/zend-framework',
            'required_version' => 'dev-hackfix-composite-key-serialization as 1.1.1',
            'installed_version' => 'dev-hackfix-composite-key-serialization',
            'expected' => ['malukenho/zend-framework' => 'dev-hackfix-composite-key-serialization as 1.1.1'],
        ];

        yield 'dev-hackfix-composite-key-serialization as v1.1.1' => [
            'package' => 'malukenho/zend-framework',
            'required_version' => 'dev-hackfix-composite-key-serialization as v1.1.1',
            'installed_version' => 'dev-hackfix-composite-key-serialization',
            'expected' => ['malukenho/zend-framework' => 'dev-hackfix-composite-key-serialization as v1.1.1'],
        ];

        yield 'dev-master@dev' => [
            'package' => 'malukenho/zend-framework',
            'required_version' => 'dev-master@dev',
            'installed_version' => 'dev-master@dev',
            'expected' => ['malukenho/zend-framework' => 'dev-master@dev'],
        ];

        yield '@dev' => [
            'package' => 'malukenho/zend-framework',
            'required_version' => '@dev',
            'installed_version' => '@dev',
            'expected' => ['malukenho/zend-framework' => '@dev'],
        ];

        yield '1.0.0' => [
            'package' => 'malukenho/zend-framework',
            'required_version' => '1.0.0',
            'installed_version' => '1.0.0',
            'expected' => ['malukenho/zend-framework' => '1.0.0'],
        ];

        yield '~2.0.23' => [
            'package' => 'malukenho/zend-framework',
            'required_version' => '~2.0.20',
            'installed_version' => '2.0.30',
            'expected' => ['malukenho/zend-framework' => '~2.0.30'],
        ];

        yield '~1.2' => [
            'package' => 'malukenho/zend-framework',
            'required_version' => '~1.2',
            'installed_version' => '1.2.3',
            'expected' => ['malukenho/zend-framework' => '~1.2.3'],
        ];

        yield 'version with leading "v" char but version prefixes are disabled via options' => [
            'package' => 'malukenho/docheader',
            'required_version' => '^1.0',
            'installed_version' => 'v1.0.1',
            'expected' => ['malukenho/docheader' => '^1.0.1'],
            'options' => ['stripVersionPrefixes' => true],
        ];
    }

    /**
     * @param array<string,mixed> $options
     */
    private function createComposerFile(string $requiredPackage, string $requiredVersion, array $options = []) : string
    {
        if ($options === []) {
            return sprintf('{
                "require": {
                    "%s": "%s"
                }
            }', $requiredPackage, $requiredVersion);
        }

        return json_encode([
            'require' => [$requiredPackage => $requiredVersion],
            'extra' => [ComposerOptions::EXTRA_IDENTIFIER => $options],
        ]);
    }
}
