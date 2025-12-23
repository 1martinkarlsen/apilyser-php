# Apilyser php
A PHP static analysis tool to discover misalignment between the code and the OpenApi documentation

## Getting started

### Installation
Install using composer<br />
The library is still under development

### Configuration
After installation, a `apilyzer.yaml` file is required for the library to work.<br />

| Configuration | description      |
| ------------- | ---------------- |
| codePath      | path to the code |
| openApiPath   | path to the open api documentation yaml file |

## Usage
You can run the simple validation command by typing<br />
```./vendor/bin/apilyser validate```

This command will analyse both the OpenApi dokumentation and the project code and run the alignment rules.

### Add custom routing parsers.
Apilyser allows you to add custom routing parsers to support different routing systems or frameworks.

To add a custom parser, create a class that implements the `RouteStrategy` interface and add it to your `apilyser.yaml` configuration:
```yaml
codePath: src/
openApiPath: openapi.yaml
customRouteParser:
  - 'App\ApilyserExtensions\MyCustomParser'
  - 'App\ApilyserExtensions\AnotherParser'
```

Your custom parser class should implement the `RouteStrategy` interface and be autoloaded in your project.

Example custom parser:
```php
namespace App\ApilyserExtensions;

use Apilyser\Parser\Route\RouteStrategy;

class MyCustomParser implements RouteStrategy
{
    public function canHandle(string $rootPath): bool
    {
        return true;
    }

    public function parseRoutes(string $rootPath): array
    {
        return $routes;
    }
}
```

## Contribution
Contributions are very welcome!<br />
For major changes, please open an issue to discuss first. Otherwise feel free to create pull requests.