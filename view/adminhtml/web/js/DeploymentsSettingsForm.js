(function () {
    /**
     * @typedef Deployment
     * @property {string} id
     * @property {string} name
     * @property {boolean} active
     */

    /**
     * Handles the deployments settings form logic.
     *
     * @param {{
     * deploymentsSettings: Deployment[],
     * }} data - Preloaded deployments settings.
     * @param {{
     * appState: string
     * }} configuration - Configuration for current app state.
     * @constructor
     */
    function DeploymentsSettingsForm(data, configuration) {
        const {
            elementGenerator: generator, validationService: validator, utilities
        } = SequraFE;

        /** @type {Deployment[]} */
        let allDeployments = (data.deploymentsSettings || []).map(dep => ({
            ...utilities.cloneObject(dep), active: dep.active !== false,
        }));

        /** @type {boolean} */
        let deploymentsChanged = false;

        /**
         * Public render method that initializes the form.
         */
        this.render = () => {
            removePreviousContent();
            initForm();
            utilities.hideLoader();
        };

        /**
         * Removes previous inner content of the form if present.
         */
        const removePreviousContent = () => {
            const pageContent =
                /** @type {HTMLElement|null} */ (document.querySelector('.sq-content'));
            const inner = pageContent?.querySelector('.sq-content-inner');
            if (inner) {
                inner.remove(); // Keeps header intact, removes form content
            }
        };

        /**
         * Initializes the deployment selection form and adds it to the page.
         */
        const initForm = () => {
            const pageContent =
                /** @type {HTMLElement|null} */ (document.querySelector('.sq-content'));
            const selectedIds = allDeployments
                .filter(dep => dep.active)
                .map(dep => dep.id);

            const content = generator.createElement('div', 'sq-content-inner sqv--deployments', '', null, [
                generator.createElement('div', 'sqp-flash-message-wrapper'),
                generator.createPageHeading({
                    title: 'deployments.title',
                    text: 'deployments.description'
                }),
                generator.createMultiItemSelectorField({
                    name: 'deployments-selector',
                    label: 'deployments.selector.label',
                    description: 'deployments.selector.description',
                    value: selectedIds.join(','),
                    options: allDeployments.map(dep => ({
                        label: dep.name,
                        value: dep.id
                    })),
                    onChange: handleDeploymentChange
                }),
                generator.createButtonField({
                    className: 'sq-continue sqm--block',
                    buttonType: 'primary',
                    buttonLabel: 'general.continue',
                    onClick: handleSave
                })
            ]);

            pageContent?.append(content);

            if (configuration.appState !== SequraFE.appStates.ONBOARDING) {
                pageContent?.append(generator.createPageFooter({
                    onCancel: () => {
                        const pageContent = document.querySelector('.sq-content');
                        while (pageContent?.firstChild) {
                            pageContent.removeChild(pageContent.firstChild);
                        }
                        initForm(); // re-render form
                    }, onSave: handleSave
                }));
            }
        };

        /**
         * Handles selection change in the deployments list.
         *
         * @param {string[]} selectedIds - Array of selected deployment IDs.
         */
        const handleDeploymentChange = (selectedIds) => {
            allDeployments.forEach(dep => {
                dep.active = selectedIds.includes(dep.id);
            });
            deploymentsChanged = true;
        };

        /**
         * Validates the form to ensure at least one deployment is selected.
         *
         * @returns {boolean} - True if valid, false otherwise.
         */
        const isFormValid = () => {
            const hasActive = allDeployments.some(dep => dep.active);
            if (!hasActive) {
                validator.validateRequiredField(
                    document.querySelector(`[name="deployments-selector"]`),
                    'validation.requiredField'
                );

                return false;
            }

            return true;
        };

        /**
         * Handles save button click.
         */
        const handleSave = () => {
            if (!isFormValid()) {
                return;
            }

            utilities.showLoader();
            saveChangedData();
        };

        /**
         * Saves deployment settings and redirects to the next page in onboarding.
         */
        const saveChangedData = () => {
            deploymentsChanged = false;

            SequraFE.state.setData('deploymentsSettings', allDeployments);

            if (configuration.appState === SequraFE.appStates.ONBOARDING) {
                const index = SequraFE.pages.onboarding.indexOf(SequraFE.appPages.ONBOARDING.DEPLOYMENTS);
                const nextPage = SequraFE.pages.onboarding[index + 1];

                window.location.hash = nextPage ? `${configuration.appState}-${nextPage}` : SequraFE.appStates.SETTINGS;
            } else {
                utilities.hideLoader();
            }
        };
    }

    SequraFE.DeploymentsSettingsForm = DeploymentsSettingsForm;
})();
