using Stemma.Model;

namespace Reviewability;

/// <summary>
/// The post's first diagram. One question: when the author cannot be asked, where does a
/// reviewer's question go? Four questions, four artifacts, and the dotted "about" edge between
/// each pair is the routing.
///
/// The author is deliberately not in this view. A box for him would suggest he is still an option.
/// </summary>
public static class QuestionDestinations
{
    public static View Define() => new(
        Id: "view_destinations",
        Name: "Where the question goes",
        BaseView: "all",
        ElementIds: new[]
        {
            "q_blast", "mod_contracts",
            "q_why", "mod_decision",
            "q_claims", "mod_tests",
            "q_scope", "mod_size",
        });
}
