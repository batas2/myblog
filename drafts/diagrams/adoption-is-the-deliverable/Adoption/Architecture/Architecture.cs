using Stemma.Model;

namespace Adoption;

/// <summary>
/// The hero figure for "Adoption Is the Deliverable".
///
/// This is not a system. It is the essay's opening scene, modelled in Stemma so the picture in the
/// post is a projection of a real model rather than a drawing of one:
///
///   view_worth   two services solving the same problem, and the only difference that counted
///
/// Every element is a Concept and every link a Relation, because nothing here asserts anything
/// about a system — these are the boxes of an argument. `stemma validate` says so out loud
/// (model/is-a-figure), which is the check working rather than a problem.
///
/// Deliberately generic: no employer, no client, no product name.
/// </summary>
public static class Architecture
{
    public static Model Build()
    {
        // --- The two candidates ------------------------------------------------------------------
        // The names carry the whole comparison, so they are sentences rather than labels. Node
        // titles wrap, so a sentence is allowed to be one.
        var correct = new Concept("con_correct",
            "Built properly. Clean boundaries, a published contract.");

        var lump = new Concept("con_lump",
            "A lump. Ambiguous naming, no versioning story.");

        // --- The four teams that decided it ------------------------------------------------------
        // Anonymous on purpose. Which teams they were is the least interesting fact about them;
        // that there were four, and that they all went the same way, is the argument.
        var teamA = new Concept("con_team_a", "A team with the problem today");
        var teamB = new Concept("con_team_b", "A team with the problem today");
        var teamC = new Concept("con_team_c", "A team with the problem today");
        var teamD = new Concept("con_team_d", "A team with the problem today");

        // --- What the teams did, which is the only measurement that mattered ---------------------
        // Deliberately unlabelled. Writing "integrated" on all four arrows says the same word four
        // times and buys nothing; the count is carried once, as ink, in the sidecar. An unlabelled
        // Relation renders as a bare arrow, which is exactly what a decision to depend on something
        // looks like from the outside.
        var a = new Relation("rel_a", "con_team_a", "con_lump");
        var b = new Relation("rel_b", "con_team_b", "con_lump");
        var c = new Relation("rel_c", "con_team_c", "con_lump");
        var d = new Relation("rel_d", "con_team_d", "con_lump");

        // Nothing points at `correct`. That absence is the figure: an architecture is worth
        // precisely what other teams are willing to depend on, and this one is worth nothing.
        return Model.Of(correct, lump, teamA, teamB, teamC, teamD, a, b, c, d);
    }
}
