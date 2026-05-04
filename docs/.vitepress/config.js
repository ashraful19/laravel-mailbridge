export default {
    base: '/laravel-mailbridge/',
    title: 'Laravel MailBridge',
    description: 'Provider-neutral transactional and marketing email for Laravel.',
    head: [
        ['meta', { name: 'theme-color', content: '#ff2d20' }],
    ],
    themeConfig: {
        siteTitle: 'Laravel MailBridge',
        nav: [
            { text: 'Guide', link: '/guide/installation', activeMatch: '/guide/' },
            { text: 'Capabilities', link: '/guide/capabilities' },
            { text: 'GitHub', link: 'https://github.com/ashraful19/laravel-mailbridge' },
        ],
        sidebar: [
            {
                text: 'Start Here',
                items: [
                    { text: 'Installation', link: '/guide/installation' },
                    { text: 'Provider Install', link: '/guide/provider-install' },
                    { text: 'Normal Laravel Mail', link: '/guide/laravel-mail' },
                ],
            },
            {
                text: 'Core Guides',
                items: [
                    { text: 'Transactional', link: '/guide/transactional' },
                    { text: 'Templates', link: '/guide/templates' },
                    { text: 'Marketing', link: '/guide/marketing' },
                    { text: 'Fallback', link: '/guide/fallback' },
                ],
            },
            {
                text: 'Operations',
                items: [
                    { text: 'Testing', link: '/guide/testing' },
                    { text: 'Security', link: '/guide/security' },
                    { text: 'Capabilities', link: '/guide/capabilities' },
                ],
            },
        ],
        search: {
            provider: 'local',
        },
        socialLinks: [
            { icon: 'github', link: 'https://github.com/ashraful19/laravel-mailbridge' },
        ],
        footer: {
            message: 'Released under the MIT License.',
            copyright: 'Copyright (c) 2026 Ashraful Islam',
        },
    },
};
