if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    function TemplateService() {
        /**
         * The configuration object for all templates.
         */
        let templates = {};
        let mainPlaceholder = '#sq-page-wrapper';
        let contentWrapper = '.sq-page-content-wrapper';

        /**
         * Gets the main page DOM element.
         *
         * @returns {HTMLElement}
         */
        this.getMainPage = () => document.querySelector(mainPlaceholder);

        /**
         * Gets the main page DOM element.
         *
         * @returns {HTMLElement}
         */
        this.getContentWrapper = () => document.querySelector(contentWrapper);

        /**
         * Clears the main page.
         *
         * @return {HTMLElement}
         */
        this.clearMainPage = () => {
            this.clearComponent(this.getMainPage());
        };

        /**
         * Sets the content templates.
         *
         * @param {{}} configuration
         */
        this.setTemplates = (configuration) => {
            templates = configuration;
        };

        /**
         * Gets the template with translated text.
         *
         * @param {string} templateId
         *
         * @return {string} HTML as string.
         */
        this.getTemplate = (templateId) => translate(templates[templateId]);

        /**
         * Replaces all translation keys in the provided HTML.
         *
         * @param {string} html
         * @return {string}
         */
        const translate = (html) => {
            return html ? SequraFE.translationService.translateHtml(html) : '';
        };

        /**
         * Removes component's children.
         *
         * @param {Element} component
         */
        this.clearComponent = (component) => {
            while (component.firstChild) {
                component.removeChild(component.firstChild);
            }
        };
    }

    SequraFE.templateService = new TemplateService();
})();
