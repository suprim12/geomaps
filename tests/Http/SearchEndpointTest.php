<?php

namespace suprim\geomaps\tests\Http;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use suprim\geomaps\Plugin;

/**
 * The JSON search endpoint over HTTP, on the site at GEO_TEST_URL. Uses the first endpoint in config/geomaps.php.
 */
class SearchEndpointTest extends TestCase
{
    private Client $client;
    private string $endpoint;

    protected function setUp(): void
    {
        $url = getenv('GEO_TEST_URL');
        if (!$url) {
            $this->markTestSkipped('Set GEO_TEST_URL to the site’s base URL.');
        }
        $endpoints = Plugin::getInstance()->getSettings()->searchEndpoints;
        if (!$endpoints || !Plugin::getInstance()->isPro()) {
            $this->markTestSkipped('The site needs Geo Maps Pro and a search endpoint in config/geomaps.php.');
        }
        $this->endpoint = (string)array_key_first($endpoints);
        $this->client = new Client(['base_uri' => rtrim($url, '/') . '/', 'verify' => false, 'http_errors' => false, 'timeout' => 20]);
    }

    private function get(array $query): array
    {
        $response = $this->client->get('actions/geomaps/search', ['query' => $query]);
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        return [$response->getStatusCode(), json_decode((string)$response->getBody(), true)];
    }

    public function testNearestFirstWithDistances(): void
    {
        [$status, $data] = $this->get(['endpoint' => $this->endpoint, 'lat' => -34.9285, 'lng' => 138.6007, 'radius' => 100, 'limit' => 3]);

        $this->assertSame(200, $status);
        $this->assertSame(['results', 'total', 'limit', 'offset', 'unit', 'origin'], array_keys($data));
        $this->assertLessThanOrEqual(3, count($data['results']));
        $distances = array_column($data['results'], 'distance');
        $sorted = $distances;
        sort($sorted);
        $this->assertSame($sorted, $distances, 'Nearest first.');
        foreach ($data['results'] as $result) {
            $this->assertLessThanOrEqual(100, $result['distance']);
            $this->assertArrayHasKey('lat', $result['location']);
        }
    }

    public function testPagesThroughResults(): void
    {
        [, $all] = $this->get(['endpoint' => $this->endpoint, 'lat' => -34.9285, 'lng' => 138.6007, 'radius' => 100, 'limit' => 4]);
        [, $second] = $this->get(['endpoint' => $this->endpoint, 'lat' => -34.9285, 'lng' => 138.6007, 'radius' => 100, 'limit' => 2, 'offset' => 2]);

        $this->assertSame(array_slice(array_column($all['results'], 'id'), 2, 2), array_column($second['results'], 'id'));
        $this->assertSame($all['total'], $second['total']);
    }

    public function testBadRequestsSayWhatsWrong(): void
    {
        [$status, $data] = $this->get(['endpoint' => 'no-such-endpoint']);
        $this->assertSame(404, $status);
        $this->assertArrayHasKey('error', $data);

        [$status, $data] = $this->get(['endpoint' => $this->endpoint, 'within' => 'not,a,box']);
        $this->assertSame(400, $status);
        $this->assertSame('within must be south,west,north,east.', $data['error']);
    }
}
