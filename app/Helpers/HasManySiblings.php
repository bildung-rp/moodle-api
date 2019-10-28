<?php
namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HasManySiblings extends HasMany
{    
    public function addConstraints()
    {
        if (static::$constraints) {
            if (is_null($foreignKeyValue = $this->getParentKey())) {
                $this->query->whereNull($this->foreignKey);
            } else {
                $this->query->where($this->foreignKey, '=', $foreignKeyValue);
                $this->query->whereNotNull($this->foreignKey);
            }
            
            $this->query->where($this->localKey, '!=', $this->parent->getAttribute($this->localKey));
        }
       
    }

    public function getParentKey()
    {
        return $this->parent->getAttribute($this->foreignKey);
    }
}