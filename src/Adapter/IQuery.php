<?php

namespace UniMapper\Adapter;

interface IQuery
{

    public function setFilter(array $filter);

    public function setAssociations(array $associations, array $associationsFilters = []);

    public function getRaw();

}