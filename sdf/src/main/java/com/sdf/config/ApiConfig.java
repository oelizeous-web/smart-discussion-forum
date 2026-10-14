package com.sdf.config;

/**
 * Central configuration for the Smart Discussion Forum backend API.
 *
 * All API clients must use this class instead of defining their own
 * copies of the backend URL.
 */
public final class ApiConfig {

    public static final String BASE_URL =
            "https://smart-discussion-forum-backend.onrender.com/api";

    private ApiConfig() {
    }
}