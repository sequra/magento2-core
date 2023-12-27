✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂
✂✂✂  REMOVE FROM THIS PART BEFORE SUBMITTING YOUR PULL REQUEST ✂✂✂
✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂

Here are some friendly reminders before submitting your pull request:

- There should be an issue describing the motivation for this change.
- Everything should be well tested.
- Check that you are not making any intensive/slow queries (provide db explain if necessary).
- Migrations should be safe https://sequra.atlassian.net/wiki/display/EN/Safe+Operations+For+High+Volume+PostgreSQL

YOU CAN REMOVE THE PARTS OF THE TEMPLATE THAT DO NOT APPLY TO YOUR PULL REQUEST

✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂
✂✂✂  REMOVE UP TO THIS PART BEFORE SUBMITTING YOUR PULL REQUEST ✂✂✂
✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂✂

 ### What is the goal?

_Provide a description of the overall goal (you can usually use the one from the issue)_

 ### References
* **Issue:** _jira issue goes here, if suggesting a new feature or change, please discuss it in an issue first_
* **Related pull-requests:** _list of related pull-requests (comma-separated): #1, #2_
* **Sentry errors:** _list of links to Sentry errors (comma-separated): link1, link2_
* **Any other references (AppSignal, Prometheus, ...):** _list of links to other references (comma-separated): link1, link2_

 ### How is it being implemented?

_Provide a description of the implementation_

 ### Opportunistic refactorings

_Have you improved the code/app in anyway? Explain what you did._

 ### Caveats

_If you find any, please describe all the special conditions._

### Does it affect (changes or update) any sensitive data?

_Check [Sensitive data list documentation](../blob/master/docs/sensitive_data/README.md) and [Sensitive data list](../blob/master/docs/sensitive_data/sensitive-data.yml)

 ### How is it tested?

_Automatic tests? Manual tests?_

_If it cannot be tested explain why._

_Add use cases if specs are incomplete or missing._

 ### How is it going to be deployed?

_If it does not require anything special, just write "Standard deployment". Otherwise, put the required steps._

- [ ] _Step 1_
- [ ] _Step 2_
