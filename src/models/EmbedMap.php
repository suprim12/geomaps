<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace suprim\geomaps\models;

use Craft;
use craft\base\Model;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use suprim\geomaps\Plugin;
use suprim\geomaps\services\GeoService;
use suprim\geomaps\web\EmbedAsset;

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
    /** GeoJSON (array or string) to draw on the map. */
    public mixed   $geoJson = null;
    /** Whether to show a pin at the centre when no markers are given. */
    public bool    $marker = true;
    /** Zoom the map to fit the shapes. */
    public bool    $fitShapes = true;
    /** Default pin image for every marker: a URL, or `{ url, width, height }`. */
    public mixed   $icon = null;
    /** Info window HTML for the centre pin (when no `markers` are given). Not escaped. */
    public mixed   $html = null;

    public function __toString(): string
    {
        return $this->embed();
    }

    public function embed(array $options = []): \Twig\Markup
    {
        $this->setOptions($options);

        $id = $this->id ?? 'geo_map_' . StringHelper::randomString(8);
        $settings = Plugin::getInstance()->getSettings();
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
            'geoJson' => $this->resolveGeoJson(),
            'fitShapes' => $this->fitShapes,
            'options' => $this->options,
            'provider' => $settings->getMapProvider(),
            'tileUrl' => $settings->getTileUrl(),
            'tileAttribution' => $settings->tileAttribution,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); // safe inside <script>

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

            function escapeHtml(value) {
                return String(value).replace(/[&<>"']/g, function(c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            function renderLeaflet(el) {
                if (typeof L === 'undefined') return;
                var map = L.map(el, config.options || {}).setView([config.center.lat, config.center.lng], config.zoom);
                L.tileLayer(config.tileUrl, { maxZoom: 19, attribution: config.tileAttribution }).addTo(map);
                (config.markers || []).forEach(function(marker) {
                    if (marker.lat == null || marker.lng == null) return;
                    var m;
                    if (marker.icon) {
                        var w = marker.icon.width, h = marker.icon.height;
                        m = L.marker([marker.lat, marker.lng], {
                            title: marker.label || '',
                            alt: marker.label || '',
                            icon: L.icon({ iconUrl: marker.icon.url, iconSize: [w, h], iconAnchor: [w / 2, h], popupAnchor: [0, -h] }),
                        });
                    } else {
                        m = L.circleMarker([marker.lat, marker.lng], {
                            radius: 8, fillColor: marker.color || '#ff0000', color: '#fff', weight: 2, opacity: 1, fillOpacity: 0.9
                        });
                    }
                    var content = marker.html || (marker.label ? escapeHtml(marker.label) : null);
                    if (content) {
                        m.bindPopup(content, { maxWidth: 320 });
                    }
                    m.addTo(map);
                    if (content && marker.open) m.openPopup();
                });
                if (config.geoJson) {
                    var shapes = L.geoJSON(config.geoJson, {
                        style: function(feature) {
                            var p = (feature && feature.properties) || {};
                            return { color: p.stroke || p.color || '#2563eb', weight: 2, fillColor: p.fill || p.color || '#2563eb', fillOpacity: 0.2 };
                        },
                        onEachFeature: function(feature, layer) {
                            var name = feature && feature.properties && (feature.properties.name || feature.properties.title);
                            if (name) layer.bindPopup(escapeHtml(name));
                        },
                    }).addTo(map);
                    if (config.fitShapes && shapes.getLayers().length) map.fitBounds(shapes.getBounds(), { padding: [20, 20], maxZoom: 17 });
                }
            }

            function renderGoogle(el) {
                var map = new google.maps.Map(el, Object.assign({
                    center: { lat: config.center.lat, lng: config.center.lng },
                    zoom: config.zoom,
                }, config.options || {}));
                var infoWindow = new google.maps.InfoWindow({ maxWidth: 320 });
                (config.markers || []).forEach(function(marker) {
                    if (marker.lat == null || marker.lng == null) return;
                    var icon = marker.icon ? {
                        url: marker.icon.url,
                        scaledSize: new google.maps.Size(marker.icon.width, marker.icon.height),
                        anchor: new google.maps.Point(marker.icon.width / 2, marker.icon.height),
                    } : {
                        path: google.maps.SymbolPath.CIRCLE,
                        fillColor: marker.color || '#ff0000', fillOpacity: 1,
                        strokeColor: '#ffffff', strokeWeight: 2, scale: 8,
                    };
                    var m = new google.maps.Marker({
                        position: { lat: marker.lat, lng: marker.lng },
                        map: map,
                        title: marker.label || null,
                        icon: icon,
                    });
                    var content = marker.html || (marker.label ? escapeHtml(marker.label) : null);
                    if (content) {
                        var open = function() { infoWindow.setContent(content); infoWindow.open({ anchor: m, map: map }); };
                        m.addListener('click', open);
                        if (marker.open) open();
                    }
                });
                if (config.geoJson) {
                    var gj = config.geoJson.type === 'FeatureCollection' || config.geoJson.type === 'Feature'
                        ? config.geoJson : { type: 'Feature', properties: {}, geometry: config.geoJson };
                    map.data.addGeoJson(gj);
                    map.data.setStyle({ strokeColor: '#2563eb', strokeWeight: 2, fillColor: '#2563eb', fillOpacity: 0.2 });
                    if (config.fitShapes) {
                        var bounds = new google.maps.LatLngBounds();
                        map.data.forEach(function(f) { f.getGeometry().forEachLatLng(function(ll) { bounds.extend(ll); }); });
                        if (!bounds.isEmpty()) map.fitBounds(bounds, 20);
                    }
                }
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

    private function resolveGeoJson(): ?array
    {
        $geoJson = is_string($this->geoJson) ? json_decode($this->geoJson, true) : $this->geoJson;

        return is_array($geoJson) && !empty($geoJson['type']) ? $geoJson : null;
    }

    private function resolveMarkers(array $center): array
    {
        if (empty($this->markers) && !$this->marker) {
            return [];
        }

        $defaultIcon = $this->resolveIcon($this->icon);

        if (empty($this->markers)) {
            return [[
                'lat' => $center['lat'],
                'lng' => $center['lng'],
                'label' => null,
                'color' => '#ff0000',
                'icon' => $defaultIcon,
                'html' => $this->html !== null ? (string)$this->html : null,
                'open' => false,
            ]];
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

            $html = $marker['html'] ?? null;

            $resolved[] = [
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
                'label' => isset($marker['label']) ? (string)$marker['label'] : null,
                'color' => $marker['color'] ?? '#ff0000',
                'icon' => array_key_exists('icon', $marker) ? $this->resolveIcon($marker['icon']) : $defaultIcon,
                'html' => $html !== null && $html !== '' ? (string)$html : null,
                'open' => !empty($marker['open']),
            ];
        }

        return $resolved;
    }

    /**
     * Normalises a pin image: a URL string, or `{ url, width, height }` (default 32 × 32 pixels).
     *
     * @return array{url: string, width: int, height: int}|null
     */
    private function resolveIcon(mixed $icon): ?array
    {
        if (is_string($icon) || $icon instanceof \Stringable) {
            $icon = ['url' => (string)$icon];
        }

        if (!is_array($icon) || empty($icon['url'])) {
            return null;
        }

        return [
            'url' => (string)$icon['url'],
            'width' => max(1, (int)($icon['width'] ?? 32)),
            'height' => max(1, (int)($icon['height'] ?? $icon['width'] ?? 32)),
        ];
    }
}
