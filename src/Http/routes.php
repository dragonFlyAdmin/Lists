<?php
// Perform action on supplied ids
Route::get('lists/{table}/{action}', [
    'uses' => '\DragonFly\Lists\Http\Controllers\DataController@perform',
    'as' => 'lists.perform'
]);

// Load table contents
Route::get('lists/{table}', [
    'uses' => '\DragonFly\Lists\Http\Controllers\DataController@load',
    'as' => 'lists.load'
]);

