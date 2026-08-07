using Stemma.Model;

namespace Reviewability;

/// <summary>
/// The diagram source for "We Deleted the Wrong Half of Agile".
///
/// This is not a system. It is the essay's own argument, modelled in Stemma so the pictures in the
/// post are real projections of a real model rather than drawings of one. Two views come out of it:
///
///   view_destinations  where a reviewer's question goes when the author cannot be asked
///   view_two_jobs      the two different jobs XP's twelve practices were doing
///
/// Everything here is deliberately generic. No employer, no client, no product: the elements are
/// the artifacts any codebase either has or does not.
/// </summary>
public static class Architecture
{
    public static Model Build()
    {
        // --- The counterparty that used to answer -----------------------------------------------
        // Present in the model, absent from view_destinations on purpose: the whole point is that
        // this box is no longer somewhere a question can be sent.
        var perAuthor = new Person("per_author", "The author", "external");

        // --- Where the four questions land ------------------------------------------------------
        var ctxArtifacts = new BoundedContext("ctx_artifacts", "What the repository can answer");

        var modContracts = new Module("mod_contracts", "Contracts at the seams", "ctx_artifacts");
        var modDecision = new Module("mod_decision", "The rejected options, written down", "ctx_artifacts");
        var modTests = new Module("mod_tests", "Tests that state intent", "ctx_artifacts");
        var modSize = new Module("mod_size", "A diff one head can hold", "ctx_artifacts");

        // --- The four questions, each hanging off the artifact that answers it -------------------
        var qBlast = new Question("q_blast", "What breaks if this is wrong?", "mod_contracts");
        // Kept short: the node clips its title around thirty characters.
        var qWhy = new Question("q_why", "Why this and not simpler?", "mod_decision");
        var qClaims = new Question("q_claims", "Does it do what it claims?", "mod_tests");
        var qScope = new Question("q_scope", "What did this not touch?", "mod_size");

        // The question that has no artifact-shaped answer. The essay leaves this one open, so the
        // model does too.
        var qCeiling = new Question("q_ceiling", "And the ones nothing can answer?", "per_author");

        // --- XP's twelve practices, split by the job they were doing -----------------------------
        var ctxReachable = new BoundedContext("ctx_reachable", "Keep the author reachable");
        var ctxSelfAnswering = new BoundedContext("ctx_selfanswering", "Make the artifact answer for itself");

        var capPairing = new Capability("cap_pairing", "Pair programming", "ctx_reachable");
        var capOnsite = new Capability("cap_onsite", "On-site customer", "ctx_reachable");
        var capCollective = new Capability("cap_collective", "Collective code ownership", "ctx_reachable");
        var capStandup = new Capability("cap_standup", "Stand-up", "ctx_reachable");

        var capTdd = new Capability("cap_tdd", "Test-driven development", "ctx_selfanswering");
        var capCi = new Capability("cap_ci", "Continuous integration", "ctx_selfanswering");
        var capSmall = new Capability("cap_small", "Small releases", "ctx_selfanswering");
        var capSimple = new Capability("cap_simple", "Simple design", "ctx_selfanswering");
        var capRefactor = new Capability("cap_refactor", "Refactoring", "ctx_selfanswering");
        var capStandards = new Capability("cap_standards", "Coding standards", "ctx_selfanswering");

        // System metaphor is the odd one. It belonged to the first group, because it lived in
        // people's heads with no artifact under it, which is exactly why teams dropped it first.
        // It has a file now, so it is modelled where it landed rather than where it started.
        var capMetaphor = new Capability("cap_metaphor", "System metaphor", "ctx_selfanswering");

        // --- Lifecycle is the argument -----------------------------------------------------------
        // "deprecated" is not a judgement on the practice. It records that the precondition these
        // four depended on, a counterparty who can still be asked, is gone for an authorless diff.
        Tag.For(capPairing, lifecycle: new Lifecycle(Status: "deprecated", Phase: "needs a counterparty"));
        Tag.For(capOnsite, lifecycle: new Lifecycle(Status: "deprecated", Phase: "needs a counterparty"));
        Tag.For(capCollective, lifecycle: new Lifecycle(Status: "deprecated", Phase: "needs a counterparty"));
        Tag.For(capStandup, lifecycle: new Lifecycle(Status: "deprecated", Phase: "needs a counterparty"));

        Tag.For(capTdd, lifecycle: new Lifecycle(Status: "current", Phase: "load-bearing"));
        Tag.For(capCi, lifecycle: new Lifecycle(Status: "current", Phase: "load-bearing"));
        Tag.For(capSmall, lifecycle: new Lifecycle(Status: "current", Phase: "load-bearing"));
        Tag.For(capSimple, lifecycle: new Lifecycle(Status: "current", Phase: "load-bearing"));
        Tag.For(capRefactor, lifecycle: new Lifecycle(Status: "current", Phase: "load-bearing"));
        Tag.For(capStandards, lifecycle: new Lifecycle(Status: "current", Phase: "load-bearing"));
        Tag.For(capMetaphor, lifecycle: new Lifecycle(Status: "to-adapt", Phase: "came back as a rules file"));

        return Model.Of(
            perAuthor,
            ctxArtifacts, modContracts, modDecision, modTests, modSize,
            qBlast, qWhy, qClaims, qScope, qCeiling,
            ctxReachable, ctxSelfAnswering,
            capPairing, capOnsite, capCollective, capStandup,
            capTdd, capCi, capSmall, capSimple, capRefactor, capStandards, capMetaphor);
    }
}
