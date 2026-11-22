# One Step More Certain

*I checked nine months of my own architecture documents for the optimism of AI models. The optimism was mine. The model added the certainty.*

I am a solution architect in a global SaaS platform. In practice my job is mostly documents. Before several squads build something together, somebody has to write down how the parts will fit, which options we rejected and why, and what we still do not know. So I write solution designs, decision records and lists of questions for the product people, and I draw diagrams for all of it.

Since March almost every one of these documents starts with an AI model. I give it the product requirements, the Slack thread and the source code, I write a prompt, and I get a draft that I correct. I keep everything in one folder, and each piece of work has its prompt saved next to the result. I described this setup in [Prompt-Driven Architecture](/blog/prompt-driven-architecture/).

Last week I wrote that [models promote optimism](/blog/the-company-promotes-optimism/): they give the pleasant answer more often than the true one, and many studies confirm it. After I published it, one thing did not leave me alone. If this is true, then it should be visible in my own folder, because the same models wrote the first draft of most of my architecture this year. So I went to check.

I will give the result first, because the rest of the text only explains it. I did not find an optimistic model. I found a model that takes whatever I give it and returns it **one step more certain**. My opinion comes back as a decision, and a plan comes back as a fact. The optimism was already in what I gave it, in the plans of the company and in my own prompts, and the model made it look settled.

## What I checked

The folder has more than a thousand files from nine months: 66 diagrams, around 140 decks and exported documents, and 308 text documents that can be read and compared. There are 36 saved prompts. I added my side project, an architecture modeling tool that I build in the evenings with coding agents. It has 301 commits, 31 decision records and 28 bug records, and it is useful here because the agents wrote almost all of it.

For every document I asked two questions. What did it claim? And what do we know today, from a later document, from the code, or from production? It is too much to read by hand, so a model did the reading, in four separate passes that did not know my expectations. I come back to this at the end, because it is a weak point.

The first result was a surprise. I expected happy language, and it is not there. In 220 documents that came from my prompts, the phrase "quick win" appears in 2. The word "assumption" appears in 80 and "open question" in 44. Most designs have a section about risks. If somebody reads one of these documents alone, it looks careful.

## One sentence in four documents

The problem is visible only when you follow one sentence through time. Here is the clearest example.

In May we had a decision document about how to model the relationship between two companies in our network. Its header said "Status: Open / Needs Further Discussion". One week later I asked a model for a design in the same area, and the design listed this recommendation under the heading "Confirmed decisions". Three weeks after that, a list of questions for the product team had a section "Already decided, please don't reopen", and the same recommendation was in it. At the end of July a new design said that the recommendation "was never ratified", and the code went a different way.

Nobody lied in this story, and the content of the sentence did not change between the documents. What changed was its status. Every statement in architecture has one, even when we do not write it: somebody's opinion, a proposal, a decision, something built, something checked in the running system. In my folder the status went up one level almost every time a model copied a sentence into a new document. I found sixteen cases of this kind.

Some of them are a bit embarrassing. For an AI assistant the model drafted fifteen decision records in one day, and all fifteen were marked "Accepted" on the day they were written, before anybody outside the session had read them. Four months later about six were reversed or dropped. In another piece of work I added a confidence field to every file, with four values to choose from. The model chose "Fact" in 19 of 30 files, and 9 of those described a state that did not exist yet.

And here is the detail that changed my thesis. It works in the other direction too. In March a model read only our documents, without the code, and produced a list of twelve gaps with the label "delivery cannot proceed without resolution". We delivered in May. So I cannot call this optimism. The model was equally sure about good news and bad news, and the direction always came from the input. In a company most of the input is plans, and plans are optimistic, so the output looks optimistic.

