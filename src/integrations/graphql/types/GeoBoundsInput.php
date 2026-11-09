<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\integrations\graphql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

/**
 * A map area for `within` searches (Pro).
 */
class GeoBoundsInput extends InputObjectType
{
    public static function getName(): string
    {
        return 'GeoFieldBounds';
    }

    public static function getType(): self
    {
        return GqlEntityRegistry::getOrCreate(
            static::getName(),
            fn() => new self([
                'name' => static::getName(),
                'fields' => [
                    'north' => [
                        'name' => 'north',
                        'type' => Type::nonNull(Type::float()),
                    ],
                    'south' => [
                        'name' => 'south',
                        'type' => Type::nonNull(Type::float()),
                    ],
                    'east' => [
                        'name' => 'east',
                        'type' => Type::nonNull(Type::float()),
                    ],
                    'west' => [
                        'name' => 'west',
                        'type' => Type::nonNull(Type::float()),
                    ],
                ],
            ])
        );
    }
}
