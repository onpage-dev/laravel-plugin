# On Page &reg; Laravel plugin

This package implements all the OnPage data and data structure in any Laravel application.
All the CLI command have to be execute at your Laravel project main directory.

## Installation

- Copy this repository in your existing Laravel project into path `packages/onpage/`

- From command line execute
    ```bash
    $ composer require onpage/laravel
    $ php artisan vendor:publish --provider 'OnPage\OnPageServiceProvider'
    $ php artisan migrate
    ```
    Your file will be copied to the specified publish location and database tables will be inizialized.


## Configuration

- Go to your On Page and generate a new snapshot or select one already created. 

- Copy the snapshot generator token from the preview section.

- In your Laravel application directory find the file `.env` and add to it this two line.
    ```bash
    ONPAGE_COMPANY=acme-inc
    ONPAGE_TOKEN=MYSECRETTOKEN
    ```

## Import

For import your data execute this command


```bash
$ php artisan onpage:import
```

If you already imported some data and execute this command you'll be notified if any important object will be deleted or updated and your are asked if you want proceed anyway.

```
--DANGER--
 The following Resources will be deleted:
-["famiglie","varianti","dimensioni","ambienti","spessori","disponibilit_","colori","texture","granulometria","fondo","paese","materiale","certificazioni"]

Do you want to proceed? (y/N):
```

Yuo can use --force flag for ski



## Restore a previous snapshot

```bash
$ php artisan onpage:rollback
```




## Filter data example

```php
$var = \Data\Famiglie::whereHas('varianti', function($q) {
    $q->whereField('descrizione_seo.it', 'like', '%quarzo%');
})
```

## File token flags

