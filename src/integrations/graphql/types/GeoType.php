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
use craft\gql\base\SingularTypeInterface;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class GeoType extends ObjectType implements SingularTypeInterface
{
    public static function getName(): string
    {
        return 'GeoFieldType';
    }

    public static function getType(): static
    {
        return GqlEntityRegistry::getOrCreate(
            static::getName(),
            fn() => new static([
                'name'   => static::getName(),
                'fields' => static::getFieldDefinitions(),
            ])
        );
    }

    public static function getFieldDefinitions(): array
    {
        return [
            'lat'          => ['type' => Type::float(),  'description' => 'Latitude'],
            'lng'          => ['type' => Type::float(),  'description' => 'Longitude'],
            'zoom'         => ['type' => Type::int(),    'description' => 'Zoom level'],
            'full_address' => ['type' => Type::string(), 'description' => 'Full address'],
            'address1'     => ['type' => Type::string(), 'description' => 'Address line 1'],
            'suburb'       => ['type' => Type::string(), 'description' => 'Suburb'],
            'postcode'     => ['type' => Type::string(), 'description' => 'Postcode'],
            'country'      => ['type' => Type::string(), 'description' => 'Country'],
            'countryCode'      => ['type' => Type::string(), 'description' => 'Country Code'],
            'geoJson'      => ['type' => Type::string(), 'description' => 'GeoJSON string'],
            'distance'     => ['type' => Type::float(),  'description' => 'Distance from the searched location (proximity searches only)'],
        ];
    }
}