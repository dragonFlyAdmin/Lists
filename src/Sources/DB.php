<?php

namespace HappyDemon\Lists\Sources;

use DB as Database;
use HappyDemon\Lists\Column;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Input;

class DB extends Contract
{

    /**
     * @var Eloquent
     */
    public $model = null;

    /**
     * The columns key that dataTables sends through
     * @var array
     */
    protected $columns = [];

    protected $selects = [];

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return $this
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Parse all the defined columns to select for this query.
     *
     * @return $this
     */
    public function select()
    {
        $this->model = $this->model->select(Database::raw(implode(', ', $this->selects)));

        return $this;
    }

    /**
     * Perform a global search and locally if defined.
     *
     * @return $this
     */
    public function search($search_term, $searchables, $fields)
    {
        $term = array_get($search_term, 'value', null);

        if ($term !== false && $term != '')
        {
            $this->model = $this->model->whereRaw(array_shift($searchables) . " LIKE '%" . $term . "%'");

            foreach ($searchables as $column)
            {
                $this->model = $this->model->orWhereRaw($column . " LIKE '%" . $term . "%'");
            }
        }

        foreach ($this->columns as $column)
        {
            if (isset($column['search']) && $column['search']['value'] != '')
            {
                $column_name = $fields[$column['name']]->column;

                $this->model = $this->model->orWhereRaw($column_name . " LIKE %" . $column['search']['value'] . "%'");
            }
        }

        return $this;
    }

    /**
     * Order the results.
     *
     * @return $this
     */
    public function order($orderables, $fields)
    {
        // Order columns if needed
        if (count($orderables) > 0)
        {
            foreach ($orderables as $order)
            {
                $column_key = $this->columns[$order['column']]['name'];

                $column_name = $fields[$column_key]->column;

                $this->model = $this->model->orderBy(Database::raw($column_name), $order['dir']);
            }
        }

        return $this;
    }

    public function prepare()
    {
        return $this->select();
    }

    public function formatColumn(Column $fieldColumnDefinition)
    {
        $column = $fieldColumnDefinition->column;

        if($fieldColumnDefinition->as == null)
        {
            $as = (str_contains($column, '.'))
                ? explode('.', $column)[1] : $column;
        }

        if($fieldColumnDefinition->select != false)
        {
            $column = $fieldColumnDefinition->select;
        }

        $this->addSelect($column . ' AS ' . $as, $fieldColumnDefinition);

        return [
            'searchable' => $fieldColumnDefinition->searchable,
            'as' => $as,
            'column' => $column
        ];
    }

    public function getPreparedData()
    {
        $total = clone $this->model;

        return [
            'total' => $total->count(),
            'records' => $this->model->skip(Input::get('start', 0))
                                     ->take(Input::get('length', 10))
                                     ->get()
        ];
    }
}