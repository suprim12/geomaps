<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */


namespace suprim\geomaps\integrations\graphql\types;

use craft\gql\base\SingularTypeInterface;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class GeoType extends ObjectType implements SingularTypeInterface
{
    public static function getName(): string
    {
        return 'GeoFieldType';
    }

    public static function getType(): self
    {
        return GqlEntityRegistry::getOrCreate(
            static::getName(),
            fn() => new self([
                'name' => static::getName(),
                'fields' => static::getFieldDefinitions(),
            ])
        );
    }

    public static function getFieldDefinitions(): array
    {
        return [
            'lat' => ['type' => Type::float(),  'description' => 'Latitude'],
            'lng' => ['type' => Type::float(),  'description' => 'Longitude'],
            'zoom' => ['type' => Type::int(),    'description' => 'Zoom level'],
            'full_address' => ['type' => Type::string(), 'description' => 'Full address'],
            'address1' => ['type' => Type::string(), 'description' => 'Address line 1'],
            'suburb' => ['type' => Type::string(), 'description' => 'Suburb'],
            'postcode' => ['type' => Type::string(), 'description' => 'Postcode'],
            'country' => ['type' => Type::string(), 'description' => 'Country'],
            'countryCode' => ['type' => Type::string(), 'description' => 'Country Code'],
            'geoJson' => ['type' => Type::string(), 'description' => 'GeoJSON string'],
            'distance' => ['type' => Type::float(),  'description' => 'Distance from the searched location (proximity searches only)'],
        ];
    }
}
