<?php

Route::get('lists/{definition}', ['uses' => '\HappyDemon\Lists\Http\Controller@load', 'as' => 'lists.load']);