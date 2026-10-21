<script setup>
const examples = [
  { href: '/examples/delivery-fee', img: '/examples/delivery-fee.jpg', name: 'Delivery fee checker', line: 'Price by zone, or by distance from the nearest store.', alt: 'A $5 delivery fee for an address inside the CBD delivery zone' },
  { href: '/examples/zone-lookup', img: '/examples/zone-lookup.jpg', name: 'Bin collection day', line: 'Which zone is an address in, and the next three dates.', alt: 'Monday bin day for an address in Norwood, with its zone on a map' },
  { href: '/examples/live-finder', img: '/examples/live-finder.jpg', name: 'Live map search', line: 'Results that follow the map, from a JSON endpoint.', alt: 'Five numbered stores beside a map with matching pins' },
  { href: '/examples/area-map', img: '/examples/area-map.jpg', name: 'Map of zones', line: 'Every area on one map, coloured, with a legend.', alt: 'Three collection zones in blue, green and orange with a legend' },
  { href: '/examples/location-page', img: '/examples/location-page.jpg', name: 'Location page', line: 'Address, directions and the nearest other stores.', alt: 'A store page with its address, directions button and nearby stores' },
  { href: '/examples/trail-maps', img: '/examples/trail-maps.jpg', name: 'Trail maps', line: 'Routes drawn as lines, coloured by difficulty, with their length.', alt: 'Three Adelaide trails beside a map, coloured by difficulty' },
  { href: '/examples/custom-pins', img: '/examples/custom-pins.jpg', name: 'Custom pins and cards', line: 'Your own pin image and an info card on click.', alt: 'Store pins on a map of Adelaide with an info card open' },
]
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import 'leaflet/dist/leaflet.css'

// ---------------------------------------------------------------------------
// The docs' sample data: the same stores and bin zones the examples use.
// ---------------------------------------------------------------------------

const STORES = [
  { name: 'Adelaide CBD Store', lat: -34.9285, lng: 138.6007 },
  { name: 'Norwood Store', lat: -34.921, lng: 138.631 },
  { name: 'Glenelg Store', lat: -34.98, lng: 138.513 },
  { name: 'Port Adelaide Store', lat: -34.846, lng: 138.503 },
  { name: 'Mount Barker Store', lat: -35.067, lng: 138.859 },
  { name: 'Victor Harbor Store', lat: -35.552, lng: 138.62 },
  { name: 'Melbourne CBD Store', lat: -37.8136, lng: 144.9631 },
  { name: 'St Kilda Store', lat: -37.8676, lng: 144.9809 },
  { name: 'Darwin Store', lat: -12.4634, lng: 130.8456 },
  { name: 'Perth Store', lat: -31.9523, lng: 115.8613 },
  { name: 'Hobart Store', lat: -42.8821, lng: 147.3272 },
  { name: 'Canberra Store', lat: -35.2809, lng: 149.13 },
  { name: 'Sydney CBD Store', lat: -33.8688, lng: 151.2093 },
  { name: 'Bondi Store', lat: -33.8915, lng: 151.2767 },
  { name: 'Brisbane Store', lat: -27.4698, lng: 153.0251 },
  { name: 'Gold Coast Store', lat: -28.0023, lng: 153.4145 },
]

const rect = (w, e, n, s) => [[w, n], [e, n], [e, s], [w, s], [w, n]]

const ZONES = [
  { name: 'Bin Zone A', area: 'Norwood', day: 'Monday', color: '#717ef0', rings: [rect(138.62, 138.645, -34.915, -34.93)] },
  { name: 'Bin Zone B', area: 'Kent Town', day: 'Tuesday', color: '#2f9e6e', rings: [rect(138.6, 138.62, -34.91, -34.93)] },
  { name: 'Bin Zone C', area: 'St Peters', day: 'Wednesday', color: '#d88a1d', rings: [rect(138.62, 138.64, -34.895, -34.91), rect(138.624, 138.632, -34.899, -34.906)] },
]

const ADELAIDE = { lat: -34.9285, lng: 138.6007 }

function km(a, b) {
  const r = Math.PI / 180
  const x = Math.sin(a.lat * r) * Math.sin(b.lat * r) + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.cos((a.lng - b.lng) * r)
  return 6371 * Math.acos(Math.min(1, Math.max(-1, x)))
}

function inRing(lng, lat, ring) {
  let inside = false
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const [xi, yi] = ring[i]
    const [xj, yj] = ring[j]
    if ((yi > lat) !== (yj > lat) && lng < ((xj - xi) * (lat - yi)) / (yj - yi) + xi) inside = !inside
  }
  return inside
}

// Outer ring in, holes out: the same rule the plugin's `contains` search uses.
const zoneAt = (p) => ZONES.find((z) => inRing(p.lng, p.lat, z.rings[0]) && !z.rings.slice(1).some((h) => inRing(p.lng, p.lat, h)))

const fmt = (n) => n.toFixed(4)
const dist = (n) => (n < 10 ? n.toFixed(1) : Math.round(n).toLocaleString('en'))

// ---------------------------------------------------------------------------
// Hero: drop a pin
// ---------------------------------------------------------------------------

const pin = ref({ lat: -34.9213, lng: 138.6305 }) // 175 The Parade, Norwood
const zone = computed(() => zoneAt(pin.value))
const nearest = computed(() =>
  STORES.map((s) => ({ ...s, d: km(pin.value, s) })).sort((a, b) => a.d - b.d).slice(0, 3),
)

// ---------------------------------------------------------------------------
// Showcase: edit the Twig, see the results
// ---------------------------------------------------------------------------

