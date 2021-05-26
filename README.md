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
Each time you import data, the snapshot is saved locally in your Laravel project.
If you want to restore a previous snapshot execute the rollback command and digit the number associated at the snapshot choosen.

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

# To query relations, you can use the standart whereHas laravel function
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

## Images and thumbnails


<!-- To generate image url use the `link` function.

```php
# original size
$product->val('gelato.jpg')->link()
``` -->

To generate image url use the `link` function.
If you to turn in into a thumbnail add an arry of option accordly with these examples.

```php
# original size
$product->val('gelato.jpg')->link()

# maintain proportions width 200px
$product->val('gelato.jpg')->link(['x' => 200])

# maintain proportions height 100px
$product->val('gelato.jpg')->link(['y' => 100])

# crop image to width 200px and height 100px
$product->val('gelato.jpg')->link(['x' => 200, 'y' => 100])

# maintain proportions and contain in a rectangle of width 200px and height 100px 
$product->val('gelato.jpg')->link(['x' => 200, 'y' => 100, 'contain' => true])
```
