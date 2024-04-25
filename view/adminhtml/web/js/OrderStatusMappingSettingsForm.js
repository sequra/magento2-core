if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * @typedef ShopOrderStatus
     * @property {string} statusId
     * @property {string} statusName
     */

    /**
     * @typedef OrderStatusMapping
     * @property {string} sequraStatus
     * @property {string} shopStatus
     */

    /**
     * @typedef OrderStatusSettings
     * @property {OrderStatusMapping[]} orderStatusMappings
     * @property {boolean} informCancellationsToSequra
     */

    /**
     * Handles order status mapping settings form logic.
     *
     * @param {{
     * orderStatusSettings: OrderStatusSettings,
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

        const { elementGenerator: generator, utilities} = SequraFE;
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

            if(!activeSettings) {
                activeSettings = utilities.cloneObject(defaultFormData);
                activeSettings.informCancellationsToSequra =
                    data?.orderStatusSettings?.informCancellationsToSequra ??
                    defaultFormData.informCancellationsToSequra;
                activeSettings.orderStatusMappings =
                    data?.orderStatusSettings?.orderStatusMappings ??
                    defaultFormData.orderStatusMappings;
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
                        text: 'orderStatusSettings.description'
                    }),
                    generator.createDropdownField({
                        label: 'orderStatusSettings.paid.label',
                        description: 'orderStatusSettings.paid.description',
                        value: changedSettings?.orderStatusMappings?.find(
                            (mapping) => mapping.sequraStatus === SEQURA_STATUSES.PAID
                        )?.shopStatus || '',
                        options: getStatusOptions(),
                        variation: 'label-left',
                        onChange: (value) => handleChange(SEQURA_STATUSES.PAID, value)
                    }),
                    generator.createDropdownField({
                        label: 'orderStatusSettings.inReview.label',
                        description: 'orderStatusSettings.inReview.description',
                        value: changedSettings?.orderStatusMappings?.find(
                            (mapping) => mapping.sequraStatus === SEQURA_STATUSES.IN_REVIEW
                        )?.shopStatus,
                        options: getStatusOptions(),
                        variation: "label-left",
                        onChange: (value) => handleChange(SEQURA_STATUSES.IN_REVIEW, value)
                    }),
                    generator.createDropdownField({
                        label: 'orderStatusSettings.cancelled.label',
                        description: 'orderStatusSettings.cancelled.description',
                        value: changedSettings.orderStatusMappings?.find(
                            (mapping) => mapping.sequraStatus === SEQURA_STATUSES.CANCELLED
                        )?.shopStatus,
                        options: getStatusOptions(),
                        variation: 'label-left',
                        onChange: (value) => handleChange(SEQURA_STATUSES.CANCELLED, value)
                    }),
                    generator.createToggleField({
                        value: changedSettings.informCancellationsToSequra,
                        label: 'orderStatusSettings.informCancellations.label',
                        description: SequraFE.translationService.translate(
                            'orderStatusSettings.informCancellations.description'
                        ).replace('{{shopName}}', data.shopName),
                        onChange: (value) => handleChange('informCancellationsToSequra', value)
                    })
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
            const options = [{ label: "None", value: ""}];
            data.shopOrderStatuses.map((shopOrderStatus) => {
                options.push({
                    label: shopOrderStatus.statusName.charAt(0).toUpperCase() + shopOrderStatus.statusName.slice(1),
                    value: shopOrderStatus.statusId
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
            if(name === 'informCancellationsToSequra') {
                changedSettings[name] = value;
            } else {
                const mapping =  changedSettings.orderStatusMappings.find((mapping) => mapping.sequraStatus === name)
                mapping ?
                    mapping.shopStatus = value :
                    changedSettings.orderStatusMappings.push({shopStatus: value, sequraStatus: name});
            }

            utilities.disableFooter(false);
        }

        /**
         * Handles saving of the form.
         */
        const handleSave = () => {
            utilities.showLoader();
            api.post(configuration.saveOrderStatusMappingSettingsUrl, changedSettings)
                .then(() => {
                    activeSettings = utilities.cloneObject(changedSettings);
                    utilities.disableFooter(true);
                })
                .finally(utilities.hideLoader);
        }
    }

    SequraFE.OrderStatusMappingSettingsForm = OrderStatusMappingSettingsForm;
})();
