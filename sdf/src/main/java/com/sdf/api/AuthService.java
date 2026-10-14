package com.sdf.api;

import com.fasterxml.jackson.databind.JsonNode;
import com.sdf.service.SyncService;
import com.sdf.session.Session;
import com.sdf.storage.TokenStorage;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

public final class AuthService {

    private AuthService() {
    }

    /**
     * Logs in and populates Session on success.
     * Returns the Session for convenience.
     */
    public static Session login(String email, String password) throws Exception {
        Map<String, Object> body = new LinkedHashMap<>();
        body.put("email", email);
        body.put("password", password);

        JsonNode json = ApiClient.get().post("/login", body);
        applySession(json);

        return Session.get();
    }

    public static Session register(
            String name,
            String email,
            String password,
            String passwordConfirmation,
            boolean agreedToRules
    ) throws Exception {

        Map<String, Object> body = new LinkedHashMap<>();
        body.put("name", name);
        body.put("email", email);
        body.put("password", password);
        body.put("password_confirmation", passwordConfirmation);
        body.put("agreed_to_rules", agreedToRules);

        JsonNode json = ApiClient.get().post("/register", body);
        applySession(json);

        return Session.get();
    }

    /**
     * Best-effort server-side token revoke.
     * Clears the local session and stored token either way.
     */
    public static void logout() {
        try {
            if (Session.get().isLoggedIn()) {
                ApiClient.get().post("/logout", null);
            }
        } catch (Exception ignored) {
            // The server token may already be invalid or expired.
            // The local session will still be cleared.
        } finally {
            TokenStorage.clearToken();
            Session.get().clear();
        }
    }

    /**
     * Reads the login or registration response,
     * creates the session and saves the token locally.
     */
    private static void applySession(JsonNode json) {
        String token = json.get("access_token").asText();
        JsonNode user = json.get("user");

        long id = user.has("id")
                ? user.get("id").asLong()
                : 0;

        String name = user.has("name")
                ? user.get("name").asText()
                : "";

        String email = user.has("email")
                ? user.get("email").asText()
                : "";

        List<String> roles = new ArrayList<>();

        if (user.has("roles") && user.get("roles").isArray()) {
            user.get("roles").forEach(role -> {
                if (role.isTextual()) {
                    roles.add(role.asText());
                } else {
                    roles.add(role.path("name").asText());
                }
            });
        }

        Session.get().start(token, id, name, email, roles);

        TokenStorage.saveToken(token);
        TokenStorage.saveLoggedInEmail(email);

        SyncService.retryPendingRequests();
    }
}