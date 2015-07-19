<?php

Route::get('lists/{table}', [
    'uses' => '\HappyDemon\Lists\Http\Controllers\DataController@load',
    'as' => 'lists.load'
]);