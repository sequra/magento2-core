if (!window.SequraFE) {
    window.SequraFE = {};
}

SequraFE.appStates = {
    ONBOARDING: 'onboarding',
    SETTINGS: 'settings',
    PAYMENT: 'payment'
};

SequraFE.appPages = {
    ONBOARDING: {
        CONNECT: 'connect',
        COUNTRIES: 'countries',
        WIDGETS: 'widgets'
    },
    SETTINGS: {
        GENERAL: 'general',
        CONNECTION: 'connection',
        ORDER_STATUS: 'order_status',
        WIDGET: 'widget'
    },
    PAYMENT: {
        METHODS: 'methods'
    }
};

(function () {
    /**
     * @typedef Store
     * @property {string} storeId
     * @property {string} storeName
     */

    /**
     * @typedef StateConfiguration
     * @property {string} stateUrl
     * @property {string} storesUrl
     * @property {string} currentStoreUrl
     * @property {string} getConnectionDataUrl
     * @property {string} versionUrl
     * @property {string} shopNameUrl
     * @property {Record<string, any>} pageConfiguration
     */

    /**
     * @typedef Version
     * @property {string} current
     * @property {string | null} new
     * @property {string | null} downloadNewVersionUrl
     */

    /**
     * @typedef ShopName
     * @property {string} shopName
     */

    /**
     * @typedef DataStore
     * @property {Version | null} version
     * @property {Store[] | null} stores
     * @property {ConnectionSettings | null} connectionSettings
     * @property {CountrySettings | null} countrySettings
     * @property {GeneralSettings | null} generalSettings
     * @property {WidgetSettings | null} widgetSettings
     * @property {PaymentMethod[] | null} paymentMethods
     * @property {SellingCountry[] | null} sellingCountries
     * @property {Category[] | null} shopCategories
     */

    /**
     * Main controller of the application.
     *
     * @param {StateConfiguration} configuration
     *
     * @constructor
     */
    function StateController(configuration) {
        /** @type AjaxServiceType */
        const api = SequraFE.ajaxService;
        const {pageControllerFactory, templateService, utilities} = SequraFE;

        let currentState = '';
        let previousState = '';

        /**
         * @type {DataStore}
         */
        let dataStore = {
            version: null,
            stores: null,
            connectionSettings: null,
            countrySettings: null,
            generalSettings: null,
            widgetSettings: null,
            paymentMethods: null,
            sellingCountries: null,
            shopCategories: null
        };

        /**
         * Main entry point for the application.
         * Determines the current state and runs the start controller.
         */
        this.display = () => {
            utilities.showLoader();
            clearDataStore();
            templateService.clearMainPage();

            window.addEventListener('hashchange', updateStateOnHashChange, false);

            api.get(!this.getStoreId() ? configuration.currentStoreUrl : configuration.storesUrl.replace(encodeURIComponent('{storeId}'), this.getStoreId()), () => null, true)
                .then(
                    /** @param {Store|Store[]} response */
                    (response) => {
                        const loadStore = (store) => {
                            this.setStoreId(store.storeId);

                            return displayPageBasedOnState();
                        };

                        let store = !Array.isArray(response) ?
                            response :
                            response.find((s) => s.storeId === this.getStoreId());

                        if (!store) {
                            // the active store is probably deleted, we need to switch to the default store
                            return api.get(configuration.currentStoreUrl, null, true).then(loadStore);
                        }

                        return loadStore(store);
                    }
                )
        };

        /**
         * Updates the application state on a hash change.
         */
        const updateStateOnHashChange = () => {
            const state = window.location.hash.substring(1);
            state && this.goToState(state);
        };

        /**
         * Opens a specific page based on the current state.
         */
        const displayPageBasedOnState = () => {
            utilities.showLoader();

            return Promise.all([
                api.get(configuration.versionUrl.replace(encodeURIComponent('{storeId}'), this.getStoreId())),
                api.get(configuration.storesUrl.replace(encodeURIComponent('{storeId}'), this.getStoreId())),
                api.get(configuration.pageConfiguration.onboarding.getConnectionDataUrl.replace(encodeURIComponent('{storeId}'), this.getStoreId())),
                api.get(configuration.pageConfiguration.onboarding.getCountrySettingsUrl.replace(encodeURIComponent('{storeId}'), this.getStoreId())),
                api.get(configuration.pageConfiguration.onboarding.getWidgetSettingsUrl.replace(encodeURIComponent('{storeId}'), this.getStoreId())),
            ]).then(([versionRes, storesRes, connectionSettingsRes, countrySettingsRes, widgetSettingsRes]) => {
                dataStore.version = versionRes;
                dataStore.stores = storesRes;
                dataStore.connectionSettings = connectionSettingsRes;
                dataStore.countrySettings = countrySettingsRes;
                dataStore.widgetSettings = widgetSettingsRes;

                return api.get(configuration.stateUrl.replace(encodeURIComponent('{storeId}'), this.getStoreId()));
            }).then((stateRes) => {
                if (SequraFE.state.getCredentialsChanged()) {
                    SequraFE.state.removeCredentialsChanged();
                }

                let page = this.getPage();
                if (stateRes.state === SequraFE.appStates.ONBOARDING) {
                    this.goToState(SequraFE.appStates.ONBOARDING, null, true);

                    return;
                }

                if (!page || SequraFE.pages.payment?.includes(page)) {
                    this.goToState(SequraFE.appStates.PAYMENT + '-' + SequraFE.appPages.PAYMENT.METHODS, null, true)

                    return;
                }

                this.goToState(SequraFE.appStates.SETTINGS + '-' + page, null, true);
            }).catch(() => {
            });
        };

        /**
         * Navigates to a state.
         *
         * @param {string} state
         * @param {Record<string, any> | null?} additionalConfig
         * @param {boolean} [force=false]
         */
        this.goToState = (state, additionalConfig = null, force = false) => {
            if ((currentState === state && !force)) {
                return;
            }

            utilities.showLoader();
            let [controllerName, page] = state.split('-');

            if (controllerName === SequraFE.appStates.ONBOARDING) {
                if (dataStore.connectionSettings?.username && dataStore.countrySettings?.length && dataStore.widgetSettings?.useWidgets !== undefined && !SequraFE.state.getCredentialsChanged()) {
                    currentState.split('-')[0] === SequraFE.appStates.ONBOARDING ?
                        this.goToState(SequraFE.appStates.PAYMENT + '-' + SequraFE.appPages.PAYMENT.METHODS) :
                        this.goToState(currentState, null, true);

                    return;
                }

                if (!page) {
                    page = SequraFE.appPages.ONBOARDING.WIDGETS;
                }

                switch (page) {
                    case SequraFE.appPages.ONBOARDING.COUNTRIES:
                        if (!dataStore.connectionSettings?.username) {
                            page = SequraFE.appPages.ONBOARDING.CONNECT
                        }

                        break;
                    case SequraFE.appPages.ONBOARDING.WIDGETS:
                        if (dataStore.countrySettings?.length === 0 || SequraFE.state.getCredentialsChanged()) {
                            page = SequraFE.appPages.ONBOARDING.COUNTRIES
                        }

                        if (!dataStore.connectionSettings?.username) {
                            page = SequraFE.appPages.ONBOARDING.CONNECT
                        }
                        break;
                    default:
                        page = SequraFE.appPages.ONBOARDING.CONNECT
                }

                displayPage(controllerName + '-' + page, additionalConfig);

                return;
            }

            if (!dataStore.connectionSettings?.username || dataStore.countrySettings?.length === 0 || dataStore.widgetSettings?.useWidgets === undefined || SequraFE.state.getCredentialsChanged()) {
                this.goToState(SequraFE.appStates.ONBOARDING, additionalConfig, true);

                return;
            }

            displayPage(state, additionalConfig);
        };

        const displayPage = (state, additionalConfig = null) => {
            let [controllerName, page] = state.split('-');
            if (!Object.values(SequraFE.appStates).includes(controllerName)) {
                SequraFE.state.display();
            }

            if (!page || !SequraFE.pages[controllerName]?.includes(page)) {
                page = SequraFE.pages[controllerName]?.[0];
                state = page ? controllerName + '-' + page : controllerName;
            }

            const config = {storeId: this.getStoreId(), ...(additionalConfig || {})};
            const controller = pageControllerFactory.getInstance(
                controllerName,
                getControllerConfiguration(controllerName, page)
            );

            previousState = currentState;
            currentState = state;
            setPage(page);

            window.location.hash = state;
            controller && controller.display(config);
        }

        /**
         * Gets controller configuration.
         *
         * @param {string} controllerName
         * @param {string?} page
         *
         * @return {Record<string, any>}
         */
        const getControllerConfiguration = (controllerName, page) => {
            let config = utilities.cloneObject(configuration.pageConfiguration[controllerName] || {});
            Object.keys(config).forEach((key) => {
                config[key] = config[key].replace(encodeURIComponent('{storeId}'), this.getStoreId);
            });

            page && (config.page = page);

            return config;
        };

        /**
         * Sets the application page to local storage.
         *
         * @param {string} page
         */
        const setPage = (page) => {
            localStorage.setItem('sq-page', page);
        }

        /**
         * Gets the application page from local storage.
         *
         * @returns {string}
         */
        this.getPage = () => {
            if (window.location.hash) {
                let page = window.location.hash.substring(1);
                if (page) {
                    page = page.split('-')[1];
                    if (page) {
                        setPage(page);
                        return page;
                    }
                }
            }
            return localStorage.getItem('sq-page');
        }

        /**
         * Sets the credentials changed flag to local storage.
         */
        this.setCredentialsChanged = () => {
            SequraFE.state.setData('paymentMethods', null);
            localStorage.setItem('sq-password-changed', '1');
        }

        /**
         * Removes the credentials changed flag from local storage.
         *
         * @returns {string}
         */
        this.removeCredentialsChanged = () => {
            localStorage.removeItem('sq-password-changed');
        }

        /**
         * Gets the credentials changed flag from local storage.
         *
         * @returns {string}
         */
        this.getCredentialsChanged = () => {
            return localStorage.getItem('sq-password-changed');
        }

        /**
         * Sets the store ID to local storage.
         *
         * @param {string} storeId
         */
        this.setStoreId = (storeId) => {
            sessionStorage.setItem('sq-active-store-id', storeId);
        };

        /**
         * Gets the store ID from local storage.
         *
         * @returns {string}
         */
        this.getStoreId = () => {
            return sessionStorage.getItem('sq-active-store-id');
        };

        /**
         * Returns a getVersion promise.
         *
         * @returns {Promise<ShopName>}
         */
        this.getShopName = () => {
            return api.get(configuration.shopNameUrl, () => {
            });
        };

        this.getData = (key) => {
            if (!Object.keys(dataStore).includes(key)) {
                return null;
            }

            return dataStore[key];
        }

        this.setData = (key, value) => {
            if (Object.keys(dataStore).includes(key)) {
                dataStore[key] = value;
            }
        }

        const clearDataStore = () => {
            dataStore = {
                version: null,
                stores: null,
                connectionSettings: null,
                countrySettings: null,
                generalSettings: null,
                widgetSettings: null,
                paymentMethods: null,
                sellingCountries: null,
                shopCategories: null
            };
        }
    }

    SequraFE.StateController = StateController;
})();
