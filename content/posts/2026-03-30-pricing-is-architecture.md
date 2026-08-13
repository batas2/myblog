# Pricing Is Architecture

*The pricing page is an architecture diagram wearing marketing clothes.*

Desta is the invoicing SaaS I build and run for the Polish accounting market. When I redesigned its subscription model, I expected a marketing exercise. A spreadsheet, some tier names, an afternoon of choosing what goes into which column.

What I got was a database migration, new middleware, a rewritten registration flow, and changes to two marketing sites. The change plan touched more than 30 controllers and a test suite of roughly 990 tests.

That afternoon produced the thesis of this post: **a pricing page is an architecture diagram wearing marketing clothes.** Every box on it is a system boundary. Every bullet point is an enforcement obligation. And somebody has to build all of it.

## The model on paper

Desta serves two audiences on one platform, and they could hardly be more different. On one side, end clients. Freelancers and small companies. They want to issue invoices, buy a cheap self-serve plan online, and never talk to a human. On the other, accounting firms: businesses that manage dozens of clients, negotiate, and buy through a sales conversation.

Two audiences, two marketing sites, one codebase. And the separation isn't a slide-deck fiction. It's enforced all the way down to the marketing layer. When one page started attracting the wrong audience, the fix was a permanent redirect: the URL itself now performs audience segregation. Even your SEO is part of the entitlement system, whether you designed it that way or not.

Then come the tiers. Here's the thing nobody writes on the pricing page: **every tier boundary is a promise, and every promise needs an enforcement point in code.** A monthly document cap means a counter, a quota guard, and a decision about what happens at the limit. A notification allowance means metering. A "this feature only in the higher plan" bullet means a feature flag with a lifecycle. The cheapest tier, meanwhile, is a distribution strategy wearing a price tag, and the codebase subsidizes it with real complexity.

## Where pricing bends the code

What surprised even me, and I designed the thing: serving B2C subscriptions inside a B2B multi-tenant architecture forced what I call the master-tenant pattern: individual self-serve clients live as per-user subscriptions *inside* one shared master tenant, while accounting firms hold classic tenant-scoped subscriptions. Two commercial models, one isolation model. The tenancy design has to keep them from ever bleeding into each other. That is core architecture, dictated entirely by the pricing page.

It goes further:

- **Quota guards** sit in the request path, counting documents against the monthly cap.
- **Read-only mode** kicks in when a subscription expires: the system must degrade gracefully, not amputate.
- **Downgrades are deliberately blocked**, a commercial policy decision compiled directly into middleware.

Commercial policy, compiled into middleware. Read that again, because it's the whole point: the sales strategy doesn't sit *next to* the system; it executes *inside* it, on every request.

And then there's the migration tax. Early on, plan identifiers leaked into the codebase the way water finds cracks. Today, renaming a single plan slug means touching 30-plus controllers and views, and re-validating nearly a thousand tests. **A pricing change is a schema change.** The spreadsheet doesn't know that. The codebase never forgets it.

## What the pricing meeting forgets

Which brings me to the organizational half of this story, because Desta is just my one-person laboratory; the same physics applies at any scale.

In most companies, pricing is decided in a meeting where no architect is present. A spreadsheet is blessed and a launch date is picked. The decision then lands downstream as years of constraints in code. Entitlement checks, grandfathered plans, exceptions with exceptions. **Architects belong in the pricing meeting**, not to set the prices but to price the promises.

Billing is also where edge cases breed faster than anywhere else I have worked. Trials. Proration. The client who downgrades mid-cycle. The grandfathered plan that predates the current model. If you've read my [Mathematicians vs. Lawyers](/blog/mathematicians-vs-lawyers/), you know the archetype whose home turf this is: billing is the one domain where I *want* the Lawyer at the table, enumerating every case with a paragraph number. The Mathematician's job is to design a model in which most of those cases cannot exist; the Lawyer's job is to catch the ones that can.

And one hard-won rule: **entitlements deserve a bounded context.** A dedicated place in the architecture that answers exactly one question: *what is this account allowed to do right now?* Smear that logic across controllers and views, and you get the worst failure mode software offers: you can't debug revenue. A support ticket that says "client paid but can't issue invoices" turns from a five-minute lookup into an archaeology expedition.

## Show me your pricing page

Here's my private conclusion after wiring commercial policy into every layer of a system I fully control, with nobody to blame but myself.

Show me your pricing page, and I'll draw your database schema. The two always converge. They have no choice. One is a set of promises. The other is where promises are kept. The only question left is whether anybody *designed* that, or whether it piled up the way most of them do: one urgent pricing experiment at a time, each looking like a marketing decision on the day it was made.

So, an exercise for your next architecture review: put the pricing page on the screen instead of the [C4 diagram](https://c4model.com/). Walk it boundary by boundary and ask where each promise is enforced.

If the room goes quiet somewhere between "unlimited" and the asterisk: congratulations. You've just found your architecture backlog ;)

---

**Updated:** 2026-08-13 10:45

**Scores:** tension=7 originality=8 personal=9 contrarian=6 substance=9 punch=8 trend=3
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** SaaS, Software Architecture, Pricing Strategy, Multi-Tenancy, Product Management
