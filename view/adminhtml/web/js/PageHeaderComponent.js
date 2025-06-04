if (!window.SequraFE) {
    window.SequraFE = {};
}

if (!window.SequraFE.components) {
    window.SequraFE.components = {};
}

(function () {
    /**
     * @typedef PageHeaderConfiguration
     * @property {string?} currentVersion
     * @property {{versionLabel?: string, versionUrl?: string}?} newVersion
     * @property {'live' | 'test'} mode
     * @property {string?} merchantName
     * @property {Option[]?} stores
     * @property {{label: string, href: string, isActive?: boolean}[]} menuItems
     * @property {string} activeStore
     * @property {(value: string) => void?} onChange
     */

    /**
     * @param {PageHeaderConfiguration} config
     * @constructor
     */
    function PageHeaderComponent({
        currentVersion,
        newVersion,
        mode,
        menuItems,
        merchantName,
        stores,
        activeStore,
        onChange
    }) {
        const generator = SequraFE.elementGenerator;

        const logoAndVersion = generator.createElement('div', 'sqp-page-header', '', null, [
            generator.createElement('div', 'sqp-header-logo', '', null, [
                generator.createElementFromHTML(SequraFE.imagesProvider.logo || ''),
                currentVersion ? generator.createVersionBadge(currentVersion) : []
            ]),
            newVersion && newVersion.versionLabel !== currentVersion
                ? generator.createElement(
                    'a',
                    'sqp-download-version',
                    '',
                    { href: newVersion.versionUrl, download: true, target: "_blank" },
                    [
                        generator.createElement('span', '', 'general.downloadNewVersion'),
                        generator.createElement('span', '', newVersion.versionLabel)
                    ]
                )
                : ''
        ]);

        let controls = [];
        if (menuItems.length) {
            controls = generator.createElement('div', 'sqp-menu-items');
            controls.append(
                ...menuItems.map((item) =>
                    generator.createButtonLink({
                        className: item.isActive ? 'sqs--active' : '',
                        text: item.label,
                        href: item.href
                    })
                )
            );
        }

        const merchant = generator.createElement('div', 'sqp-header-merchant', '', null, [
            merchantName ? generator.createElement('div', 'sqp-merchant', '', null, [
                generator.createElement('span', 'sqp-merchant-label', 'general.merchant'),
                generator.createElement('span', 'sqp-merchant-name', merchantName)
            ]) : [],
            generator.createElement(
                'span',
                'sq-mode-badge' + (mode ? ' sqt--' + mode : ''),
                'general.mode.' + mode.toLowerCase(),
                null
            )
        ]);

        const storeSwitcher = stores.length <= 1 ? [] : generator.createStoreSwitcher({
            label: 'general.switchStore',
            value: activeStore,
            options: stores,
            onChange: onChange
        });

        return generator.createElement('div', 'sq-page-header', '', null, [
            generator.createElement('div', 'sqp-header-top', '', null, [logoAndVersion, controls]),
            generator.createElement('div', 'sqp-header-bottom', '', null, [merchant, storeSwitcher])
        ]);
    }

    SequraFE.components.PageHeader = {
        /** @param {PageHeaderConfiguration} config */
        create: (config) => new PageHeaderComponent(config)
    };
})();
