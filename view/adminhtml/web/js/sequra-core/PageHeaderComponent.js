if (!window.SequraFE) {
    window.SequraFE = {};
}

if (!window.SequraFE.components) {
    window.SequraFE.components = {};
}

(function () {
    /**
     * Marks a link that leaves the shop for the seQura portal. Drawn rather than taken from
     * the icon font, which carries no such glyph.
     *
     * @type {string}
     */
    const EXTERNAL_LINK_ICON = '<svg class="sqp-external-icon" viewBox="0 0 16 16" width="12" height="12"'
        + ' fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"'
        + ' stroke-linejoin="round" aria-hidden="true" focusable="false">'
        + '<path d="M9.5 2.5H13.5V6.5" /><path d="M13.5 2.5L7.5 8.5" />'
        + '<path d="M12 9.5V13C12 13.2761 11.7761 13.5 11.5 13.5H3C2.72386 13.5 2.5 13.2761 2.5 13V4.5'
        + 'C2.5 4.22386 2.72386 4 3 4H6.5" /></svg>';

    /**
     * @typedef PageHeaderConfiguration
     * @property {string?} currentVersion
     * @property {{versionLabel?: string, versionUrl?: string}?} newVersion
     * @property {'live' | 'test'} mode
     * @property {string?} merchantName
     * @property {Option[]?} stores
     * @property {{label: string, href: string, isActive?: boolean}[]} menuItems
     * @property {string} activeStore
     * @property {string?} portalUrl Address of the store's integration in the seQura portal,
     * where the merchant manages the configuration the shop does not keep.
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
        portalUrl,
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

        // Where the merchant configures everything the shop does not keep. It sits with
        // the mode badge rather than on a page of its own, so it is offered wherever the
        // merchant happens to be. A store that is not connected yet has no integration
        // to look at, but the link is still built so a later connection can reveal it.
        const portalLink = generator.createElement(
            'a',
            'sqp-portal-link',
            '',
            { href: portalUrl || '#', target: '_blank', rel: 'noopener noreferrer' },
            [
                generator.createElement('span', '', 'general.viewInPortal'),
                generator.createElementFromHTML(EXTERNAL_LINK_ICON)
            ]
        );
        if (!portalUrl) portalLink.style.display = 'none';

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
            ),
            portalLink
        ]);

        const storeSwitcher = (!SequraFE.flags.isStoreSwitcherVisible || stores.length <= 1) ? [] : generator.createStoreSwitcher({
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
        create: (config) => new PageHeaderComponent(config),
        setPortalUrl: (portalUrl) => {
            const link = document.querySelector('.sqp-portal-link');
            if (!link) return;

            link.style.display = portalUrl ? '' : 'none';
            if (portalUrl) link.href = portalUrl;
        }
    };
})();
