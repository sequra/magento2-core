---
name: architecture
description: Map of how Sequra_Core is wired — service bootstrap and repository registration, persistence through integration-core's ORM, the declarative payment gateway in di.xml, the checkout payment-methods REST API, webhook and comeback controllers, the admin SPA, and order-lifecycle observers. Use when locating where a feature lives, tracing a flow across layers, or adding a new integration service, gateway command, controller, or observer.
---

# Sequra_Core architecture

**Bootstrap & service wiring.** `Services/Bootstrap.php` extends core's `BootstrapComponent`. It overrides `initServices()` and `initRepositories()` to register this module's concrete implementations against core's interfaces using `ServiceRegister::registerService(...)` and `RepositoryRegistry::registerRepository(...)`. `Observer/ServiceRegisterObserver.php` triggers `Bootstrap::init()` so the container is populated at runtime.

**Persistence.** All core entities (`ConnectionData`, `CountryConfiguration`, `GeneralSettings`, `SeQuraOrder`, `PaymentMethod`, `Credentials`, `Deployment`, queue items, etc.) are stored through core's ORM, backed here by `Repository/BaseRepository.php` (generic) and specialized repos like `Repository/SeQuraOrderRepository.php` and `Repository/QueueItemRepository.php`. Schema lives in `etc/db_schema.xml`; data migrations are versioned patches under `Setup/Patch/Data/Version*.php` and `Setup/Patch/Schema/`.

**Payment gateway.** Configured almost entirely declaratively in `etc/di.xml` via Magento's payment-facade virtual types: `SequraPaymentGatewayFacade` (`Magento\Payment\Model\Method\Adapter`), `SequraPaymentGatewayCommandPool` (capture/refund), and the value-handler pool. PHP gateway classes under `Gateway/` (`Request/`, `Http/`, `Response/`) build, transfer, and handle responses for capture/refund/void/order-update against the SeQura API. The payment method code constant is `Sequra\Core\Model\Ui\ConfigProvider::CODE`.

**Checkout payment-methods API.** Frontend checkout fetches available SeQura methods and the payment form through REST endpoints declared in `etc/webapi.xml` (`/V1/sequra_core/...`), served by `Model/Api/` services. There are guest vs. customer variants and a newer `Checkout/` set, wired through `di.xml` virtual types (`Sequra{Customer,Guest}PaymentService`, etc.) with `CartProvider` strategies.

**Controllers.** `Controller/Webhook/` and `Controller/IntegrationWebhook/` receive SeQura callbacks; `Controller/Comeback/` and `Controller/Hpp/` handle the hosted-payment-page return flow; `Controller/AsyncProcess/` runs core's async task queue; `Controller/Adminhtml/Configuration/` backs the admin onboarding/settings UI. CSRF for webhook endpoints is handled by `Plugin/Framework/App/Request/CsrfValidator.php`.

**Admin UI.** The configuration screen is a single-page app shipped as prebuilt assets from the `sequra-core-admin-fe` (a.k.a. `integration-core-ui`) npm package, copied into `view/adminhtml/web/`. Update the package version and re-import with `bin/update-integration-core-ui` rather than editing the copied assets.

**Order lifecycle.** `Observer/` hooks Magento order events (cancellation, shipment, address changes) to keep SeQura order state in sync; `Plugin/OrderDetails.php` and the widget plugins augment storefront/admin rendering. Promotional widgets and banners are configured through `Block/Widget*`, `Block/Banner.php`, and `Services/BusinessLogic/PromotionalWidget/`.
