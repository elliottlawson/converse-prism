import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Converse Prism',
  description: 'Seamless Prism PHP integration for Laravel Converse',
  base: '/',
  
  head: [
    ['meta', { name: 'theme-color', content: '#3eaf7c' }]
  ],
  
  themeConfig: {
    nav: [
      { text: 'Home', link: '/' },
      { text: 'Guide', link: '/installation' },
      { text: 'API', link: '/api/' },
      { text: 'GitHub', link: 'https://github.com/elliottlawson/converse-prism' }
    ],
    
    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Installation', link: '/installation' },
          { text: 'Setup', link: '/setup' },
          { text: 'Migration from Converse', link: '/migration' }
        ]
      },
      {
        text: 'Guide',
        items: [
          { text: 'Basic Usage', link: '/basic-usage' },
          { text: 'Streaming', link: '/streaming' },
          { text: 'Advanced Features', link: '/advanced-features' }
        ]
      },
      {
        text: 'API Reference',
        items: [
          { text: 'Overview', link: '/api/' },
          { text: 'Conversations', link: '/api/conversations' },
          { text: 'Messages', link: '/api/messages' },
          { text: 'PrismStream', link: '/api/prism-stream' },
          { text: 'Metadata', link: '/api/metadata' }
        ]
      },
      {
        text: 'Examples',
        items: [
          { text: 'Code Examples', link: '/examples' }
        ]
      }
    ],
    
    socialLinks: [
      { icon: 'github', link: 'https://github.com/elliottlawson/converse-prism' }
    ],
    
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2024 Elliott Lawson'
    },
    
    search: {
      provider: 'local'
    }
  }
}) 