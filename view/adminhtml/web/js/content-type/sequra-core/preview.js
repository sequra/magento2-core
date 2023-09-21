define([
    "Magento_PageBuilder/js/content-type/preview",
    "Magento_PageBuilder/js/content-type-toolbar",
    "Magento_PageBuilder/js/events",
    "Magento_PageBuilder/js/content-type-menu/hide-show-option",
    "Magento_PageBuilder/js/uploader",
    "Magento_PageBuilder/js/wysiwyg/factory",
    "Magento_PageBuilder/js/config"
], function (
    PreviewBase,
    Toolbar,
    events,
    hideShowOption,
    Uploader,
    WysiwygFactory,
    Config
) {
    "use strict";

    /**
     * Sequra content type preview class
     *
     * @param parent
     * @param config
     * @param stageId
     * @constructor
     */
    function Preview(parent, config, stageId) {
        PreviewBase.call(this, parent, config, stageId);

        var sequraConfigParams = this.config.additional_data.previewConfig.widgetConfig;

        if (typeof Sequra !== "undefined") {
            Sequra.refreshComponents?.();
        } else {
            (
                function (i, s, o, g, r, a, m) {
                    i["SequraConfiguration"] = g;
                    i["SequraOnLoad"] = [];
                    i[r] = {};
                    i[r][a] = function (callback) {
                        i["SequraOnLoad"].push(callback);
                    };
                    (a = s.createElement(o)),
                        (m = s.getElementsByTagName(o)[0]);
                    a.async = 1;
                    a.src = g.scriptUri;
                    m.parentNode.insertBefore(a, m);
                }
            )
            (window, document, "script", sequraConfigParams, "Sequra", "onLoad");
        }

        events.on("Sequra_Core:renderAfter", function (args) {
            if (typeof Sequra !== "undefined") {
                Sequra.refreshComponents?.();
            }
        });
    }

    var $super = PreviewBase.prototype;

    Preview.prototype = Object.create(PreviewBase.prototype);

    /**
     * Bind any events required for the content type to function
     */
    Preview.prototype.bindEvents = function () {
        PreviewBase.prototype.bindEvents.call(this);
    };

    /**
     * An example callback from the above bound event
     *
     * @param args
     */
    Preview.prototype.handleEvent = function (args) {
        console.log("Binding Works");
    };

    /**
     * Determine if the WYSIWYG editor is supported
     *
     * @returns {boolean}
     */
    Preview.prototype.isWysiwygSupported = function () {
        return Config.getConfig("can_use_inline_editing_on_stage");
    };

    /**
     * Init the WYSIWYG component
     *
     * @param {HTMLElement} element
     */
    Preview.prototype.initWysiwyg = function (element) {
        var self = this;
        var config = this.config.additional_data.wysiwygConfig.wysiwygConfigData;

        this.element = element;
        element.id = this.contentType.id + "-editor";

        config.adapter.settings.fixed_toolbar_container =
            "#" + this.contentType.id + " .quote-description-text-content";

        WysiwygFactory(
            this.contentType.id,
            element.id,
            this.config.name,
            config,
            this.contentType.dataStore,
            "description",
            this.contentType.stageId
        ).then(function (wysiwyg) {
            self.wysiwyg = wysiwyg;
        });
    };

    /**
     * Stop event to prevent execution of action when editing text area
     *
     * @returns {boolean}
     */
    Preview.prototype.stopEvent = function () {
        event.stopPropagation();
        return true;
    };

    /**
     * Modify the options returned by the content type
     *
     * @returns {*}
     */
    Preview.prototype.retrieveOptions = function () {
        var options = $super.retrieveOptions.call(this, arguments);
        delete options.edit;

        return options;
    };

    return Preview;
});
