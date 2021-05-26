# On Page &reg; Laravel plugin

This package implements all the OnPage data and data structure in any Laravel application.
All the CLI command have to be execute at your Laravel project main directory.

## Installation

Add the repository to your composer file and install the package:
```bash
composer config repositories.repo-name vcs 'https://github.com/onpage-dev/laravel-plugin.git'
composer require onpage-dev/laravel-plugin
```
Publish the configuration file
```bash
php artisan vendor:publish --provider 'OnPage\OnPageServiceProvider'
```
Run plugin migrations (we use the `op_*` prefix for our tables)
```bash
php artisan migrate
```


## Configuration

1. Go to your On Page and generate a new snapshot from the "Snapshot Generator" section
2. Copy the Snapshot Generator API token
3. Add the following to your `.env` file:
    ```bash
    ONPAGE_COMPANY=acme-inc
    ONPAGE_TOKEN=SNAPSHOT-GENERATOR-API-TOKEN
    ```

## Import data
To import your data execute this command:
```bash
php artisan onpage:import
```

__Error prevention:__
If some resources or fields have been removed or changed, the import will prompt you whether you want to continue or not. You can use the `--force` flag to ignore this warning.
```bash
php artisan onpage:import --force # Not recommended
```



## Restore a previous snapshot

```bash
$ php artisan onpage:rollback
```




## Querying data
Because the plugin does not actually generate tables and columns corresponding for your data, you will have to use the `whereField` function instead of the `where` clause, which works in the same manner.
If you have trouble doing some operations, please open an issue explaining your use case.
```php
# If the description field is translatable, the query will run on the current locale language
\Data\Products::whereField('code', 'AT-1273')->first();

# By default, the filter will be applied on the current locale language
\Data\Products::whereField('description', 'like', '%icecream%')->get();

# You force the filter to search for values in a specific language
\Data\Products::whereField('description.it', 'like', '%gelato%')->paginate();

# To query relations, you can use the standard whereHas laravel function
\Data\Products::whereHas('categories', function($q) {
    $q->whereField('is_visible', true);
})->get();

# If you have a file field, you can query by token and by name
\Data\Products::whereField('image:name', 'gelato.jpg')->get();
\Data\Products::whereField('image:token', '79YT34R8798FG7394N')->get();

# For dimension fields (dim2 and dim3) you can use both the x,y,z selectors, or the 0,1,2 selectors
\Data\Products::whereField('dimension:x', '>', 100)->get();
// ... is the same as
\Data\Products::whereField('dimension:0', '>', 100)->get();
```


## Files and thumbnails

