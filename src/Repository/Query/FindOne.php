<?php

namespace UniMapper\Repository\Query;


class FindOne
{
    /**
     * Unimaper entity associations
     *
     * @var array
     */
    protected $associate = [];

    /**
     * Selection
     *
     * @var string[]
     */
    protected $selection;

    /**
     * Primary value
     *
     * @var integer|string
     */
    protected $primaryValue;

    /**
     * FindOne constructor.
     *
     * @param integer|string $primaryValue
     * @param array          $associate
     * @param array          $selection
     */
    public function __construct($primaryValue, array $associate = [], array $selection = [])
    {
        $this->associate = $associate;
        $this->selection = $selection;
        $this->primaryValue = $primaryValue;
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

    /**
     * @return int|string
     */
    public function getPrimaryValue()
    {
        return $this->primaryValue;
    }

    /**
     * @param int|string $primaryValue
     */
    public function setPrimaryValue($primaryValue)
    {
        $this->primaryValue = $primaryValue;
    }

}