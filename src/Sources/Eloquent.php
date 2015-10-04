<?php

namespace DragonFly\Lists\Sources;

use DragonFly\Lists\Column;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Eloquent extends DB
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

    protected $relations = [];

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return $this
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function getName()
    {
        return strtolower(class_basename($this->model));
    }

    public function prepare()
    {
        return $this->relations()->select();
    }

    /**
     * Add all the relations that were defined.
     *
     * @return $this
     */
    public function relations()
    {
        if (count($this->relations) > 0)
        {
            $this->model = $this->model->with($this->relations);
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

    public function getFormattedPrimaryKey($relations = null)
    {
        return $this->getTableName($relations) . '.' . $this->getPrimaryKey($relations);
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


    public function formatColumn(Column $fieldColumnDefinition)
    {
        // Store relation for loading, if needed
        if ($fieldColumnDefinition->relation != null && !in_array($fieldColumnDefinition->relation, $this->relations))
        {
            $this->relations[] = $fieldColumnDefinition->relation;
        }
        $column = false;
        $as = null;
        $searchable = false;

        if ($fieldColumnDefinition->column != null || $fieldColumnDefinition->select != false)
        {
            if ($fieldColumnDefinition->column != null)
            {
                // normalize the column's name
                if (!str_contains($fieldColumnDefinition->column, '.'))
                {
                    $as = ($fieldColumnDefinition->as) ?: $fieldColumnDefinition->column;

                    // Prefix with the table name
                    $column = '(:table)' . '.' . $fieldColumnDefinition->column;
                }
                else
                {
                    $column = $fieldColumnDefinition->column;

                    $as = ($fieldColumnDefinition->as) ?: explode('.', $fieldColumnDefinition->column)[1];
                }

                if ($fieldColumnDefinition->searchable)
                {
                    $searchable = true;
                    $this->searchables[] = $fieldColumnDefinition->as;
                }

                $this->addSelect($column . ' AS ' . $as, $fieldColumnDefinition);
            }
            else if ($fieldColumnDefinition->select != false)
            {
                if ($fieldColumnDefinition->as == null)
                {
                    Throw new \Exception($this->title . ': "as" property should be defined if using select (" ' . $fieldColumnDefinition->select . ' ") - ' . var_export($this->as, true));
                }
                $column = $fieldColumnDefinition->select;
                $as = $fieldColumnDefinition->as;

                if ($fieldColumnDefinition->searchable)
                {
                    $searchable = true;
                    $this->searchables[] = $fieldColumnDefinition->column;
                }

                $this->addSelect($column . ' AS ' . $as, $fieldColumnDefinition);
            }
        }

        $as = $this->replace($as, $fieldColumnDefinition);
        $column = $this->replace($column, $fieldColumnDefinition);

        return compact('searchable', 'as', 'column');
    }

    public function addSelect($select, $column = null)
    {
        if ($column == null)
        {
            $column = new \stdClass();
            $column->relation = null;
        }


        $this->selects[] = $this->replace($select, $column);
    }

    protected function replace($value, $column)
    {
        $replacements = [
            '(:table)'       => $this->getTableName($column->relation),
            '(:primary_key)' => $this->getPrimaryKey($column->relation)
        ];

        return strtr($value, $replacements);
    }
}