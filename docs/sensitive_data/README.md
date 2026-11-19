# Sensitive information identification and classification

The [European General Data Protection Regulation](https://gdpr-info.eu/) (GDPR) enforces us to keep sensitive information safe and secure. This document describes the process to identify and classify sensitive information in our applications.

This is a legal requirement that we must comply with. It is not a technical requirement, but it is a requirement that we must comply with in order to minimize the risk of a security breach and fines from the European Union.

To fulfill that requirement, we are going to use `docs/sensitive_data.yaml` file in each repository. This file will contain a list of sensitive information that the application handles, and the classification of that information following the definitions documented in the [Sensitive data classification guide](https://sequra.atlassian.net/wiki/spaces/EN/pages/3880026161/Sensitive+data+classification+guide) page. In case on doubt with any classification, get in touch with @erolosada

_All services, applications, and repositories must include the `docs/sensitive-data.yaml` following the structure defined to allow parsing and unification of all of them when need it._

The ownership of the file is the responsibility of the team or teams that own the application, ensuring _their_ information is in sync with reality. The file must be updated when the application is updated to include new sensitive information.

The approach described above was decided in the [Tech decision record - Sensitive information document](https://sequra.atlassian.net/wiki/spaces/EN/pages/3814883329/2022.11.25+-+Accepted+-+Sensitive+information+document+for+all+application+repositories), there you can find more information about the process and the reasons behind it.
