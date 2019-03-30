<?php

namespace UniMapper\Association;

use UniMapper\Entity\Reflection;
use UniMapper\Exception\AssociationException;

abstract class CustomAssociation extends \UniMapper\Association
{

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