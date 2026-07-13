# Pricing Is Architecture

*The pricing page is an architecture diagram wearing marketing clothes.*

Some time ago I redesigned the subscription model for Desta, the invoicing SaaS I build and operate for the Polish accounting market. I expected a marketing exercise: a spreadsheet, some tier names, an afternoon of choosing what goes into which column.

What I actually got was a database migration, new middleware, a rewritten registration flow, and changes to two marketing sites, plus a change plan that touched more than thirty controllers and a test suite of roughly nine hundred and ninety tests.

That afternoon produced the thesis of this post: **a pricing page is an architecture diagram wearing marketing clothes.** Every box on it is a system boundary. Every bullet point is an enforcement obligation. And somebody has to build all of it.

## The Model on Paper

Desta serves two audiences on one platform, and they could hardly be more different. On one side, end clients: freelancers and small companies who want to issue invoices, buy a cheap self-serve plan online, and never talk to a human. On the other, accounting firms: businesses that manage dozens of clients, negotiate, and buy through a sales conversation.

Two audiences, two marketing sites, one codebase. And the separation isn't a slide-deck fiction. It's enforced all the way down to the marketing layer. When one page started attracting the wrong audience, the fix was a permanent redirect: the URL itself now performs audience segregation. Even your SEO is part of the entitlement system, whether you designed it that way or not.

Then come the tiers. Here's the thing nobody writes on the pricing page: **every tier boundary is a promise, and every promise needs an enforcement point in code.** A monthly document cap means a counter, a quota guard, and a decision about what happens at the limit. A notification allowance means metering. A "this feature only in the higher plan" bullet means a feature flag with a lifecycle. The cheapest tier, meanwhile, isn't really a revenue line at all. It's a distribution strategy that the codebase has to subsidize with real complexity.

## Where Pricing Bends the Code

Now the part that surprised even me, and I designed the thing.

Serving B2C subscriptions inside a B2B multi-tenant architecture forced what I call the master-tenant pattern: individual self-serve clients live as per-user subscriptions *inside* one shared master tenant, while accounting firms hold classic tenant-scoped subscriptions. Two commercial models, one isolation model. The tenancy design has to keep them from ever bleeding into each other. That is not a billing detail. That is core architecture, dictated entirely by the pricing page.

It goes further:

- **Quota guards** sit in the request path, counting documents against the monthly cap.
- **Read-only mode** kicks in when a subscription expires: the system must degrade gracefully, not amputate.
- **Downgrades are deliberately blocked**, a commercial policy decision compiled directly into middleware.

Commercial policy, compiled into middleware. Read that again, because it's the whole point: the sales strategy doesn't sit *next to* the system; it executes *inside* it, on every request.

And then there's the migration tax. Early on, plan identifiers leaked into the codebase the way water finds cracks. Today, renaming a single plan slug means touching thirty-plus controllers and views, and re-validating nearly a thousand tests. **A pricing change is a schema change.** The spreadsheet doesn't know that. The codebase never forgets it.

## What the Pricing Meeting Forgets

Which brings me to the organizational half of this story, because Desta is just my one-person laboratory; the same physics applies at any scale.

In most companies, pricing is decided in a meeting where no architect is present. A spreadsheet is blessed, a launch date is picked, and the decision lands downstream as years of constraints in code: entitlement checks, grandfathered plans, exceptions with exceptions. **Architects belong in the pricing meeting**, not to set the prices but to price the promises.

Billing is also where edge cases breed with a fury I've rarely seen elsewhere: trials, proration, the client who downgrades mid-cycle, the grandfathered plan that predates the current model. If you've read my [Mathematicians vs. Lawyers](/blog/mathematicians-vs-lawyers/), you know the archetype whose home turf this is: billing is the one domain where I *want* the Lawyer at the table, enumerating every case with a paragraph number. The Mathematician's job is to design a model in which most of those cases cannot exist; the Lawyer's job is to catch the ones that can.

And one hard-won rule: **entitlements deserve a bounded context.** A dedicated place in the architecture that answers exactly one question: *what is this account allowed to do right now?* Smear that logic across controllers and views, and you get the worst failure mode software offers: you can't debug revenue. A support ticket that says "client paid but can't issue invoices" turns from a five-minute lookup into an archaeology expedition.

## Show Me Your Pricing Page

Here's my private conclusion after wiring commercial policy into every layer of a system I fully control, with nobody to blame but myself.

Show me your pricing page, and I'll draw your database schema. The two always converge; they have no choice, because one is a set of promises and the other is where promises are kept. The only real question is whether anyone *designed* that convergence, or whether it just happened, one urgent pricing experiment at a time.

So, an exercise for your next architecture review: put the pricing page on the screen instead of the C4 diagram. Walk it boundary by boundary and ask where each promise is enforced.

If the room goes quiet somewhere between "unlimited" and the asterisk: congratulations. You've just found your real architecture backlog ;)

---

**Tags:** SaaS, Software Architecture, Pricing Strategy, Multi-Tenancy, Product Management
