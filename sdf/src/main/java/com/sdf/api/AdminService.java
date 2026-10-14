package com.sdf.api;

import com.fasterxml.jackson.databind.JsonNode;

import java.util.LinkedHashMap;
import java.util.Map;

public final class AdminService {

    private AdminService() {}

    /** POST /api/admin/register-lecturer */
    public static void registerLecturer(String name, String email, String password, String passwordConfirmation) throws Exception {
        Map<String, Object> body = new LinkedHashMap<>();
        body.put("name", name);
        body.put("email", email);
        body.put("password", password);
        body.put("password_confirmation", passwordConfirmation);
        body.put("agreed_to_rules", true);
        ApiClient.get().post("/admin/register-lecturer", body);
    }

    /** GET /api/admin/groups  */
    public static JsonNode listAllGroups() throws Exception {
        return ApiClient.get().get("/admin/groups");
    }

    /** GET /api/admin/dashboard — stat cards + 7-day activity for the dashboard chart. */
    public static JsonNode dashboardStats() throws Exception {
        return ApiClient.get().get("/admin/dashboard");
    }

    /** GET /api/admin/users — every user on the platform with role/status/warning_count. */
    public static JsonNode listUsers() throws Exception {
        return ApiClient.get().get("/admin/users");
    }

    /** GET /api/admin/lecturers — every user with the lecturer role. */
    public static JsonNode listLecturers() throws Exception {
        return ApiClient.get().get("/admin/lecturers");
    }

    /** POST /api/admin/users/{id}/warn */
    public static void warnUser(long userId) throws Exception {
        ApiClient.get().post("/admin/users/" + userId + "/warn", new LinkedHashMap<>());
    }

    /** POST /api/admin/users/{id}/suspend */
    public static void suspendUser(long userId) throws Exception {
        ApiClient.get().post("/admin/users/" + userId + "/suspend", new LinkedHashMap<>());
    }

    /** POST /api/admin/users/{id}/reinstate */
    public static void reinstateUser(long userId) throws Exception {
        ApiClient.get().post("/admin/users/" + userId + "/reinstate", new LinkedHashMap<>());
    }

    /** GET /api/admin/flags — pending flagged posts for review. */
    public static JsonNode listFlags() throws Exception {
        return ApiClient.get().get("/admin/flags");
    }

    /** POST /api/admin/flags/{id}/resolve */
    public static void resolveFlag(long flagId) throws Exception {
        ApiClient.get().post("/admin/flags/" + flagId + "/resolve", new LinkedHashMap<>());
    }
}
