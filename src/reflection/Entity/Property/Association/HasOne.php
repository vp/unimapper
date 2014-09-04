<?php

namespace UniMapper\Reflection\Entity\Property\Association;

use UniMapper\Reflection;

class HasOne extends Reflection\Entity\Property\Association
{

    protected $expression = "1:1\s*=\s*(.*)";

    public function __construct(
        Reflection\Entity $currentReflection,
        Reflection\Entity $targetReflection,
        $definition
    ) {
        parent::__construct($currentReflection, $targetReflection, $definition);

        if (empty($this->matches[0])) {
            throw new Exception\AssociationParseException(
                "You must define a reference key!"
            );
        }
    }

    public function getReferenceKey()
    {
        return $this->matches[0];
    }

}