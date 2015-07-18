<?php

namespace HappyDemon\Lists\Http;

use Illuminate\Contracts\Http\Kernel;
use HappyDemon\Lists\Definition;

class Controller extends \App\Http\Controllers\Controller {

    public function load($definition, Kernel $kernel)
    {
        $table = $kernel->loadTable($definition);

        return response()->json($table->output());
    }
}