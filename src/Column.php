<?php

namespace HappyDemon\Lists;

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
     * @var Table
     */
    protected $table = null;

    public function __construct(Table $table)
    {
        $this->table = $table;
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
     * @return array
     * @throws \Exception
     */
    public function getRequestMeta()
    {
        $meta = [
            'searchable' => false,
            'relation'   => $this->relation
        ];

        $meta = array_merge($meta, $this->formatColumnAs());

        // Check if there's a format function
        $this->checkFormat();

        return $meta;
    }

    /**
     * Format provided columns or selects to a universal format.
     *
     * @return array
     * @throws \Exception
     */
    protected function formatColumnAs()
    {
        $meta = [
            'searchable' => false
        ];

        $replacements = [
            '(:table)'       => $this->table->model->getTableName($this->relation),
            '(:primary_key)' => $this->table->model->getPrimaryKey($this->relation)
        ];

        if ($this->column != null || $this->select != false)
        {
            if ($this->column != null)
            {
                // normalize the column's name
                if (!str_contains($this->column, '.'))
                {
                    if ($this->as == false)
                    {
                        // make sure (:primary_key) gets replaced
                        $this->as = strtr($this->column, $replacements);
                    }

                    // Prefix with the table name
                    $this->column = $replacements['(:table)'] . '.' . $this->column;
                }
                else
                {
                    $this->column = strtr($this->column, $replacements);

                    if ($this->as == false)
                    {
                        $this->as = explode('.', $this->column)[1];
                    }
                }

                if ($this->searchable)
                {
                    $meta['searchable'] = $this->as;
                }

                $meta['select'] = $this->column . ' AS ' . $this->as;
            }
            else if ($this->select != false)
            {
                if ($this->as == null)
                {
                    Throw new \Exception($this->title . ': "as" property should be defined if using select (" ' . $this->select . ' ") - ' . var_export($this->as, true));
                }
                $this->column = strtr($this->select, $replacements);
                $this->as = strtr($this->as, $replacements);

                $meta['select'] = $this->column . ' AS ' . $this->as;

                if ($this->searchable)
                {
                    $meta['searchable'] = $this->column;
                }
            }
        }

        return $meta;
    }

    /**
     * Check if there's a format method defined
     */
    protected function checkFormat()
    {
        // Overwrite the format value if a method exists
        $this->format = $this->checkMethodExists('format', $this->as);
    }

    /**
     * Return the column's meta data needed for rendering the JS tag
     *
     * @return array
     */
    public function getDefinitionMeta()
    {
        $this->formatColumnAs();

        // Check if there's a render function
        if ($this->render === false)
        {
            $this->render = $this->checkMethodExists('render', $this->as);
        }

        // Check if there's a format function
        $this->checkFormat();

        return [
            'type' => isset($this->options['type']) ? $this->options['type'] : false
        ];
    }

    /**
     * Check if a 'render' or 'format' method exists for the specified column.
     *
     * @param       $type   either 'render' or 'format'
     * @param       $column name of the column
     *
     * @return array|null
     */
    protected function checkMethodExists($type, $column)
    {
        $method = 'get' . ucfirst(camel_case($column)) . ucfirst($type);

        if (method_exists($this->table, $method))
        {
            return [$this->table, $method];
        }

        return $this->{$type};
    }
}