<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace suprim\geomaps\integrations\graphql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

class GeoQueryArgument extends InputObjectType
{
    public static function getName(): string
    {
        return 'GeoFieldQuery';
    }

    public static function getType(): self
    {
        return GqlEntityRegistry::getOrCreate(
            static::getName(),
            fn() => new self([
                'name' => static::getName(),
                'fields' => static::getQueryInputDefinitions(),
            ])
        );
    }

    public static function getQueryInputDefinitions(): array
    {
        return [
            'location' => [
                'name' => 'location',
                'type' => Type::string(),
                'description' => 'Search by address string or place name',
            ],
            'coordinate' => [
                'name' => 'coordinate',
                'type' => GeoCoordsInput::getType(),
                'description' => 'Search by lat/lng coordinate',
            ],
            'radius' => [
                'name' => 'radius',
                'type' => Type::float(),
                'description' => 'Radius to search within',
            ],
            'contains' => [
                'name' => 'contains',
                'type' => Type::string(),
                'description' => 'Find entries whose areas contain this address or place',
            ],
            'containsCoordinate' => [
                'name' => 'containsCoordinate',
                'type' => GeoCoordsInput::getType(),
                'description' => 'Find entries whose areas contain this lat/lng coordinate',
            ],
            'country' => [
                'name' => 'country',
                'type' => Type::string(),
                'description' => 'Two-letter country code to narrow address lookups',
            ],
            'unit' => [
                'name' => 'unit',
                'type' => Type::string(),
                'description' => 'Unit of distance: km or mi (default: km)',
            ],
        ];
    }
}
