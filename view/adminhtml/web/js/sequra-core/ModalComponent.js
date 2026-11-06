if (!window.SequraFE) {
    window.SequraFE = {};
}

if (!window.SequraFE.components) {
    window.SequraFE.components = {};
}

(function () {
    /**
     * @typedef ButtonConfig
     * @property {string} label
     * @property {string?} className
     * @property {'primary' | 'secondary'} type
     * @property {() => void} onClick
     */

    /**
     * @typedef ModalConfiguration
     * @property {string?} title
     * @property {string?} className
     * @property {HTMLElement} content The content of the body.
     * @property {ButtonConfig[]} buttons Footer buttons.
     * @property {(modal: HTMLDivElement) => void?} onOpen Will fire after the modal is opened.
     * @property {() => boolean?} onClose Will fire before the modal is closed.
     *      If the return value is false, the modal will not be closed.
     * @property {boolean} [footer=false] Indicates whether to use footer. Defaults to false.
     * @property {boolean} [canClose=true] Indicates whether to use an (X) button or click outside the modal
     * to close it. Defaults to true.
     * @property {boolean} [fullWidthBody=false] Indicates whether to make body full width
     */

    /**
     * @param {ModalConfiguration} config
     * @constructor
     */
    function ModalComponent(config) {
        const { templateService, translationService, utilities, elementGenerator } = SequraFE;

        /**
         * @type {HTMLDivElement}
         */
        let modal;

        /**
         * Closes the modal on Esc key.
         *
         * @param {KeyboardEvent} event
         */
        const closeOnEsc = (event) => {
            if (event.key === 'Escape') {
                this.close();
            }
        };

        /**
         * Closes the modal.
         */
        this.close = () => {
            if (!config.onClose || config.onClose()) {
                window.removeEventListener('keyup', closeOnEsc);
                modal?.remove();
            }
        };

        /**
         * Opens the modal.
         */
        this.open = (contentWrapper = null) => {
            const modalTemplate =
                '<div id="sq-modal" class="sq-modal sqs--hidden">\n' +
                '    <div class="sqp-modal-content">' +
                '        <button class="sqp-close-button"><span></span></button>' +
                '        <div class="sqp-title"></div>' +
                '        <div class="sqp-body"></div>' +
                '        <div class="sqp-footer"></div>' +
                '    </div>' +
                '</div>';

            modal = SequraFE.elementGenerator.createElementFromHTML(modalTemplate);
            const closeBtn = modal.querySelector('.sqp-close-button'),
                closeBtnSpan = modal.querySelector('.sqp-close-button span'),
                title = modal.querySelector('.sqp-title'),
                body = modal.querySelector('.sqp-body'),
                footer = modal.querySelector('.sqp-footer');

            utilities.showElement(modal);
            if (config.canClose === false) {
                utilities.hideElement(closeBtn);
            } else {
                window.addEventListener('keyup', closeOnEsc);
                closeBtn.addEventListener('click', this.close);
                closeBtnSpan.style.display = 'flex';
                modal.addEventListener('click', (event) => {
                    if (event.target.id === 'sq-modal') {
                        event.preventDefault();
                        this.close();

                        return false;
                    }
                });
            }

            if (config.title) {
                title.innerHTML = translationService.translate(config.title);
            } else {
                utilities.hideElement(title);
            }

            if (config.className) {
                modal.classList.add(...config.className.split(' '));
            }

            config.content && body.append(...(Array.isArray(config.content) ? config.content : [config.content]));
            if (config.fullWidthBody) {
                body.classList.add('sqm--full-width');
            }

            if (config.footer === false || !config.buttons) {
                utilities.hideElement(footer);
            } else {
                config.buttons.forEach((button) => {
                    footer.appendChild(elementGenerator.createButton(button));
                });
            }

            if(contentWrapper) {
                document.querySelector(contentWrapper).appendChild(modal);
            } else {
                templateService.getContentWrapper().appendChild(modal);
            }

            if (config.onOpen) {
                config.onOpen(modal);
            }
        };
    }

    SequraFE.components.Modal = {
        /** @param {ModalConfiguration} config */
        create: (config) => new ModalComponent(config)
    };
})();
