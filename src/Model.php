<?php

namespace HappyDemon\Lists;

use DB;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Input;

class Model
{
    /**
     * @var Definition
     */
    protected $definition = null;

    /**
     * @var Eloquent
     */
    public $model = null;

    /**
     * The columns key that dataTables sends through
     * @var array
     */
    protected $columns = [];

    public function __construct(Table $definition, $request_columns)
    {
        $this->definition = $definition;

        $this->columns = $request_columns;
    }

    public function setModel(Eloquent $model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Parse all the defined columns to select for this query.
     *
     * @return $this
     */
    public function select()
    {
        $this->model = $this->model->select(DB::raw(implode(', ', $this->definition->select)));

        return $this;
    }

    /**
     * Add all the relations that were defined.
     *
     * @return $this
     */
    public function relations()
    {
        if (count($this->definition->relationships) > 0)
        {
            $this->model = $this->model->with($this->definition->relationships);
        }

        return $this;
    }

    /**
     * Perform a global search and locally if defined.
     *
     * @return $this
     */
    public function search($search_term)
    {
        $term = array_get($search_term, 'value', null);

        if ($term !== false && $term != '')
        {
            foreach ($this->definition->searchables as $column)
            {
                $this->model = $this->model->where($column, 'LIKE', '%' . $term . '%');
            }
        }

        foreach ($this->columns as $column)
        {
            if (isset($column['search']) && $column['search']['value'] != '')
            {
                $this->model = $this->model->where($this->definition->fields[$column['name']]->as, 'LIKE', '%' . $column['search']['value'] . '%');
            }
        }

        return $this;
    }

    /**
     * Order the results.
     *
     * @return $this
     */
    public function order($orderables)
    {
        // Order columns if needed
        if (count($orderables) > 0)
        {
            foreach ($orderables as $order)
            {
                $column_key = $this->columns[$order['column']]['name'];

                $column_name = ($this->definition->fields[$column_key]->select)
                    ?: $this->definition->fields[$column_key]->column;

                $this->model = $this->model->orderBy(DB::raw($column_name), $order['dir']);
            }
        }

        return $this;
    }
}