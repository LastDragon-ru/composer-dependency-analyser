<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Config\Ignore;

use LogicException;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\SymbolKind;
use function array_fill_keys;
use function preg_match;
use function strpos;

class IgnoreList
{

    /**
     * @var array<ErrorType::*, bool>
     */
    private $ignoredErrors;

    /**
     * @var array<string, array<ErrorType::*, bool>>
     */
    private $ignoredErrorsOnPath = [];

    /**
     * @var array<string, array<ErrorType::*, bool>>
     */
    private $ignoredErrorsOnPackage = [];

    /**
     * @var array<string, array<string, array<ErrorType::*, bool>>>
     */
    private $ignoredErrorsOnPackageAndPath = [];

    /**
     * @var array<string, bool>
     */
    private $ignoredUnknownClasses;

    /**
     * @var array<string, bool>
     */
    private $ignoredUnknownClassesRegexes;

    /**
     * @var array<string, bool>
     */
    private $ignoredUnknownFunctions;

    /**
     * @var array<string, bool>
     */
    private $ignoredUnknownFunctionsRegexes;

    /**
     * @param list<ErrorType::*> $ignoredErrors
     * @param array<string, list<ErrorType::*>> $ignoredErrorsOnPath
     * @param array<string, list<ErrorType::*>> $ignoredErrorsOnPackage
     * @param array<string, array<string, list<ErrorType::*>>> $ignoredErrorsOnPackageAndPath
     * @param list<string> $ignoredUnknownClasses
     * @param list<string> $ignoredUnknownClassesRegexes
     * @param list<string> $ignoredUnknownFunctions
     * @param list<string> $ignoredUnknownFunctionsRegexes
     */
    public function __construct(
        array $ignoredErrors,
        array $ignoredErrorsOnPath,
        array $ignoredErrorsOnPackage,
        array $ignoredErrorsOnPackageAndPath,
        array $ignoredUnknownClasses,
        array $ignoredUnknownClassesRegexes,
        array $ignoredUnknownFunctions,
        array $ignoredUnknownFunctionsRegexes
    )
    {
        $this->ignoredErrors = array_fill_keys($ignoredErrors, false);

        foreach ($ignoredErrorsOnPath as $path => $errorTypes) {
            $this->ignoredErrorsOnPath[$path] = array_fill_keys($errorTypes, false);
        }

        foreach ($ignoredErrorsOnPackage as $packageName => $errorTypes) {
            $this->ignoredErrorsOnPackage[$packageName] = array_fill_keys($errorTypes, false);
        }

        foreach ($ignoredErrorsOnPackageAndPath as $packageName => $paths) {
            foreach ($paths as $path => $errorTypes) {
                $this->ignoredErrorsOnPackageAndPath[$packageName][$path] = array_fill_keys($errorTypes, false);
            }
        }

        $this->ignoredUnknownClasses = array_fill_keys($ignoredUnknownClasses, false);
        $this->ignoredUnknownClassesRegexes = array_fill_keys($ignoredUnknownClassesRegexes, false);
        $this->ignoredUnknownFunctions = array_fill_keys($ignoredUnknownFunctions, false);
        $this->ignoredUnknownFunctionsRegexes = array_fill_keys($ignoredUnknownFunctionsRegexes, false);
    }

    /**
     * @return list<UnusedErrorIgnore|UnusedSymbolIgnore>
     */
    public function getUnusedIgnores(): array
    {
        $unused = [];

        foreach ($this->ignoredErrors as $errorType => $ignored) {
            if (!$ignored) {
                $unused[] = new UnusedErrorIgnore($errorType, null, null);
            }
        }

        foreach ($this->ignoredErrorsOnPath as $path => $errorTypes) {
            foreach ($errorTypes as $errorType => $ignored) {
                if (!$ignored) {
                    $unused[] = new UnusedErrorIgnore($errorType, $path, null);
                }
            }
        }

        foreach ($this->ignoredErrorsOnPackage as $packageName => $errorTypes) {
            foreach ($errorTypes as $errorType => $ignored) {
                if (!$ignored) {
                    $unused[] = new UnusedErrorIgnore($errorType, null, $packageName);
                }
            }
        }

        foreach ($this->ignoredErrorsOnPackageAndPath as $packageName => $paths) {
            foreach ($paths as $path => $errorTypes) {
                foreach ($errorTypes as $errorType => $ignored) {
                    if (!$ignored) {
                        $unused[] = new UnusedErrorIgnore($errorType, $path, $packageName);
                    }
                }
            }
        }

        foreach ($this->ignoredUnknownClasses as $class => $ignored) {
            if (!$ignored) {
                $unused[] = new UnusedSymbolIgnore($class, false, SymbolKind::CLASSLIKE);
            }
        }

        foreach ($this->ignoredUnknownClassesRegexes as $regex => $ignored) {
            if (!$ignored) {
                $unused[] = new UnusedSymbolIgnore($regex, true, SymbolKind::CLASSLIKE);
            }
        }

        foreach ($this->ignoredUnknownFunctions as $function => $ignored) {
            if (!$ignored) {
                $unused[] = new UnusedSymbolIgnore($function, false, SymbolKind::FUNCTION);
            }
        }

        foreach ($this->ignoredUnknownFunctionsRegexes as $regex => $ignored) {
            if (!$ignored) {
                $unused[] = new UnusedSymbolIgnore($regex, true, SymbolKind::FUNCTION);
            }
        }

        return $unused;
    }

