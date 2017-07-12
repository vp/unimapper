<?php

namespace UniMapper\Association;

use UniMapper\Entity\Reflection;
use UniMapper\Exception\AssociationException;

abstract class CustomAssociation extends \UniMapper\Association
{

    /**
     * @param Reflection $sourceReflection
     * @param Reflection $targetReflection
     *
     * @throws AssociationException
     */
    public function __construct(
        Reflection $sourceReflection,
        Reflection $targetReflection
    ) {
        $this->sourceReflection = $sourceReflection;
        $this->targetReflection = $targetReflection;
    }

    public function associate(
        \UniMapper\Adapter $adapter,
        \UniMapper\Entity\Reflection\Property\Option\Assoc $association,
        array $primaryKeys,
        array $targetSelection = [],
        array $targetFilter = []
    ) {
        throw new AssociationException('Local associate not implemented for ' . __CLASS__ . '!');
    }


}