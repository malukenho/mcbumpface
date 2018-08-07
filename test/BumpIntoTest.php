<?php

declare(strict_types=1);

namespace MalukenhoTest\McBumpface;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\Locker;
use Composer\Package\RootPackageInterface;
use Composer\Script\Event;
use Composer\Semver\Constraint\ConstraintInterface;
use Malukenho\McBumpface\BumpInto;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamFile;
use PHPUnit\Framework\TestCase;
use function file_get_contents;
use function json_decode;
use function sprintf;
use function uniqid;

final class BumpIntoTest extends TestCase
{
    /**
     * @test
     *
     * @param string[] $expected expected end structure
     *
     * @dataProvider providerVersions
     */
    public function updateVersions(string $requiredPackage, string $requiredVersion, string $installedVersion, array $expected) : void
    {
        $fileName = uniqid('file', true) . '.json';
        $fakeDir  = vfsStream::setup('fakeDir');

        $fileStream = new vfsStreamFile($fileName);
        $fileStream->write(sprintf('{
            "require": {
                "%s": "%s"
            }
        }', $requiredPackage, $requiredVersion));

        $fakeDir->addChild($fileStream);

        $composerEvent = $this->createMock(Event::class);
        $IOInterface   = $this->createMock(IOInterface::class);
        $composer      = $this->createMock(Composer::class);
        $config        = $this->createMock(Config::class);
        $configSource  = $this->createMock(Config\ConfigSourceInterface::class);
        $package       = $this->createMock(RootPackageInterface::class);

        $composerEvent
            ->expects(self::once())
            ->method('getIO')
            ->willReturn($IOInterface);

        $composerEvent
            ->expects(self::once())
            ->method('getComposer')
            ->willReturn($composer);

        $composer
            ->expects(self::once())
            ->method('getConfig')
            ->willReturn($config);

        $composer
            ->expects(self::once())
            ->method('getPackage')
            ->willReturn($package);

        $config
            ->expects(self::once())
            ->method('getConfigSource')
            ->willReturn($configSource);

        $configSource
            ->expects(self::once())
            ->method('getName')
            ->willReturn(vfsStream::url('fakeDir') . '/' . $fileName);

        $locker = $this->createMock(Locker::class);
        $locker
            ->expects(self::once())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name' => $requiredPackage,
                        'version' => $installedVersion,
                    ],
                ],
             ]);

        $package
            ->expects(self::once())
            ->method('getReplaces')
            ->willReturn([]);

        $composer
            ->expects(self::once())
            ->method('getLocker')
            ->willReturn($locker);

        $link       = $this->createMock(Link::class);
        $constraint = $this->createMock(ConstraintInterface::class);

        $link
            ->expects(self::once())
            ->method('getConstraint')
            ->willReturn($constraint);

        $constraint
            ->expects(self::once())
            ->method('getPrettyString')
            ->willReturn($requiredVersion);

        $package
            ->expects(self::once())
            ->method('getRequires')
            ->willReturn([$requiredPackage => $link]);

        $package
            ->expects(self::once())
            ->method('getDevRequires')
            ->willReturn([]);

        BumpInto::versions($composerEvent);

        $composerFinalContent = file_get_contents(vfsStream::url('fakeDir') . '/' . $fileName);

        self::assertSame(['require' => $expected], json_decode($composerFinalContent, true));
    }

    /**
     * @return string[][]
     */
    public function providerVersions() : array
    {
        return [
            [
                'package' => 'malukenho/docheader',
                'required_version' => '^1.0',
                'installed_version' => '1.0.0',
                'expected' => ['malukenho/docheader' => '^1.0.0'],
            ],
            [
                'package' => 'malukenho/docheader',
                'required_version' => '^1.3',
                'installed_version' => '1.9.6',
                'expected' => ['malukenho/docheader' => '^1.9.6'],
            ],
            [
                'package' => 'malukenho/zend-framework',
                'required_version' => 'dev-hackfix-composite-key-serialization as 1.1.1',
                'installed_version' => 'dev-hackfix-composite-key-serialization',
                'expected' => ['malukenho/zend-framework' => 'dev-hackfix-composite-key-serialization as 1.1.1'],
            ],
        ];
    }
}
