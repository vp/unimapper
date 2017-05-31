<?php

namespace UniMapper;

abstract class Association
{

    /** @var Entity\Reflection */
    protected $sourceReflection;

    /** @var Entity\Reflection */
    protected $targetReflection;

    /** @var bool */
    protected $dominant = true;

    /** @var array */
    protected $mapBy = [];

    /** @var string */
    protected $propertyName;

    /** @var  array */
    protected $targetSelection = [];

    /** @var array  */
    protected $targetSelectionUnampped = [];

    /** @var array array */
    protected $targetFilter = [];

    public function __construct(
        $propertyName,
        Entity\Reflection $sourceReflection,
        Entity\Reflection $targetReflection,
        array $mapBy,
        $dominant = true
    ) {
        $this->propertyName = $propertyName;
        $this->sourceReflection = $sourceReflection;
        $this->targetReflection = $targetReflection;
        $this->dominant = (bool) $dominant;
        $this->mapBy = $mapBy;

        if (!$this->sourceReflection->hasAdapter()) {
            throw new Exception\AssociationException(
                "Can not use associations while source entity "
                . $sourceReflection->getName()
                . " has no adapter defined!"
            );
        }

        if (!$this->targetReflection->hasAdapter()) {
            throw new Exception\AssociationException(
                "Can not use associations while target entity "
                . $targetReflection->getName() . " has no adapter defined!"
            );
        }
    }

    /**
     * MapBy definition
     *
     * @internal
     * @return array
     */
    public function getMapBy()
    {
        return $this->mapBy;
    }

    /**
     * Source primary key name
     *
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->sourceReflection->getPrimaryProperty()->getName(true);
    }

    /**
     * Target entity reflection
     *
     * @return \UniMapper\Entity\Reflection
     */
    public function getTargetReflection()
    {
        return $this->targetReflection;
    }

    /**
     * Target resource name
     *
     * @return string
     */
    public function getTargetResource()
    {
        return $this->targetReflection->getAdapterResource();
    }

    /**
     * Source resource name
     *
     * @return string
     */
    public function getSourceResource()
    {
        return $this->sourceReflection->getAdapterResource();
    }

    /**
     * Target adapter name
     *
     * @return string
     */
    public function getTargetAdapterName()
    {
        return $this->targetReflection->getAdapterName();
    }

    /**
     * Referenced column name on target resource
     *
     * @return int|string
     */
    public abstract function getReferencedKey();

    /**
     * Referencing column name on source resource
     *
     * @return int|string
     */
    public abstract function getReferencingKey();

    /**
     * Return's true if association targets remote resource or is explicitly set as remote
     *
     * @return bool
     */
    public function isRemote()
    {
        // optional checkout through annotation parameter
        $sourceProperty = $this->sourceReflection->getProperty($this->getPropertyName());
        if ($sourceProperty->hasOption(\UniMapper\Entity\Reflection\Property::OPTION_ASSOC_REMOTE)) {
            $assocRemote = $sourceProperty->getOption(\UniMapper\Entity\Reflection\Property::OPTION_ASSOC_REMOTE);
            return $assocRemote === 'true' || is_int($assocRemote) ? (bool) $assocRemote : false;
        }
        // default behaviour

        return $this->sourceReflection->getAdapterName()
            !== $this->targetReflection->getAdapterName();
    }

    /**
     * Association property name on source resource
     *
     * @return string
     */
    public function getPropertyName()
    {
        return $this->propertyName;
    }

    /**
     * Selection for target resource
     *
     * @return array
     */
    public function getTargetSelection()
    {
        return $this->targetSelection;
    }

    /**
     * Set's target resource selection
     *
     * @param array $targetSelection
     */
    public function setTargetSelection(array $targetSelection)
    {
        $this->targetSelection = $targetSelection;
    }

    /**
     * Return's unmapped target selection
     *
     * @internal
     * @return array
     */
    public function getTargetSelectionUnampped()
    {
        return $this->targetSelectionUnampped;
    }

    /**
     * Set's unmapped target selection
     *
     * @internal
     * @param array $targetSelectionUnampped
     */
    public function setTargetSelectionUnampped($targetSelectionUnampped)
    {
        $this->targetSelectionUnampped = $targetSelectionUnampped;
    }

    /**
     * Return's target filter
     *
     * @return array
     */
    public function getTargetFilter()
    {
        return $this->targetFilter;
    }

    /**
     * Set's target filter
     *
     * @param array $filter
     */
    public function setTargetFilter(array $filter)
    {
        $this->targetFilter = $filter;
    }

    /**
     * Group associative array
     *
     * @param array $original
     * @param array $keys
     * @param int   $level
     *
     * @return array
     *
     * @link http://tigrou.nl/2012/11/26/group-a-php-array-to-a-tree-structure/
     *
     * @throws \Exception
     */
    protected function groupResult(array $original, array $keys, $level = 0)
    {
        $converted = [];
        $key = $keys[$level];
        $isDeepest = sizeof($keys) - 1 == $level;

        $level++;

        $filtered = [];
        foreach ($original as $k => $subArray) {

            $subArray = (array) $subArray;
            if (!isset($subArray[$key])) {
                throw new \Exception(
                    "Index '" . $key . "' not found on level '" . $level . "'!"
                );
            }

            $thisLevel = $subArray[$key];

            if (is_object($thisLevel)) {
                $thisLevel = (string) $thisLevel;
            }

            if ($isDeepest) {
                $converted[$thisLevel] = $subArray;
            } else {
                $converted[$thisLevel] = [];
            }
            $filtered[$thisLevel][] = $subArray;
        }

        if (!$isDeepest) {
            foreach (array_keys($converted) as $value) {
                $converted[$value] = $this->groupResult(
                    $filtered[$value],
                    $keys,
                    $level
                );
            }
        }

        return $converted;
    }

    /**
     * Load's association as remote for source referencing keys
     *
     * @param \UniMapper\Connection $connection     Connection instance
     * @param array                 $primaryValues  Referencing key values
     * @param array                 $selection      Target selection
     *
     * @return array Assoc array [referencing key value => associated row's]
     */
    abstract public function load(Connection $connection, array $primaryValues, array $selection = []);

    /**
     * Save changes in target collection or entity
     *
     * @param string|int                          $primaryValue Primary value from source entity
     * @param Connection                          $connection
     * @param Entity\Collection|\UniMapper\Entity $associated   Target collection or entity
     *
     * @throws \UniMapper\Exception\AssociationException
     */
    public function saveChanges($primaryValue, Connection $connection, $associated)
    {
        throw new \UniMapper\Exception\AssociationException('Saving not implementet for association of type ' . __CLASS__);
    }
}