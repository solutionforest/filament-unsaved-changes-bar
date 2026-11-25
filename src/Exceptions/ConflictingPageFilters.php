<?php

namespace SolutionForest\FilamentUnsavedChangesBar\Exceptions;

use LogicException;

class ConflictingPageFilters extends LogicException
{
    public static function make(): self
    {
        return new self('UnsavedChangesBarPlugin: use either includePages() or excludePages(), not both.');
    }
}
