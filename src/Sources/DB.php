<?php

namespace HappyDemon\Lists\Sources;

use DB as Database;
use HappyDemon\Lists\Column;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Input;

class DB extends Contract
{

    /**
     * @var Builder
     */
    public $model = null;

    /**
     * The columns key that dataTables sends through
     * @var array
     */
    protected $columns = [];

    protected $selects = [];

    /**
     * @param \Illuminate\Database\Query\Builder $model
     *
     * @return $this
     */
    public function __construct(Builder $model)
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
        $column = false;
        $as = null;
        $searchable = false;

        if ($fieldColumnDefinition->column != null || $fieldColumnDefinition->select != false)
        {
            if ($fieldColumnDefinition->as == null)
            {
                $as = (str_contains($fieldColumnDefinition->column, '.'))
                    ? explode('.', $fieldColumnDefinition->column)[1] : $fieldColumnDefinition->column;
            }
            else
            {
                $as = $fieldColumnDefinition->as;
            }

            if ($fieldColumnDefinition->select != false)
            {
                $column = $fieldColumnDefinition->select;
            }
            else
            {
                $column = $fieldColumnDefinition->column;
            }

            $this->addSelect($column . ' AS ' . $as, $fieldColumnDefinition);
        }

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

    /**
     * Get the correct table name.
     *
     * @param null|string $relations
     *
     * @return string
     */
    public function getTableName($relations = null)
    {
        return $this->model->from;
    }

    /**
     * Get the correct primary key name.
     *
     * @param null|string $relations
     *
     * @return string
     */
    public function getPrimaryKey($relations = null)
    {
        return 'id';
    }

    public function getFormattedPrimaryKey($relations = null)
    {
        return $this->model->from . '.id';
    }
}