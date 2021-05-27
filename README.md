# On Page &reg; Laravel plugin

This package implements all the OnPage data and data structure in any Laravel application.
All the CLI command have to be execute at your Laravel project main directory.

## Installation

Add the repository to your composer file and install the package:
```bash
composer config repositories.repo-name vcs 'https://github.com/onpage-dev/laravel-plugin.git'
composer require onpage-dev/laravel-plugin:^v1
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

By default, the plugin will create a `onpage-models` directory in your base directory.
The folder will be filled with On Page generated models, which you should not modify, as they are generated automatically.
By default, the models will live in the Data namespace, so you can access them using `\Data\ModelName::...`.
To make this work, you need to instruct composer to preload this folder.
```json
{
  ...
  "autoload": {
    "psr-4": {
      ...
      "Data\\": "onpage-models"
    },
  }
}
```

Finally make sure to update composer.
```bash
composer dumpautoload
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

__Useless import prevention:__
If you try to import same data you already have, the import is stopped. You can use the `--anyway` flag to ignore this warning.
```bash
php artisan onpage:import --anyway # Not recommended
```

## Restore a previous snapshot
Each time you import data, the snapshot is saved locally in your Laravel project.
If you want to restore a previous snapshot execute the rollback command and digit the number associated at the snapshot choosen.

```bash
php artisan onpage:rollback
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

## Getting values
Once you get your records, you need to display the related data.
To access field data for a record, you need to use the `->val($field_name, $lang)` function.
The `$lang` is set to use the current default language.
Examples:
```php
$product->val('name') // Icecream
$product->val('name', 'it') // Gelato
```

### Multivalue fields
For fields which contain multiple values, the `val` function will always return a collection of the values:
```php
echo $product->val('descriptions')->first();
// ... or
foreach ($product->val('descriptions') as $descr) {
    echo "- $descr\n";
}
```

### File and image fields
For these files, the returned value will be an instance of `\OnPage\File::class`.
To get a file or image url use the `->link()` function. The link will point to the original file.

```php
# original size
$product->val('specsheet')->name // icecream-spec.pdf
$product->val('specsheet')->token // R417C0YAM90RF
$product->val('specsheet')->link() // https://acme-inc.onpage.it/api/storage/R417C0YAM90RF?name=icecream-spec.pdf
```

To turn images into a thumbnail add an array of options as shown below:
```php
# maintain proportions width 200px
$product->val('cover_image')->link(['x' => 200])

# maintain proportions height 100px
$product->val('cover_image')->link(['y' => 100])

# crop image to width 200px and height 100px
$product->val('cover_image')->link(['x' => 200, 'y' => 100])

# maintain proportions and contain in a rectangle of width 200px and height 100px 
$product->val('cover_image')->link(['x' => 200, 'y' => 100, 'contain' => true])

# convert the image to png (default is jpg)
$product->val('cover_image')->link(['x' => 200, 'format' => 'png'])
```
