<?php

namespace HappyDemon\Lists\Sources;

use Illuminate\Support\Collection as DataSource;

use HappyDemon\Lists\Column;
use Illuminate\Support\Facades\Input;

class Collection extends Contract
{

    /**
     * @var DataSource
     */
    public $data = null;

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
    public function __construct(DataSource $data)
    {
        $this->data = $data;
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
            $this->data = $this->data->filter(function($record) use($term, $searchables){
                $found = [];

                foreach($searchables as $search_key)
                {
                    if(!array_key_exists($search_key, $record))
                        continue;

                    $found[] = str_contains($record[$search_key], $term);
                }

                return in_array(true, $found);
            });
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

                $this->data = $this->data->sortBy(function($record) use($column_name){
                    return $record[$column_name];
                }, SORT_REGULAR, $order['dir'] == 'desc');
            }
        }

        return $this;
    }

    public function prepare()
    {
        return $this;
    }

    public function formatColumn(Column $fieldColumnDefinition)
    {
        $as = $column = $fieldColumnDefinition->column;

        $this->addSelect($column, $fieldColumnDefinition);

        return [
            'searchable' => $fieldColumnDefinition->searchable,
            'as' => $as,
            'column' => $column
        ];
    }

    public function getPreparedData()
    {
        return [
            'total' => $this->data->count(),
            'records' => $this->data->slice(Input::get('start', 0), Input::get('length', 10))
        ];
    }
}