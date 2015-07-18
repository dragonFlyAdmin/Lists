<?php

namespace HappyDemon\Lists\Http\Controllers;

use Illuminate\Contracts\Http\Kernel;
use HappyDemon\Lists\Definition;

class DataController extends \App\Http\Controllers\Controller {

    public function load($definition, Kernel $kernel)
    {
        $table = $kernel->loadTable($definition);

        return response()->json($table->output());
    }
}