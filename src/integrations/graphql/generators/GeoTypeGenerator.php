<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim <support@suprim.com.au> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 suprim
 */


namespace suprim\geomaps\integrations\graphql\generators;

use craft\gql\base\GeneratorInterface;
use craft\gql\base\ObjectType;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;
use suprim\geomaps\integrations\graphql\types\GeoType;

class GeoTypeGenerator implements GeneratorInterface, SingleGeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        return [static::generateType($context)];
    }

    public static function generateType(mixed $context = null): ObjectType
    {
        $typeName = GeoType::getName();

        return GqlEntityRegistry::getOrCreate(
            $typeName,
            fn() => new GeoType([
                'name' => $typeName,
                'fields' => fn() => GeoType::getFieldDefinitions(),
            ])
        );
    }
}
