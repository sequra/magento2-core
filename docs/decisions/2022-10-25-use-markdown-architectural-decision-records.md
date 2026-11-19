# Use Markdown Architectural Decision Records
An architecture decision record (ADR) is a document that captures an important architecture decision made along with its context and consequences.

---
**💡 Proposed 💡**
* **Deciders:** @franmosteiro, @jaimevelaz
* **Proposal date:** 25/10/2022
* **Due date:** 01/11/2022
* **Technical information:** None

---
### Contents
* [Context and Problem Statement](./##context-and-problem-statement)
* [Decision Drivers](./##decision-drivers)
* [Considered Options](./##considered-options)
* [Decision Outcome](./##decision-outcome)
  * [ADR file name conventions](./###adr-file-name-conventions)
  * [Suggestions for writing good ADRs](./###suggestions-for-writing-good-adrs)
* [Credits](./##credits)

## Context and Problem Statement
### Context
As the team grows, we find that there is a growing need for documenting better and more the technical decisions we make in our services.

This is not important just to the current people in the teams, or to the ones to come and even for our future ourselves when we need to recheck why we did something or how we decided something, but also for other teams and coworkers to reflect their own decisions and this way, finding some kind of alignment in the technical part without patronizing the autonomy in the teams.

In short, trying to maintain a good balance between them: autonomy, pragmatism, and a similar stack.

### Hypothesis
We think that by providing some base templating for **ADR generation** and using them for our services design decisions, we can make this goal of documenting more and better relatively easier, simpler, faster and ultimately more straightforward for teams, so they will only have to make use of them when creating a new feature or iterating older ones.

### Experiment
We propose using **ADRs** as a tool that **might help us document our technical and design decisions more and better**. And also we think that the best place to place them is near the code, using them as another good way to understand the bigger picture before jumping into the tests and production code to get the detail of the things documented in the ADRs firstly.

Of course, this will be an initial approach and we expect the template to evolve and grow with the collaboration, experience and necessities of any of the teams! 😊

## Decision Drivers
* Incremental growth in the size and number of teams and services.
* Lack of context on other teams owned services, tracing, telemetry etc.
* Lack of context in decision-making and overall direction of services.
* Looking to improve cross-team collaboration.
* Need to enable teams to understand the bigger picture.

## Considered Options
* Use Google Drive and its online editing capabilities, through a Google Doc or Google Sheet.
* Use Atlassian Jira, through the tool's planning tracker.
* Use Atlassian Confluence (wiki) and create wiki-style ADRs.
* Making use of source code version control, such as git, we could create a file for each ADR nearest to the code.
  * [MADR](https://adr.github.io/madr/) 2.1.2 – The Markdown Architectural Decision Records.
  * [Michael Nygard's template](http://thinkrelevance.com/blog/2011/11/15/documenting-architecture-decisions) – The first incarnation of the term "ADR".
  * [Sustainable Architectural Decisions](https://www.infoq.com/articles/sustainable-architectural-design-decisions) – The Y-Statements.
  * Other templates listed at <https://github.com/joelparkerhenderson/architecture_decision_record>
  * Formless – No conventions for file format and structure.

## Decision Outcome
We want to record architectural decisions made in this project.

**Which format and structure should these records follow?**

**Choosen option:** ADRs and in particular the [MADR model](https://adr.github.io/madr/)(without the tooling), because

* Implicit assumptions should be made explicit.
  Design documentation is important to enable people to understand the decisions later on.  
  See also [A rational design process: How and why to fake it](https://doi.org/10.1109/TSE.1986.6312940).
* The MADR format is lean and fits our development style.
* The MADR structure is comprehensible and facilitates usage & maintenance.
* The MADR project is vivid.

### ADR ownership and execution
**Ownership** of the ADR during the drafting and validation process **belongs to the persons/teams that originally initiated it.**

Among other things, **they will be responsible for:**

* **Communicate**, through the necessary and appropriate channels and as many times as necessary, the entire content of the ADR (even before it is finalised or closed, in draft).

* **Obtain the buy-in** of the interlocutors and/or teams involved in the change or improvement proposed by the ADR, including stakeholders, if applicable.

* **By consent** (this means that there is no more appropriate or better proposal, so we go with this one), **the tech team should be aligned and aware** of the step with this new ADR and become part of their own technical decisions.

* **Be advocates of the idea** behind the ADR even after it has gone live, **reminding and helping new teams and colleagues to keep it relevant in their designs.**

### ADR file name conventions
If you choose to create your ADRs using typical text files, then you may want to come up with your own ADR file name convention.

We prefer to use a file name convention that has a specific format. Based on [this other ADR in Simba](https://github.com/sequra/simba/blob/master/adr/2022-03-10-adr-ids-timestamps.md), we will choose this format:

* yyyy-mm-dd-choose-database.md
* yyyy-mm-dd-format-timestamps.md
* yyyy-mm-dd-manage-passwords.md
* yyyy-mm-dd-handle-exceptions.md

Our file name convention:

* Date of creation: yyyy-mm-dd
* The name has a present tense imperative verb phrase. This helps readability and matches our commit message format.
* The name uses lowercase and dashes (same as this repo). This is a balance of readability and system usability.
* The extension is markdown. This can be useful for easy formatting.

### ADR workflow suggestion
```text
⚠️ This is just a suggested workflow. The choosed workflow may depend on the necessities your team has around the ADR: sharing an idea, sharing an idea with some Proof of Concept, sharing the implementation of a new design approach etc.
```

1. Share a new **P**ull**R**equest including the ADR in the docs/decisions folder, to start the conversation with the related teams/peers.

2. Start the conversation on this first PR until it is “accepted” or “rejected”. Meanwhile, add in this PR (or in another one related somehow to the ADR PR) the proposed changes.

3. Before closing the PR containing the ADR, remember to update it with the correct status and the final decisions taken regarding the comments of the rest of the team.

```text
ℹ️ These are just suggestion. Please, feel encouraged to set the workflow better fits within your concrete necessities and let’s learn with the process
```

### ADR ‘status’ changes
An ADR can go through these different states:

* **🚧 WIP 🚧:** still working on the ADR, not yet released.
* **💡 Proposed 💡:** already proposed to the team.
* **🚫 Rejected 🚫:** the team has rejected the ADR, but we save it for traceability.
* **✅ Accepted ✅:** the team has accepted the ADR.
* **🕸 Deprecated 🕸:** the ADR has been deprecated (ex: tech stack change, library deprecation etc).
* **🌱 Superseded by ADR-202x-xx-xx 🌱:** if an ADR supersedes an older ADR then the status of the older ADR is changed to "superseded by ADR-yyyy-mm-dd", and links to the new ADR.

#### How do we know when an ADR is accepted/rejected
There are several mechanisms that will allow us to know whether an ADR is accepted or not.  
As a starting point, there are **two scenarios**:

* The **ADR has a due date**: which means that it needs to be resolved by that date at the latest.
* The **ADR does not have a due date**.

Regardless of the starting point, there are a number of mechanisms that will allow us to know whether or not an ADR is accepted by the due date (whether closed or not).

```text
📝 Once shared with the team, we will review acceptance ideally on the basis of consensus, although we will accept consent if the lack of feedback/interactions in the team is significant and the change is reversible (1).
```

#### Artifacts to review:
* If there is explicit approval ✅ in the PRs, at least, from the owner team (in the PR including the ADR).
* If there are **opinions against** the ADR, they must be justified in the comments and have **explicit RC** (request changes) **PR reviews ⛔**
* If there are still unresolved discussions against the ADR idea or, if there are, they have been resolved.

```text
📝 Once all the information has been reviewed or if the due date arrives, we will transition the ADR to the appropriate status: accepted or rejected.
```

### Suggestions for writing good ADRs
Characteristics of a good ADR:
* Rational: Explain the reasons for doing the particular AD. This can include the context (see below), pros and cons of various potential choices, feature comparisons, cost/benefit discussions, and more.
* Specific: Each ADR should be about one AD, not multiple ADs.
* Timestamps: Identify when each item in the ADR is written. This is especially important for aspects that may change over time, such as costs, schedules, scaling, and the like.
* Immutable: Don't alter existing information in an ADR. Instead, amend the ADR by adding new information, or supersede the ADR by creating a new ADR.

Characteristics of a good "Context" section in an ADR:
* Explain your organization's situation and business priorities.
* Include rationale and considerations based on social and skills makeup of your teams.
* Include pros and cons that are relevant, and describe them in terms that align with your needs and goals.

Characteristics of good "Consequences" section in an ADR:
* Explain what follows from making the decision. This can include the effects, outcomes, outputs, follow-ups, and more.
* Include information about any subsequent ADRs. It's relatively common for one ADR to trigger the need for more ADRs, such as when one ADR makes a big overarching choice, which in turn creates the need for smaller decisions.
* Include any after-action review processes. It's typical for teams to review each ADR one month later, to compare the ADR information with what's happened in actual practice, to learn and grow.

A new ADR may take the place of a previous ADR:
* When an AD is made that replaces or invalidates a previous ADR, then a new ADR should be created.

## Credits
KUDOS to Joel Parker for sharing all this knowledge with us through [this repo](https://github.com/joelparkerhenderson/architecture-decision-record).

## Links
[(1) Reversible changes](https://www.businessinsider.com/amazon-jeff-bezos-letter-reversible-decisions-1997-hq2-new-york-2019-2?op=1)
