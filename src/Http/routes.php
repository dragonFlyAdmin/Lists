<?php
// Perform action on supplied ids
Route::get('lists/{table}/{action}', [
    'uses' => '\HappyDemon\Lists\Http\Controllers\DataController@perform',
    'as' => 'lists.perform'
]);

// Load table contents
Route::get('lists/{table}', [
    'uses' => '\HappyDemon\Lists\Http\Controllers\DataController@load',
    'as' => 'lists.load'
]);

