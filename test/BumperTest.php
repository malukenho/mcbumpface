<?php

declare(strict_types=1);

namespace MalukenhoTest\McBumpface;

use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\Locker;
use Generator;
use Malukenho\McBumpface\Bumper;
use Malukenho\McBumpface\Options;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;

use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function mkdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

final class BumperTest extends TestCase
{
    /**
     * @param string[] $expected expected end structure
     * @param array<string,mixed> $options optional composer options provided via `extra`
     * @test
     * @dataProvider providerVersions
     */
    public function updateVersions(
        string $requiredPackage,
        string $requiredVersion,
        string $installedVersion,
        array $expected,
        bool $constraintIsModified = false,
        array $options = [],
    ): void {
        $directory = sys_get_temp_dir() . '/' . uniqid('test-composer', false);

        mkdir($directory);

        file_put_contents(
            $directory . '/composer.json',
            $this->createComposerFile($requiredPackage, $requiredVersion, $options)
        );

        file_put_contents(
            $directory . '/composer.lock',
            sprintf(
                '{
            "content-hash": "fake-hash",
            "packages": [{
                "name": "%s",
                "version": "%s"
            }]
        }',
                $requiredPackage,
                $installedVersion
            )
        );

        $inputInterface = $this->createMock(InputInterface::class);
        $ioInterface    = $this->createMock(IOInterface::class);
        $composer       = (new Factory())->createComposer(
            $ioInterface,
            $directory . '/composer.json',
            false,
            $directory
        );

        $ioInterface
            ->expects(self::exactly($constraintIsModified ? 2 : 1))
            ->method('write');

        Bumper::versions($composer, $ioInterface, $inputInterface);

        $composerFinalContent     = file_get_contents($directory . '/composer.json');
        $composerLockFinalContent = file_get_contents($directory . '/composer.lock');

