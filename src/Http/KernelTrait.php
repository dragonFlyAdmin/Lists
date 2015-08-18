<?php

namespace DragonFly\Lists\Http;


trait KernelTrait
{

    /**
     * @param $table
     *
     * @return Table
     *
     * @throws \Exception
     */
    public function loadTable($table)
    {
        if (!isset($this->tables[$table]))
        {
            abort(404, 'No table "' . $table . '" definition found.');
        }

        return new $this->tables[$table];
    }

    /**
     * Check if this table key is already assigned.
     *
     * @param string $table
     *
     * @return boolean
     */
    public function isTableDefined($table)
    {
        return array_key_exists($table, $this->tables);
    }
}