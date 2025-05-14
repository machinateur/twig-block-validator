# Twig Block Validator (for Shopware 6)

A twig block validator, inspired by the Shopware 6 IntelliJ plugin's twig block versioning. Pure PHP.

## Inspiration

After visiting SCUC 2025 in Cologne, where it was mentioned and recommended multiple times,
 I held the idea that making twig-block validation possible via CLI would be a great help
  with CI for large shopware projects.

When dealing with many plugins and templates, it can get confusing quite fast.
 Let this tool help you with those twig blocks and validate if they're still up to date with their parent's content.

It also provides a way to automatically add and update the block version comment on your templates.

> This project is still a proof-of-concept. Beta version.
>
> It's missing some guardrails, so edge-cases might still lead to internal errors or bugs.

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

## Usage

This tool may also be used without Shopware, it supports both.

### Examples with Shopware

#### Validate the `tests/res_sw` dir

To validate against the dev-dependency `shopware/storefront:^6.4` installed at `vendor/shopware/storefront/Resources/views`,
 expecting version `6.7` in the comments in `tests/res_sw/` (`__main__` as default namespace), run:

```
$ time bin/shopware twig:block:validate -t '@Storefront:vendor/shopware/storefront/Resources/views' -c tests/res_sw/ -r 6.7

 ! [NOTE] Adding namespace "__main__"...                                                                                

 ! [NOTE] Adding namespace "Storefront"...                                                                              

 ! [NOTE] Loading namespace "__main__".                                                                                 

 1/1 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100% < 1 sec

 ! [NOTE] Collected 1 blocks across 1 templates.                                                                        

 ------------------------------ --------------------------------------- --------------------------- ------------------------------------------------------------------ ---------- ---------- 
  template                       parent template                         block                       hash                                                               version    mismatch  
 ------------------------------ --------------------------------------- --------------------------- ------------------------------------------------------------------ ---------- ---------- 
  @__main__/template.html.twig   @Storefront/storefront/base.html.twig   base_body_skip_to_content   c1954b12f0c43a0244c3d781256b6aa676b5699a9700c10983a468a68a0e5eb1   v6.6.6.0   [x]       
                                                                                                     4820b0cd1d32b5079aa8f3e988bd756bc0c52ea20a694353370b39a8ded2d5ac   6.7                  
 ------------------------------ --------------------------------------- --------------------------- ------------------------------------------------------------------ ---------- ---------- 

> 0.18s user 0.08s system 59% cpu 0.433 total (tested on Mac M1)
```

Here's [the example template](tests/res_sw/template.html.twig) that would produce the above errors:

```twig
{% sw_extends "@Storefront/storefront/base.html.twig" %}

{# shopware-block: c1954b12f0c43a0244c3d781256b6aa676b5699a9700c10983a468a68a0e5eb1@v6.6.6.0 #}
{% block base_body_skip_to_content %}
    My overwrite content
{% endblock %}
```

I just generated a random SHA265 for this test.

It's also possible to use `twig-block` here,
 but since this was inspired by [Shopware's PhpStorm plugin](https://github.com/shopwareLabs/shopware6-phpstorm-plugin), `shopware-block` is also supported.  

In general, the version is recommended, but since this tool is not strictly limited to working with
 shopware, it is not enforced. The provided version (`-r` flag) will be the default version, if none is set for a block.
  If not given, it will try to use the

#### Annotate `@Storefront` itself

The following command will go through the templates of `shopware/storefront`,
 put the annotation comment for those blocks, that extend another template (and therefor have a parent),
  and finally write the changed templates to `./var/cache/twig-block-validator/views`.

```bash
$ bin/shopware twig:block:annotate -c @Storefront:vendor/shopware/storefront/Resources/views \
  -r 6.6.10.3 ./var/cache/twig-block-validator/views

 To annotate the templates in-place can lead to permanent loss of data!
 Continue? (yes/no) [no]:
 > yes

 ! [NOTE] Adding namespace "Storefront"...                                                                              

 ! [NOTE] Loading namespace "Storefront".                                                                               

 324/324 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%  1 sec

 ! [NOTE] Collected 2390 blocks across 324 templates.                                                                   

 ! [NOTE] Loading namespace "Storefront".                                                                               

 324/324 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100% < 1 sec

 ! [NOTE] Found 0 comments in 324 templates.                                                                            
```

> **Note**: As of now, there is no overview of what comments/blocks were added/updated in the process.
>  This will be improved in future versions, as proper events are already in place to facilitate console output.

The result:

```bash
$ tree var/cache/twig-block-validator/views

var/cache/twig-block-validator/views
├── storefront
│   ├── component
│   │   ├── account
│   │   │   ├── customer-group-register-address-form.twig
│   │   │   └── customer-group-register.html.twig
│   │   ├── address
│   │   │   └── address-manager-modal-list.html.twig
│   │   ├── listing
│   │   │   └── filter
│   │   │       └── filter-rating-select.html.twig
│   │   ├── product
│   │   │   ├── card
│   │   │   │   ├── box-image.html.twig
│   │   │   │   └── box-wishlist.html.twig
│   │   │   └── feature
│   │   │       └── types
│   │   │           └── feature-attribute.html.twig
│   │   └── wishlist
│   │       └── listing.html.twig
│   ├── layout
│   │   └── header
│   │       └── header-minimal.html.twig
│   └── page
│       └── account
│           ├── order
│           │   ├── address.html.twig
│           │   └── confirm-shipping.html.twig
│           └── order-history
│               └── cancel-order-modal.html.twig
```

### How to validate

The validation can be performed by calling the CLI command `twig:block:validate`.
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

### How to annotate

The annotation can be performed by calling the CLI command `twig:block:annotate`.
 Here's it's synopsis:

```Usage:
$ bin/twig-block-annotate -h

  twig:block:annotate [options] [--] [<output-path>]

Arguments:
  output-path                      Where to write the annotated templates

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

> Caution! Always back up your templates and use a VCS.
>  A bug or user error can cause permanent loss of data!

### Which CLI to use

There are a total of three different CLIs available:

- `bin/shopware`:
  A shopware integrated CLI.
  - Runs a full shopware-aware CLI (in `dev` env), which supports all built-in twig extensions.
  - If used inside a project directly as a bundle, it supports any custom functions, blocks, etc.
- `bin/console`:
  A symfony CLI application.
  - Runs a symfony kernel with symfony framework and debug commands (in `dev` or `test`).
  - Only supports the twig extensions that are available in the symfony context.
- `bin/twig-block-validate`:
  - Runs only the `twig:block:validate` command as standalone application.
  - Only supports the twig extensions that are available in the symfony context.
- `bin/twig-block-annotate`:
  - Runs only the `twig:block:annotate` command as standalone application.
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

## License

It's MIT.
