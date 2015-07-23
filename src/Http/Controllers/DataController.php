<?php

namespace HappyDemon\Lists\Http\Controllers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Input;
use HappyDemon\Lists\Definition;

class DataController extends \App\Http\Controllers\Controller {

    protected function loadTableDefinition(Kernel $kernel, $definition)
    {
        return $kernel->loadTable($definition);
    }

    /**
     * Load data for a table.
     *
     * @param                                   $definition
     * @param \Illuminate\Contracts\Http\Kernel $kernel
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function load($definition, Kernel $kernel)
    {
        $table = $this->loadTableDefinition($kernel, $definition);

        return response()->json($table->output());
    }

    /**
     * Perform an action on the supplied primary keys
     *
     * @param                                   $definition
     * @param                                   $action
     * @param \Illuminate\Contracts\Http\Kernel $kernel
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function perform($definition, $action, Kernel $kernel)
    {
        try {
            $table = $this->loadTableDefinition($kernel, $definition);
            return response()->json($table->perform($action, Input::get('list_keys', [])));
        }
        catch(\Exception $e)
        {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}