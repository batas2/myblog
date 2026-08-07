using Stemma.Model;

namespace Reviewability;

/// <summary>
/// The post's second diagram. One question: which of XP's practices still work when the author is
/// a session that closed? The two groups are the two jobs the twelve practices were doing, and the
/// lifecycle status on each is the answer.
/// </summary>
public static class TwoJobs
{
    public static View Define() => new(
        Id: "view_two_jobs",
        Name: "The two jobs XP was doing",
        BaseView: "all",
        ElementIds: new[]
        {
            "ctx_reachable", "cap_pairing", "cap_onsite", "cap_collective", "cap_standup",
            "ctx_selfanswering", "cap_tdd", "cap_ci", "cap_small", "cap_simple",
            "cap_refactor", "cap_standards", "cap_metaphor",
        });
}
