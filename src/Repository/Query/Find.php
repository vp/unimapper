<?php

namespace UniMapper\Repository\Query;


class Find
{
    /**
     * Limit
     *
     * @var int|null
     */
    protected $limit;

    /**
     * Offset
     *
     * @var int|null
     */
    protected $offset;

    /**
     * Unimaper orderBy
     *
     * @var array
     */
    protected $orderBy = [];

    /**
     * Unimaper entity associations
     *
     * @var array
     */
    protected $associate = [];

    /**
     * Unimaper filters
     *
     * @var array
     */
    protected $filter = [];


    /**
     * Selection
     *
     * @var string[]
     */
    protected $selection;

    /**
     * Find constructor.
     *
     * @param array $filter
     * @param array $orderBy
     * @param int   $limit
     * @param int   $offset
     * @param array $associate
     * @param array $selection
     */
    public function __construct(
        array $filter = [],
        array $orderBy = [],
        $limit = 0,
        $offset = 0,
        array $associate = [],
        array $selection = []
    ) {
        $this->limit = $limit;
        $this->offset = $offset;
        $this->orderBy = $orderBy;
        $this->associate = $associate;
        $this->filter = $filter;
        $this->selection = $selection;
    }


    /**
     * @return int|null
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param int|null $limit
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    /**
     * @return int|null
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @param int|null $offset
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;
    }

    /**
     * @return array
     */
    public function getOrderBy()
    {
        return $this->orderBy;
    }

    /**
     * @param array $orderBy
     */
    public function setOrderBy($orderBy)
    {
        $this->orderBy = $orderBy;
    }

    /**
     * @return array
     */
    public function getAssociate()
    {
        return $this->associate;
    }

    /**
     * @param array $associate
     */
    public function setAssociate($associate)
    {
        $this->associate = $associate;
    }

    /**
     * @return array
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @param array $filter
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }

    /**
     * @return \string[]
     */
    public function getSelection()
    {
        return $this->selection;
    }

    /**
     * @param \string[] $selection
     */
    public function setSelection($selection)
    {
        $this->selection = $selection;
    }


}