# Mathematicians vs. Lawyers

*Two thinking archetypes I keep meeting in architecture rooms, and why your system needs both.*

Put two smart people in front of the same business problem and watch what happens.

One of them walks to the whiteboard and starts drawing boxes and arrows. Entities, relations, flows. Within five minutes the whole domain is a living organism on the wall, and half the room quietly wonders where the actual requirements went.

The other one opens a document and starts listing cases. What if the customer is from Germany? What if the invoice was already corrected? What if the correction arrives after the fiscal year closes? Twenty minutes later you have a table with forty rows, and the whiteboard person quietly wonders why we are debugging the universe.

After years of working as a Solution Architect, I started recognizing these two recurring archetypes of thinking. I call them **Mathematicians** and **Lawyers**.

Before anyone gets offended: there are many more archetypes than two, and nobody is purely one of them. These are modes of thinking, not personality boxes. But once you see these two, you cannot unsee them.

## The Lawyers

Lawyers live in a **linear world of rules**. Given condition A and condition B, then C applies, unless exception D. Their thinking moves like a legal act: precise, sequential, exhaustive.

And they are *good*. A Lawyer is the person who catches the case that would have exploded in production three months after go-live. While the rest of the room admires the elegant model, the Lawyer asks the boring, deadly question: "and what happens on February 29th?"

Their natural artifact is the Document. The big one.

## The Edge Case Explosion

This is where corporate culture enters the story, and where my observation stopped being about individuals and started being about the system.

A product company has a very hard time saying **no**. Every client exception gets accepted. Every "just this one special case" becomes a requirement. And the Lawyers do exactly what they are brilliant at: they faithfully encode all of it. Rule by rule, exception by exception.

The document grows. Fifty pages. Eighty. Hundreds of edge cases, each one individually justified.

Let's be honest with ourselves: **nobody reads that document carefully.** Nobody except other Lawyers. The rest of us skim it, nod in the review meeting, and approve. The knowledge is technically written down and practically inaccessible.

There is also a trap hiding in plain sight. A thick specification fits perfectly into corporate optics: **volume looks like effort**. "Big" reads as "a lot of work was done." A three-hundred-page document is impressive in a way that a single clean diagram will never be. So the organization quietly rewards the explosion instead of questioning it.

What you end up with is complexity that nobody ordered. It was delivered one accepted exception at a time, by people doing their jobs well, inside a culture that couldn't refuse anyone.

## The Mathematicians

Mathematicians think in **abstract systems**. They see high-level entities and how they cooperate. Their world is not made of rules but of **relations between objects**: how those relations evolve over time, what constraints must hold, who governs what.

Show a Mathematician eighty edge cases and they will not see eighty problems. They will ask a different question: *what is wrong with the model that keeps generating these exceptions?* Change the model, and whole families of edge cases simply stop existing. Nobody has to handle a case that the design made impossible.

Their curse: **the impact is less visible.** One diagram against three hundred pages. When a Mathematician does the job well, the visible result is… nothing. Complexity that never appeared. Incidents that never fired. An integration that didn't need a workaround. Try putting *that* on a promotion slide.

In a culture where volume equals work, Mathematicians chronically look underemployed. Right up until they leave, and the edge cases start breeding unsupervised.

And to be fair, Mathematicians have their own failure mode. Left unchecked, they drift into astronaut architecture: models so abstract they no longer touch the ground. Guess who pulls them back? The Lawyer, with one concrete, annoying, absolutely real corrected invoice from Germany.

## Two Worlds, One System

This is not a war, and I'm not writing this to declare a winner.

The best design sessions I have ever seen had both minds in the room. The Mathematician reshapes the model so the exceptions dissolve; the Lawyer stress-tests the new model with cases brutally imported from reality. Lawyers keep the Mathematicians honest. Mathematicians keep the Lawyers' world from exploding.

A room full of Lawyers produces an edge case explosion with a spreadsheet to track it. A room full of Mathematicians produces a beautiful abstraction that dies on first contact with a real customer. If everyone in your design review thinks the same way, *that* is your biggest architecture risk, and it's not in any diagram.

These two worlds complement each other. Use both. Deliberately.

## Where I Choose to Stand

My private rule as a Solution Architect: **stay at the mathematical level. Don't descend into the details.**

Not because details are beneath me. Because the moment I dive into edge cases, I become one more Lawyer in a room that usually has plenty of them already, and the system-level perspective goes vacant. Every edge case has an owner somewhere. The relations *between* systems often have none. That empty chair is exactly where an architect should sit.

I won't pretend it's easy. Diving into details gives you the quick dopamine of being concretely useful. Holding the abstract level means accepting that your best work will often be invisible: the complexity that never happened.

So, next time you are in a design review, look around the room. Who is writing the rules, and who is drawing the relations? Which one are you when the pressure rises?

And more importantly: who is holding the other half of the picture?

---

**Tags:** Software Architecture, Systems Thinking, Corporate Culture, Engineering Leadership, Team Collaboration
