if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * Handles settings page logic.
     *
     * @param {{
     * getConnectionDataUrl: string,
     * getWidgetSettingsUrl: string,
     * getGeneralSettingsUrl: string,
     * getOrderStatusMappingSettingsUrl: string,
     * getShopOrderStatusesUrl: string,
     * getShopCategoriesUrl: string,
     * getSellingCountriesUrl: string,
     * getCountrySettingsUrl: string,
     * validateConnectionDataUrl: string,
     * disconnectUrl: string,
     * page: string
     * }} configuration
     * @constructor
     */
    function SettingsController(configuration) {
        const { templateService, elementGenerator: generator, utilities, formFactory } = SequraFE;

        /** @type AjaxServiceType */
        const api = SequraFE.ajaxService;
        let currentStoreId = '';
        /** @type Version */
        let version;
        /** @type string */
        let merchantId= '';
        /** @type string */
        let env= '';
        /** @type Store[] */
        let stores ;


        /**
         * Displays page content.
         *
         * @param {{ state?: string, storeId: string }} config
         */
        this.display = ({ storeId }) => {
            utilities.showLoader();
            currentStoreId = storeId;
            templateService.clearMainPage();

            Promise.all([
                    SequraFE.state.getStores(),
                    SequraFE.state.getVersion(),
                    api.get(configuration.getConnectionDataUrl)
                ])
                .then(([res1, res2, res3]) => {
                    stores = res1;
                    version = res2;
                    merchantId = res3.merchantId;
                    env = res3.environment;
                }).finally(() => {
                    initializePage();
                    renderPage();
                    utilities.hideLoader();
                });
        };

        /**
         * Handles rendering of a form based on state.
         */
        const renderPage = () => {
            utilities.showLoader();
            let page = configuration.page;
            let renderer;
            let promises;

            if(!SequraFE.pages.settings.includes(page)) {
                page = SequraFE.pages.settings[0];
            }

            switch (page) {
                case SequraFE.appPages.SETTINGS.CONNECTION:
                    renderer = renderConnectionSettingsForm;
                    promises = Promise.all([
                        api.get(configuration.getConnectionDataUrl),
                        api.get(configuration.getCountrySettingsUrl)
                    ])
                    break;
                case SequraFE.appPages.SETTINGS.ORDER_STATUS:
                    renderer = renderOrderStatusMappingSettingsForm;
                    promises = Promise.all([
                        api.get(configuration.getOrderStatusMappingSettingsUrl),
                        api.get(configuration.getShopOrderStatusesUrl),
                        SequraFE.state.getShopName()
                    ])
                    break;
                case SequraFE.appPages.SETTINGS.WIDGET:
                    renderer = renderWidgetSettingsForm;
                    promises = Promise.all([
                        api.get(configuration.getWidgetSettingsUrl),
                        api.get(configuration.getConnectionDataUrl),
                        api.get(configuration.getCountrySettingsUrl)
                    ])
                    break;
                default:
                    renderer = renderGeneralSettingsForm;
                    promises = Promise.all([
                        SequraFE.isPromotional ? [] : api.get(configuration.getGeneralSettingsUrl),
                        api.get(configuration.getCountrySettingsUrl),
                        SequraFE.isPromotional ? [] : api.get(configuration.getShopCategoriesUrl),
                        api.get(configuration.getSellingCountriesUrl),
                        api.get(configuration.getConnectionDataUrl)
                    ])
            }

            promises
                .then((array) => renderer(...array))
                .catch()
                .finally(() => utilities.hideLoader());
        };

        /**
         * Renders the connection settings form.
         *
         * @param connectionSettings
         * @param countrySettings
         */
        const renderConnectionSettingsForm = (connectionSettings, countrySettings) => {
            const form = formFactory.getInstance(
                'connectionSettings',
                {connectionSettings, countrySettings},
                {...configuration, appState: SequraFE.appStates.SETTINGS}
            );

            form && form.render();
        }

        /**
         * Renders the order status mappings settings form.
         *
         * @param orderStatusSettings
         * @param shopOrderStatuses
         * @param shopName
         */
        const renderOrderStatusMappingSettingsForm = (orderStatusSettings, shopOrderStatuses, shopName) => {
            const form = formFactory.getInstance(
                'orderStatusMappingSettings',
                {orderStatusSettings, shopOrderStatuses, shopName: shopName.shopName},
                {...configuration}
            );

            form && form.render();
        }

        /**
         * Renders the widget settings form.
         *
         * @param widgetSettings
         * @param connectionSettings
         * @param countrySettings
         */
        const renderWidgetSettingsForm = (widgetSettings, connectionSettings, countrySettings) => {
            const form = formFactory.getInstance(
                'widgetSettings',
                { widgetSettings, connectionSettings, countrySettings },
                {...configuration, appState: SequraFE.appStates.SETTINGS}
            );

            form && form.render();
        }

        /**
         * Renders the general settings form.
         *
         * @param generalSettings
         * @param countrySettings
         * @param shopCategories
         * @param sellingCountries
         * @param connectionSettings
         */
        const renderGeneralSettingsForm = (
            generalSettings,
            countrySettings,
            shopCategories,
            sellingCountries,
            connectionSettings
        ) => {
            const form = formFactory.getInstance(
                'generalSettings',
                {generalSettings, countrySettings, shopCategories, sellingCountries, connectionSettings},
                {...configuration, appState: SequraFE.appStates.SETTINGS}
            );

            form && form.render();
        }

        /**
         * Get sidebar link options.
         *
         * @returns {unknown[]}
         */
        const getLinkConfiguration = () => {
            return  SequraFE.pages.settings.map((link) => {
                const activePage = configuration.page ?? SequraFE.pages.settings[0]
                switch (link) {
                    case SequraFE.appPages.SETTINGS.GENERAL:
                        return {
                            label: 'sidebar.generalSettings',
                            href: '#settings-general',
                            isActive: activePage === SequraFE.appPages.SETTINGS.GENERAL
                        }
                    case SequraFE.appPages.SETTINGS.CONNECTION:
                        return {
                            label: 'sidebar.connectionSettings',
                            href: '#settings-connection',
                            isActive: activePage === SequraFE.appPages.SETTINGS.CONNECTION
                        }
                    case SequraFE.appPages.SETTINGS.ORDER_STATUS:
                        return {
                            label: 'sidebar.orderStatusSettings',
                            href: '#settings-order_status',
                            isActive: activePage === SequraFE.appPages.SETTINGS.ORDER_STATUS
                        }
                    case SequraFE.appPages.SETTINGS.WIDGET:
                        return {
                            label: 'sidebar.widgetSettings',
                            href: '#settings-widget',
                            isActive: activePage === SequraFE.appPages.SETTINGS.WIDGET
                        }
                }
            });
        }

        /**
         * Initializes general settings state content.
         */
        const initializePage = () => {
            const pageWrapper = document.getElementById('sq-page-wrapper')

            pageWrapper.append(
                generator.createElement('div', 'sq-page-content-wrapper sqv--settings', '', null, [
                    SequraFE.components.PageHeader.create(
                        {
                            currentVersion: version?.current,
                            newVersion: version?.new && version?.downloadNewVersionUrl ? {
                                versionLabel: version.new,
                                versionUrl: version.downloadNewVersionUrl
                            } : null,
                            mode: env === 'live' ? env : 'test',
                            merchantName: merchantId,
                            activeStore: currentStoreId,
                            stores: stores.map((store) => ({label: store.storeName, value: store.storeId})),
                            onChange: (storeId) => {
                                if (storeId !== SequraFE.state.getStoreId()) {
                                    SequraFE.state.setStoreId(storeId);
                                    window.location.hash = '';
                                    SequraFE.state.display();
                                }
                            },
                            menuItems: SequraFE.isPromotional ? [] : [
                                {
                                    label: 'general.paymentMethods',
                                    href: window.location.href.split('#')[0] + '#payment'
                                },
                                {
                                    label: 'general.settings',
                                    href: window.location.href.split('#')[0] + '#settings',
                                    isActive: true,
                                }
                            ]
                        }
                    ),
                    generator.createElement('div', 'sq-page-content', '', null, [
                        generator.createElement('div', 'sq-content-row', '', null, [
                            generator.createSettingsSidebar({ links: getLinkConfiguration() }),
                            generator.createElement('main', 'sq-content')
                        ])
                    ]),
                ]))
        }
    }

    SequraFE.SettingsController = SettingsController;
})();
