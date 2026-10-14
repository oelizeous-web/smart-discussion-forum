package com.sdf.components;

import java.util.List;

/**
 * The four platform rules shown at registration and editable (locally
 * only — the API spec has no endpoint to persist them) from the admin
 * settings screen. Kept in one place so both screens show the same text.
 */
public final class PlatformRules {

    private PlatformRules() {}

    public static final List<String> RULES = List.of(
            "Be respectful to all members",
            "No spam or irrelevant content",
            "Use the platform for academic discussions",
            "Violation may lead to warnings or bans"
    );

    public static String asPlainText() {
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < RULES.size(); i++) {
            sb.append(i + 1).append(". ").append(RULES.get(i));
            if (i < RULES.size() - 1) sb.append("\n");
        }
        return sb.toString();
    }
}
