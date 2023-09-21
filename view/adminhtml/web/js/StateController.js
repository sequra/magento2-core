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
     * Main controller of the application.
     *
     * @param {StateConfiguration} configuration
     *
     * @constructor
     */
    function StateController(configuration) {
        /** @type AjaxServiceType */
        const api = SequraFE.ajaxService;
        const { pageControllerFactory, templateService, utilities } = SequraFE;

        let currentState = '';
        let previousState = '';

        /**
         * Main entry point for the application.
         * Determines the current state and runs the start controller.
         */
        this.display = () => {
            utilities.showLoader();
            templateService.clearMainPage();

            window.addEventListener('hashchange', updateStateOnHashChange, false);

            api.get(!this.getStoreId() ? configuration.currentStoreUrl : configuration.storesUrl, () => null, true)
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
                .finally(utilities.hideLoader);
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
            return api.get(configuration.stateUrl.replace(encodeURIComponent('{storeId}'), this.getStoreId()), () => {})
                .then((response) => {
                    let hash = response.state;
                    let page = getPage();
                    if(page) {
                        hash += '-' + page;
                    }

                    if(response.state === SequraFE.appStates.ONBOARDING) {
                        this.goToState(hash, null, true);

                        return;
                    }

                    if(!page || SequraFE.pages.payment?.includes(page)) {
                        this.goToState(SequraFE.appStates.PAYMENT, null, true)

                        return;
                    }

                    this.goToState(SequraFE.appStates.SETTINGS + '-' + page, null, true);
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
            let [controllerName, page] = state.split('-');
            if ((currentState === state && !force)) {
                return;
            }

            if(!Object.values(SequraFE.appStates).includes(controllerName)) {
                SequraFE.state.display();
            }

            if(!page) {
                page = SequraFE.pages[controllerName]?.[0];
                state = page ? controllerName + '-' + page : controllerName;
            }

            const doneStep = parseInt(localStorage.getItem('sq-done-step'));
            // Forbid from skipping an onboarding step
            if(controllerName === SequraFE.appStates.ONBOARDING && (doneStep < SequraFE.pages.onboarding.indexOf(page))) {
                page = SequraFE.pages.onboarding[doneStep];
                state = controllerName + '-' + page;
            }

            if(!SequraFE.pages[controllerName]?.includes(page)) {
                window.location.hash = controllerName;

                return;
            }

            window.location.hash = state;

            const config = { storeId: this.getStoreId(), ...(additionalConfig || {}) };
            const controller = pageControllerFactory.getInstance(
                controllerName,
                getControllerConfiguration(controllerName, page)
            );

            controller && controller.display(config);
            previousState = currentState;
            currentState = state;
            setPage(page);
        };

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
            })

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
        const getPage = () => {
            return localStorage.getItem('sq-page');
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
         * Returns a getStores promise.
         *
         * @returns {Promise<Store | Store[]>}
         */
        this.getStores = () => {
            return api.get(configuration.storesUrl, () => {});
        };

        /**
         * Returns a getVersion promise.
         *
         * @returns {Promise<Version>}
         */
        this.getVersion = () => {
            if (SequraFE.isPromotional) {
                return null;
            }

            return api.get(configuration.versionUrl, () => {});
        };

        /**
         * Returns a getVersion promise.
         *
         * @returns {Promise<ShopName>}
         */
        this.getShopName = () => {
            return api.get(configuration.shopNameUrl, () => {});
        };
    }

    SequraFE.StateController = StateController;
})();