        /** @var array<string, mixed> $lockContent */
        $lockContent = json_decode($composerLockFinalContent, true);
        self::assertSame(
            Locker::getContentHash($composerFinalContent),
            $lockContent['content-hash']
        );
        self::assertSame($expected, json_decode($composerFinalContent, true)['require'] ?? []);
    }

    /**
     * @param array<string,mixed> $options
     */
    private function createComposerFile(string $requiredPackage, string $requiredVersion, array $options = []): string
    {
        if ($options === []) {
            return sprintf(
                '{
                "require": {
                    "%s": "%s"
                }
            }',
                $requiredPackage,
                $requiredVersion
            );
        }

        return json_encode([
            'require' => [$requiredPackage => $requiredVersion],
            'extra'   => [Options::IDENTIFIER => $options],
        ]);
    }

    /**
     * @return Generator<string, array{
     *     package: string,
     *     required_version: string,
     *     installed_version: string,
     *     expected: array<string, string>,
     *     constraintIsModified?: bool,
     *     options?: array<string, mixed>,
     * }>
     */
    public function providerVersions(): Generator
    {
        yield '^1.0' => [
            'package'              => 'malukenho/docheader',
            'required_version'     => '^1.0',
            'installed_version'    => '1.0.0',
            'expected'             => ['malukenho/docheader' => '^1.0.0'],
            'constraintIsModified' => true,
        ];

        yield 'version with leading "v" char' => [
            'package'              => 'malukenho/docheader',
            'required_version'     => '^1.0',
            'installed_version'    => 'v1.0.1',
            'expected'             => ['malukenho/docheader' => '^1.0.1'],
            'constraintIsModified' => true,
        ];

        yield 'locked versions should not be marked for updated' => [
            'package'              => 'malukenho/docheader',
            'required_version'     => '1.0',
            'installed_version'    => 'v1.0.0',
            'expected'             => ['malukenho/docheader' => '1.0.0'],
            'constraintIsModified' => true,
        ];

        yield '^1.3' => [
            'package'              => 'malukenho/docheader',
            'required_version'     => '^1.3',
            'installed_version'    => '1.9.6',
            'expected'             => ['malukenho/docheader' => '^1.9.6'],
            'constraintIsModified' => true,
        ];

        yield 'dev-master-bits' => [
            'package'           => 'malukenho/zend-framework',
            'required_version'  => 'dev-master-bits',
            'installed_version' => 'dev-master-bits',
            'expected'          => ['malukenho/zend-framework' => 'dev-master-bits'],
        ];

        yield 'dev-master#4e4cd83e1bc67fef9efca32f30648011d6d319cb' => [
            'package'           => 'malukenho/zend-framework',
            'required_version'  => 'dev-master#4e4cd83e1bc67fef9efca32f30648011d6d319cb',
            'installed_version' => 'dev-master',
            'expected'          => [
                'malukenho/zend-framework' => 'dev-master#4e4cd83e1bc67fef9efca32f30648011d6d319cb',
            ],
        ];

        yield 'dev-hackfix-composite-key-serialization-2.7@dev' => [
            'package'           => 'malukenho/zend-framework',
            'required_version'  => 'dev-hackfix-composite-key-serialization-2.7@dev',
            'installed_version' => 'dev-hackfix-composite-key-serialization-2.7',
            'expected'          => ['malukenho/zend-framework' => 'dev-hackfix-composite-key-serialization-2.7@dev'],
        ];

        yield 'dev-hackfix-composite-key-serialization as 1.1.1' => [
            'package'           => 'malukenho/zend-framework',
            'required_version'  => 'dev-hackfix-composite-key-serialization as 1.1.1',
            'installed_version' => 'dev-hackfix-composite-key-serialization',
            'expected'          => ['malukenho/zend-framework' => 'dev-hackfix-composite-key-serialization as 1.1.1'],
        ];

        yield 'dev-hackfix-composite-key-serialization as v1.1.1' => [
            'package'           => 'malukenho/zend-framework',
            'required_version'  => 'dev-hackfix-composite-key-serialization as v1.1.1',
            'installed_version' => 'dev-hackfix-composite-key-serialization',
            'expected'          => ['malukenho/zend-framework' => 'dev-hackfix-composite-key-serialization as v1.1.1'],
        ];

        yield 'dev-master@dev' => [
            'package'           => 'malukenho/zend-framework',
            'required_version'  => 'dev-master@dev',
            'installed_version' => 'dev-master@dev',
            'expected'          => ['malukenho/zend-framework' => 'dev-master@dev'],
        ];

        yield '^2@dev' => [
            'package'           => 'ruflin/elastica',
            'required_version'  => '^2@dev',
            'installed_version' => '2.x-dev',
            'expected'          => ['ruflin/elastica' => '^2@dev'],
        ];

        yield '@dev' => [
            'package'           => 'malukenho/zend-framework',
            'required_version'  => '@dev',
            'installed_version' => '@dev',
            'expected'          => ['malukenho/zend-framework' => '@dev'],
        ];

        yield '1.0.0' => [
            'package'           => 'malukenho/zend-framework',
            'required_version'  => '1.0.0',
            'installed_version' => '1.0.0',
            'expected'          => ['malukenho/zend-framework' => '1.0.0'],
        ];

        yield '^1.9.3 || ^2.0' => [
            'package'           => 'malukenho/zend-framework',
            'required_version'  => '^1.9.3 || ^2.0',
            'installed_version' => '2.0.2',
            'expected'          => ['malukenho/zend-framework' => '^1.9.3 || ^2.0'],
        ];

        yield '~2.0.23' => [
            'package'              => 'malukenho/zend-framework',
            'required_version'     => '~2.0.20',
            'installed_version'    => '2.0.30',
            'expected'             => ['malukenho/zend-framework' => '~2.0.30'],
            'constraintIsModified' => true,
        ];

        yield '~2.0.30' => [
            'package'           => 'malukenho/zend-framework',
            'required_version'  => '~2.0.30',
            'installed_version' => '2.0.30',
            'expected'          => ['malukenho/zend-framework' => '~2.0.30'],
        ];

        yield '^1.3.0, <1.4.0' => [
            'package'           => 'malukenho/zend-framework',
            'required_version'  => '^1.3.0, <1.4.0',
            'installed_version' => '1.3.5',
            'expected'          => ['malukenho/zend-framework' => '^1.3.0, <1.4.0'],
        ];

        yield 'new version with leading "v" char but stripping prefixes is default' => [
            'package'              => 'malukenho/docheader',
            'required_version'     => '^1.0',
            'installed_version'    => 'v1.0.1',
            'expected'             => ['malukenho/docheader' => '^1.0.1'],
            'constraintIsModified' => true,
        ];

        yield 'matching version with leading "v" char but stripping prefixes is default' => [
            'package'           => 'malukenho/docheader',
            'required_version'  => '^1.0.1',
            'installed_version' => 'v1.0.1',
            'expected'          => ['malukenho/docheader' => '^1.0.1'],
        ];

        yield 'version with leading "v" char but stripping prefixes is disabled via options' => [
            'package'              => 'malukenho/docheader',
            'required_version'     => '^1.0',
            'installed_version'    => 'v1.0.1',
            'expected'             => ['malukenho/docheader' => '^v1.0.1'],
            'constraintIsModified' => true,
            'options'              => [Options::OPTION_STRIP_PREFIX => false],
        ];

        yield 'constraint prefix ~ should be replaced when disabled' => [
            'package'              => 'malukenho/docheader',
            'required_version'     => '~2.0',
            'installed_version'    => '2.0.30',
            'expected'             => ['malukenho/docheader' => '^2.0.30'],
            'constraintIsModified' => true,
            'options'              => [Options::OPTION_KEEP_VERSION_CONSTRAINT_PREFIX => false],
        ];
    }
}
