<?php

namespace UniMapper\Entity\Reflection\Property\Option;

use UniMapper\Entity\Reflection;
use UniMapper\Entity\Reflection\Property;
use UniMapper\Entity\Reflection\Property\IOption;
use UniMapper\Exception\OptionException;

class Assoc implements IOption
{

    const KEY = "assoc";

    /** @var Reflection */
    private $targetReflection;

    /** @var Reflection */
    private $sourceReflection;

    /** @var array */
    private $definition = [];

    /** @var string */
    private $type;

    public function __construct(
        $type,
        Reflection $sourceReflection,
        Reflection $targetReflection,
        Property $property,
        array $definition = []
    ) {
        if (!$sourceReflection->hasAdapter()) {
            throw new OptionException(
                "Can not use associations while source entity "
                . $sourceReflection->getName()
                . " has no adapter defined!"
            );
        }

        if (!$targetReflection->hasAdapter()) {
            throw new OptionException(
                "Can not use associations while target entity "
                . $targetReflection->getName() . " has no adapter defined!"
            );
        }

        if (!in_array($property->getType(), [Property::TYPE_COLLECTION, Property::TYPE_ENTITY], true)) {
            throw new OptionException(
                "Property type must be collection or entity if association "
                . "defined!"
            );
        }

        $this->type = strtolower($type);
        $this->targetReflection = $targetReflection;
        $this->sourceReflection = $sourceReflection;
        $this->definition = $definition;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    public function getBy()
    {
        return $this->getParameter('by');
    }

    /**
     * Set's concrete definition
     *
     * @param string $name
     * @param mixed $value
     */
    protected function setParameter($name, $value)
    {
        $this->definition[$name] = $value;
    }

    public function hasParameter($name) {
        return isset($this->definition[$name]);
    }

    public function getParameter($name) {
        if (!$this->hasParameter($name)) {
            throw new OptionException(
                "Not existing option parameter '{$name}'!"
            );
        }
        return $this->definition[$name];
    }

    public function getTargetSelection()
    {
        return isset($this->definition['selection']) ? $this->definition['selection'] : [];
    }

    public function setTargetSelection(array $targetSelection)
    {
        $this->setParameter('selection', $targetSelection);
    }

    public function getTargetFilter()
    {
        return isset($this->definition['filter']) ? $this->definition['filter'] : [];
    }

    public function setTargetFilter(array $filter)
    {
        $this->setParameter('filter', $filter);
    }

    /**
     * @return Reflection
     */
    public function getTargetReflection()
    {
        return $this->targetReflection;
    }

    /**
     * @return Reflection
     */
    public function getSourceReflection()
    {
        return $this->sourceReflection;
    }

    /**
     * Cross-adapter association?
     *
     * @return bool
     */
    public function isRemote()
    {
        // optional checkout through definition
        if (isset($this->definition['remote'])) {
            return $this->definition['remote'] === 'true' || is_int($this->definition['remote']) ? (bool) $this->definition['remote'] : false;
        }
        // default behaviour

        return $this->sourceReflection->getAdapterName()
            !== $this->targetReflection->getAdapterName();
    }

    public static function create(
        Property $property,
        $value = null,
        array $parameters = []
    ) {
        if (!$value) {
            throw new OptionException("Association definition required!");
        }

        $definition = array_combine(array_map(function ($key) {
            return substr($key, strlen(Assoc::KEY) + 1);
        }, array_keys($parameters)), array_values($parameters));

        if (isset($definition["by"])) {
            $definition["by"] = explode("|", $definition["by"]);
        } else {
            $definition["by"] = [];
        }

        $definition['type'] = $value;

        return new self(
            $value,
            $property->getReflection(),
            Reflection::load($property->getTypeOption()),
            $property,
            $definition
        );
    }

    public static function afterCreate(Property $property, $option)
    {
        if ($property->hasOption(Map::KEY)
            || $property->hasOption(Enum::KEY)
            || $property->hasOption(Computed::KEY)
        ) {
            throw new OptionException(
                "Association can not be combined with mapping, computed or "
                . "enumeration!"
            );
        }
    }

}