    public function shouldIgnoreUnknownClass(string $class, string $filePath): bool
    {
        $ignoredGlobally = $this->shouldIgnoreErrorGlobally(ErrorType::UNKNOWN_CLASS);
        $ignoredByPath = $this->shouldIgnoreErrorOnPath(ErrorType::UNKNOWN_CLASS, $filePath);
        $ignoredByRegex = $this->shouldIgnoreUnknownClassByRegex($class);
        $ignoredByBlacklist = $this->shouldIgnoreUnknownClassByBlacklist($class);

        return $ignoredGlobally || $ignoredByPath || $ignoredByRegex || $ignoredByBlacklist;
    }

    public function shouldIgnoreUnknownFunction(string $function, string $filePath): bool
    {
        $ignoredGlobally = $this->shouldIgnoreErrorGlobally(ErrorType::UNKNOWN_FUNCTION);
        $ignoredByPath = $this->shouldIgnoreErrorOnPath(ErrorType::UNKNOWN_FUNCTION, $filePath);
        $ignoredByRegex = $this->shouldIgnoreUnknownFunctionByRegex($function);
        $ignoredByBlacklist = $this->shouldIgnoreUnknownFunctionByBlacklist($function);

        return $ignoredGlobally || $ignoredByPath || $ignoredByRegex || $ignoredByBlacklist;
    }

    private function shouldIgnoreUnknownClassByBlacklist(string $class): bool
    {
        if (isset($this->ignoredUnknownClasses[$class])) {
            $this->ignoredUnknownClasses[$class] = true;
            return true;
        }

        return false;
    }

    private function shouldIgnoreUnknownFunctionByBlacklist(string $function): bool
    {
        if (isset($this->ignoredUnknownFunctions[$function])) {
            $this->ignoredUnknownFunctions[$function] = true;
            return true;
        }

        return false;
    }

    private function shouldIgnoreUnknownClassByRegex(string $class): bool
    {
        foreach ($this->ignoredUnknownClassesRegexes as $regex => $ignoreUsed) {
            $matches = preg_match($regex, $class);

            if ($matches === false) {
                throw new LogicException("Invalid regex: '$regex'");
            }

            if ($matches === 1) {
                $this->ignoredUnknownClassesRegexes[$regex] = true;
                return true;
            }
        }

        return false;
    }

    private function shouldIgnoreUnknownFunctionByRegex(string $function): bool
    {
        foreach ($this->ignoredUnknownFunctionsRegexes as $regex => $ignoreUsed) {
            $matches = preg_match($regex, $function);

            if ($matches === false) {
                throw new LogicException("Invalid regex: '$regex'");
            }

            if ($matches === 1) {
                $this->ignoredUnknownFunctionsRegexes[$regex] = true;
                return true;
            }
        }

        return false;
    }

    /**
     * @param ErrorType::SHADOW_DEPENDENCY|ErrorType::UNUSED_DEPENDENCY|ErrorType::DEV_DEPENDENCY_IN_PROD|ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV $errorType
     */
    public function shouldIgnoreError(string $errorType, ?string $realPath, ?string $packageName): bool
    {
        $ignoredGlobally = $this->shouldIgnoreErrorGlobally($errorType);
        $ignoredByPath = $realPath !== null && $this->shouldIgnoreErrorOnPath($errorType, $realPath);
        $ignoredByPackage = $packageName !== null && $this->shouldIgnoreErrorOnPackage($errorType, $packageName);
        $ignoredByPackageAndPath = $realPath !== null && $packageName !== null && $this->shouldIgnoreErrorOnPackageAndPath($errorType, $packageName, $realPath);

        return $ignoredGlobally || $ignoredByPackageAndPath || $ignoredByPath || $ignoredByPackage;
    }

    /**
     * @param ErrorType::* $errorType
     */
    private function shouldIgnoreErrorGlobally(string $errorType): bool
    {
        if (isset($this->ignoredErrors[$errorType])) {
            $this->ignoredErrors[$errorType] = true;
            return true;
        }

        return false;
    }

    /**
     * @param ErrorType::* $errorType
     */
    private function shouldIgnoreErrorOnPath(string $errorType, string $filePath): bool
    {
        foreach ($this->ignoredErrorsOnPath as $path => $errorTypes) {
            if ($this->isFilepathWithinPath($filePath, $path) && isset($errorTypes[$errorType])) {
                $this->ignoredErrorsOnPath[$path][$errorType] = true;
                return true;
            }
        }

        return false;
    }

    /**
     * @param ErrorType::* $errorType
     */
    private function shouldIgnoreErrorOnPackage(string $errorType, string $packageName): bool
    {
        if (isset($this->ignoredErrorsOnPackage[$packageName][$errorType])) {
            $this->ignoredErrorsOnPackage[$packageName][$errorType] = true;
            return true;
        }

        return false;
    }

    /**
     * @param ErrorType::* $errorType
     */
    private function shouldIgnoreErrorOnPackageAndPath(string $errorType, string $packageName, string $filePath): bool
    {
        if (isset($this->ignoredErrorsOnPackageAndPath[$packageName])) {
            foreach ($this->ignoredErrorsOnPackageAndPath[$packageName] as $path => $errorTypes) {
                if ($this->isFilepathWithinPath($filePath, $path) && isset($errorTypes[$errorType])) {
                    $this->ignoredErrorsOnPackageAndPath[$packageName][$path][$errorType] = true;
                    return true;
                }
            }
        }

        return false;
    }

    private function isFilepathWithinPath(string $filePath, string $path): bool
    {
        return strpos($filePath, $path) === 0;
    }

}
