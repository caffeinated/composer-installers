<?php

namespace Caffeinated\Composer\Installers;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use InvalidArgumentException;
use Composer\Package\PackageInterface;
use Composer\Installer\BinaryInstaller;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;

class Installer extends LibraryInstaller
{
    /**
     * Package types to installer class map.
     * 
     * @var array
     */
    private $supportedTypes = array(
        'caffeinated-themes' => 'ThemeInstaller',
    );

    public function __construct(IOInterface $io, Composer $composer, $type = 'library', Filesystem $filesystem = null, BinaryInstaller $binaryInstaller = null)
    {
        parent::__construct($io, $composer, $type, $filesystem, $binaryInstaller);

        $this->removeDisabledInstallers();
    }

    public function getInstallPath(PackageInterface $package)
    {
        $type          = $package->getType();
        $frameworkType = $this->findFrameworkType($type);

        if ($frameworkType === false) {
            throw new InvalidArgumentException('Sorry the package type of this package is not supported.');
        }

        $class     = 'Caffeinated\\Composer\\Installers\\'.$this->supportedTypes[$frameworkType];
        $installer = new $class($package, $this->composer, $this->getIO());

        return $installer->getInstallPath($package, $frameworkType);
    }

    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::uninstall($repo, $package);

        $installPath = $this->getPackageBasePath($package);

        $this->io->write(sprintf(
            'Deleting %s - %s',
            $installPath,
            ! file_exists($installPath)
                ? '<comment>deleted</comment>'
                : '<error>not deleted</error>'
        ));
    }

    public function supports($packageType)
    {
        $frameworkType = $this->findFrameworkType($packageType);

        if ($frameworkType === false) {
            return false;
        }

        $locationPattern = $this->getLocationPattern($frameworkType);

        return preg_math('#'.$frameworkType.'-'.$locationPattern.'#', $packageType, $matches) === 1;
    }

    protected function findFrameworkType($type)
    {
        $frameworkType = false;

        krsort($this->supportedTypes);

        foreach ($this->supportedTypes as $key => $value) {
            if ($key === substr($type, 0, strlen($key))) {
                $frameworkType = substr($type, 0, strlen($key));

                break;
            }
        }

        return $frameworkType;
    }

    protected function getLocationPattern($frameworkType)
    {
        $pattern = false;

        if (! empty($this->supportedTypes[$frameworkType])) {
            $frameworkClass = 'Caffeinated\\Composer\\Installers\\'.$this->supportedTypes[$frameworkType];

            $framework = new $frameworkClass(null, $this->composer, $this->getIO());
            $locations = array_keys($framework->getLocations());
            $pattern   = $locations ? '('.implode('|', $locations).')' : false;
        }

        return $pattern ?: '(\w+)';
    }

    private function getIO()
    {
        return $this->io;
    }

    protected function removeDisabledInstallers()
    {
        $extra = $this->composer->getPackage()->getExtra();

        if (! isset($extra['installer-disable']) or $extra['installer-disable'] === false) {
            return;
        }

        $disable = $extra['installer-disable'];

        if (! is_array($disable)) {
            $disable = array($disable);
        }

        $all       = array(true, "all", "*");
        $intersect = array_intersect($all, $disable);

        if (! empty($intersect)) {
            $this->supportedTypes = array();
        } else {
            foreach ($disable as $key => $installer) {
                if (is_string($installer) and key_exists($installer, $this->supportedTypes)) {
                    unset($this->supportedTypes[$installer]);
                }
            }
        }
    }
}