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
| path          | path to the code |
| openApiPath  | path to the open api documentation yaml file

## Usage
You can run the simple validation command by typing<br />
```./vendor/bin/apilyser validate```

This command will analyse both the OpenApi dokumentation and the project code and run the alignment rules.

## Contribution
Contributions are very welcome!<br />
For major changes, please open an issue to discuss first. Otherwise feel free to create pull requests.