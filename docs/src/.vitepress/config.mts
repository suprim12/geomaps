import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Geo Maps',
  description: 'Locations, shapes and distance search for Craft CMS',
  base: '/',

  themeConfig: {
    logo: { src: '/logo.svg', width: 24, height: 24 },
    siteTitle: 'Geo Maps',
    nav: [
      { text: 'Plugin Store', link: 'https://plugins.craftcms.com/geomaps', target: '_blank' },
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Introduction', link: '/' },
          { text: 'Installation', link: '/getting-started/installation' },
          { text: 'Configuration', link: '/getting-started/configuration' },
          { text: 'Migrating from Maps or Geoman', link: '/getting-started/migrating-from-maps' },
        ],
      },
      {
        text: 'Guides',
        items: [
          { text: 'The Field', link: '/guides/field' },
          { text: 'Templating', link: '/guides/templating' },
          { text: 'Location Search', link: '/guides/search' },
          { text: 'Maps', link: '/guides/maps' },
          { text: 'GraphQL', link: '/guides/graphql' },
          { text: 'Feed Me', link: '/guides/feed-me' },
          { text: 'Console Commands', link: '/guides/console' },
        ],
      },
      {
        text: 'Examples',
        items: [
          { text: 'Store Locator', link: '/examples/store-locator' },
          { text: 'Location Page', link: '/examples/location-page' },
          { text: 'Bin Collection Zones', link: '/examples/zone-lookup' },
          { text: 'Map of Areas', link: '/examples/area-map' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/suprim12/craft-geomaps' },
    ],

    footer: {
      message: 'A Craft CMS plugin by <a href="https://suprimgolay.com.np/" target="_blank">Suprim</a>',
    },

    editLink: {
      pattern: 'https://github.com/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },

    search: {
      provider: 'local',
    },
  },
})
