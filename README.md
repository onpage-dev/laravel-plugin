# OnPage-laravel-plugin

This package implements all the OnPage data and data structure in any Laravel application.
All the CLI command have to be execute at your laravel project main directory.

##Installation



- Copy this repository in your existing laravel project into path ```packages/onpage/```

- From command line execute
    ```shell
    $ composer require onpage/laravel
    $ php artisan vendor:publish --provider 'OnPage\OnPageServiceProvider'
    $ php artisan migrate
    ```
    Your file will be copied to the specified publish location and database tables will be inizialized.


##Configuration

- Go to {MYCOMPANY}.onpage.it and generate a new snapshot or select one already created.

- Copy the API Token from the preview section. {APITOKEN}

- In your laravel application directory find the file .env and add to it this two line (replacing the rispective values).
    ```
    ONPAGE_COMPANY={MYCOMPANY}
    ONPAGE_TOKEN={APITOKEN}
    ```

##Import

First import

```shell
$ php artisan onpage:import
```

If you already imported some data and execute this command you'll be notified if any important object will be deleted or updated.

```diff
--DANGER--
- The following Resources will be deleted:
-["famiglie","varianti","dimensioni","ambienti","spessori","disponibilit_","colori","texture","granulometria","fondo","paese","materiale","certificazioni"]
/fsdfsd
@@ dfsdf
+ ddf

```





##Restore a previous snapshot

```shell
$ php artisan onpage:rollback
```
