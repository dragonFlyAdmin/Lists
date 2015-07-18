<?php

namespace HappyDemon\Lists;

use Illuminate\Database\Eloquent\Model;

class Column
{
    /**
     * Column definition.
     *
     * @var array
     */
    protected $options = [
        'title'      => null,
        'sortable'   => false,
        'searchable' => false,
        'relation'   => null,
        'column'     => null,
        'select'     => false,
        'as'         => null,
        'format'     => false,
        'render'     => false,
        'default'    => false
    ];

    /**
     * @var Model
     */
    protected $model = null;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function __set($key, $val)
    {
        $this->options[$key] = $val;
    }

    /**
     * @param $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->options[$key];
    }

    /**
     * Set multiple attributes at once
     *
     * @param $attributes
     */
    public function set($attributes)
    {
        $this->options = array_merge($this->options, $attributes);

        return $this;
    }

    /**
     * Return the column's meta data.
     *
     * @param \HappyDemon\Lists\Table $definition
     *
     * @return array
     * @throws \Exception
     */
    public function getRequestMeta(Table $definition)
    {
        $meta = [
            'searchable' => false,
            'relation' => $this->relation
        ];

        $meta = array_merge($meta, $this->formatColumnAs($definition));

        // Check if there's a format function
        $this->checkFormat($definition);

        return $meta;
    }

    /**
     * Format provided columns or selects to a universal format.
     *
     * @param \HappyDemon\Lists\Table $definition
     *
     * @return array
     * @throws \Exception
     */
    protected function formatColumnAs(Table $definition)
    {
        $meta = [];

        $table = $definition->getModelTableName($this->relation);

        if ($this->column != null || $this->select != false)
        {
            if ($this->column != null)
            {
                // normalize the column's name
                if (!str_contains($this->column, '.'))
                {
                    if ($this->as == null)
                    {
                        $this->as = $this->column;
                    }

                    $this->column = $table . '.' . $this->column;
                }
                else
                {
                    $this->column = str_replace('(:table)', $table, $this->column);

                    if ($this->as == null)
                    {
                        $this->as = explode('.', $this->column)[1];
                    }
                }

                $meta['select'] = $this->column . ' AS ' . $this->as;
            }
            else if ($this->select != false)
            {
                if ($this->as == null)
                {
                    Throw new \Exception($this->title . ': "as" property should be defined if using select (" ' . $this->select . ' ") - ' . var_export($this->as, true));
                }

                $meta['select'] = str_replace('(:table)', $table, $this->select) . ' AS ' . $this->as;
            }

            // Return the eventual column name if searchable
            $meta['searchable'] = ($this->searchable === true) ? $this->as : false;
        }

        return $meta;
    }

    /**
     * Check if there's a format method defined
     *
     * @param \HappyDemon\Lists\Table $definition
     */
    protected function checkFormat(Table $definition)
    {
        // Overwrite the format value if a method exists
        $this->format = $this->checkMethodExists('format', $this->as, $definition);
    }

    /**
     * Return the column's meta data needed for rendering the JS tag
     *
     * @param \HappyDemon\Lists\Table $definition
     *
     * @return array
     */
    public function getDefinitionMeta(Table $definition)
    {
        $this->formatColumnAs($definition);

        // Check if there's a render function
        if ($this->render === false)
        {
            $this->render = $this->checkMethodExists('render', $this->as, $definition);
        }

        // Check if there's a format function
        $this->checkFormat($definition);

        return [
            'type' => isset($this->options['type']) ? $this->options['type'] : false
        ];
    }

    /**
     * Check if a 'render' or 'format' method exists for the specified column.
     *
     * @param       $type   either 'render' or 'format'
     * @param       $column name of the column
     * @param Table $definition
     *
     * @return array|null
     */
    protected function checkMethodExists($type, $column, Table $definition)
    {
        $method = 'get' . ucfirst(camel_case($column)) . ucfirst($type);

        if (method_exists($definition, $method))
        {
            return [$definition, $method];
        }

        return $this->{$type};
    }
}