if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * Handles settings page logic.
     *
     * @param {{
     * getConnectionDataUrl: string,
     * getNotConnectedDeploymentsUrl: string,
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
        /** @type ConnectionSettings **/
        let connectionSettings;
        /** @type string | null */
        let successMessage;

        /**
         * Displays page content.
         *
         * @param {{ state?: string, storeId: string, successMessage?: string | null }} config
         */
        this.display = ({storeId, successMessage: message = null}) => {
            utilities.showLoader();
            currentStoreId = storeId;
            successMessage = message;
            templateService.clearMainPage();
            stores = SequraFE.state.getData('stores');
            version = SequraFE.state.getData('version');
            connectionSettings = SequraFE.state.getData('connectionSettings');

            initializePage();
            renderPage();
        };

        /**
         * Renders the connection settings page.
         */
        const renderPage = () => {
            utilities.showLoader();

            const cached = SequraFE.state.getData('notConnectedDeployments');
            // The store id is already filled in: the state controller resolves every URL of
            // the page configuration before handing it to a controller.
            const url = configuration.getNotConnectedDeploymentsUrl;

            if (!cached && !url) {
                // A store that does not say where the deployments are still gets its
                // connection page; only the manage-deployments button has nothing to offer.
                console.error(
                    'SequraFE: pageConfiguration.settings.getNotConnectedDeploymentsUrl is not configured'
                );
            }

            (cached ? Promise.resolve(cached) : url ? api.get(url, null, SequraFE.customHeader) : Promise.resolve([]))
                .then(renderConnectionSettingsForm)
                .catch((error) => {
                    console.error('Error occurred while rendering the page: ', error);
                })
                .finally(() => utilities.hideLoader());
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
                {connectionSettings, activeDeploymentsIds, notConnectedDeployments, successMessage},
                {...configuration, appState: SequraFE.appStates.SETTINGS}
            );

            form?.render();
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
                    case SequraFE.appPages.SETTINGS.CONNECTION:
                        return {
                            label: 'sidebar.connectionSettings',
                            href: '#settings-connection',
                            icon: 'connection',
                            isActive: activePage === SequraFE.appPages.SETTINGS.CONNECTION
                        }
                }
            }).filter(Boolean);
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
                            portalUrl: connectionSettings?.portalUrl,
                            menuItems: SequraFE.utilities.getMenuItems(SequraFE.appStates.SETTINGS)
                        }
                    ),
                    generator.createElement('div', 'sq-page-content', '', null, [getSidebarRow()]),
                    generator.createSupportLink()
                ]))
        }

        const getSidebarRow = () => {
            const links = getLinkConfiguration();

            // One destination is not a navigation - the same rule the top menu follows. A
            // store that keeps a single settings page in the shop, the rest being the seQura
            // portal's, gives that page the whole row.
            return generator.createElement('div', 'sq-content-row', '', null, [
                links.length < 2 ? [] : generator.createSettingsSidebar({links}),
                generator.createElement('main', 'sq-content')
            ]);
        }
    }

    SequraFE.SettingsController = SettingsController;
})();
