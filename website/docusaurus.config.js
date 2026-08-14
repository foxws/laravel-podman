// @ts-check
const { themes: prismThemes } = require('prism-react-renderer');

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'Laravel Podman',
  tagline: "Renders Podman Quadlet units from your Laravel app's config",

  url: 'https://foxws.github.io',
  baseUrl: '/laravel-podman/',

  organizationName: 'foxws',
  projectName: 'laravel-podman',
  deploymentBranch: 'gh-pages',
  trailingSlash: false,

  onBrokenLinks: 'throw',
  onBrokenMarkdownLinks: 'warn',

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          path: '../docs',
          routeBasePath: '/',
          sidebarPath: require.resolve('./sidebars.js'),
          editUrl: 'https://github.com/foxws/laravel-podman/edit/main/docs/',
        },
        blog: false,
        theme: {
          customCss: require.resolve('./src/css/custom.css'),
        },
      }),
    ],
  ],

  themes: [
    [
      '@easyops-cn/docusaurus-search-local',
      /** @type {import('@easyops-cn/docusaurus-search-local').PluginOptions} */
      ({
        hashed: true,
        indexBlog: false,
        docsRouteBasePath: '/',
      }),
    ],
  ],

  themeConfig:
    /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      navbar: {
        title: 'Laravel Podman',
        items: [
          {
            href: 'https://github.com/foxws/laravel-podman',
            label: 'GitHub',
            position: 'right',
          },
          {
            href: 'https://packagist.org/packages/foxws/laravel-podman',
            label: 'Packagist',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [
          {
            title: 'Docs',
            items: [
              { label: 'Introduction', to: '/' },
              { label: 'Command Reference', to: '/commands' },
              { label: 'Customizing', to: '/customizing' },
            ],
          },
          {
            title: 'More',
            items: [
              { label: 'GitHub', href: 'https://github.com/foxws/laravel-podman' },
              { label: 'lpod CLI', href: 'https://github.com/foxws/lpod' },
              { label: 'Packagist', href: 'https://packagist.org/packages/foxws/laravel-podman' },
            ],
          },
        ],
        copyright: `Copyright © ${new Date().getFullYear()} foxws. Built with Docusaurus.`,
      },
      prism: {
        theme: prismThemes.github,
        darkTheme: prismThemes.dracula,
      },
    }),
};

module.exports = config;
