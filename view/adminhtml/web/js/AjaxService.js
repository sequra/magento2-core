if (!window.SequraFE) {
    window.SequraFE = {};
}

/**
 * @typedef AjaxServiceType
 * @property {(url:string, errorCallback?: (error?: Record<string, any>) => Promise<any> | any, fallthrough?:
 *     boolean) => Promise<any>} get
 * @property {(url:string, data?: any, customHeader?: Record<string, string>, errorCallback: (error?:
 *     Record<string, any>) => Promise<void>) => Promise<any>} post
 * @property {(url:string, data?: any, customHeader?: Record<string, string>, errorCallback: (error?:
 *     Record<string, any>) => Promise<void>) => Promise<any>} put
 * @property {(url:string, data?: any, errorCallback?: (error?: Record<string, any>) => Promise<void>) =>
 *     Promise<any>} delete
 */
(function () {
    /**
     * Ajax/API service.
     *
     * @returns {AjaxServiceType}
     */
    const AjaxService = () => {
        /**
         * Handles the server response.
         * @param {Response} response
         * @param {(error: Record<string, any>) => Promise<void>?} errorCallback
         * @returns {Record<string, any>}
         */
        const handleResponse = (response, errorCallback) => {
            if (!errorCallback) {
                errorCallback = SequraFE.responseService.errorHandler;
            }

            try {
                if (response.ok) {
                    return response.json();
                }

                if (response.status === 401 || response.status === 403) {
                    return response.json().then(SequraFE.responseService.unauthorizedHandler);
                }

                if (response.status === 400) {
                    return response.json().then(errorCallback);
                }
            } catch (e) {}

            return errorCallback({ status: response.status, error: response.statusMessage });
        };

        /**
         * Performs GET ajax request.
         *
         * @param {string} url The URL to call.
         * @param {(error: Record<string, any>) => Promise<void>?} errorCallback
         * @param {Record<string, string>?} customHeader
         */
        const get = (url, errorCallback, customHeader = {}) => call('GET', url, null, errorCallback, customHeader);

        /**
         * Performs POST ajax request.
         *
         * @param {string} url The URL to call.
         * @param {Record<string, any>?} data
         * @param {Record<string, string>?} customHeader
         * @param {(error: Record<string, any>) => Promise<void>?} errorCallback
         */
        const post = (url, data, customHeader, errorCallback) => call('POST', url, data, errorCallback, customHeader);

        /**
         * Performs PUT ajax request.
         *
         * @param {string} url The URL to call.
         * @param {Record<string, any>} data
         * @param {Record<string, string>?} customHeader
         * @param {(error: Record<string, any>) => Promise<void>?} errorCallback
         */
        const put = (url, data, customHeader, errorCallback) => call('PUT', url, data, errorCallback, customHeader);

        /**
         * Performs DELETE ajax request.
         *
         * @param {string} url The URL to call.
         * @param {Record<string, any>?} data
         * @param {(error: Record<string, any>) => Promise<void>?} errorCallback
         */
        const del = (url, data, errorCallback) => call('DELETE', url, data, errorCallback);

        /**
         * Performs ajax call.
         *
         * @param {'GET' | 'POST' | 'PUT' | 'DELETE'} method The HTTP method.
         * @param {string} url The URL to call.
         * @param {Record<string, any>?} data The data to send.
         * @param {(error: Record<string, any>) => Promise<any>?} errorCallback An error callback. If not set, the
         *     default one will be used.
         * @param {Record<string, string>?} customHeader
         * @returns {Promise<Record<string, any>>}
         */
        const call = (method, url, data, errorCallback, customHeader) => {
            const callUUID = SequraFE.StateUUIDService.getStateUUID();

            return new Promise((resolve, reject) => {
                url = url.replace('https:', '');
                url = url.replace('http:', '');

                const headers = {
                    'Content-Type': 'application/json',
                    ...(customHeader || {})
                };

                if (headers['Content-Type'] === 'multipart/form-data') {
                    delete headers['Content-Type'];
                }

                const body = data
                    ? headers['Content-Type'] === 'application/json'
                        ? JSON.stringify(data)
                        : data
                    : undefined;

                fetch(url, { method, headers, body }).then((response) => {
                    if (callUUID !== SequraFE.StateUUIDService.getStateUUID()) {
                        // Obsolete request. The app has changed the original state (page) that issued the call.
                        console.log('cancelling an obsolete request', url);
                        return;
                    }

                    handleResponse(response, errorCallback).then(resolve).catch(reject);
                }).catch(reject);
            });
        };

        return {
            get,
            post,
            put,
            delete: del,
            call,
            handleResponse
        };
    };

    SequraFE.ajaxService = AjaxService();
})();
