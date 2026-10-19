import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Geo Maps',
  description: 'Locations, shapes and distance search for Craft CMS',
  base: '/',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' }],
    ['link', { rel: 'icon', type: 'image/png', sizes: '64x64', href: '/favicon.png' }],
    ['link', { rel: 'apple-touch-icon', sizes: '180x180', href: '/apple-touch-icon.png' }],
    ['meta', { name: 'theme-color', content: '#12141c' }],
    ['link', { rel: 'preconnect', href: 'https://api.fontshare.com' }],
    ['link', { rel: 'preconnect', href: 'https://cdn.fontshare.com', crossorigin: '' }],
    ['link', { rel: 'stylesheet', href: 'https://api.fontshare.com/v2/css?f[]=clash-grotesk@400,500&display=swap' }],
  ],

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
          { text: 'Importing Existing Fields', link: '/getting-started/importing' },
          { text: 'Troubleshooting', link: '/getting-started/troubleshooting' },
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
          { text: 'Custom Pins & Info Windows', link: '/examples/custom-pins' },
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
      pattern: 'https://github.com/suprim12/craft-geomaps/edit/main/docs/src/:path',
      text: 'Edit this page on GitHub',
    },

    search: {
      provider: 'local',
    },
  },
})
