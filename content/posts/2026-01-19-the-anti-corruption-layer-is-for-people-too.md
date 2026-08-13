# The Anti-Corruption Layer Is for People Too

*My favorite pattern from Domain-Driven Design protects codebases from foreign models. I keep prescribing it to departments.*

Ask an architect for their favorite pattern and you'll usually hear something impressive: event sourcing, CQRS, cells. Mine is humbler: **the anti-corruption layer**. A boring translation shim from the [Domain-Driven Design](https://en.wikipedia.org/wiki/Domain-driven_design) toolbox. I love it because it's the only pattern I know that works equally well on an integration diagram and on an org chart.

## What the layer actually protects

A quick refresher for the non-DDD crowd. An [anti-corruption layer](https://learn.microsoft.com/en-us/azure/architecture/patterns/anti-corruption-layer) sits between your system and an upstream system whose model you do not control: a legacy platform, a vendor API, another team's service. Its job is translation: their concepts arrive, get mapped into *your* language at the border, and only then touch your domain.

The name is the important part. Evans didn't call it "adapter" or "facade". He called it *anti-corruption*, because that is what it prevents. And here is what most teams get wrong: **the layer protects your model's integrity, not your API.** The danger was never that a remote call fails; you have retries for that. The danger is that their `status = 7`, their nullable-everything contract, their subtly different definition of "customer" starts living inside your entities. You import one convenient enum today, and six months later your code speaks two languages, neither of them fluently. Every feature after that pays the tax.

Is the extra hop always worth it? Honestly, no. Translation costs: another mapping class, more tests, and a guaranteed "why this indirection?" comment in code review. If the upstream model is stable, close to yours, and read-only, map it directly and move on. The layer earns its keep in three cases, and they are worth naming separately because teams usually notice the third one long after it has already cost them a quarter of rework. The upstream model keeps changing. Its language contradicts yours. Or you catch yourself sprinkling `if (theirType == LEGACY_B)` across your domain logic. That `if` *is* the corruption, already inside. Not a warning of it. The thing.

## Semantic corruption, human edition

Now watch the same mechanism run on people.

Teams import each other's vocabularies exactly the way codebases import foreign enums: silently and wholesale. The channel isn't an API; it's a shared spreadsheet, a copied ticket, a slide lifted from another department's deck. A word walks across the boundary ("account", "assessment", "active") carrying someone else's model inside it, and nobody inspects the luggage.

I've written before that most "data-driven" fights are really collisions between semantic contracts nobody wrote down ([Data-Driven Theater](/blog/data-driven-theater/)). This is how those contracts get broken in the first place. A term optimized for sales, with sales' compromises baked in, becomes an engineering domain term. A status that conflates two different situations gets copied into three more teams' backlogs. The breakage replicates through the organization precisely like corrupted records through an unvalidated pipeline.

Nobody decides any of this. That is the point. **Corruption rarely announces itself; it arrives one borrowed word at a time.**

## Translate or unify, but never "just align"

So what do you do at a boundary, human or technical? You have exactly two honest options.

Adopt a genuinely shared model when the concept is core to both sides and you are prepared to co-own its evolution. This is the ubiquitous-language dream, and it is expensive: shared definitions need governance, a change process, and someone empowered to say no. Reserve it for the handful of concepts that deserve it. This is model work (relations, constraints, evolution), the level I argued architects should stay at in [Mathematicians vs. Lawyers](/blog/mathematicians-vs-lawyers/).

Translate at the boundary when the other side's model is legitimately *theirs*: owned by them, stable, optimized for their world. Keep a glossary. Better yet, keep a translator, a person who consciously maintains the mapping and can say out loud that "their 'client' is our 'buyer', except during trials". A named, human anti-corruption layer. It feels like bureaucracy. It costs one page and saves quarters.

The trap is the third option, the one everyone reaches for first: **"let's just align the teams."** It sounds cheap. It is the most expensive item on the menu. Alignment means permanent joint ownership of a language, so every future change to the concept now requires both parties, forever, including the parties who will inherit it after the next reorg and were never asked. Most teams that "aligned" in a two-hour workshop actually just adopted whichever vocabulary was spoken loudest, imported its bugs along with it, and called the result consensus.

Choose deliberately: unify what is core, translate what is foreign, and never leave a boundary unmanaged just because the two teams happen to like each other. Friendship is not a data contract.

## The audit

A small exercise for your next quiet Friday. Pick the three most central words on your team's diagrams. For each one, ask: where did this word come from? Does it mean the same thing one department over? And if it doesn't, who is doing the translation?

If the answer to the last question is "nobody", congratulations. You have just located the exact spot where your model is importing someone else's bugs, free of charge, on a daily schedule.

I stopped asking teams to align. Now I ask who translates. That is a smaller question and a much easier one to answer honestly, because the answer has a name in it and alignment never does. The person who says "that's our word, not theirs" is doing architecture, whatever their title says. Every domain word has an accent. The useful part is knowing whose.

---

**Updated:** 2026-08-13 10:45

**Scores:** tension=6 originality=7 personal=4 contrarian=7 substance=8 punch=7 trend=3
**Scored:** 2026-07-27 rubric=v1 model=claude-opus-5

**Tags:** Domain-Driven Design, Software Architecture, Team Communication, Organizational Culture, Software Engineering