const radius = ref(25)
const unit = ref('km')
const results = computed(() => {
  const factor = unit.value === 'mi' ? 0.621371 : 1
  const r = Math.max(0, Number(radius.value) || 0)
  return STORES.map((s) => ({ ...s, d: km(ADELAIDE, s) * factor })).filter((s) => s.d <= r).sort((a, b) => a.d - b.d)
})

// ---------------------------------------------------------------------------
// How it works: the pinned frame follows the step being read
// ---------------------------------------------------------------------------

const step = ref(0)
const copied = ref('')

async function copy(text, key) {
  try {
    await navigator.clipboard.writeText(text)
    copied.value = key
    setTimeout(() => (copied.value = ''), 1600)
  } catch {
    /* clipboard unavailable: the text is still selectable */
  }
}

const adelaideTime = ref('')

let map
let observer
let timer

onMounted(async () => {
  const L = (await import('leaflet')).default
  const el = document.getElementById('gh-live-map')
  if (!el) return

  map = L.map(el, { scrollWheelZoom: false, zoomControl: true, attributionControl: true }).setView([-34.915, 138.62], 14)
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(map)

  for (const z of ZONES) {
    L.polygon(z.rings.map((ring) => ring.map(([lng, lat]) => [lat, lng])), {
      color: z.color, weight: 2, fillColor: z.color, fillOpacity: 0.18,
    }).addTo(map).bindTooltip(`${z.name} · ${z.day}`)
  }

  for (const s of STORES.slice(0, 5)) {
    L.circleMarker([s.lat, s.lng], { radius: 6, color: '#fff', weight: 2, fillColor: '#15171f', fillOpacity: 1 })
      .addTo(map).bindTooltip(s.name)
  }

  const pinIcon = L.divIcon({
    className: 'gh-pin',
    html: '<svg width="30" height="40" viewBox="0 0 30 40" aria-hidden="true"><path d="M15 39s13-12.6 13-23A13 13 0 0 0 2 16c0 10.4 13 23 13 23z" fill="#717ef0" stroke="#fff" stroke-width="2"/><circle cx="15" cy="15.5" r="5" fill="#fff"/></svg>',
    iconSize: [30, 40],
    iconAnchor: [15, 39],
  })
  const marker = L.marker([pin.value.lat, pin.value.lng], { icon: pinIcon, draggable: true, keyboard: true, title: 'Dropped pin' }).addTo(map)

  const place = (latlng) => {
    pin.value = { lat: latlng.lat, lng: latlng.lng }
    marker.setLatLng(latlng)
  }
  map.on('click', (e) => place(e.latlng))
  marker.on('drag', (e) => place(e.target.getLatLng()))

  // Pinned story: whichever step is in the middle of the screen drives the frame.
  observer = new IntersectionObserver((entries) => {
    for (const entry of entries) {
      if (entry.isIntersecting) step.value = Number(entry.target.dataset.step)
    }
  }, { rootMargin: '-45% 0px -45% 0px' })
  document.querySelectorAll('.gh-steps [data-step]').forEach((s) => observer.observe(s))

  const tick = () => {
    adelaideTime.value = new Intl.DateTimeFormat('en-AU', { timeZone: 'Australia/Adelaide', hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date())
  }
  tick()
  timer = setInterval(tick, 30000)
})

onBeforeUnmount(() => {
  map?.remove()
  observer?.disconnect()
  clearInterval(timer)
})

const embedCmd = '{{ entry.storeLocation.embed({ height: 400 }) }}'
const installCmd = 'composer require suprim/geomaps && php craft plugin/install geomaps'
</script>

<template>
  <div class="gh">
    <!-- Hero ------------------------------------------------------------- -->
    <section class="gh-hero">
      <div class="gh-wrap gh-hero__head" data-reveal>
        <h1>Location fields for Craft CMS</h1>
        <p class="gh-lead">Pin addresses, draw zones and find the nearest entries, on free OpenStreetMap maps. Click anywhere on the map below to try it.</p>
        <p class="gh-actions">
          <a class="gh-btn" href="/getting-started/installation">Get started</a>
          <a class="gh-link gh-link--light" href="/examples/store-locator">See the examples →</a>
        </p>
      </div>

      <div class="gh-wrap">
        <div class="gh-live" data-ui data-reveal>
          <div id="gh-live-map" class="gh-live__map" role="application" aria-label="Map of Adelaide's inner east. Click to drop a pin."></div>
          <div class="gh-live__panel" aria-live="polite">
            <p class="gh-live__label">Dropped pin</p>
            <p class="gh-live__coords">{{ fmt(pin.lat) }}, {{ fmt(pin.lng) }}</p>

            <div class="gh-live__zone" :style="{ '--zone': zone ? zone.color : '#9aa0ad' }">
              <template v-if="zone">
                <strong>{{ zone.name }} · {{ zone.area }}</strong>
                <span>Bins collected every {{ zone.day }}</span>
              </template>
              <template v-else>
                <strong>Not in a collection zone</strong>
                <span>Try inside a coloured area. The park cut out of Zone C doesn’t count.</span>
              </template>
            </div>

            <p class="gh-live__label">Nearest stores</p>
            <ol class="gh-live__list">
              <li v-for="s in nearest" :key="s.name"><span>{{ s.name }}</span><b>{{ dist(s.d) }} km</b></li>
            </ol>

            <pre class="gh-live__code"><code><span class="t-k">{%</span> set zone = craft.entries.section(<span class="t-s">'binZones'</span>)
  .zoneArea({ contains: { lat: <span class="t-n">{{ fmt(pin.lat) }}</span>, lng: <span class="t-n">{{ fmt(pin.lng) }}</span> } })
  .one() <span class="t-k">%}</span></code></pre>
          </div>
        </div>
      </div>
    </section>

    <!-- Showcase: input half, output half --------------------------------- -->
    <section class="gh-split" aria-labelledby="gh-split-title">
      <div class="gh-split__in">
        <h2 id="gh-split-title" class="gh-split__title gh-split__title--a">Ask in Twig,</h2>
        <pre class="gh-split__code" data-ui><code><span class="gh-dim">{# Change the radius or the unit #}</span>
<span class="t-k">{%</span> set stores = craft.entries()
  .section(<span class="t-s">'stores'</span>)
  .storeLocation({
    location: <span class="t-s">'Adelaide CBD'</span>,
    radius: <label class="gh-edit"><span class="sr-only">Radius</span><input v-model="radius" type="number" min="1" max="5000" inputmode="numeric"></label>,
    unit: <label class="gh-edit"><span class="sr-only">Unit</span><select v-model="unit"><option value="km">'km'</option><option value="mi">'mi'</option></select></label>,
  })
  .all() <span class="t-k">%}</span></code></pre>
      </div>
      <div class="gh-split__out">
        <div class="gh-results" data-ui aria-live="polite">
          <p class="gh-results__head">{{ results.length }} store{{ results.length === 1 ? '' : 's' }} within {{ radius || 0 }} {{ unit }} · nearest first</p>
          <ol>
            <li v-for="s in results.slice(0, 7)" :key="s.name"><span>{{ s.name }}</span><b>{{ dist(s.d) }} {{ unit }}</b></li>
          </ol>
          <p v-if="results.length > 7" class="gh-results__more">and {{ results.length - 7 }} more</p>
          <p v-if="!results.length" class="gh-results__more">No stores that close. Try 50.</p>
        </div>
        <p class="gh-split__title gh-split__title--b" aria-hidden="true">get places back.</p>
      </div>
    </section>

    <!-- Features ------------------------------------------------------------ -->
    <section class="gh-wrap gh-features" aria-labelledby="gh-features-title">
      <h2 id="gh-features-title" data-reveal>Everything a map field needs</h2>
      <div class="gh-cells">
        <article class="gh-cell" data-reveal>
          <div class="gh-cell__text"><h3>Address search</h3><p>Type a street or a business. Street, suburb, postcode and coordinates fill in by themselves.</p></div>
          <img src="/home/search.jpg" width="1400" height="707" alt="The field's address search listing places on Rundle Mall, Adelaide" loading="lazy">
        </article>
        <article class="gh-cell gh-cell--flip" data-reveal>
          <div class="gh-cell__text"><h3>Zones as GeoJSON</h3><p>Draw areas with holes, give them properties and edit everything as GeoJSON beside the map.</p></div>
          <img src="/home/geojson.jpg" width="1400" height="1033" alt="A zone with a hole drawn on the map, its GeoJSON open beside it" loading="lazy">
        </article>
        <article class="gh-cell" data-reveal>
          <div class="gh-cell__text"><h3>Your own pins</h3><p>Front-end maps take your pin image and any HTML for the card that opens on click.</p></div>
          <img src="/home/pins.jpg" width="1400" height="764" alt="Purple store pins on a map with an info window open" loading="lazy">
        </article>
        <article class="gh-cell gh-cell--flip" data-reveal>
          <div class="gh-cell__text"><h3>Settings per field</h3><p>Hide the pin for zone fields, turn drawing off, limit search to five countries.</p></div>
          <img src="/home/settings.jpg" width="1400" height="925" alt="Field settings: Enable Drawing, Show Location Pin, Hide Search, Hide Address and Hide Map switches" loading="lazy">
        </article>
        <article class="gh-cell" data-reveal>
          <div class="gh-cell__text"><h3>No API key needed</h3><p>OpenStreetMap from the moment you install. Add Google keys only if you want Google.</p></div>
          <img src="/home/no-key.jpg" width="1400" height="410" alt="Plugin settings: Google Maps keys are optional" loading="lazy">
        </article>
        <article class="gh-cell gh-cell--flip" data-reveal>
          <div class="gh-cell__text"><h3>Ready for GraphQL</h3><p>Read locations, search by distance and find zones from a headless front end.</p></div>
          <pre class="gh-cell__code" data-ui><code>{
  entries(section: <span class="t-s">"stores"</span>,
    storeLocation: { location: <span class="t-s">"Glenelg"</span>, radius: <span class="t-n">10</span> }) {
    title
    ... on store_Entry {
      storeLocation { full_address distance }
    }
  }
}</code></pre>
        </article>
      </div>
    </section>

    <!-- Shape details ----------------------------------------------------------- -->
    <section class="gh-shape" aria-labelledby="gh-shape-title">
      <div class="gh-wrap gh-shape__grid">
        <figure class="gh-shape__shot" data-reveal>
          <img src="/images/shape-panel-zone.jpg" width="1400" height="996" alt="Clicking a zone opens its panel: the name Bin Zone C, “Area · 2.48 km²”, colour swatches and a collectionDay property" loading="lazy">
        </figure>
        <div class="gh-shape__text">
          <h2 id="gh-shape-title" data-reveal>Every shape carries its own details</h2>
          <p data-reveal>Click a zone, route or pin to name it, colour it and give it the facts your site needs: a collection day, a delivery fee, a trail’s difficulty. They’re saved with the shape and ready in Twig.</p>
          <ul class="gh-shape__list" data-reveal>
            <li><strong>Measured as you draw.</strong> Areas show their size with holes left out, lines their length. <code>area()</code> and <code>length()</code> return the same numbers in templates.</li>
            <li><strong>Typed values.</strong> Text, numbers, true/false, colours or JSON.</li>
            <li><strong>Styled on the front end.</strong> The colour you pick is the colour visitors see.</li>
          </ul>
          <p data-reveal><a class="gh-link" href="/guides/field#the-shape-panel">More about drawing shapes →</a></p>
        </div>
      </div>
    </section>

    <!-- Adelaide -------------------------------------------------------------- -->
    <section class="gh-ex" aria-labelledby="gh-ex-title">
      <div class="gh-wrap">
        <h2 id="gh-ex-title" data-reveal>Built with Geo Maps</h2>
        <p class="gh-ex__intro" data-reveal>Complete templates you can copy, each running on real Adelaide streets.</p>
        <a class="gh-ex__lead" href="/examples/store-locator" data-reveal>
          <img src="/examples/store-locator.jpg" width="1800" height="1054" alt="A store locator with numbered stores beside a map of Adelaide" loading="lazy">
          <span class="gh-ex__text">
            <span class="gh-ex__name">Store locator</span>
            <span class="gh-ex__line">Search by address or “use my location”, numbered pins, distances and pagination.</span>
          </span>
        </a>
        <ul class="gh-ex__grid">
          <li v-for="ex in examples" :key="ex.href" data-reveal>
            <a :href="ex.href">
              <img :src="ex.img" width="1800" height="1054" :alt="ex.alt" loading="lazy">
              <span class="gh-ex__name">{{ ex.name }}</span>
              <span class="gh-ex__line">{{ ex.line }}</span>
            </a>
          </li>
        </ul>
      </div>
    </section>

    <section class="gh-city" aria-labelledby="gh-city-title">
      <div class="gh-wrap gh-city__grid">
        <figure class="gh-city__plan" data-reveal>
          <img src="/world/light-plan-of-adelaide-1840.jpg" width="847" height="1400" alt="An 1840 engraving of the City of Adelaide street grid, with the acre allotments numbered, surveyed by Colonel Light" loading="lazy">
          <figcaption>The City of Adelaide, surveyed by Col. Light. Engraved by John Arrowsmith, 1840.</figcaption>
        </figure>
        <div class="gh-city__text">
          <h2 id="gh-city-title" data-reveal>Every example runs on Adelaide</h2>
          <p data-reveal>Colonel Light laid out Adelaide’s grid in 1837. The stores, zones and addresses in these docs sit on the same streets, from 1 King William Street to The Parade in Norwood, so every example can be tried with real places.</p>
          <figure data-reveal>
            <img src="/world/king-william-street-adelaide.jpg" width="1400" height="1050" alt="King William Street, Adelaide, seen from above, with trams and early twentieth-century buildings" loading="lazy">
            <figcaption>King William Street, home of the docs’ first store. Photograph by Frank Hurley.</figcaption>
          </figure>
          <figure data-reveal>
            <img src="/world/hurtle-square-children-sitting-under-tree.jpg" width="1400" height="1050" alt="Children sitting under a tree in Hurtle Square, Adelaide" loading="lazy">
            <figcaption>Hurtle Square, one of the five squares on Light’s plan.</figcaption>
          </figure>
        </div>
      </div>
    </section>

    <!-- How it works: pinned frame ---------------------------------------------- -->
    <section class="gh-wrap gh-how" aria-labelledby="gh-how-title">
      <h2 id="gh-how-title" data-reveal>Three steps to a map</h2>
      <div class="gh-how__grid">
        <div class="gh-steps">
          <div class="gh-step" data-step="0">
            <p class="gh-step__say">First, install it.</p>
            <div class="gh-call">
              <pre><code>composer require suprim/geomaps
php craft plugin/install geomaps</code></pre>
              <button type="button" class="gh-copy" @click="copy(installCmd, 'install')">{{ copied === 'install' ? 'Copied' : 'Copy' }}</button>
            </div>
          </div>
          <div class="gh-step" data-step="1">
            <p class="gh-step__say">Then add the field.</p>
            <p class="gh-step__note">Settings → Fields → New field, type Geo Maps. Authors search an address, drop the pin or draw areas.</p>
          </div>
          <div class="gh-step" data-step="2">
            <p class="gh-step__say">Then show it.</p>
            <div class="gh-call">
              <pre v-pre><code><span class="t-k">{{</span> entry.storeLocation.embed({ height: <span class="t-n">400</span> }) <span class="t-k">}}</span></code></pre>
              <button type="button" class="gh-copy" @click="copy(embedCmd, 'embed')">{{ copied === 'embed' ? 'Copied' : 'Copy' }}</button>
            </div>
          </div>
        </div>
        <div class="gh-frame" aria-hidden="true">
          <div class="gh-frame__inner">
            <pre class="gh-frame__slide gh-frame__slide--term" :class="{ 'is-on': step === 0 }"><code><span class="t-k">$</span> composer require suprim/geomaps
<span class="gh-dim">  - Installing suprim/geomaps (1.1.0)</span>
<span class="t-k">$</span> php craft plugin/install geomaps
<span class="gh-ok">*** installed geomaps successfully</span></code></pre>
            <img class="gh-frame__slide" :class="{ 'is-on': step === 1 }" src="/home/field.jpg" width="1400" height="1169" alt="" loading="lazy">
            <img class="gh-frame__slide" :class="{ 'is-on': step === 2 }" src="/examples/zone-lookup.jpg" width="1400" height="987" alt="" loading="lazy">
          </div>
        </div>
      </div>
    </section>

    <!-- Close ----------------------------------------------------------------- -->
    <section class="gh-close">
      <div class="gh-wrap">
        <h2>Put your places on the map.</h2>
        <div class="gh-close__row">
          <pre class="gh-close__cmd"><code>composer require suprim/geomaps</code></pre>
          <button type="button" class="gh-btn" @click="copy('composer require suprim/geomaps', 'close')">{{ copied === 'close' ? 'Copied' : 'Copy command' }}</button>
          <a class="gh-link gh-link--light" href="/getting-started/installation">Installation guide →</a>
        </div>
      </div>
    </section>

    <!-- Footer tiles ----------------------------------------------------------- -->
    <footer class="gh-foot">
      <div class="gh-tile gh-tile--about">
        <p class="gh-tile__big">A Craft CMS plugin by suprim.</p>
        <p class="gh-tile__small">Map data © OpenStreetMap contributors. Photographs: “King William Street, Adelaide” by Frank Hurley and “Hurtle Square” by the State Government Photographer, both CC0 via Wikimedia Commons. Plan of Adelaide by W. Light and J. Arrowsmith, 1840, public domain via Wikimedia Commons.</p>
      </div>
      <nav class="gh-tile gh-tile--nav" aria-label="Docs">
        <a href="/getting-started/installation">Installation</a>
        <a href="/examples/store-locator">Examples</a>
        <a href="/guides/search">Location search</a>
        <a href="/getting-started/troubleshooting">Troubleshooting</a>
        <a href="/privacy">Privacy policy</a>
        <a href="https://github.com/suprim12/geomaps">GitHub</a>
      </nav>
      <div class="gh-tile gh-tile--time">
        <p class="gh-tile__small">Adelaide, where the examples live</p>
        <p class="gh-tile__clock">{{ adelaideTime || '—' }}</p>
      </div>
      <div class="gh-tile gh-tile--mail">
        <p class="gh-tile__small">Questions and support</p>
        <a href="mailto:support@suprim.com.au">support@suprim.com.au</a>
      </div>
      <div class="gh-tile gh-tile--mark"><span>Geo Maps</span></div>
      <a class="gh-tile gh-tile--go" href="/getting-started/installation">
        <span>Read the docs</span>
        <svg width="56" height="56" viewBox="0 0 56 56" aria-hidden="true"><path d="M12 44 44 12M20 12h24v24" fill="none" stroke="currentColor" stroke-width="4"/></svg>
      </a>
    </footer>
  </div>
</template>

<style scoped>
.gh {
  --soot: #12141c;
  --soot-2: #1d2030;
  --paper: #f2efe9;      /* OpenStreetMap land */
  --paper-2: #e7e2d8;
  --ink: #15171f;
  --ink-soft: #474b57;
  --light: #f4f3f0;
  --light-soft: #b4b8c6;
  --accent: #717ef0;     /* Geo Maps brand */
  --accent-ink: #0d0f1a;
  --gap: 8px;
  --s-ui: 15px;     /* interface and code */
  --s-body: 17px;
  --s-lead: clamp(19px, 1.5vw, 23px);
  --s-head: clamp(28px, 2.6vw, 36px);
  --s-sect: clamp(36px, 3.6vw, 56px);
  --m: 24px;
  font-family: 'Clash Grotesk', var(--vp-font-family-base);
  color: var(--ink);
  background: var(--paper);
}

@media (min-width: 960px) { .gh { --m: 48px; } }
@media (max-width: 959px) {
  .gh-features, .gh-how { padding-top: 112px !important; padding-bottom: 112px !important; }
  .gh-city { padding: 112px 0 !important; }
  .gh-close { padding: 112px 0 96px !important; }
}

.gh-wrap { max-width: 1344px; margin: 0 auto; padding: 0 var(--m); }

.gh h1, .gh h2, .gh h3 { font-weight: 500; letter-spacing: -0.03em; line-height: 0.98; margin: 0; border: 0; padding: 0; }
.gh p { margin: 0; }
.gh a { text-decoration: none; }

.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

.t-k { color: #9aa3f7; }
.t-s { color: #8fd3a6; }
.t-n { color: #f0b46a; }

.gh-btn {
  display: inline-flex; align-items: center; height: 48px; padding: 0 24px;
  border: 0; border-radius: 999px; background: var(--accent); color: var(--accent-ink);
  font: 500 var(--s-body)/1 'Clash Grotesk', var(--vp-font-family-base); cursor: pointer;
  transition: transform 0.2s cubic-bezier(0.2, 0.7, 0.2, 1), background-color 0.2s;
}
.gh-btn:hover { background: #8a95f4; }
.gh-btn:active { transform: scale(0.97); }
.gh-btn:focus-visible, .gh-link:focus-visible, .gh-copy:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }

.gh-link { font-size: var(--s-body); font-weight: 500; color: var(--ink); transition: opacity 0.2s; }
.gh-link--light { color: var(--light); }
.gh-link:hover { opacity: 0.7; }

/* Hero */
.gh-hero { background: var(--soot); color: var(--light); padding: 72px 0 96px; }
.gh-hero__head { display: grid; gap: 24px; margin-bottom: 48px; }
.gh-hero h1 { font-size: clamp(44px, 6vw, 92px); max-width: 11ch; }
.gh-lead { font-size: var(--s-lead); line-height: 1.45; max-width: 34em; color: var(--light); }
.gh-actions { display: flex; flex-wrap: wrap; gap: 24px; align-items: center; }

.gh-live {
  display: grid; overflow: hidden; border-radius: 10px; background: #fff; color: var(--ink);
  font-family: var(--vp-font-family-base);
}
@media (min-width: 960px) { .gh-live { grid-template-columns: 7fr 5fr; } }
.gh-live__map { min-height: 420px; height: 62vh; max-height: 640px; background: var(--paper); cursor: crosshair; }
.gh-live__panel { display: grid; align-content: start; gap: 12px; padding: 28px; font-size: var(--s-ui); }
.gh-live__label { font-size: var(--s-ui); font-weight: 600; color: #5d6170; }
.gh-live__coords { font: 500 var(--s-lead)/1.1 'Clash Grotesk', var(--vp-font-family-base); font-variant-numeric: tabular-nums; margin-bottom: 8px !important; }
.gh-live__zone {
  display: grid; gap: 4px; padding: 16px 18px; margin-bottom: 8px;
  border-radius: 8px; background: color-mix(in srgb, var(--zone) 14%, #fff);
  border-left: 4px solid var(--zone);
}
.gh-live__zone strong { font-size: var(--s-body); }
.gh-live__zone span { color: #474b57; }
.gh-live__list { margin: 0 0 8px; padding: 0; list-style: none; }
.gh-live__list li { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px solid #ecebe6; }
.gh-live__list li:last-child { border-bottom: 0; }
.gh-live__list b { font-variant-numeric: tabular-nums; }
.gh-live__code {
  margin: 0; padding: 14px 16px; border-radius: 8px; background: var(--soot); color: #e8e9ee;
  font: var(--s-ui)/1.6 var(--vp-font-family-mono); white-space: pre-wrap; overflow-wrap: anywhere;
}
.gh-live :deep(.gh-pin) { background: none; border: 0; }
.gh-live :deep(.leaflet-bar a) { font: 500 20px/26px var(--vp-font-family-base); }

/* Split */
.gh-split { display: grid; }
@media (min-width: 960px) { .gh-split { grid-template-columns: 1fr 1fr; min-height: 90svh; } }
.gh-split__in, .gh-split__out { position: relative; display: grid; align-content: start; gap: 16px; padding: 96px var(--m) 140px; }
.gh-split__in { background: var(--soot-2); color: var(--light); }
.gh-split__out { background: var(--paper); }
@media (min-width: 960px) {
  .gh-split__in, .gh-split__out { padding-top: 18vh; }
  .gh-split__in { justify-items: end; }
}
.gh-split__code {
  margin: 0; width: min(100%, 36ch); font: var(--s-lead)/1.6 var(--vp-font-family-mono); color: #e8e9ee;
  white-space: pre-wrap; background: none;
}
@media (min-width: 960px) { .gh-split__code { width: 36ch; } }
.gh-edit input, .gh-edit select {
  font: inherit; color: #f0b46a; background: #2b2f44; border: 0; border-radius: 4px; padding: 0 6px;
  width: 4.2em; height: 1.4em; vertical-align: baseline;
}
.gh-edit select { width: auto; color: #8fd3a6; }
.gh-edit input:focus, .gh-edit select:focus { outline: 2px solid var(--accent); }
.gh-split__title {
  position: absolute; bottom: 32px; font: 500 var(--s-sect)/1 'Clash Grotesk', var(--vp-font-family-base);
  letter-spacing: -0.03em;
}
.gh-split__title--a { right: var(--m); color: var(--light); }
.gh-split__title--b { left: var(--m); color: var(--ink); }
@media (max-width: 959px) {
  .gh-split__title--a { left: var(--m); right: auto; }
}

.gh-results {
  width: min(100%, 500px); padding: 22px 24px; border-radius: 10px; background: #fff;
  font-family: var(--vp-font-family-base); font-size: var(--s-ui);
}
.gh-results__head { font-weight: 600; margin-bottom: 8px !important; }
.gh-results ol { margin: 0; padding: 0; list-style: none; }
.gh-results li { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ecebe6; }
.gh-results li:last-child { border-bottom: 0; }
.gh-results b { font-variant-numeric: tabular-nums; }
.gh-results__more { color: #5d6170; padding-top: 8px; }

/* Split heading reads as one line on phones, where the halves stack */
@media (max-width: 959px) {
  .gh-split__title--a::after { content: ' get places back.'; }
  .gh-split__title--b { display: none; }
}

/* Shape details */
.gh-shape { background: var(--paper-2); padding: 160px 0; }
.gh-shape__grid { display: grid; gap: 48px; align-items: center; }
@media (min-width: 960px) { .gh-shape__grid { grid-template-columns: 7fr 5fr; gap: 64px; } }
.gh-shape__shot { margin: 0; }
.gh-shape__shot img { display: block; width: 100%; height: auto; border-radius: 10px; }
.gh-shape__text { display: grid; gap: 24px; }
.gh-shape__text h2 { font-size: var(--s-sect); }
.gh-shape__text > p { font-size: var(--s-lead); line-height: 1.5; }
.gh-shape__list { display: grid; gap: 14px; margin: 0; padding: 0; list-style: none; font-size: var(--s-body); line-height: 1.5; }
.gh-shape__list strong { font-weight: 600; }
.gh-shape__list code { font: var(--s-ui) var(--vp-font-family-mono); padding: 1px 6px; border-radius: 4px; background: var(--paper); }
@media (max-width: 959px) { .gh-shape { padding: 112px 0; } }

/* Examples gallery */
.gh-ex { background: var(--soot); color: var(--light); padding: 160px 0; }
.gh-ex h2 { font-size: var(--s-sect); }
.gh-ex .gh-ex__intro { margin: 16px 0 56px; font-size: var(--s-lead); line-height: 1.35; color: var(--light); max-width: 36ch; }
.gh-ex a { display: block; color: var(--light); text-decoration: none; }
.gh-ex img { display: block; width: 100%; height: auto; border-radius: 10px; background: var(--soot-2); transition: transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1); }
.gh-ex a:hover img { transform: translateY(-4px); }
.gh-ex a:focus-visible { outline: 2px solid var(--accent); outline-offset: 6px; border-radius: 12px; }
.gh-ex__name { display: block; margin-top: 16px; font: 500 var(--s-lead)/1.2 'Clash Grotesk', var(--vp-font-family-base); }
.gh-ex__line { display: block; margin-top: 6px; font-size: var(--s-body); line-height: 1.45; color: var(--light-soft); }
.gh-ex__lead { margin-bottom: 64px; }
.gh-ex__grid { display: grid; gap: 48px 24px; margin: 0; padding: 0; list-style: none; }
@media (min-width: 760px) { .gh-ex__grid { grid-template-columns: 1fr 1fr; } }
@media (min-width: 1100px) {
  .gh-ex a.gh-ex__lead { display: grid; grid-template-columns: 8fr 4fr; gap: 40px; align-items: end; }
  .gh-ex__lead .gh-ex__name { margin-top: 0; font-size: var(--s-head); }
  .gh-ex__grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 959px) { .gh-ex { padding: 96px 0; } .gh-ex .gh-ex__intro { margin-bottom: 40px; } .gh-ex__lead { margin-bottom: 48px; } }

/* Features: a swipeable row on phones instead of a long stack of boxes */
@media (max-width: 759px) {
  .gh-features .gh-cells {
    display: flex; overflow-x: auto; scroll-snap-type: x mandatory; gap: 12px;
    margin: 0 calc(var(--m) * -1); padding: 0 var(--m) 8px; scroll-padding: 0 var(--m);
    scrollbar-width: none;
  }
  .gh-features .gh-cells::-webkit-scrollbar { display: none; }
  .gh-features .gh-cells > .gh-cell { flex: 0 0 84%; scroll-snap-align: start; }
}

/* Features */
.gh-features { padding-top: 160px; padding-bottom: 160px; }
.gh-features h2 { font-size: var(--s-sect); margin-bottom: 56px; }
.gh-cells { display: grid; gap: var(--gap); }
@media (min-width: 760px) { .gh-cells { grid-template-columns: 1fr 1fr; } }
@media (min-width: 1100px) { .gh-cells { grid-template-columns: repeat(3, 1fr); } }
.gh-cell {
  display: grid; grid-template-rows: auto 1fr; gap: 24px;
  min-height: 460px; overflow: hidden; border-radius: 10px; background: var(--paper-2);
}
.gh-cell__text { padding: 28px 28px 0; }
.gh-cell h3 { font-size: var(--s-lead); margin-bottom: 10px; }
.gh-cell p { font-size: var(--s-body); line-height: 1.45; color: var(--ink); }
.gh-cell img, .gh-cell__code {
  display: block; align-self: end; width: calc(100% - 56px); margin: 0 28px 28px;
  border-radius: 8px;
}
/* Whole screenshot, inset from the card edges */
.gh-cell img { height: auto; box-sizing: border-box; padding: 12px; background: #fff; }
.gh-cell__code { height: 280px; }
.gh-cell--flip { grid-template-rows: 1fr auto; }
.gh-cell--flip > :first-child { order: 2; padding: 0 28px 28px; }
.gh-cell--flip img, .gh-cell--flip .gh-cell__code { align-self: start; margin: 28px 28px 0; }
@media (max-width: 759px) {
  .gh-cell { min-height: 0; }
  .gh-cell__text { padding: 20px 20px 0; }
  .gh-cell--flip > :first-child { padding: 0 20px 20px; }
  .gh-cell img, .gh-cell__code { width: calc(100% - 40px); margin: 0 20px 20px; }
  .gh-cell--flip img, .gh-cell--flip .gh-cell__code { margin: 20px 20px 0; }
}
.gh-cell__code {
  padding: 22px 24px; background: var(--soot); color: #e8e9ee;
  font: var(--s-ui)/1.6 var(--vp-font-family-mono); white-space: pre-wrap; overflow: hidden;
}

/* Adelaide */
.gh-city { background: var(--paper-2); padding: 160px 0; }
.gh-city__grid { display: grid; gap: 48px; align-items: start; }
@media (min-width: 960px) { .gh-city__grid { grid-template-columns: 5fr 7fr; gap: 64px; } }
.gh-city__plan { margin: 0; }
@media (min-width: 960px) { .gh-city__plan { position: sticky; top: calc(var(--vp-nav-height) + 24px); } }
.gh-city__plan img { width: 100%; height: auto; display: block; }
.gh-city figure { margin: 0; }
.gh-city figcaption { margin-top: 12px; font-size: var(--s-ui); color: var(--ink-soft); }
.gh-city__text { display: grid; gap: 32px; }
.gh-city__text h2 { font-size: var(--s-sect); }
.gh-city__text > p { font-size: var(--s-lead); line-height: 1.5; max-width: 32em; }
.gh-city__text img { width: 100%; height: auto; display: block; }

/* How it works */
.gh-how { padding-top: 160px; padding-bottom: 160px; }
.gh-how h2 { font-size: var(--s-sect); margin-bottom: 56px; }
.gh-how__grid { display: grid; gap: 48px; }
@media (min-width: 960px) { .gh-how__grid { grid-template-columns: 5fr 7fr; gap: 64px; } }
.gh-steps { display: grid; gap: 0; }
.gh-step { min-height: 60vh; display: grid; align-content: center; gap: 16px; }
@media (max-width: 959px) { .gh-step { min-height: 0; padding: 24px 0; } }
.gh-step__say { font: 500 var(--s-head)/1.1 'Clash Grotesk', var(--vp-font-family-base); letter-spacing: -0.02em; }
.gh-step__note { font-size: var(--s-body); line-height: 1.5; max-width: 30em; }
.gh-call { position: relative; border-radius: 8px; background: var(--soot); color: #e8e9ee; }
.gh-call pre { margin: 0; padding: 20px 88px 20px 20px; font: var(--s-ui)/1.6 var(--vp-font-family-mono); white-space: pre-wrap; overflow-wrap: anywhere; }
.gh-copy {
  position: absolute; top: 12px; right: 12px; height: 32px; padding: 0 14px; border: 0; border-radius: 999px;
  background: #2b2f44; color: #e8e9ee; font: 500 var(--s-ui)/1 var(--vp-font-family-base); cursor: pointer;
}
.gh-copy:hover { background: #3a3f5a; }
.gh-frame { display: none; }
@media (min-width: 960px) {
  .gh-frame { display: block; position: relative; }
  .gh-frame__inner { position: sticky; top: calc(var(--vp-nav-height) + 10vh); aspect-ratio: 4 / 3.3; border-radius: 10px; overflow: hidden; background: var(--paper-2); }
}
.gh-frame__slide {
  position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: top left;
  opacity: 0; transform: scale(1.02); transition: opacity 0.5s, transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.gh-frame__slide.is-on { opacity: 1; transform: none; }
.gh-frame__slide--term {
  margin: 0; display: grid; align-content: center; padding: 48px; background: var(--soot); color: #e8e9ee;
  font: var(--s-body)/1.9 var(--vp-font-family-mono); white-space: pre-wrap;
}
.gh-dim { color: #8d92a3; }
.gh-ok { color: #8fd3a6; }

/* Close */
.gh-close { background: var(--soot); color: var(--light); padding: 160px 0 120px; }
.gh-close h2 { font-size: clamp(44px, 7vw, 112px); max-width: 12ch; margin-bottom: 48px; }
.gh-close__row { display: flex; flex-wrap: wrap; gap: 16px 24px; align-items: center; }
.gh-close__cmd {
  display: inline-flex; align-items: center; height: 48px; margin: 0; padding: 0 20px; border-radius: 999px;
  background: var(--soot-2); color: #e8e9ee; font: var(--s-ui)/1 var(--vp-font-family-mono);
}

/* Footer tiles */
.gh-foot {
  display: grid; gap: var(--gap); padding: var(--gap); background: var(--soot);
  grid-template-columns: repeat(2, 1fr);
}
@media (min-width: 960px) { .gh-foot { grid-template-columns: repeat(12, 1fr); } }
.gh-tile { display: grid; align-content: space-between; gap: 16px; padding: 24px; border-radius: 10px; background: var(--soot-2); color: var(--light); min-height: 200px; }
.gh-tile__big { font-size: var(--s-lead); line-height: 1.25; }
.gh-tile__small { font-size: var(--s-ui); line-height: 1.5; color: var(--light-soft); max-width: 60ch; }
.gh-tile--about { grid-column: 1 / -1; }
.gh-tile--nav { align-content: start; gap: 8px; }
.gh-tile--nav a, .gh-tile--mail a { color: var(--light); font-size: var(--s-body); }
.gh-tile--nav a:hover, .gh-tile--mail a:hover { color: var(--accent); }
.gh-tile--mail a { font-size: var(--s-lead); overflow-wrap: anywhere; }
.gh-tile__clock { font: 500 var(--s-sect)/1 'Clash Grotesk', var(--vp-font-family-base); font-variant-numeric: tabular-nums; }
.gh-tile--mark { grid-column: 1 / -1; min-height: 220px; place-items: center; background: var(--paper); color: var(--ink); }
.gh-tile--mark span { font: 500 clamp(64px, 13vw, 200px)/0.9 'Clash Grotesk', var(--vp-font-family-base); letter-spacing: -0.04em; }
.gh-tile--go { grid-column: 1 / -1; background: var(--accent); color: var(--accent-ink); font: 500 var(--s-head)/1.1 'Clash Grotesk', var(--vp-font-family-base); transition: background-color 0.2s; }
.gh-tile--go:hover { background: #8a95f4; }
.gh-tile--go svg { justify-self: end; transition: transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1); }
.gh-tile--go:hover svg { transform: translate(4px, -4px); }
@media (min-width: 960px) {
  .gh-tile { min-height: 220px; }
  .gh-tile--about { grid-column: 1 / 5; }
  .gh-tile--nav { grid-column: 5 / 8; }
  .gh-tile--time { grid-column: 8 / 10; }
  .gh-tile--mail { grid-column: 10 / 13; }
  .gh-tile--mark { grid-column: 1 / 9; min-height: 320px; }
  .gh-tile--go { grid-column: 9 / 13; min-height: 320px; }
}
</style>
