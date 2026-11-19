✂✂✂✂✂< REMOVE FROM THIS PART BEFORE SUBMITTING YOUR ADR ✂✂✂✂✂
# ⚠️ Should this be an ADR?
Are you in doubt if this document you or your team propose needs to be an Architectural Decision Record?

Let's check it through several questions 😊

Starting with a `stop question`:

* ❓ Is this ADR related to a change in this specific application or service?
✋ **If** the answer **is "no"**, which means that this ADR affects this application and others, **end this document here** and start writing a general Decision Record [here](https://sequra.atlassian.net/wiki/spaces/EN/pages/3771039771/Tech+Decision+Records#List-of-Tech-Decision-Records)
👇 **If** the previous **answer is a "yes"**

Let's **continue** with these other questions:

* ❓ Will this ADR provoke a breaking changes or sensible modifications inside this application or services' engine, interfaces, APIs or [seams](https://archive.org/details/working-effectively-with-legacy-code/page/n51/mode/2up) `(look into chapter 4 of this book for the reference)` between domains?
* ❓ Will this ADR introduce new architectural or software design patterns in the project (f.ex: big refactors which provide that an old feature can work in a new way, introducing a new technology/gem/package/etc ...)
* ❓ Will this ADR impact other teams’ roadmaps or velocities meaningfully?
* ❓ Does this ADR imply different approaches/options for which you want/need the opinion of the rest of the team?
* ❓ Does this ADR modify/impact another existing or very similar ADR (superseeding or refining it)?

Then, evaluate your answers:

* 👉  If you checked any of those boxes, this topic does need an ADR.
* 👉  If you didn’t check any of those boxes, doing an ADR is optional.

**❗  Depending on your decision, either delete this section or delete this document!**

✂✂✂✂✂< REMOVE FROM THIS PART BEFORE SUBMITTING YOUR ADR ✂✂✂✂✂

# [short title of solved problem and solution]

---
* **Status:** [🚧🌱 wip | 💡 proposed | 🚫 rejected | ✅ accepted | 🕸 deprecated | … | ⬆️🌱 superseded by [ADR-0005](0005-example.md)] <!-- optional -->
* **Deciders:** [list everyone involved in the decision] <!-- optional -->
* **Proposal date:** [YYYY-MM-DD when the decision was proposed] <!-- optional -->
* **Due date:** [YYYY-MM-DD when the decision is finally made] <!-- optional -->
* **Technical Story:** [description | ticket/issue URL] <!-- optional -->
---
## Context and Problem Statement

[Describe the context and problem statement, e.g., in free form using two to three sentences. You may want to articulate the problem in form of a question.]

## Decision Drivers <!-- optional -->

* [driver 1, e.g., a force, facing concern, …]
* [driver 2, e.g., a force, facing concern, …]
* … <!-- numbers of drivers can vary -->

## Considered Options

* [option 1]
* [option 2]
* [option 3]
* … <!-- numbers of options can vary -->

## Decision Outcome

Chosen option: "[option 1]", because [justification. e.g., only option, which meets k.o. criterion decision driver | which resolves force force | … | comes out best (see below)].

### Positive Consequences <!-- optional -->

* [e.g., improvement of quality attribute satisfaction, follow-up decisions required, …]
* …

### Negative Consequences <!-- optional -->

* [e.g., compromising quality attribute, follow-up decisions required, …]
* …

## Pros and Cons of the Options <!-- optional -->

### [option 1]

[example | description | pointer to more information | …] <!-- optional -->

* Good, because [argument a]
* Good, because [argument b]
* Bad, because [argument c]
* … <!-- numbers of pros and cons can vary -->

### [option 2]

[example | description | pointer to more information | …] <!-- optional -->

* Good, because [argument a]
* Good, because [argument b]
* Bad, because [argument c]
* … <!-- numbers of pros and cons can vary -->

### [option 3]

[example | description | pointer to more information | …] <!-- optional -->

* Good, because [argument a]
* Good, because [argument b]
* Bad, because [argument c]
* … <!-- numbers of pros and cons can vary -->

## Links <!-- optional -->

* [Link type] [Link to ADR] <!-- example: Refined by [ADR-0005](0005-example.md) -->
* … <!-- numbers of links can vary -->
