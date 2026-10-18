<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace sup\craftgeo\integrations\graphql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

class GeoCoordsInput extends InputObjectType
{
    public static function getName(): string
    {
        return 'GeoFieldCoords';
    }

    public static function getType(): self
    {
        return GqlEntityRegistry::getOrCreate(
            static::getName(),
            fn() => new self([
                'name' => static::getName(),
                'fields' => [
                    'lat' => [
                        'name' => 'lat',
                        'type' => Type::nonNull(Type::float()),
                    ],
                    'lng' => [
                        'name' => 'lng',
                        'type' => Type::nonNull(Type::float()),
                    ],
                ],
            ])
        );
    }
}
