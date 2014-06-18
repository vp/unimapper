<?php

namespace UniMapper\Reflection;

use UniMapper\Exceptions\PropertyException;

/**
 * Entity reflection
 */
class Entity
{

    /** @var \UniMapper\Reflection\Entity\Mapper */
    private $mapper;

    /** @var array */
    private $properties;

    /** @var string */
    private $className;

    /** @var string */
    private $parentClassName;

    /** @var string */
    private $fileName;

    /** @var string */
    private $docComment;

    /** @var array */
    private $constants;

    /** @var string */
    private $primaryPropertyName;

    public function __construct($class)
    {
        $reflection = new \ReflectionClass($class);

        $this->className = $reflection->getName();
        $this->fileName = $reflection->getFileName();
        $this->docComment = $reflection->getDocComment();
        $this->parentClassName = $reflection->getParentClass()->name; // @todo undefined method, needs some refactoring
        $this->constants = $reflection->getConstants();

        $this->mapper = $this->parseMapper();
        $this->parseProperties();
    }

    public function getConstants()
    {
        return $this->constants;
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * Parse properties from annotations
     *
     * @return array Collection of \UniMapper\Reflection\Entity\Property with proeprty name as index
     *
     * @throws \UniMapper\Exceptions\PropertyException
     */
    private function parseProperties()
    {
        preg_match_all(
            '/\s*\*\s*@property([ -](read)*\s*.*)/',
            $this->docComment,
            $annotations
        );
        $properties = [];
        foreach ($annotations[1] as $index => $definition) {

            $property = new Entity\Property($definition, $this);
            if (isset($properties[$property->getName()])) {
                throw new PropertyException(
                    "Duplicate property with name '" . $property->getName() . "'!",
                    $this,
                    $definition
                );
            }

            // Primary property
            if ($property->isPrimary() && $this->primaryPropertyName !== null) {
                throw new PropertyException("Primary property already defined!", $this, $annotation);
            } elseif ($property->isPrimary()) {
                $this->primaryPropertyName = $property->getName();
            }
            if ($property->isAssociation() && $this->primaryPropertyName === null) {
                throw new PropertyException("You must define primary property before the association!", $this, $annotation);
            }

            $this->properties[$property->getName()] = $property;
        }

        // Include inherited doc comments too
        if (stripos($this->docComment, "{@inheritDoc}") !== false) {
            $this->properties = array_merge($this->properties, $this->getEntityProperties($this->parentClassName)); // @todo
        }
    }

    /**
     * Get mapper definition from annotations
     *
     * @return \UniMapper\Reflection\Entity\Mapper
     *
     * @throws \UniMapper\Exceptions\PropertyException
     */
    private function parseMapper()
    {
        preg_match_all(
            '#@mapper (.*?)\n#s',
            $this->docComment,
            $annotations
        );

        if (empty($annotations[0])) {
            throw new PropertyException("No mapper defined!", $this);
        }

        if (count($annotations[0]) > 1) {
            throw new PropertyException("Only one mapper definition allowed!", $this, $annotations[0][1]);
        }

        return new Mapper(substr($annotations[0][0], 8), $this);
    }

    public function getMapperReflection()
    {
        return $this->mapper;
    }

    public function hasProperty($name)
    {
        return isset($this->properties[$name]);
    }

    /**
     * Get property reflection object
     *
     * @param string $name
     *
     * @return \Unimapper\Reflection\Entity\Property
     *
     * @throws \Exception
     */
    public function getProperty($name)
    {
        if (!$this->hasProperty($name)) {
            throw new \Exception("Unknown property " . $name . "!");
        }
        return $this->properties[$name];
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function hasPrimaryProperty()
    {
        return $this->primaryPropertyName !== null;
    }

    /**
     * Get primary property reflection
     *
     * @return \UniMapper\Reflection\Entity\Property
     */
    public function getPrimaryProperty()
    {
        return $this->properties[$this->primaryPropertyName];
    }

}
