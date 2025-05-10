# Twig Block Version Validator for Shopware 6 (Beaver)

When dealing with many plugins and twig blocks, it can get confusing quite fast.
 Let *beaver* help you gnaw away those twig blocks and validate if they're still up to date with their parents.

This project is a pure PHP implementation and twig integration of the Shopware 6 IntelliJ plugin's twig template-hash feature.

> This project is a proof-of-concept at the moment.

## Example

```bash
bin/shopware shopware:twig-block:validate -t @Storefront:vendor/shopware/storefront/Resources/views -c tests/res/ -r 6.7

Analysis result
===============

 ------------------------------ ----------------------------------------------------- --------------------------- ------------------------------------------------------------------ --------- ---------- 
  template                       parent template                                       block                       hash                                                               version   mismatch  
 ------------------------------ ----------------------------------------------------- --------------------------- ------------------------------------------------------------------ --------- ---------- 
  @__main__/template.html.twig   @Storefront/storefront/page/account/index.html.twig   page_account_main_content   c46e2748def26f1ff33af5eb04a9732fe2c2824f6fdf0aa98c94104b6afee48d   v6.6.0    [x]       
                                                                                                                   ee173de4df62556b65c720ab394292fbcd8d4afaf6724885ba70c651ef5c57d0   6.7                 
 ------------------------------ ----------------------------------------------------- --------------------------- ------------------------------------------------------------------ --------- ---------- 
```

## Installation

Requirements:
- PHP `8.2` or above
- At least Shopware `6.4`
- Twig version `^3.15`

In your shopware project or plugin, run:

```bash
composer require --dev machinateur/twig-block-validator
```

Also make sure the bundle is available in the desired environments, usually `dev` and `test`. So in `config/bundles.php`:

```php
// ...
    Machinateur\Shopware\TwigBlockValidator\TwigBlockValidatorBundle::class => ['dev' => true, 'test' => true],
```

## Standalone

> Note: The phar is a planned feature.

The tool can be used as standalone, phar or source, for example in CI pipelines:

```
git clone https://github.com/machinateur/twig-block-validator.git
```

PHP is required. The source archive will need [composer](https://getcomposer.org/) to be available
 and may also be used instead of `git clone`, when not available in your environment.

Find the source archive and prebuilt phar attached to
 the [latest release](https://github.com/machinateur/twig-block-validator/releases).

## TODO

- Still needs verification if the content is correctly hashed
- Memory?
  - Use profiler or stopwatch
- Improve independency from shopware version
  - This is needed in order to support standalone
