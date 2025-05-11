# Twig Block Version Validator for Shopware 6 (Beaver)

When dealing with many plugins and twig blocks, it can get confusing quite fast.
 Let *beaver* help you gnaw away those twig blocks and validate if they're still up to date with their parents.

This project is a pure PHP implementation and twig integration of the Shopware 6 IntelliJ plugin's twig template-hash feature.

> This project is a proof-of-concept at the moment.

This tool may also be used without Shopware, it supports both.

## Example with Shopware

To validate against the dev-dependency `shopware/storefront:^6.4` installed at `vendor/shopware/storefront/Resources/views`,
 expecting version `6.7` in the comments in `tests/res/` (`__main__` as default namespace), run:

```
$ bin/shopware-beaver twig:block:validate -t "@Storefront:vendor/shopware/storefront/Resources/views" -c "tests/res/" -r 6.7

 ! [NOTE] Adding namespace "__main__" with paths:                                                                       

 * tests/res/

 ! [NOTE] Adding namespace "Storefront" with paths:                                                                     

 * vendor/shopware/storefront/Resources/views

 ! [NOTE] Loading namespace "__main__" files:                                                                           

 * template.html.twig

Analysis result
===============

 ------------------------------ ----------------------------------------------------- --------------------------- ------------------------------------------------------------------ --------- ---------- 
  template                       parent template                                       block                       hash                                                               version   mismatch  
 ------------------------------ ----------------------------------------------------- --------------------------- ------------------------------------------------------------------ --------- ---------- 
  @__main__/template.html.twig   @Storefront/storefront/page/account/index.html.twig   page_account_main_content   c46e2748def26f1ff33af5eb04a9732fe2c2824f6fdf0aa98c94104b6afee48d   v6.6.0    [x]       
                                                                                                                   ee173de4df62556b65c720ab394292fbcd8d4afaf6724885ba70c651ef5c57d0   6.7                 
 ------------------------------ ----------------------------------------------------- --------------------------- ------------------------------------------------------------------ --------- ---------- 
```

Here's [the example template](tests/res/template.html.twig) that would produce the above errors:

```twig
{% sw_extends '@Storefront/storefront/page/account/index.html.twig' %}

{# shopware-block: c46e2748def26f1ff33af5eb04a9732fe2c2824f6fdf0aa98c94104b6afee48d@v6.6.0 #}
{% block page_account_main_content %}

    Test content
{% endblock %}
```

I just generated a random SHA265 for this test.

It's also possible to use `twig-block` here,
 but since this was inspired by [Shopware's PhpStorm plugin](https://github.com/shopwareLabs/shopware6-phpstorm-plugin), `shopware-block` is also supported.  

In general, the version is recommended, but since this tool is not strictly limited to working with
 shopware, it is not enforced and the provided version (`-r` flag) will be the default version, if none is set for a block.

## Installation

Requirements:
- PHP `8.2` or above
- Twig version `^3.15`
- At least Shopware `6.4` (optional)

In your shopware project or plugin, run:

```bash
composer require --dev machinateur/twig-block-validator@dev
```

Also make sure the bundle is available in the desired environments, usually `dev` and `test`. So in `config/bundles.php`:

```php
// ...
    Machinateur\Shopware\TwigBlockValidator\TwigBlockValidatorBundle::class => ['dev' => true, 'test' => true],
```

## How to validate

The validation can be performed by calling the CLI or command (`twig:block:validate`).
 Here's it's synopsis:

```
$ bin/twig-block-validate -h

Usage:
  twig:block:validate [options]

Options:
  -t, --template=TEMPLATE          Twig template path to load (multiple values allowed)
  -c, --validate=VALIDATE          Twig template path to validate (multiple values allowed)
  -r, --use-version[=USE-VERSION]  The version number required
  -h, --help                       Display help for the given command. When no command is given display help for the list command
      --silent                     Do not output any message
  -q, --quiet                      Only errors are displayed. All other output is suppressed
  -V, --version                    Display this application version
      --ansi|--no-ansi             Force (or disable --no-ansi) ANSI output
  -n, --no-interaction             Do not ask any interactive question
  -e, --env=ENV                    The Environment name. [default: "dev"]
      --no-debug                   Switch off debug mode.
      --profile                    Enables profiling (requires debug).
  -v|vv|vvv, --verbose             Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

### Which CLI to use

There are a total of three different CLIs available:

- `bin/shopware-beaver`:
  A shopware integrated CLI.
  - Runs a full shopware-aware CLI (in `dev` env), which supports all built-in twig extensions.
  - If used inside a project directly as a bundle, it supports any custom functions, blocks, etc.
- `bin/console`:
  A symfony CLI application.
  - Runs a symfony kernel with symfony framework and debug commands (in `dev` or `test`).
  - Only supports the twig extensions that are available in the symfony context.
- `bin/twig-block-validate`
  - Runs only the `twig:block:validate` command as standalone application.
  - Only supports the twig extensions that are available in the symfony context.

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

## License

It's MIT.
