---
layout: home

hero:
  text: Geo Maps for Craft CMS
  tagline: Pin addresses, draw zones and find the nearest entries. Works with free OpenStreetMap maps out of the box, or Google Maps when you add a key.
  actions:
    - theme: brand
      text: Get started
      link: /getting-started/installation
    - theme: alt
      text: See the examples
      link: /examples/store-locator
---

<div class="home">

<figure class="home-hero-shot">
  <img src="/images/field.jpg" alt="The Geo Maps field in the Craft control panel: address search, a map with drawing tools, and the street, suburb, postcode and coordinates beside it" width="1400" height="1169">
  <figcaption>The field authors use: search an address, drop the pin or draw areas. The street, suburb, postcode and coordinates fill in.</figcaption>
</figure>

<section class="home-row">
  <div class="home-copy">
    <h2>Show the nearest first</h2>
    <p>Search entries within a radius of an address, a postcode or the visitor’s own location. Results come back nearest first, each with its distance, and they work with pagination, categories and keyword search.</p>
    <p><a href="/examples/store-locator">Build a store locator →</a></p>
  </div>
  <img src="/examples/store-locator.jpg" alt="A store locator listing the nearest stores to Adelaide with distances, a map and pagination" width="1400" height="1455" loading="lazy">
</section>

<section class="home-row home-row--flip">
  <div class="home-copy">
    <h2>Which zone is this address in?</h2>
    <p>Draw delivery areas, sales territories or bin collection zones, then ask which one contains an address. Holes are respected and a house on a shared border belongs to one zone, never two.</p>
    <p><a href="/examples/zone-lookup">Look up a bin day →</a></p>
  </div>
  <img src="/examples/zone-lookup.jpg" alt="An address search showing its bin collection zone, the collection day and the zone on a map" width="1400" height="987" loading="lazy">
</section>

<section class="home-row">
  <div class="home-copy">
    <h2>Your pins, your info windows</h2>
    <p>Front-end maps take your own pin image and any HTML for the card that opens when a pin is clicked. One Twig call, on OpenStreetMap or Google.</p>
    <p><a href="/examples/custom-pins">Style your markers →</a></p>
  </div>
  <img src="/examples/custom-pins.jpg" alt="A map with purple custom pins and an info window showing a store’s name, address and directions link" width="1400" height="949" loading="lazy">
</section>

<section class="home-row home-row--flip">
  <div class="home-copy">
    <h2>No API key needed</h2>
    <p>Maps, address search and geocoding run on OpenStreetMap from the moment you install. Add Google keys when you want Google tiles, Places search or static map images. If Google ever rejects a key, maps quietly switch back to OpenStreetMap.</p>
    <p><a href="/getting-started/configuration">See the settings →</a></p>
  </div>
  <img src="/images/plugin-settings.jpg" alt="The Geo Maps plugin settings: optional Google keys, and the OpenStreetMap tiles URL and credit" width="1400" height="956" loading="lazy">
</section>

<section class="home-band home-end">
<div class="home-copy">
<h2>Bring your existing fields</h2>
<p>Already using another map plugin? Convert its fields in place from <strong>Utilities → Geo Maps Import</strong>, or from the terminal. Handles, field layouts and saved values carry over, drafts and revisions included, and common template code keeps working.</p>

```bash
php craft geomaps/import
```

<p><a href="/getting-started/importing">How importing works →</a></p>
</div>
<div class="home-copy">
<h2>Install in a minute</h2>
<p>Geo Maps needs Craft CMS 5.10 or later and PHP 8.2. Install it from the Plugin Store, or with Composer:</p>

```bash
composer require suprim/geomaps
php craft plugin/install geomaps
```

<p><a class="home-button" href="/getting-started/installation">Read the installation guide</a></p>
</div>
</section>

</div>
