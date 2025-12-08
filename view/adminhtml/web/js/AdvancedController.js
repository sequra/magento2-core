import { DataTable } from 'simple-datatables';
import JSONFormatter from 'json-formatter-js'

if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {

    /**
    * @typedef Log
    * @property {string} type
    * @property {string} datetime
    * @property {string} message
    * @property {string} context
    */

    /**
     * @typedef LogSettings
     * @property {boolean} isEnabled
     * @property {number} level
     */

    /**
     * Handles debug logs page logic.
     *
     * @param {{
     * getLogsUrl: string,
     * }} configuration
     * @constructor
     */
    function AdvancedController(configuration) {
        const { templateService, elementGenerator: generator, components, utilities } = SequraFE;
        /** @type AjaxServiceType */
        const api = SequraFE.ajaxService;
        /** @type string */
        let currentStoreId = '';
        /** @type Version */
        let version;
        /** @type Store[] */
        let stores;
        /** @type ConnectionSettings */
        let connectionSettings;
        /** @type LogSettings */
        let logsSettings;
        /** @type string[] */
        let logs;
        /** @type string */
        let logList;

        /** @type DataTable */
        let dataTable;

        const wrapperClass = 'sqp-datatable-wrapper';

        /**
         * Displays page content.
         *
         * @param {{ state?: string, storeId: string }} config
         */
        this.display = ({ storeId }) => {

            currentStoreId = storeId;
            templateService.clearMainPage();

            stores = SequraFE.state.getData('stores');
            version = SequraFE.state.getData('version');
            connectionSettings = SequraFE.state.getData('connectionSettings');
            logsSettings = SequraFE.state.getData('logsSettings');

            logs = SequraFE.state.getData('logs');

            if (!logs) {
                api.get(configuration.getLogsUrl, null, SequraFE.customHeader)
                    .then(logsRes => {
                        SequraFE.state.setData('logs', logsRes)
                        logs = logsRes;
                    })
                    .catch(error => console.error(error))
                    .finally(() => {
                        initializePage();
                        utilities.hideLoader()
                    });
            } else {
                initializePage();
                utilities.hideLoader();
            }
        };

        /**
         * Renders the page contents.
         */
        const initializePage = () => {
            const pageWrapper = document.getElementById('sq-page-wrapper');

            pageWrapper.append(
                generator.createElement('div', 'sq-page-content-wrapper sqv--advanced', '', null, [
                    SequraFE.components.PageHeader.create(
                        {
                            currentVersion: version?.current,
                            newVersion: {
                                versionLabel: version?.new,
                                versionUrl: version?.downloadNewVersionUrl
                            },
                            mode: connectionSettings.environment === 'live' ? connectionSettings.environment : 'test',
                            activeStore: currentStoreId,
                            stores: stores.map((store) => ({ label: store.storeName, value: store.storeId })),
                            onChange: (storeId) => {
                                if (storeId !== SequraFE.state.getStoreId()) {
                                    SequraFE.state.setStoreId(storeId);
                                    window.location.hash = '';
                                    SequraFE.state.display();
                                }
                            },
                            menuItems: SequraFE.utilities.getMenuItems(SequraFE.appStates.ADVANCED)
                        }
                    ),
                    generator.createElement('div', 'sq-page-content', '', null, [
                        generator.createElement('div', 'sq-content-row', '', null, [
                            generator.createElement('main', 'sq-content', '', null, [
                                generator.createElement('div', 'sq-content-inner', '', null, [
                                    generator.createElement('div', 'sqp-flash-message-wrapper'),
                                    generator.createElement('div', 'sq-table-heading', '', null, [
                                        generator.createPageHeading({
                                            title: 'general.advanced'
                                        }),
                                    ]),
                                    generator.createToggleField({
                                        className: 'sq-log-settings-toggle',
                                        value: logsSettings.isEnabled,
                                        label: 'debug.title',
                                        description: 'debug.description',
                                        onChange: handleEnableDebugChange
                                    }),
                                    generator.createDropdownField({
                                        label: 'debug.log.minLevel',
                                        description: 'debug.log.minLevelDescription',
                                        value: logsSettings.level,
                                        options: getLogLevelOptions(),
                                        variation: "label-left",
                                        onChange: handleLogLevelChange
                                    }),
                                    generator.createElement('div', wrapperClass),
                                ]),
                            ])
                        ])
                    ]),
                    generator.createSupportLink()
                ]));

            initializeDataTable();
        }

        /**
        * Returns log level options.
        *
        * @returns {[{label: string, value: string}]}
        */
        const getLogLevelOptions = () => [
            { label: 'DEBUG', value: 3 },
            { label: 'INFO', value: 2 },
            { label: 'WARNING', value: 1 },
            { label: 'ERROR', value: 0 },
        ]

        /**
         * Returns table headers.
         *
         * @returns {TableCell[]}
         */
        const getTableHeaders = () => {
            return [
                { label: 'debug.log.severity', className: 'sqm--text-left' },
                { label: 'debug.log.datetime', className: 'sqm--text-left' },
                { label: 'debug.log.message', className: 'sqm--text-left' },
            ];
        }

        /**
         * Parse raw log data and return an object.
         *
         * @returns {Log}
         */
        const parseLog = (raw) => {
            const data = raw.split('\t');
            return {
                type: data[0].trim(),
                datetime: data[1].trim(),
                message: data[2].trim(),
                context: data[3].trim()
            };
        }


        /** @param {EventTarget} btn */
        const renderDetails = (btn) => {
            btn.classList.toggle('sqm--log-details-open');
            const container = btn.parentElement.querySelector('.sqm--log-context');
            container.style.display = btn.classList.contains('sqm--log-details-open') ? 'block' : 'none';

            if (!container.firstChild) {
                let json = '';
                try {
                    json = JSON.parse(logList[btn.getAttribute('data-index')].context);
                } catch (e) {
                    json = 'Error parsing log content: ' + e.message;
                }
                const formatter = new JSONFormatter(json, 0);
                container.appendChild(formatter.render());
            }
        }

        /**
         * Returns table rows.
         *
         * @param {string[]} logs
         *
         * @returns {TableCell[][]}
         */
        const getTableRows = (logs) => {
            logList = logs.map(rawLog => parseLog(rawLog));

            return logList.map((log, index) => {
                const className = `sqm--text-left sqm--log sqm--log-${log.type.toLowerCase()}`;

                return [
                    { label: log.type, className },
                    { label: log.datetime, className },
                    {
                        label: log.message,
                        className: `${className} sqm--log-index-${index}`,
                        renderer: (cell) => {
                            if ('' != log.context) {
                                const btn = generator.createButton({
                                    type: 'text',
                                    className: 'sqm--log-details',
                                });
                                btn.setAttribute('data-index', index);
                                cell.append(btn);
                                cell.append(generator.createElement('div', 'sqm--log-context'));
                            }
                            return cell;
                        }
                    }
                ];
            });
        }

        const initializeDataTable = () => {
            if (dataTable) {
                dataTable.destroy();
                dataTable = null;
            }

            const wrapper = document.querySelector(`.${wrapperClass}`);
            if (wrapper) {
                SequraFE.templateService.clearComponent(wrapper);
            }

            wrapper.append(components.DataTable.create(getTableHeaders(), logs?.length ? getTableRows(logs) : []));

            dataTable = new DataTable(`.${wrapperClass} table`, {
                classes: {
                    active: "sq-datatable__active",
                    bottom: "sq-datatable__bottom",
                    container: "sq-datatable__container",
                    cursor: "sq-datatable__cursor",
                    dropdown: "sq-datatable__dropdown",
                    ellipsis: "sq-datatable__ellipsis",
                    empty: "sq-datatable__empty",
                    headercontainer: "sq-datatable__headercontainer",
                    info: "sq-datatable__info",
                    input: "sq-datatable__input",
                    loading: "sq-datatable__loading",
                    pagination: "sq-datatable__pagination",
                    paginationList: "sq-datatable__pagination-list",
                    search: "sq-datatable__search",
                    selector: "sq-datatable__selector",
                    sorter: "sq-datatable__sorter",
                    table: "sq-datatable__table",
                    top: "sq-datatable__top",
                    wrapper: "sq-datatable__wrapper"
                }
            });

            const searchWrapper = document.querySelector('.sq-datatable__search');
            if (!searchWrapper) {
                return;
            }
            searchWrapper.append(
                generator.createButtonField({
                    className: 'sq-controls sqm--block sqm--block-inline',
                    buttonLabel: 'debug.reload',
                    onClick: handleReloadLogs
                }),
                generator.createButtonField({
                    className: 'sq-controls sqm--block sqm--block-inline',
                    buttonType: 'danger',
                    buttonLabel: 'debug.remove',
                    onClick: handleRemoveLogs
                })
            );

            const resetDetails = () => {
                document.querySelectorAll('.sqm--log-context').forEach(elem => {
                    elem.style.display = 'none'
                    elem.innerHTML = '';
                });
                document.querySelectorAll('.sqm--log-details').forEach(elem => {
                    elem.classList.remove('sqm--log-details-open');
                });
            }

            dataTable.on('datatable.init', resetDetails);
            dataTable.on('datatable.page', resetDetails);
            dataTable.on('datatable.selectrow', (rowIndex, event) => {
                if (event.target.classList.contains('sqm--log-details')) {
                    renderDetails(event.target);
                }
            });
        }

        /**
         * Save logs settings.
         * @param {LogSettings} settings
         */
        const saveLogsSettings = (settings) => {
            utilities.showLoader();
            api.post(configuration.saveLogsSettingsUrl, settings, SequraFE.customHeader)
                .then(response => {
                    logsSettings = settings;
                    SequraFE.state.setData('logsSettings', logsSettings);
                }).catch(error => {
                    console.error(error);
                    document.querySelector('.sq-log-settings-toggle input').checked = !settings.isEnabled;
                    showFlashMessage('general.errors.unknown', 'error');
                })
                .finally(() => utilities.hideLoader());
        }

        const handleEnableDebugChange = (value) => saveLogsSettings({
            ...logsSettings,
            isEnabled: value
        });

        const handleLogLevelChange = (value) => saveLogsSettings({
            ...logsSettings,
            level: parseInt(value)
        });

        const showFlashMessage = (message, status) => {
            const container = document.querySelector('.sqp-flash-message-wrapper');
            if (!container) {
                return;
            }

            SequraFE.templateService.clearComponent(container);

            container.prepend(SequraFE.utilities.createFlashMessage(message, status));
        }

        /**
         * Shows the confirm remove modal.
         *
         * @returns {Promise}
         */
        const showConfirmRemoveModal = () => {
            return new Promise((resolve) => {
                const modal = components.Modal.create({
                    title: "debug.log.removeModal.title",
                    className: "sq-modal",
                    content: [generator.createElement('p', '', "debug.log.removeModal.message")],
                    footer: true,
                    canClose: false,
                    buttons: [
                        {
                            type: 'default',
                            label: 'general.cancel',
                            onClick: () => {
                                modal.close();
                                resolve(false);
                            }
                        },
                        {
                            type: 'danger',
                            label: 'debug.remove',
                            onClick: () => {
                                modal.close();
                                resolve(true);
                            }
                        }
                    ]
                });

                modal.open();
            });
        }

        const handleRemoveLogs = () => {
            showConfirmRemoveModal().then((confirmed) => {
                if (!confirmed) {
                    return;
                }

                utilities.showLoader();
                api.delete(configuration.removeLogsUrl, null, null, SequraFE.customHeader)
                    .then(_ => {
                        logs = [];
                        SequraFE.state.setData('logs', logs)
                        initializeDataTable();
                    }).catch(error => {
                        console.error(error);
                        showFlashMessage('general.errors.failedToRemoveLog', 'error');
                    })
                    .finally(() => utilities.hideLoader());
            });
        }

        const handleReloadLogs = () => {
            utilities.showLoader();
            api.get(configuration.getLogsUrl, null, SequraFE.customHeader)
                .then(logsRes => {
                    logs = logsRes;
                    SequraFE.state.setData('logs', logs)
                    initializeDataTable();
                }).catch(error => {
                    console.error(error);
                    showFlashMessage('general.errors.failedToRetrieveLog', 'error');
                })
                .finally(() => utilities.hideLoader());
        }
    }

    SequraFE.AdvancedController = AdvancedController;
})();
