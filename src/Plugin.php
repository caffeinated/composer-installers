<?php

namespace Caffeinated\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Caffeinated\Composer\Installers\Installer;

class Plugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new Installer($io, $composer);
        
        $composer->getInstallationManager()->addInstaller($installer);
    }
}
