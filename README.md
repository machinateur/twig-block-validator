# Twig Block Version Validator for Shopware 6

A twig implementation/integration of the Shopware 6 IntelliJ plugin's twig template-hash feature.

> This project is a proof-of-concept at the moment.

## Example

```bash
bin/shopware shopware:twig-block:validate -c @Storefront:vendor/shopware/storefront/Resources/views -c tests/res/ -r 6.7

Analysis result
===============

 ------------------------------ ----------------------------------------------------- --------------------------- ------------------------------------------------------------------ --------- ---------- 
  template                       parent template                                       block                       hash                                                               version   mismatch  
 ------------------------------ ----------------------------------------------------- --------------------------- ------------------------------------------------------------------ --------- ---------- 
  @__main__/template.html.twig   @Storefront/storefront/page/account/index.html.twig   page_account_main_content   c46e2748def26f1ff33af5eb04a9732fe2c2824f6fdf0aa98c94104b6afee48d   v6.6.0    [x]       
                                                                                                                   ee173de4df62556b65c720ab394292fbcd8d4afaf6724885ba70c651ef5c57d0   6.7                 
 ------------------------------ ----------------------------------------------------- --------------------------- ------------------------------------------------------------------ --------- ---------- 

```

## TODO

- Still needs verification if the content is correctly hashed
- Content is currently selected by line, not specifically to the actual text content
- Multi-level inheritance for blocks will not resolve correctly
- Memory?
  - Use profiler or stopwatch
- Disable debug output based on verbosity
  - Make sure access to `$output` is done similarly
- 
