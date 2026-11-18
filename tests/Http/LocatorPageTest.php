<?php

namespace suprim\geomaps\tests\Http;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use suprim\geomaps\Plugin;

/**
 * A page with a locator on it, at GEO_TEST_LOCATOR_PATH (default examples/locator) on GEO_TEST_URL.
 */
class LocatorPageTest extends TestCase
{
    private Client $client;
    private string $path;

    protected function setUp(): void
    {
        $url = getenv('GEO_TEST_URL');
        if (!$url) {
            $this->markTestSkipped('Set GEO_TEST_URL to the site’s base URL.');
        }
        if (!Plugin::getInstance()->isPro()) {
            $this->markTestSkipped('Searching an area needs Geo Maps Pro.');
        }
        $this->path = getenv('GEO_TEST_LOCATOR_PATH') ?: 'examples/locator';
        $this->client = new Client(['base_uri' => rtrim($url, '/') . '/', 'verify' => false, 'http_errors' => false, 'timeout' => 20]);
    }

    /**
     * @return array{0: int, 1: string[], 2: string}
     */
    private function search(array $near): array
    {
        $response = $this->client->get($this->path, ['query' => ['near' => $near]]);
        $html = (string)$response->getBody();
        preg_match_all('/class="geomaps-locator__name">([^<]+)</', $html, $m);

        return [$response->getStatusCode(), array_map('html_entity_decode', $m[1]), $html];
    }

    public function testSearchingAnAreaOnlyFindsWhatsInIt(): void
    {
        [$status, $nearby] = $this->search(['lat' => -34.93, 'lng' => 138.6, 'radius' => 100]);
        $this->assertSame(200, $status);
        if (count($nearby) < 2) {
            $this->markTestSkipped('The locator needs at least two locations within 100 km of Adelaide.');
        }

        [, $everywhere, $html] = $this->search(['lat' => -34.93, 'lng' => 138.6, 'within' => '-90,-180,90,180']);
        $this->assertSame($nearby, array_slice($everywhere, 0, count($nearby)), 'Still nearest first from the location.');
        $this->assertStringContainsString('in this area', $html);

        [, $nowhere, $html] = $this->search(['lat' => -34.93, 'lng' => 138.6, 'within' => '10,10,11,11']);
        $this->assertSame([], $nowhere);
        $this->assertStringContainsString('No locations in this area', $html);

        [$status] = $this->search(['within' => 'not a box']);
        $this->assertSame(200, $status, 'A bad area is ignored, not an error.');
    }
}
