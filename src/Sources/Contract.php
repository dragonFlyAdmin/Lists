<?php

namespace HappyDemon\Lists\Sources;


use HappyDemon\Lists\Column;

abstract class Contract
{


    public function setRequestColumns($request_columns)
    {
        $this->columns = $request_columns;

        return $this;
    }

    public function getIdName()
    {
        if (method_exists($this, 'getName'))
        {
            return $this->getName();
        }

        return false;
    }

    public function addSelect($select, $column=null)
    {
        $this->selects[] = $select;
    }

    public $searchables = [];

    abstract public function prepare();

    abstract public function search($search_term, $searchables, $fields);

    abstract public function order($orderables, $fields);

    abstract public function formatColumn(Column $fieldColumnDefinition);

    abstract public function getPreparedData();
}