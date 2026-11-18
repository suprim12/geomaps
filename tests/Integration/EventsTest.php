<?php

namespace suprim\geomaps\tests\Integration;

use Craft;
use craft\elements\Entry;
use suprim\geomaps\events\GeocodeEvent;
use suprim\geomaps\events\MapEvent;
use suprim\geomaps\events\SearchResultEvent;
use suprim\geomaps\models\EmbedMap;
use suprim\geomaps\models\Locator;
use suprim\geomaps\services\GeoService;
use suprim\geomaps\tests\Fixture;
use suprim\geomaps\tests\TestCase;
use yii\base\Event;

class EventsTest extends TestCase
{
    /** @var array<int, array{class-string, string, callable}> */
    private array $handlers = [];

    protected function tearDown(): void
    {
        foreach ($this->handlers as [$class, $name, $handler]) {
            Event::off($class, $name, $handler);
        }
        parent::tearDown();
    }

    private function on(string $class, string $name, callable $handler): void
    {
        // First in line: the fixture’s handler answers every lookup and stops the others.
        Event::on($class, $name, $handler, null, false);
        $this->handlers[] = [$class, $name, $handler];
    }

    public function testBeforeGeocodeCanAnswerTheLookup(): void
    {
        $seen = [];
        $this->on(GeoService::class, GeoService::EVENT_BEFORE_GEOCODE, function(GeocodeEvent $e) use (&$seen) {
            $seen[] = [$e->address, $e->country, $e->purpose];
            if ($e->address === 'Head office') {
                $e->result = ['lat' => -33.0, 'lng' => 151.0];
                $e->handled = true;
            }
        });

        $this->assertSame(['lat' => -33.0, 'lng' => 151.0], GeoService::geocode('Head office', 'au'));
        $this->assertSame(['lat' => -33.0, 'lng' => 151.0], GeoService::geocodeForSaving('Head office'));
        $this->assertSame([['Head office', 'AU', 'search'], ['Head office', null, 'save']], $seen);
    }

    public function testAfterGeocodeCanChangeTheResult(): void
    {
        $this->on(GeoService::class, GeoService::EVENT_AFTER_GEOCODE, function(GeocodeEvent $e) {
            if ($e->result) {
                $e->result['lat'] = round($e->result['lat'], 1);
            }
        });
        $this->on(GeoService::class, GeoService::EVENT_AFTER_GEOCODE, function(GeocodeEvent $e) {
            if ($e->address === 'Glenelg') {
                $e->result = null;
            }
        });

        $this->assertSame(-34.9, GeoService::geocode('Adelaide')['lat']);
        $this->assertNull(GeoService::geocode('Glenelg'), 'A handler can reject a result.');
        $this->assertSame(-34.9, GeoService::resolveLocation('Adelaide')['lat']);
    }

    public function testMapsCanBeChangedBeforeTheyreOutput(): void
    {
        $this->on(EmbedMap::class, EmbedMap::EVENT_BEFORE_RENDER, function(MapEvent $e) {
            $e->options['markers'][] = ['location' => ['lat' => -34.5, 'lng' => 138.5], 'label' => 'Added by an event'];
            $e->options['zoom'] = 7;
        });

        $html = (string)(new EmbedMap())->embed(['center' => Fixture::ADDRESSES['adelaide'], 'markers' => [['location' => Fixture::ADDRESSES['norwood'], 'label' => 'Norwood']]]);
        $js = implode("\n", array_merge(...array_values(Craft::$app->getView()->js)));

        $this->assertStringContainsString('data-geo-embed', $html);
        $this->assertStringContainsString('Added by an event', $js);
        $this->assertStringContainsString('Norwood', $js);
        $this->assertStringContainsString('"zoom":7', $js);
    }

    public function testLocatorResultsCanBeChanged(): void
    {
        $this->place('Norwood', 'norwood');
        $this->on(Locator::class, Locator::EVENT_DEFINE_RESULT, function(SearchResultEvent $e) {
            $this->assertInstanceOf(Entry::class, $e->element);
            $this->assertSame('Norwood', $e->element->title);
            $e->result['address'] = 'Changed by an event';
        });

        $html = (string)(new Locator())->render(['field' => Fixture::FIELD, 'section' => Fixture::SECTION]);

        $this->assertStringContainsString('Changed by an event', $html);
    }
}
