<?php


/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */


namespace sup\craftgeo\integrations\graphql\resolvers;

use craft\gql\base\Resolver;
use craft\helpers\Json;
use GraphQL\Type\Definition\ResolveInfo;

class GeoResolver extends Resolver
{
    public static function resolve(
        mixed $source,
        array $arguments,
        mixed $context,
        ResolveInfo $resolveInfo,
    ): mixed {
        $fieldName = $resolveInfo->fieldName;
        $value = $source->$fieldName;

        if (empty($value)) {
            return null;
        }

        // Decode if stored as JSON string
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        if (!is_array($value)) {
            return null;
        }

        return [
            'lat' => isset($value['lat']) ? (float)  $value['lat'] : null,
            'lng' => isset($value['lng']) ? (float)  $value['lng'] : null,
            'zoom' => isset($value['zoom']) ? (int)    $value['zoom'] : null,
            'full_address' => isset($value['full_address']) ? (string) $value['full_address'] : null,
            'address1' => isset($value['address1']) ? (string) $value['address1'] : null,
            'suburb' => isset($value['suburb']) ? (string) $value['suburb'] : null,
            'postcode' => isset($value['postcode']) ? (string) $value['postcode'] : null,
            'country' => isset($value['country']) ? (string) $value['country'] : null,
            'geoJson' => isset($value['geoJson']) ? (string) $value['geoJson'] : null,
        ];
    }
}
