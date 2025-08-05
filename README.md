# On Page &reg; Laravel plugin

This package implements all the OnPage data and data structure in any Laravel application.
All the CLI command have to be execute at your Laravel project main directory.

## Installation

Add the repository to your composer file and install the package:
```bash
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

### Upgrade
Run plugin migrations (we use the `op_*` prefix for our tables)
```bash
composer upgrade onpage-dev/laravel-plugin
```



## Configuration

1. Go to your On Page and generate a new snapshot from the "Snapshot Generator" section
2. Copy the Snapshot Generator API token
3. Add the following to your `.env` file:
    ```bash
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

__Error prevention__
If some resources or fields have been removed or changed, the import will prompt you whether you want to continue or not. You can use the `--force` flag to ignore this warning.
```bash
php artisan onpage:import --force # Not recommended
```

__Useless import prevention__
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
// Search by native fields (id, created_at, updated_at, order)
\Data\Products::where('id', 123123)->first();

// For other fields, use the `whereField` function
\Data\Products::whereField('code', 'AT-1273')->first();

// By default, the filter will be applied on the current locale language
\Data\Products::whereField('description', 'like', '%icecream%')->get();

// You force the filter to search for values in a specific language
\Data\Products::whereField('description.it', 'like', '%gelato%')->paginate();

// To query relations, you can use the standard whereHas laravel function
\Data\Products::whereHas('categories', function($q) {
    $q->whereField('is_visible', true);
})->get();

// If you have a file field, you can query by token and by name
\Data\Products::whereField('image:name', 'gelato.jpg')->get();
\Data\Products::whereField('image:token', '79YT34R8798FG7394N')->get();

// For dimension fields (dim2 and dim3) you can use both the x,y,z selectors, or the 0,1,2 selectors
\Data\Products::whereField('dimension:x', '>', 100)->get();
// ... is the same as
\Data\Products::whereField('dimension:0', '>', 100)->get();
```


__Advanced whereField clauses:__

If you need to query data with advanced `where*` clauses, you have to use the advanced `whereField*` functions.


```php

// Prefix 'or' for expand the results to another condition
\Data\Products::whereField('name','icecream')->orWhereField('kcal','>',200)->get()

// Suffix 'Not' for negative query
\Data\Products::whereFieldNot('calories','like','low calorie')->get()     

// Suffix 'In' for search in an array of values
\Data\Products::whereFieldIn('code',['4','8','15','16','23','42'])->get();
```

You can also combine them to obtain more advanced clauses, which work in the same manner.


```php
$q->orWhereFieldNot(...)     
$q->orWhereFieldIn(...)   
$q->whereFieldNotIn(...)  
$q->orWhereFieldNotIn(...)
```



## Getting values
Once you get your records, you need to display the related data.
To access field data for a record, you need to use the `->val($field_name, $lang)` function.
The `$lang` is set to use the current default language.
Examples:
```php
// Native field values:
$product->id // 123123
$product->created_at // 2022-01-01 00:00:00
$product->updated_at // 2023-02-03 01:30:00

// Field values:
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
// Original size
$product->val('specsheet')->name // Icecream-spec.pdf
$product->val('specsheet')->token // R417C0YAM90RF
$product->val('specsheet')->link() // https://acme-inc.onpage.it/api/storage/R417C0YAM90RF?name=icecream-spec.pdf
```

To turn images into a thumbnail add an array of options as shown below:
```php
// Maintain proportions width 200px
$product->val('cover_image')->link(['x' => 200])

// Maintain proportions height 100px
$product->val('cover_image')->link(['y' => 100])

// Crop image to width 200px and height 100px
$product->val('cover_image')->link(['x' => 200, 'y' => 100])

// Maintain proportions and contain in a rectangle of width 200px and height 100px 
$product->val('cover_image')->link(['x' => 200, 'y' => 100, 'contain' => true])

// Convert the image to png (default is jpg)
$product->val('cover_image')->link(['x' => 200, 'ext' => 'png'])
```


## Getting Resources and Fields

```php

// Get a resource definition by name:
$prod_res = \Onpage\resource('prodotti') // Returns \OnPage\Resource::class
$prod_res->label; // "Prodotti"
$prod_res->name; // "products"
$prod_res->labels; // [ 'it' => 'Prodotti', 'en' => 'Products' ]

// Get all fields available for this resource:
$weight_field = $prod_res->field('weight'); // Returns \OnPage\Field::class or null
$weight_field->label; // "Peso"
$weight_field->name; // "weight"
$weight_field->type; // "real"
$weight_field->getUnit(); // "kg"
$weight_field->labels; // [ 'it' => 'Peso', 'en' => 'Weight' ]
$weight_field->descriptions; // 'Rappresenta il peso espresso in kg'
$weight_field->description; // [ 'it' => 'Rappresenta il peso espresso in kg', 'en' => 'Represents the weight expressed in kg' ]


// Get all resource fields:
$prod_res->fields // Collection of \OnPage\Field::class

// Print all fields of the products resource
foreach($products->fields as $field) {
  echo "- $field->label (type: $field->type)\n"
}


````


## Using Fields Folders

```php

$prod_res = \Onpage\resource('prodotti') // Returns \OnPage\Resource::class

$prod_res->field_folders; // Collection of \OnPage\FieldFolders::class

$field_folder = $prod_res->field_folders->first() // get the first field folder
$field_folder->label; // "Folder1"

$field_folder->fields; // Collection of \OnPage\Field::class

$prod_res->things->first()->default_folder; // Get the default folder for the thing or null
````
