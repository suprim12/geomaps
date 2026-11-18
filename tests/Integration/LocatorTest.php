<?php

namespace suprim\geomaps\tests\Integration;

use Craft;
use suprim\geomaps\models\Locator;
use suprim\geomaps\tests\Fixture;
use suprim\geomaps\tests\TestCase;

class LocatorTest extends TestCase
{
    private function render(array $options = []): string
    {
        return (string)(new Locator())->render($options + ['field' => Fixture::FIELD, 'section' => Fixture::SECTION]);
    }

    public function testSearchesLiveOnlyOnPro(): void
    {
        $this->place('Norwood', 'norwood');

        $this->lite();
        $lite = $this->render();
        $this->assertStringNotContainsString('data-live', $lite);
        $this->assertStringNotContainsString('data-locator-area', $lite);
        $this->assertStringNotContainsString('[within]', $lite);
        $this->assertStringContainsString('Norwood', $lite);

        $this->pro();
        $pro = $this->render();
        $this->assertStringContainsString('data-live', $pro);
        $this->assertStringContainsString('data-locator-area', $pro);
        $this->assertStringContainsString('name="near[within]"', $pro);
        preg_match('/data-markers="([^"]*)"/', $pro, $m);
        $markers = json_decode(html_entity_decode($m[1]), true);
        $this->assertSame(Fixture::ADDRESSES['norwood']['lat'], $markers[0]['lat'], 'The pins, for swapping in after a live search.');
        $this->assertSame('Norwood', $markers[0]['label']);

        $this->assertStringNotContainsString('data-live', $this->render(['live' => false]));
        $this->assertStringNotContainsString('data-locator-area', $this->render(['searchArea' => false]));
    }

    public function testTheSameLocatorHasTheSameIdOnEveryRequest(): void
    {
        $prop = new \ReflectionProperty(Locator::class, '_rendered');
        $prop->setValue(null, 0);
        preg_match('/id="(geomaps-locator-\d+)"/', $this->render(), $first);
        $prop->setValue(null, 0);
        preg_match('/id="(geomaps-locator-\d+)"/', $this->render(), $again);

        $this->assertSame($first[1], $again[1]);
        $this->assertSame('mine', (new \DOMDocument())->loadHTML('<?xml encoding="utf-8"?>' . $this->render(['id' => 'mine']), LIBXML_NOERROR) ? 'mine' : null);
    }

    public function testFiltersAreRenderedInsideTheForm(): void
    {
        $dir = Craft::getAlias('@templates') . '/_geomaps-test';
        @mkdir($dir);
        file_put_contents("$dir/filters.twig", '<select name="type"><option value="">All</option><option value="parking">Parking</option></select>');

        try {
            $html = $this->render(['filtersTemplate' => '_geomaps-test/filters']);
        } finally {
            unlink("$dir/filters.twig");
            rmdir($dir);
        }

        $this->assertMatchesRegularExpression('/<form[^>]*data-locator-form.*data-locator-filters><select name="type">.*<\/form>/s', $html);
    }
}
