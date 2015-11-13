<?php

namespace UniMapper\Query;

use UniMapper\Association;
use UniMapper\Entity\Filter;
use UniMapper\Exception;
use UniMapper\Entity\Reflection;

trait Selectable
{

    /** @var array */
    protected $associations = [
        "local" => [],
        "remote" => []
    ];

    /** @var array */
    protected $selection = [];

    public function associate($args)
    {
        foreach (func_get_args() as $arg) {

            if (!is_array($arg)) {
                $arg = [$arg];
            }

            foreach ($arg as $name) {

                if (!$this->entityReflection->hasProperty($name)) {
                    throw new Exception\QueryException(
                        "Property '" . $name . "' is not defined on entity "
                        . $this->entityReflection->getClassName() . "!"
                    );
                }

                $property = $this->entityReflection->getProperty($name);
                if (!$property->hasOption(Reflection\Property\Option\Assoc::KEY)) {
                    throw new Exception\QueryException(
                        "Property '" . $name . "' is not defined as association"
                        . " on entity " . $this->entityReflection->getClassName()
                        . "!"
                    );
                }

                $association = $property->getOption(Reflection\Property\Option\Assoc::KEY);
                if ($association->isRemote()) {
                    $this->associations["remote"][$name] = $association;
                } else {
                    $this->associations["local"][$name] = $association;
                }
            }
        }

        return $this;
    }

    public function select($args)
    {
        foreach (func_get_args() as $arg) {

            if (!is_array($arg)) {
                $arg = [$arg];
            }

            foreach ($arg as $name) {

                if (!$this->entityReflection->hasProperty($name)) {
                    throw new Exception\QueryException(
                        "Property '" . $name . "' is not defined on entity "
                        . $this->entityReflection->getClassName() . "!"
                    );
                }

                $property = $this->entityReflection->getProperty($name);
                if ($property->hasOption(Reflection\Property\Option\Assoc::KEY)
                    || $property->hasOption(Reflection\Property\Option\Computed::KEY)
                    || ($property->hasOption(Reflection\Property\Option\Map::KEY)
                        && !$property->getOption(Reflection\Property\Option\Map::KEY))
                ) {
                    throw new Exception\QueryException(
                        "Associations, computed and properties with disabled mapping can not be selected!"
                    );
                }

                if (!array_search($name, $this->selection)) {
                    $this->selection[] = $name;
                }
            }
        }

        return $this;
    }

    protected function createSelection()
    {
        if (empty($this->selection)) {

            $selection = [];
            foreach ($this->entityReflection->getProperties() as $property) {

                // Exclude associations & computed properties & disabled mapping
                if (!$property->hasOption(Reflection\Property\Option\Assoc::KEY)
                    && !$property->hasOption(Reflection\Property\Option\Computed::KEY)
                    && !($property->hasOption(Reflection\Property\Option\Map::KEY)
                        && !$property->getOption(Reflection\Property\Option\Map::KEY))
                ) {
                    $selection[] = $property->getUnmapped();
                }
            }
        } else {

            // Add properties from filter
            $selection = $this->selection;

            // Include primary automatically if not provided
            if ($this->entityReflection->hasPrimary()) {

                $primaryName = $this->entityReflection
                    ->getPrimaryProperty()
                    ->getName();

                if (!in_array($primaryName, $selection)) {
                    $selection[] = $primaryName;
                }
            }

            // Unmap all names
            foreach ($selection as $index => $name) {
                $selection[$index] = $this->entityReflection->getProperty($name)->getUnmapped();
            }
        }

        // Add required keys from remote associations
        foreach ($this->associations["remote"] as $association) {

            if (($association instanceof Association\ManyToOne || $association instanceof Association\OneToOne)
                && !in_array($association->getReferencingKey(), $selection, true)
            ) {
                $selection[] = $association->getReferencingKey();
            }
        }

        return $selection;
    }

}