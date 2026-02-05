<?php declare(strict_types=1);

namespace Apilyser\Parser;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileScanner
{

    private string $regex = '#(?<!/)\.php$|^[^\.]*$#i';
    private RecursiveDirectoryIterator $recursiveDirectory;
    private RecursiveIteratorIterator $directoryIterator;

    public string $folderRoot;

    public function __construct(string $folderRoot) {
        $this->folderRoot = $folderRoot;
    }

    /**
     * @return string[]
     */
    public function getFiles(?string $folderPath = null): array {
        $fullPath = $folderPath ? $this->folderRoot . "/" . $folderPath : $this->folderRoot;
        $this->recursiveDirectory = new RecursiveDirectoryIterator($fullPath , RecursiveDirectoryIterator::SKIP_DOTS);
        $this->directoryIterator = new RecursiveIteratorIterator($this->recursiveDirectory);

        // Array of files to return
        $files = [];

        foreach ($this->directoryIterator as $pathInfo) {
            $path = $pathInfo->getPathname();
            $pathDirs = explode("/", $path);
            $lastPath = end($pathDirs);
            if (preg_match($this->regex, $lastPath)) {
                array_push($files, $path);
            }
        }

        return $files;
    }
}
