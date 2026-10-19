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
use sup\craftgeo\web\EmbedAsset;

class EmbedMap extends Model
{
    public mixed   $center = null;
    public mixed   $centerFallback = null;
    public int     $width = 640;
    public int     $height = 400;
    public int     $zoom = 14;
    public int     $scale = 1;
    public array   $markers = [];
    public ?string $id = null;
    public array   $options = [];

    public function __toString(): string
    {
        return $this->embed();
    }

    public function embed(array $options = []): \Twig\Markup
    {
        $this->setOptions($options);

        $id = $this->id ?? 'geo_map_' . StringHelper::randomString(8);
        $settings = Geo::getInstance()->getSettings();
        $mapToken = $settings->getMapToken();
        $center = $this->resolveCenter();
        $markers = $this->resolveMarkers($center);

        $config = Json::encode([
            'id' => $id,
            'center' => $center,
            'zoom' => $this->zoom,
            'width' => $this->width,
            'height' => $this->height,
            'markers' => $markers,
            'options' => $this->options,
            'provider' => $settings->getMapProvider(),
            'tileUrl' => $settings->getTileUrl(),
            'tileAttribution' => $settings->tileAttribution,
        ]);

        $view = Craft::$app->getView();

        // Leaflet is always loaded so maps can fall back to OpenStreetMap if Google rejects the key.
        $view->registerAssetBundle(EmbedAsset::class);

        if ($mapToken) {
            // Catch Google rejecting the key (it reports this asynchronously via window.gm_authFailure).
            $view->registerJs(<<<'HOOK'
            if (!window.__supgeoAuthHooked) {
                window.__supgeoAuthHooked = true;
                var previous = window.gm_authFailure;
                window.gm_authFailure = function() {
                    window.__supgeoGoogleFailed = true;
                    window.dispatchEvent(new CustomEvent('supgeo:google-failed'));
                    if (typeof previous === 'function') previous();
                };
            }
            HOOK, \craft\web\View::POS_HEAD, 'supgeo-google-auth');
            $view->registerJsFile(
                'https://maps.googleapis.com/maps/api/js?' . http_build_query(['key' => $mapToken]),
                ['position' => \craft\web\View::POS_HEAD]
            );
        }

        $style = "width:{$this->width}px;height:{$this->height}px;";

        $js = <<<JS
        (function() {
            var config = {$config};

            function renderLeaflet(el) {
                if (typeof L === 'undefined') return;
                var map = L.map(el, config.options || {}).setView([config.center.lat, config.center.lng], config.zoom);
                L.tileLayer(config.tileUrl, { maxZoom: 19, attribution: config.tileAttribution }).addTo(map);
                (config.markers || []).forEach(function(marker) {
                    if (marker.lat == null || marker.lng == null) return;
                    var m = L.circleMarker([marker.lat, marker.lng], {
                        radius: 8, fillColor: marker.color || '#ff0000', color: '#fff', weight: 2, opacity: 1, fillOpacity: 0.9
                    });
                    if (marker.label) m.bindPopup(String(marker.label).replace(/[&<>"']/g, function(c) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                    }));
                    m.addTo(map);
                });
            }

            function renderGoogle(el) {
                var map = new google.maps.Map(el, Object.assign({
                    center: { lat: config.center.lat, lng: config.center.lng },
                    zoom: config.zoom,
                }, config.options || {}));
                (config.markers || []).forEach(function(marker) {
                    if (marker.lat == null || marker.lng == null) return;
                    new google.maps.Marker({
                        position: { lat: marker.lat, lng: marker.lng },
                        map: map,
                        title: marker.label || null,
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            fillColor: marker.color || '#ff0000', fillOpacity: 1,
                            strokeColor: '#ffffff', strokeWeight: 2, scale: 8,
                        },
                    });
                });
            }

            function init() {
                var el = document.getElementById(config.id);
                if (!el) return;

                var useGoogle = config.provider === 'google' && !window.__supgeoGoogleFailed
                    && typeof google !== 'undefined' && google.maps && google.maps.Map;

                if (!useGoogle) {
                    renderLeaflet(el);
                    return;
                }

                renderGoogle(el);

                // Google rejected the key: redraw this map with OpenStreetMap.
                window.addEventListener('supgeo:google-failed', function() {
                    var fresh = el.cloneNode(false);
                    el.parentNode.replaceChild(fresh, el);
                    renderLeaflet(fresh);
                }, { once: true });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        JS;

        $view->registerJs($js, \craft\web\View::POS_END);

        $noscript = '';
        $staticUrl = (new StaticMap())->img([
            'center' => $center,
            'zoom' => $this->zoom,
            'width' => $this->width,
            'height' => $this->height,
        ]);
        if ($staticUrl) {
            $noscript = Html::tag('noscript',
                Html::tag('img', '', [
                    'src' => $staticUrl,
                    'width' => $this->width,
                    'height' => $this->height,
                    'alt' => 'Map',
                    'style' => $style,
                ])
            );
        }


        $html = Html::tag('div', $noscript, [
            'id' => $id,
            'style' => $style,
            'data' => ['geo-embed' => true],
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
            $fb = $this->centerFallback;
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
            $coords = null;

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
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
                'label' => $marker['label'] ?? null,
                'color' => $marker['color'] ?? '#ff0000',
            ];
        }

        return $resolved;
    }
}
