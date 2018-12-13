<?php

namespace Caffeinated\Composer\Installers;

use Composer\Composer;
use Composer\IO\IOInterface;
use InvalidArgumentException;
use Composer\Package\PackageInterface;

abstract class BaseInstaller
{
    /**
     * @var array
     */
    protected $locations = array();

    /**
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * @var \Composer\Package\PackageInterface
     */
    protected $package;

    /**
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * Create a new BaseInstaller instance.
     * 
     * @param  \Composer\Package\PackageInterface  $package
     * @param  \Composer\Composer  $composer
     * @param  \Composer\IO\IOInterface  $io
     */
    public function __construct(PackageInterface $package = null, Composer $composer = null, IOInterface $io = null)
    {
        $this->composer = $composer;
        $this->package  = $package;
        $this->io       = $io;
    }

    public function getInstallPath(PackageInterface $package, $frameworkType = '')
    {
        $type = $this->package->getType();

        $prettyName = $this->package->getPrettyName();

        if (strpos($prettyName, '/') !== false) {
            list($vendor, $name) = explode('/', $prettyName);
        } else {
            $vendor = '';
            $name   = $prettyName;
        }

        $availableVariables = $this->inflectPackageVariables(compact('name', 'vendor', 'type'));

        $extra = $package->getExtra();

        if (! empty($extra['installer-name'])) {
            $availableVariables['name'] = $extra['installer-name'];
        }

        if ($this->composer->getPackage()) {
            $extra = $this->composer->getPackage()->getExtra();

            if (! empty($extra['installer-paths'])) {
                $customPath = $this->mapCustomInstallPaths($extra['installer-paths'], $prettyName, $type, $vendor);

                if ($customPath !== false) {
                    return $this->templatePath($customPath, $availableVariables);
                }
            }
        }

        $packageType = substr($type, strlen($frameworkType) + 1);
        $locations   = $this->getLocations();

        if (! isset($locations[$packageType])) {
            throw new InvalidArgumentException(sprintf('Package type "%s" is not supported', $type));
        }

        return $this->templatePath($locations[$packageType], $availableVariables);
    }

    public function inflectPackageVariables($variables)
    {
        return $variables;
    }

    public function getLocations()
    {
        return $this->locations;
    }

    protected function templatePath($path, array $variables = array())
    {
        if (strpos($path, '{') !== false) {
            extract($variables);
            preg_match_all('@\{\$([A-Za-z0-9_]*)\}@i', $path, $matches);

            if (! empty($matches[1])) {
                foreach ($matches[1] as $variable) {
                    $path = str_replace('{$'.$variable.'}', $$variable, $path);
                }
            }
        }

        return $path;
    }

    protected function mapCustomInstallPaths(array $paths, $name, $type, $vendor = null)
    {
        foreach ($paths as $path => $names) {
            if (in_array($name, $names) or in_array('type:'.$type, $names) or in_array('vendor:'.$vendor, $names)) {
                return $path;
            }
        }

        return false;
    }
}