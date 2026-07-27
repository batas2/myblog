# Edge Cases Written by Parliament

*What integrating with mandatory government systems taught me about specs you cannot refactor.*

The strictest product owner I have ever worked with has never attended a standup, ignores my roadmap entirely, and publishes requirements as versioned PDFs. It's the state.

Building Desta, my invoicing platform for the Polish market, means integrating with three government systems: KSeF (the mandatory national e-invoicing API), the public company registry with its SOAP interface, and the JPK unified tax files. A quick disclaimer before we start: this is an engineering story, not tax advice. Please keep paying your accountant ;)

## The Spec You Cannot Refactor

Tax law is an upstream dependency with no negotiation channel. You cannot file an issue against a statute. You cannot suggest a breaking change be batched into the next major version. You implement what the paragraph says, and the paragraph doesn't care about your domain model.

Two examples that changed how I think about requirements.

First: in KSeF, **the XML is the legal invoice.** The PDF (the thing every user believes is "the invoice") is legally just a *visualization*, and has to be labeled as one. Think about what that does to your architecture: the artifact your users see and print is a derived view; the artifact almost nobody reads is the source of truth. Get the relationship backwards and you've built a very pretty bug.

Second: invoice rows with certain VAT exemptions must render the VAT column *blank*. Not "0.00". Not a dash. Blank. **A one-pixel difference that is a legal requirement, not a style choice.** There is no elegant abstraction that makes this go away; there is only encoding it correctly and pinning it with tests named after tax scenarios.

I wrote in [Mathematicians vs. Lawyers](/blog/mathematicians-vs-lawyers/) about two thinking archetypes: the modelers who dissolve edge cases by reshaping the model, and the rule-thinkers who enumerate them. Well, this is the domain where the Lawyer archetype simply wins. Every edge case here has a paragraph number attached. My instinct is to find the model that makes exceptions disappear; in compliance work, the exceptions *are* the model.

## SOAP, Sessions, and the State

Now the part everyone loves to mock: the technology.

The company registry speaks SOAP in 2026. The session ID travels in a custom HTTP header, not a cookie. That detail alone costs every integrator an afternoon of their life. The field for a building number has a bureaucratic Polish name that no autocomplete will ever guess. The documentation is a versioned PDF.

The inconvenient truth: **it all works.** The same requests produce the same responses, year after year. My integration code for that registry is the least-touched code I own.

KSeF authentication is a small choreography: fetch the public key, request a challenge, encrypt your token and a timestamp with RSA, redeem the result for a session. Is it elaborate? Yes. Is it security theater? Partly, perhaps. But it's *documented* choreography. You implement it once, wrap it, and forget it exists.

That word, "wrap", is the architectural lesson. This is where the anti-corruption layer stops being a conference slide and starts earning its keep. The government's model, its vocabulary, its SOAP envelopes and bureaucratic field names get translated at the boundary, once, in one place. My domain never learns what the ministry calls a building number. The state stays outside; the model stays clean.

## Lessons for Architects Raised on REST

If your entire career has been JSON over HTTPS with a `v2` in the path, integrating with the state is a masterclass in things our industry forgot.

**Stability is a feature, arguably the feature.** These APIs change less per decade than a typical startup API changes per quarter. Deprecations are announced by legislation, with timelines measured in years. Meanwhile I've integrated with modern SaaS APIs that shipped three breaking changes in a year, each announced in a changelog nobody reads.

**Compliance is a bounded context.** It has its own language (the statute's, not yours), its own tests (named after tax scenarios, not user stories), and its own release rhythm: the calendar of the state, not your sprint. Treating it as "just another feature" is how compliance logic ends up smeared across a codebase.

**The reference implementation is the real spec.** The ministry publishes reference client SDKs, and reading them is archaeology. You dig through layers to learn what the API *actually* expects, as opposed to what the PDF implies. Archaeology beats guessing. I made a similar argument in [Stop Calling It Legacy Code](https://medium.com/@bfrackowiak/stop-calling-it-legacy-code-02308db28cb2): old code is a knowledge container. Government reference code is the same idea at civilizational scale: decades of edge cases, fossilized and readable.

## Ugly, Documented, Stable

Complaining about government APIs is the cheapest joke in engineering, and I've told it myself. But after a year of running production integrations against them, I have to report something embarrassing: I trust these contracts more than most microservices I've met in my career.

They are ugly. They are documented. They are stable. **Two of those three qualities are the ones that actually matter**, and they're not the one we keep optimizing for.

So, a question for your next architecture review: which of your internal APIs would survive being frozen for five years? Because the ministry's would. And did.

---

**Scores:** tension=6 originality=8 personal=8 contrarian=7 substance=8 punch=8 trend=3
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Software Architecture, Government Technology, Domain-Driven Design, Legacy Systems, E-Invoicing
