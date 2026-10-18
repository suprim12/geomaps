<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace sup\craftgeo\models;

use Craft;
use craft\base\Model;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use sup\craftgeo\Geo;
use sup\craftgeo\services\GeoService;

class EmbedMap extends Model
{
    public mixed   $center         = null;
    public mixed   $centerFallback = null;
    public int     $width          = 640;
    public int     $height         = 400;
    public int     $zoom           = 14;
    public int     $scale          = 1;
    public array   $markers        = [];
    public ?string $id             = null;
    public array   $options        = [];

    public function __toString(): string
    {
        return $this->embed();
    }

    public function embed(array $options = []): \Twig\Markup
    {
        $this->setOptions($options);

        $id       = $this->id ?? 'geo_map_' . StringHelper::randomString(8);
        $mapToken = Geo::getInstance()->getSettings()->getMapToken();
        $center   = $this->resolveCenter();
        $markers  = $this->resolveMarkers($center);

        $config = Json::encode([
            'id'      => $id,
            'center'  => $center,
            'zoom'    => $this->zoom,
            'width'   => $this->width,
            'height'  => $this->height,
            'markers' => $markers,
            'options' => $this->options,
        ]);

        $view = Craft::$app->getView();

        if ($mapToken) {
            $view->registerJsFile(
                "https://maps.googleapis.com/maps/api/js?key={$mapToken}&libraries=places",
                ['position' => \craft\web\View::POS_HEAD]
            );
        }

        $style = "width:{$this->width}px;height:{$this->height}px;";

        $js = <<<JS
        (function() {
            function initGeoEmbed() {
                var config = {$config};
                var el = document.getElementById(config.id);
                if (!el) return;

                if (typeof L !== 'undefined') {
                    var map = L.map(el, config.options || {}).setView(
                        [config.center.lat, config.center.lng],
                        config.zoom
                    );
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(map);
                    if (config.markers && config.markers.length) {
                        config.markers.forEach(function(marker) {
                            if (marker.lat == null || marker.lng == null) return;
                            var color = marker.color || '#ff0000';
                            var m = L.circleMarker([marker.lat, marker.lng], {
                                radius: 8,
                                fillColor: color,
                                color: '#fff',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.9,
                            });
                            if (marker.label) m.bindPopup(marker.label);
                            m.addTo(map);
                        });
                    }
                    return;
                }

                if (typeof google !== 'undefined' && google.maps) {
                    var map = new google.maps.Map(el, Object.assign({
                        center: { lat: config.center.lat, lng: config.center.lng },
                        zoom: config.zoom,
                    }, config.options || {}));

                    if (config.markers && config.markers.length) {
                        config.markers.forEach(function(marker) {
                            if (marker.lat == null || marker.lng == null) return;
                            var color = marker.color || '#ff0000';
                            new google.maps.Marker({
                                position: { lat: marker.lat, lng: marker.lng },
                                map: map,
                                label: marker.label || null,
                                icon: {
                                    path: google.maps.SymbolPath.CIRCLE,
                                    fillColor: color,
                                    fillOpacity: 1,
                                    strokeColor: '#ffffff',
                                    strokeWeight: 2,
                                    scale: 8,
                                },
                            });
                        });
                    }
                    return;
                }

                console.warn('GeoMaps: No map library found. Include Leaflet or Google Maps.');
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initGeoEmbed);
            } else {
                initGeoEmbed();
            }
        })();
        JS;

        $view->registerJs($js, \craft\web\View::POS_END);

        $noscript = '';
        $staticUrl = (new StaticMap())->img([
            'center' => $center,
            'zoom'   => $this->zoom,
            'width'  => $this->width,
            'height' => $this->height,
        ]);
        if ($staticUrl) {
            $noscript = Html::tag('noscript',
                Html::tag('img', '', [
                    'src'    => $staticUrl,
                    'width'  => $this->width,
                    'height' => $this->height,
                    'alt'    => 'Map',
                    'style'  => $style,
                ])
            );
        }


          $html = Html::tag('div', $noscript, [
            'id'    => $id,
            'style' => $style,
            'data'  => ['geo-embed' => true],
        ]);

        return  new \Twig\Markup($html, 'UTF-8');
    }

    private function setOptions(array $options): void
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    private function resolveCenter(): array
    {
        $center = $this->center;

        if (is_array($center)) {
            $lat = $center['lat'] ?? $center[0] ?? null;
            $lng = $center['lng'] ?? $center[1] ?? null;
            if (is_numeric($lat) && is_numeric($lng)) {
                return ['lat' => (float) $lat, 'lng' => (float) $lng];
            }
        }

        if (is_string($center) && !empty(trim($center))) {
            $coords = GeoService::resolveLocation($center);
            if ($coords) {
                return $coords;
            }
        }

        if ($this->centerFallback) {
            $fb  = $this->centerFallback;
            $lat = $fb['lat'] ?? $fb[0] ?? null;
            $lng = $fb['lng'] ?? $fb[1] ?? null;
            if (is_numeric($lat) && is_numeric($lng)) {
                return ['lat' => (float) $lat, 'lng' => (float) $lng];
            }
        }

        return ['lat' => 0.0, 'lng' => 0.0];
    }

    private function resolveMarkers(array $center): array
    {
        if (empty($this->markers)) {
            return [['lat' => $center['lat'], 'lng' => $center['lng'], 'label' => null, 'color' => '#ff0000']];
        }

        $resolved = [];

        foreach ($this->markers as $marker) {
            $location = $marker['location'] ?? null;
            $coords   = null;

            if (is_array($location)) {
                $lat = $location['lat'] ?? $location[0] ?? null;
                $lng = $location['lng'] ?? $location[1] ?? null;
                if (is_numeric($lat) && is_numeric($lng)) {
                    $coords = ['lat' => (float) $lat, 'lng' => (float) $lng];
                }
            } elseif (is_string($location) && !empty($location)) {
                $coords = GeoService::resolveLocation($location);
            }

            if (!$coords) {
                $coords = $center;
            }

            $resolved[] = [
                'lat'   => $coords['lat'],
                'lng'   => $coords['lng'],
                'label' => $marker['label'] ?? null,
                'color' => $marker['color'] ?? '#ff0000',
            ];
        }

        return $resolved;
    }
}