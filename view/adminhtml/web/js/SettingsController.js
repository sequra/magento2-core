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
     * getPaymentMethodsUrl: string,
     * getAllAvailablePaymentMethodsUrl: string,
     * validateConnectionDataUrl: string,
     * disconnectUrl: string,
     * page: string
     * }} configuration
     * @constructor
     */
    function SettingsController(configuration) {
        const {templateService, elementGenerator: generator, utilities, formFactory} = SequraFE;

        /** @type AjaxServiceType */
        const api = SequraFE.ajaxService;
        let currentStoreId = '';
        /** @type Version */
        let version;
        /** @type Store[] */
        let stores;
        /** @type CountrySettings[] **/
        let countrySettings;
        /** @type ConnectionSettings **/
        let connectionSettings;
        /** @type WidgetSettings **/
        let widgetSettings;

        /**
         * Displays page content.
         *
         * @param {{ state?: string, storeId: string }} config
         */
        this.display = ({storeId}) => {
            utilities.showLoader();
            currentStoreId = storeId;
            templateService.clearMainPage();
            stores = SequraFE.state.getData('stores');
            version = SequraFE.state.getData('version');
            connectionSettings = SequraFE.state.getData('connectionSettings');
            countrySettings = SequraFE.state.getData('countrySettings');
            widgetSettings = SequraFE.state.getData('widgetSettings');

            initializePage();
            renderPage();
        };

        /**
         * Handles rendering of a form based on state.
         */
        const renderPage = () => {
            utilities.showLoader();
            let page = SequraFE.state.getPage();
            let renderer;
            let promises;

            if (!SequraFE.pages.settings.includes(page)) {
                page = SequraFE.pages.settings[0];
            }

            switch (page) {
                case SequraFE.appPages.SETTINGS.CONNECTION:
                    renderer = renderConnectionSettingsForm;
                    promises = Promise.all([
                        SequraFE.state.getData('notConnectedDeployments') ?? api.get(
                            configuration.pageConfiguration.onboarding.getNotConnectedDeploymentsUrl.replace(
                                '{storeId}', SequraFE.state.getStoreId()
                            ),
                            null,
                            SequraFE.customHeader
                        ),
                    ])
                    break;
                case SequraFE.appPages.SETTINGS.ORDER_STATUS:
                    renderer = renderOrderStatusMappingSettingsForm;
                    promises = Promise.all([
                        api.get(configuration.getOrderStatusMappingSettingsUrl, null, SequraFE.customHeader),
                        api.get(configuration.getShopOrderStatusesUrl, null, SequraFE.customHeader),
                        SequraFE.state.getShopName()
                    ])
                    break;
                case SequraFE.appPages.SETTINGS.WIDGET:
                    renderer = renderWidgetSettingsForm;
                    promises = Promise.all([
                        SequraFE.state.getData('paymentMethods') ?? api.get(configuration.getPaymentMethodsUrl.replace('{merchantId}', countrySettings[0].merchantId), null, SequraFE.customHeader),
                        SequraFE.state.getData('allAvailablePaymentMethods') ?? api.get(configuration.getAllAvailablePaymentMethodsUrl, null, SequraFE.customHeader),
                    ])
                    break;
                default:
                    renderer = renderGeneralSettingsForm;
                    promises = Promise.all([
                        SequraFE.isPromotional ? [] :
                            SequraFE.state.getData('generalSettings') ?? api.get(configuration.getGeneralSettingsUrl, null, SequraFE.customHeader),
                        SequraFE.isPromotional ? [] :
                            SequraFE.state.getData('shopCategories') ?? api.get(configuration.getShopCategoriesUrl, null, SequraFE.customHeader),
                        SequraFE.state.getData('sellingCountries') ?? api.get(configuration.getSellingCountriesUrl, null, SequraFE.customHeader),
                    ])
            }

            promises
                .then((array) => renderer(...array))
        };

        /**
         * Renders the connection settings form.
         */
        const renderConnectionSettingsForm = (notConnectedDeployments) => {
            const activeDeploymentsIds = connectionSettings?.connectionData?.map(
                cd => cd.deployment
            ).filter(Boolean) || [];

            notConnectedDeployments = Array.isArray(notConnectedDeployments)
                ? notConnectedDeployments.filter(deployment => !activeDeploymentsIds.includes(deployment.id))
                : [];

            SequraFE.state.setData('notConnectedDeployments', notConnectedDeployments);

            const form = formFactory.getInstance(
                'connectionSettings',
                {connectionSettings, countrySettings, activeDeploymentsIds, notConnectedDeployments},
                {...configuration, appState: SequraFE.appStates.SETTINGS}
            );

            form?.render();
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

            form?.render();
        }

        /**
         * Renders the widget settings form.
         *
         * @param paymentMethods
         * @param allAvailablePaymentMethods
         */
        const renderWidgetSettingsForm = (paymentMethods, allAvailablePaymentMethods) => {
            if (!SequraFE.state.getData('paymentMethods')) {
                SequraFE.state.setData('paymentMethods', paymentMethods)
            }

            if (!SequraFE.state.getData('allAvailablePaymentMethods')) {
                SequraFE.state.setData('allAvailablePaymentMethods', allAvailablePaymentMethods)
            }

            const form = formFactory.getInstance(
                'widgetSettings',
                {widgetSettings, connectionSettings, countrySettings, paymentMethods, allAvailablePaymentMethods},
                {...configuration, appState: SequraFE.appStates.SETTINGS}
            );

            form?.render();
        }

        /**
         * Renders the general settings form.
         *
         * @param generalSettings
         * @param shopCategories
         * @param sellingCountries
         */
        const renderGeneralSettingsForm = (
            generalSettings,
            shopCategories,
            sellingCountries,
        ) => {
            saveFetchedDataToDataStore(generalSettings, shopCategories, sellingCountries);

            const form = formFactory.getInstance(
                'generalSettings',
                {generalSettings, shopCategories, sellingCountries, connectionSettings, countrySettings},
                {...configuration, appState: SequraFE.appStates.SETTINGS}
            );

            form?.render();
        }

        /**
         * Saves data to data store if fetched on render.
         *
         * @param generalSettings
         * @param shopCategories
         * @param sellingCountries
         */
        const saveFetchedDataToDataStore = (generalSettings, shopCategories, sellingCountries) => {
            if (!SequraFE.state.getData('generalSettings')) {
                SequraFE.state.setData('generalSettings', generalSettings)
            }

            if (!SequraFE.state.getData('shopCategories')) {
                SequraFE.state.setData('shopCategories', shopCategories)
            }

            if (!SequraFE.state.getData('sellingCountries')) {
                SequraFE.state.setData('sellingCountries', sellingCountries)
            }
        }

        /**
         * Get sidebar link options.
         *
         * @returns {unknown[]}
         */
        const getLinkConfiguration = () => {
            return SequraFE.pages.settings.map((link) => {
                const activePage = SequraFE.state.getPage() ?? SequraFE.pages.settings[0]
                switch (link) {
                    case SequraFE.appPages.SETTINGS.GENERAL:
                        return {
                            label: 'sidebar.generalSettings',
                            href: '#settings-general',
                            icon: 'general',
                            isActive: activePage === SequraFE.appPages.SETTINGS.GENERAL
                        }
                    case SequraFE.appPages.SETTINGS.CONNECTION:
                        return {
                            label: 'sidebar.connectionSettings',
                            href: '#settings-connection',
                            icon: 'connection',
                            isActive: activePage === SequraFE.appPages.SETTINGS.CONNECTION
                        }
                    case SequraFE.appPages.SETTINGS.ORDER_STATUS:
                        return {
                            label: 'sidebar.orderStatusSettings',
                            href: '#settings-order_status',
                            icon: 'order',
                            isActive: activePage === SequraFE.appPages.SETTINGS.ORDER_STATUS
                        }
                    case SequraFE.appPages.SETTINGS.WIDGET:
                        return {
                            label: 'sidebar.widgetSettings',
                            href: '#settings-widget',
                            icon: 'widget',
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
                            mode: connectionSettings.environment === 'live' ? connectionSettings.environment : 'test',
                            activeStore: currentStoreId,
                            stores: stores.map((store) => ({label: store.storeName, value: store.storeId})),
                            onChange: (storeId) => {
                                if (storeId !== SequraFE.state.getStoreId()) {
                                    SequraFE.state.setStoreId(storeId);
                                    window.location.hash = '';
                                    SequraFE.state.display();
                                }
                            },
                            menuItems: SequraFE.utilities.getMenuItems(SequraFE.appStates.SETTINGS)
                        }
                    ),
                    generator.createElement('div', 'sq-page-content', '', null, [getSidebarRow()]),
                    generator.createSupportLink()
                ]))
        }

        const getSidebarRow = () => {
            return generator.createElement('div', 'sq-content-row', '', null, [
                generator.createSettingsSidebar({links: getLinkConfiguration()}),
                generator.createElement('main', 'sq-content')
            ]);
        }
    }

    SequraFE.SettingsController = SettingsController;
})();
