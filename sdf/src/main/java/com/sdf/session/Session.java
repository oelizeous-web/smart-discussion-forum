package com.sdf.session;

import java.util.Collections;
import java.util.List;

/**
 * Holds everything the app knows about the currently logged-in user:
 * the Sanctum bearer token (needed on every authenticated request) and
 * the profile/roles returned by /api/login or /api/register.
 *
 * This is a simple in-memory singleton. It is intentionally NOT persisted
 * to disk yet — on every launch the user has to log in again. Persisting
 * the token (e.g. to a small file in the user's home directory) is a
 * natural next step once this is wired up and working.
 */
public final class Session {

    private static final Session INSTANCE = new Session();

    private String token;
    private long userId;
    private String name;
    private String email;
    private List<String> roles = Collections.emptyList();

    private Session() {}

    public static Session get() {
        return INSTANCE;
    }

    public void start(String token, long userId, String name, String email, List<String> roles) {
        this.token = token;
        this.userId = userId;
        this.name = name;
        this.email = email;
        this.roles = roles == null ? Collections.emptyList() : roles;
    }

    /** Clears everything on logout. */
    public void clear() {
        token = null;
        userId = 0;
        name = null;
        email = null;
        roles = Collections.emptyList();
    }

    public boolean isLoggedIn() {
        return token != null && !token.isBlank();
    }

    public String getToken() {
        return token;
    }

    public long getUserId() {
        return userId;
    }

    public String getName() {
        return name == null ? "" : name;
    }

    public String getEmail() {
        return email;
    }

    public List<String> getRoles() {
        return roles;
    }

    public boolean hasRole(String role) {
        return roles.stream().anyMatch(r -> r.equalsIgnoreCase(role));
    }

    public boolean isAdmin() {
        return hasRole("admin");
    }

    public boolean isLecturer() {
        return hasRole("lecturer");
    }

    /** Single uppercase letter for the sidebar avatar circle. */
    public String getInitial() {
        String n = getName().trim();
        return n.isEmpty() ? "?" : n.substring(0, 1).toUpperCase();
    }
}
