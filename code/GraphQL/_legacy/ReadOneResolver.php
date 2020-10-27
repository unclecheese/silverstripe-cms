<?php

namespace SilverStripe\CMS\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\ReadOne;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\ORM\DataList;

if (!class_exists(ReadOne::class)) {
    return;
}

/**
 * Shim to make readOnePage work like GraphQL 4
 */
class ReadOneResolver
{
    public static function resolve($obj, array $args, array $context, ResolveInfo $info)
    {
        $idKey = StaticSchema::inst()->formatField('ID');
        $id = $args['filter'][$idKey]['eq'];
        $readOne = Injector::inst()->createWithArgs(ReadOne::class, ['Page']);
        $args = [$idKey => $id];
        return $readOne->resolve($obj, $args, $context, $info);
    }
}
