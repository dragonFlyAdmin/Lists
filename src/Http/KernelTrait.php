<?php

namespace HappyDemon\Lists\Http;


trait KernelTrait {
    /**
     * @param $table
     *
     * @return Table
     *
     * @throws \Exception
     */
    public function loadTable($table)
    {
        if(!isset($this->tables[$table]))
        {
            abort(404, 'No table "'.$table.'" definition found.');
        }

        return new $this->tables[$table];
    }
}