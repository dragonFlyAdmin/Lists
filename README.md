Lists
=====

`DragonFly\Lists` is a [Laravel](http://laravel.com) 5 package that makes it easy to set up [dataTables](https://datatables.net) for you front-end
code, as wel as handle the requests that it requires to retrieve data.

I've taken a similar approach to this problem as Laravel's own form `Request`'s.

## Installation

First run composer require:

    composer require dragonfly/lists
    
Next open up `app/config/app.php` and add the serviceProvider:

    'DragonFly\Lists\ServiceProvider',
    
Once that's done open up `app/Http/Kernel.php`, we'll need to add a trait to the class and a new property:

```php
    use \DragonFly\Lists\Http\KernelTrait;
    
    /**
    * DataTable definitions (the key is used as a slug for routing)
    * 
    * @var array
    */
    protected $tables = [];
```

Lastly you'll create a new folder called `Tables` in `app/Http` and you're all set to go.

However if you'd like to make use of the config file & assets that comes bundled, you could run the following command in your terminal:

    php artisan php artisan vendor:publish --provider="DragonFly\Lists\ServiceProvider" --tag="merge"
    
If you'd like to tweak the views or lang file you cn publish those using:

    php artisan php artisan vendor:publish --provider="DragonFly\Lists\ServiceProvider" --tag="solid"

## Features

 * Laravel 5 support
 * Supports both `Eloquent` and `QueryBuilder` as data source
 * Flexible rendering
 * Easily generate skeleton definition
 * Offers optional table bulk-actions
 * Full localisation support
 * Fully documented
 
## Documentation

I've written down the documentation in this repo's [wiki](https://github.com/dragonFlyAdmin/Lists/wiki)

1. [Your first table definition](https://github.com/dragonFlyAdmin/Lists/wiki/Your-first-table-definition)
2. [Generating table definitions](https://github.com/dragonFlyAdmin/Lists/wiki/Generating-tables)
3. [Table definition](https://github.com/dragonFlyAdmin/Lists/wiki/Table-definition)
 * [Routing](https://github.com/dragonFlyAdmin/Lists/wiki/Table-definition#routing)
 * [Data](https://github.com/dragonFlyAdmin/Lists/wiki/Table-definition#data-loading)
 * [HTML](https://github.com/dragonFlyAdmin/Lists/wiki/Table-definition#html)
 * [Row manipulation](https://github.com/dragonFlyAdmin/Lists/wiki/Table-definition#row-manipulation)
 * [Setting dataTable options](https://github.com/dragonFlyAdmin/Lists/wiki/Table-definition#setting-datatable-options)
4. [Defining fields](https://github.com/dragonFlyAdmin/Lists/wiki/Defining-fields)
 * [Adding fields](https://github.com/dragonFlyAdmin/Lists/wiki/Defining-fields#adding-fields)
 * [Setting properties](https://github.com/dragonFlyAdmin/Lists/wiki/Defining-fields#properties)
 * [Presenting data](https://github.com/dragonFlyAdmin/Lists/wiki/Defining-fields#presenting-data)
5. [Defining bulk actions](https://github.com/dragonFlyAdmin/Lists/wiki/Defining-bulk-actions)
 * [Adding a new action](https://github.com/dragonFlyAdmin/Lists/wiki/Defining-bulk-actions#defining-an-action)
 * [Process an action](https://github.com/dragonFlyAdmin/Lists/wiki/Defining-bulk-actions#performing-your-action)
6. [Localisation](https://github.com/dragonFlyAdmin/Lists/wiki/Localisation)
 * [DataTables](https://github.com/dragonFlyAdmin/Lists/wiki/Localisation#datatables)
 * [Column titles](https://github.com/dragonFlyAdmin/Lists/wiki/Localisation#column-titles)
 * [Bulk actions](https://github.com/dragonFlyAdmin/Lists/wiki/Localisation#actions)
7. [Custom AJAX routing](https://github.com/dragonFlyAdmin/Lists/wiki/Custom-routing)
 * [Data load route](https://github.com/dragonFlyAdmin/Lists/wiki/Custom-routing#data-load-route)
 * [Perform Action route](https://github.com/dragonFlyAdmin/Lists/wiki/Custom-routing#perform-action-route)
