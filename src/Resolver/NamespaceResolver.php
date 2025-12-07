<?php declare(strict_types=1);

namespace Apilyser\Resolver;

use PhpParser\Node\Stmt\Namespace_;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Project-aware namespace to file path resolver
 * 
 * This class uses Composer's autoloader to find files in the host project context
 */
class NamespaceResolver {

    private string $rootPath;

    function __construct(public OutputInterface $output, string $rootPath)
    {
        $this->rootPath = $rootPath;
    }

    /**
     * Finds the full namespace for a class by looking at the imports
     * 
     * @param string $className
     * @param string[] $imports
     * 
     * @return string
     */
    public function findFullNamespaceForClass(
        string $className, 
        array $imports,
        ?Namespace_ $currentNamespace = null
    ): string {
        foreach ($imports as $import) {
            $importArr = explode("\\", $import);
            $lastElm = end($importArr);
            if ($lastElm == $className) {
                return $import;
            }
        }

        // If not in imports and we have current namespace context,
        // assume it's in the same namespace
        if ($currentNamespace !== null && $currentNamespace->name !== null) {
            return $currentNamespace->name->toString() . "\\" . $className;
        }

        return $className;
    }
    
    /**
     * Resolve a namespace from any library in the project
     * 
     * @param string $namespace Fully qualified namespace
     * @return string|false File path or false if not found
     */
    function resolveNamespace($namespace) 
    {
        // Remove leading namespace separator if present
        $namespace = ltrim($namespace, '\\');
        
        // Path to composer.json
        $composerJsonPath = rtrim($this->rootPath, DIRECTORY_SEPARATOR) . 
                            DIRECTORY_SEPARATOR . 'composer.json';

        // Check if composer.json exists
        if (!file_exists($composerJsonPath)) {
            $this->output->writeln("Could not find composer for " . $composerJsonPath);
            return false;
        }
        
        // Load composer configuration
        $composerConfig = json_decode(file_get_contents($composerJsonPath), true);
        if (!$composerConfig || !isset($composerConfig['autoload'])) {
            $this->output->writeln("Could not find composer config");
            return false;
        }
        
        // Check PSR-4 autoload configuration
        $psr4Mappings = [];
        
        // Process project's own mappings
        if (isset($composerConfig['autoload']['psr-4'])) {
            foreach ($composerConfig['autoload']['psr-4'] as $prefix => $paths) {
                $prefix = rtrim($prefix, '\\') . '\\';
                if (is_array($paths)) {
                    foreach ($paths as $path) {
                        $psr4Mappings[$prefix][] = $this->rootPath . DIRECTORY_SEPARATOR . $path;
                    }
                } else {
                    $psr4Mappings[$prefix][] = $this->rootPath . DIRECTORY_SEPARATOR . $paths;
                }
            }
        }

        // Process autoload-dev mappings (for tests)
        if (isset($composerConfig['autoload-dev']['psr-4'])) {
            foreach ($composerConfig['autoload-dev']['psr-4'] as $prefix => $paths) {
                $prefix = rtrim($prefix, '\\') . '\\';
                if (is_array($paths)) {
                    foreach ($paths as $path) {
                        $psr4Mappings[$prefix][] = $this->rootPath . DIRECTORY_SEPARATOR . $path;
                    }
                } else {
                    $psr4Mappings[$prefix][] = $this->rootPath . DIRECTORY_SEPARATOR . $paths;
                }
            }
        }
        
        // Process vendor mappings
        $vendorDir = $this->rootPath . DIRECTORY_SEPARATOR . 'vendor';
        $installedJsonPath = $vendorDir . DIRECTORY_SEPARATOR . 'composer' . 
                            DIRECTORY_SEPARATOR . 'installed.json';

        if (file_exists($installedJsonPath)) {
            $installedData = json_decode(file_get_contents($installedJsonPath), true);
            
            // Handle different versions of composer's installed.json format
            $packages = isset($installedData['packages']) ? $installedData['packages'] : $installedData;
            
            foreach ($packages as $package) {
                if (isset($package['autoload']['psr-4'])) {
                    $packagePath = $vendorDir . DIRECTORY_SEPARATOR . $package['name'];
                    
                    foreach ($package['autoload']['psr-4'] as $prefix => $paths) {
                        $prefix = rtrim($prefix, '\\') . '\\';
                        if (is_array($paths)) {
                            foreach ($paths as $path) {
                                $psr4Mappings[$prefix][] = $packagePath . DIRECTORY_SEPARATOR . $path;
                            }
                        } else {
                            $psr4Mappings[$prefix][] = $packagePath . DIRECTORY_SEPARATOR . $paths;
                        }
                    }
                }
            }
        }
        
        // Find the matching namespace prefix
        $matchingPrefix = '';
        $matchingPaths = [];
        
        foreach ($psr4Mappings as $prefix => $paths) {
            if (strpos($namespace . '\\', $prefix) === 0 && strlen($prefix) > strlen($matchingPrefix)) {
                $matchingPrefix = $prefix;
                $matchingPaths = $paths;
            }
        }
        
        if (!$matchingPrefix) {
            return false; // No matching prefix found
        }
        
        // Get relative class name and convert to path
        $relativeClass = substr($namespace, strlen($matchingPrefix));
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        
        // Try each matching path
        foreach ($matchingPaths as $basePath) {
            $fullPath = rtrim($basePath, DIRECTORY_SEPARATOR) . 
                        DIRECTORY_SEPARATOR . $relativePath;
            
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }
        
        return false;
    }
}