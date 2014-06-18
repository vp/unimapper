<?php

namespace UniMapper\Reflection\Entity\Property;

use UniMapper\Reflection;

abstract class Association
{

    /** @var \UniMapper\Reflection\Entity */
    protected $currentEntityReflection;

    /** @var \UniMapper\Reflection\Entity */
    protected $targetEntityReflection;

    /** @var array $parameters Additional association informations */
    protected $parameters = [];

    public function __construct(Reflection\Entity $currentEntityReflection, Reflection\Entity $targetEntityReflection, $parameters)
    {
        $this->currentEntityReflection = $currentEntityReflection;
        $this->targetEntityReflection = $targetEntityReflection;
        $this->parameters = explode("|", $parameters);
    }

    public function getPrimaryKey()
    {
        return $this->currentEntityReflection->getPrimaryProperty()->getMappedName();
    }

    public function getTargetReflection()
    {
        return $this->targetEntityReflection;
    }

    public function getTargetResource()
    {
        return $this->targetEntityReflection->getMapperReflection()->getResource();
    }

    public function getTargetMapperName()
    {
        return $this->targetEntityReflection->getMapperReflection()->getName();
    }

    public function isRemote()
    {
        return $this->currentEntityReflection->getMapperReflection()->getName() !== $this->targetEntityReflection->getMapperReflection()->getName();
    }

}