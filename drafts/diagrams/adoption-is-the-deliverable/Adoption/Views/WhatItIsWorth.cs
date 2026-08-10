using Stemma.Model;

namespace Adoption;

/// <summary>
/// The post's hero. One question: of two services solving the same problem, which one was worth
/// something? Four arrows arrive at one box and none at the other, and that is the whole answer.
///
/// `BaseView: "all"` because there is no lens to apply — the arrangement is the argument, and it
/// is stated as a layout constraint in the sidecar rather than dragged into place.
/// </summary>
public static class WhatItIsWorth
{
    public static View Define() => new(
        Id: "view_worth",
        Name: "What an architecture is worth",
        BaseView: "all",
        ElementIds: new[]
        {
            "con_team_a", "con_team_b", "con_team_c", "con_team_d",
            "con_lump",
            "con_correct",
        });
}
