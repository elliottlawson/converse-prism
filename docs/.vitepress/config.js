export default {
  title: 'Converse-Prism',
  description: 'Seamless integration between Laravel Converse and Prism PHP for AI conversations',
  base: '/',
  
  head: [
    ['link', { rel: 'icon', href: '/favicon.ico' }],
    ['meta', { name: 'theme-color', content: '#3eaf7c' }],
  ],
  
  themeConfig: {
    nav: [
      { text: 'Home', link: '/' },
      { text: 'Documentation', link: '/getting-started' },
      { text: 'GitHub', link: 'https://github.com/elliottlawson/converse-prism' }
    ],
    
    sidebar: [
      {
        text: 'Introduction',
        items: [
          { text: 'Overview', link: '/' },
          { text: 'Getting Started', link: '/getting-started' }
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
        text: 'Reference',
        items: [
          { text: 'API Reference', link: '/api-reference' },
          { text: 'Examples', link: '/examples' },
          { text: 'Migration Guide', link: '/migration-guide' }
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
} 