There is a study that shows the same mechanism at a smaller scale. When ten models summarised scientific papers, their summaries were [nearly five times more likely](https://www.ncbi.nlm.nih.gov/pmc/articles/PMC12042776/) to contain broad generalisations than summaries written by people. The first thing a summary loses is the qualifier, the "in this group" or "probably". In architecture the qualifier is the whole difference between "proposed" and "accepted".

## Where the optimism came from

So if the model does not bring the optimism, who does? This part was not pleasant to read, because the answer is me.

In 17 prompts I had written my own solution, or hard constraints, into the question. In 17 of 17 that solution came back. For a feature with saved filters I put two constraints into the prompt, and they returned as ADR-001 and ADR-002, in the format of a decision that somebody had analysed. In another prompt I wrote "in my opinion that make sense". The design agreed with me. It did have one objection, and it put the objection into the list of open decisions as number 3. The Slack reply that I prepared from this design did not mention it at all.

The model was not passive, and this is the interesting part. In one area it pushed back in 8 of 11 outputs. But it always pushed back against somebody else: a product document, an old diagram, a claim from another team. It did not question the premise of the prompt even once. When I asked openly, for example "give me two or three options and I will choose", the result was different. In 5 cases of 5 the output contradicted a position that I had already taken somewhere else.

Researchers at the UK AI Security Institute showed this spring that most of the flattery disappears when you [ask the model a question](https://www.aisi.gov.uk/blog/ask-dont-tell-reducing-sycophancy-in-large-language-models-2) and do not give it your own statement. I linked that result last week. My prompts from July show that I was not doing it myself.

## The doubts were on the last page

Then where was the truth during all this time? In most cases it was in the same document. It was at the end.

The first heading about risks or open questions sits, on average, between 73 and 84% of the length of a design. This is partly our own fault: the solution design template in our company has twelve sections, and none of them is for risks or open questions, so the model adds the section in the place where it always goes. A reader who has ten minutes reads the summary and the diagram and never arrives there.

The July design of the assistant shows how much this costs. It planned the first delivery for the border of Q3 and Q4. Its own risk table, at 93% of the file, rated two risks as high impact and high probability: the frontend platform is not ready, and there are no people to build it. In November the project was on hold, for these two reasons. I could follow five flagged risks from the document to the end of the story, and all five happened.

So the doubts were correct, and almost nothing was done with them.

![What happened to what was written down. The solution from my prompt always came back, the flagged risks I could follow all happened, and almost no question or safeguard was acted on.](/assets/img/posts/one-step-more-certain/what-happened-to-what-was-written.png)

The 48 question lists in the folder contain about 490 questions, and 38 of them have an answer recorded anywhere. The designs moved 78 safeguards (a monitoring job, a cleanup, a load test) into the risk section or into "phase 2", and I can confirm 8 of them as built. For 29 there is no trace in any document, so some of them may exist.

My side project showed the same thing from the other side. When an agent wrote "not yet wired" or "deferred" in its status notes, it was correct everywhere I could check. The problem was the notes like "shipped end to end": in one case every operation failed in the running application two and a half weeks later. I traced 18 false claims that something works, and no test that existed at that moment caught any of them.

## What changed a design

If the documents repeated each other and became more certain with every copy, then what corrected them? In nine months I find only a short list: somebody read the code, somebody ran a test, or a person answered a question in writing.

The best example is one test. In July a decision record for the assistant said that no model writes SQL queries. In November the new design relaxed this rule, with a note that nobody objected. Then a colleague tried it, and the generated query returned a company from outside the network of the buyer who was asking. Three days later the design said again that no model writes SQL for buyers. The written rule did not survive one round of planning, and one test result brought it back in three days.

## What I do now

I changed three things, and none of them is about a better model.

First, the status of a sentence can be raised only by a person or by the code. The model may copy a status from the source, but it may not promote it, and "Accepted" on a decision record now needs a name of somebody who accepted. In my modeling tool I learned the same lesson: the rules that survived are the ones written as checks that can fail, and the rules written as sentences were broken without anybody noticing.

Second, I ask before I tell. When I have an opinion, I put it into the prompt as a hypothesis and ask the model to attack it. Third, the risks go to the top of the document, and each one gets an owner and a date, because a risk on the last page is read by nobody who decides.

To be fair to my own rule, I have to say one more thing. This review was also done by a model, so its numbers have the status of a proposal. I will check 17 of 17 and 8 of 78 by hand, and if they move, I will correct them here. And there is one result I cannot explain yet. My documents from autumn are clearly more careful than the ones from spring, and I do not know if I learned something or if the models changed ;)

## Sources

- Peters and Chin-Yee, [Generalization bias in large language model summarization of scientific research](https://www.ncbi.nlm.nih.gov/pmc/articles/PMC12042776/) (Royal Society Open Science, 2025)
- UK AI Security Institute, [Ask, don't tell: reducing sycophancy in large language models](https://www.aisi.gov.uk/blog/ask-dont-tell-reducing-sycophancy-in-large-language-models-2) (2026)

---

**Scores:** tension=7 originality=6 personal=8 contrarian=7 substance=8 punch=5 trend=8
**Scored:** 2026-11-22 rubric=v1 model=claude-fable-5-2

**Related:** the-company-promotes-optimism, prompt-driven-architecture, the-model-grades-its-own-homework

**Tags:** Software Architecture, Artificial Intelligence, AI Transformation, Decision Making, Engineering Leadership
