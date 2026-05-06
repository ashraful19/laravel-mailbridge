export default {
    base: '/laravel-mailbridge/',
    title: 'Laravel MailBridge',
    description: 'Provider-neutral transactional and marketing email for Laravel.',
    head: [
        ['meta', { name: 'theme-color', content: '#ff2d20' }],
    ],
    themeConfig: {
        // Docs governance:
        // - Core guides explain package-level behavior.
        // - Provider-specific differences belong in /guide/providers.
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
                    { text: 'Provider Guides', link: '/guide/providers' },
                    { text: 'Capabilities', link: '/guide/capabilities' },
                ],
            },
            {
                text: 'Core Guides',
                items: [
                    { text: 'Transactional', link: '/guide/transactional' },
                    { text: 'Templates', link: '/guide/templates' },
                    { text: 'Marketing', link: '/guide/marketing' },
                    { text: 'Response Shapes', link: '/guide/responses' },
                    { text: 'Fallback', link: '/guide/fallback' },
                    { text: 'Laravel Mail Compatibility', link: '/guide/laravel-mail' },
                ],
            },
            {
                text: 'Operations',
                items: [
                    { text: 'Exception Handling', link: '/guide/exceptions' },
                    { text: 'Testing', link: '/guide/testing' },
                    { text: 'Security', link: '/guide/security' },
                    { text: 'Troubleshooting', link: '/guide/troubleshooting' },
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
