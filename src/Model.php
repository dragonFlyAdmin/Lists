<?php

namespace DragonFly\Lists;

use DB;
use Illuminate\Database\Eloquent\Model as Eloquent;

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

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return $this
     */
    public function __construct(Eloquent $model)
    {
        $this->model = $model;
    }

    public function setRequestColumns($request_columns)
    {
        $this->columns = $request_columns;

        return $this;
    }

    /**
     * Parse all the defined columns to select for this query.
     *
     * @return $this
     */
    public function select($selects)
    {
        $this->model = $this->model->select(DB::raw(implode(', ', $selects)));

        return $this;
    }

    /**
     * Add all the relations that were defined.
     *
     * @return $this
     */
    public function relations($relationships)
    {
        if (count($relationships) > 0)
        {
            $this->model = $this->model->with($relationships);
        }

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

                $this->model = $this->model->orderBy(DB::raw($column_name), $order['dir']);
            }
        }

        return $this;
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
        return $this->getRelationInfo($relations)['table'];
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
        return $this->getRelationInfo($relations)['primary_key'];
    }

    /**
     * Store the relation's table name & primary key
     * @var array
     */
    protected $relation_cache = [];

    /**
     * Retrieve a relation or this model's table name & primary key name.
     *
     * Loops over relations to get the needed data.
     *
     * @param $relation
     *
     * @return array
     */
    protected function getRelationInfo($relation)
    {
        if (!array_key_exists($relation, $this->relation_cache))
        {
            $instance = $this->model;

            // Loop over the (nested) relation to get the table name
            if ($relation != null)
            {
                $relations = explode('.', $relation);
                foreach ($relations as $rel)
                {
                    $instance = call_user_func([$instance, $rel]);
                }
            }

            // Store the table's & primary key's name
            $this->relation_cache[$relation] = [
                'table'       => $instance->getTable(),
                'primary_key' => $instance->getKeyName()
            ];
        }

        return $this->relation_cache[$relation];
    }
}