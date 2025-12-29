if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * @typedef ShopOrderStatus
     * @property {string} id
     * @property {string} name
     */

    /**
     * @typedef OrderStatusMapping
     * @property {string} sequraStatus
     * @property {string} shopStatus
     */

    /**
     * Handles order status mapping settings form logic.
     *
     * @param {{
     * orderStatusSettings: OrderStatusMapping[],
     * shopOrderStatuses: ShopOrderStatus[],
     * shopName: string
     * }} data
     * @param {{
     * getShopOrderStatusesUrl: string,
     * getOrderStatusMappingSettingsUrl: string,
     * saveOrderStatusMappingSettingsUrl: string,
     * page: string,
     * }} configuration
     * @constructor
     */
    function OrderStatusMappingSettingsForm(data, configuration) {
        const SEQURA_STATUSES = {
            PAID: 'approved',
            IN_REVIEW: 'needs_review',
            CANCELLED: 'cancelled'
        }

        const { elementGenerator: generator, utilities } = SequraFE;
        /** @type AjaxServiceType */
        const api = SequraFE.ajaxService;
        /** @type OrderStatusSettings */
        let activeSettings;
        /** @type OrderStatusSettings */
        let changedSettings;

        /** @type OrderStatusSettings*/
        const defaultFormData = {
            orderStatusMappings: [],
            informCancellationsToSequra: true
        };

        /**
         * Handles form rendering.
         */
        this.render = () => {
            utilities.showLoader();

            if (!activeSettings) {
                activeSettings = data?.orderStatusSettings ? data.orderStatusSettings.map(utilities.cloneObject) : utilities.cloneObject(defaultFormData);
            }

            changedSettings = utilities.cloneObject(activeSettings);

            initForm();
            utilities.disableFooter(true);
            utilities.hideLoader();
        }

        /**
         * Initializes the form structure.
         */
        const initForm = () => {
            const pageContent = document.querySelector('.sq-content');
            pageContent?.append(
                generator.createElement('div', 'sq-content-inner', '', null, [
                    generator.createElement('div', 'sqp-flash-message-wrapper'),
                    generator.createPageHeading({
                        title: 'orderStatusSettings.title',
                        text: SequraFE.translationService.translate(
                            'orderStatusSettings.description'
                        ).replace('{shopName}', data.shopName),
                    }),
                    generator.createDropdownField({
                        label: 'orderStatusSettings.paid.label',
                        description: 'orderStatusSettings.paid.description',
                        value: changedSettings.find(
                            (mapping) => mapping.sequraStatus === SEQURA_STATUSES.PAID
                        )?.shopStatus || '',
                        options: getStatusOptions(),
                        variation: 'label-left',
                        onChange: (value) => handleChange(SEQURA_STATUSES.PAID, value)
                    }),
                    generator.createDropdownField({
                        label: 'orderStatusSettings.inReview.label',
                        description: 'orderStatusSettings.inReview.description',
                        value: changedSettings.find(
                            (mapping) => mapping.sequraStatus === SEQURA_STATUSES.IN_REVIEW
                        )?.shopStatus,
                        options: getStatusOptions(),
                        variation: "label-left",
                        onChange: (value) => handleChange(SEQURA_STATUSES.IN_REVIEW, value)
                    }),
                    generator.createDropdownField({
                        label: 'orderStatusSettings.cancelled.label',
                        description: 'orderStatusSettings.cancelled.description',
                        value: changedSettings.find(
                            (mapping) => mapping.sequraStatus === SEQURA_STATUSES.CANCELLED
                        )?.shopStatus,
                        options: getStatusOptions(),
                        variation: 'label-left',
                        onChange: (value) => handleChange(SEQURA_STATUSES.CANCELLED, value)
                    }),
                ]),
                generator.createPageFooter({
                    onCancel: () => {
                        const pageContent = document.querySelector('.sq-content');
                        while (pageContent?.firstChild) {
                            pageContent?.removeChild(pageContent.firstChild);
                        }

                        this.render();
                    },
                    onSave: handleSave
                })
            );
        }

        /**
         * Returns shop status options.
         *
         * @returns {[{label: string, value: string}]}
         */
        const getStatusOptions = () => {
            const options = [{ label: "None", value: "" }];
            data.shopOrderStatuses.map((shopOrderStatus) => {
                options.push({
                    label: shopOrderStatus.name.charAt(0).toUpperCase() + shopOrderStatus.name.slice(1),
                    value: shopOrderStatus.id
                })
            });

            return options;
        }

        /**
         * Handles form input changes.
         *
         * @param name
         * @param value
         */
        const handleChange = (name, value) => {
            const mapping = changedSettings.find((mapping) => mapping.sequraStatus === name)
            mapping ?
                mapping.shopStatus = value :
                changedSettings.push({ shopStatus: value, sequraStatus: name });

            utilities.disableFooter(false);
        }

        /**
         * Handles saving of the form.
         */
        const handleSave = () => {
            utilities.showLoader();
            api.post(configuration.saveOrderStatusMappingSettingsUrl, changedSettings, SequraFE.customHeader)
                .then(() => {
                    activeSettings = utilities.cloneObject(changedSettings);
                    SequraFE.state.setData('orderStatusSettings', activeSettings);
                    utilities.disableFooter(true);
                })
                .finally(utilities.hideLoader);
        }
    }

    SequraFE.OrderStatusMappingSettingsForm = OrderStatusMappingSettingsForm;
})();
