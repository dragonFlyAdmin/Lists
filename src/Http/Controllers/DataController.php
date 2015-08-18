<?php

namespace DragonFly\Lists\Http\Controllers;

use Illuminate\Support\Facades\Input;
use DragonFly\Lists\Definition;

class DataController extends \App\Http\Controllers\Controller
{

    /**
     * Load data for a table.
     *
     * @param                                   $definition
     * @param \Illuminate\Contracts\Http\Kernel $kernel
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function load($definition)
    {
        $table = app('TableLoader')->table($definition);

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
    public function perform($definition, $action)
    {
        try
        {
	        $table = app('TableLoader')->table($definition);

            return response()->json($table->perform($action, Input::get('list_keys', [])));
        }
        catch (\Exception $e)
        {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}