package com.sdf.api;

import java.util.Collections;
import java.util.Map;

/**
 * Thrown whenever the API returns a non-response. Laravel's default
 */
public class ApiException extends Exception {

    private final int statusCode;
    private final Map<String, java.util.List<String>> fieldErrors;

    public ApiException(int statusCode, String message, Map<String, java.util.List<String>> fieldErrors) {
        super(message);
        this.statusCode = statusCode;
        this.fieldErrors = fieldErrors == null ? Collections.emptyMap() : fieldErrors;
    }

    public int getStatusCode() {
        return statusCode;
    }

    public Map<String, java.util.List<String>> getFieldErrors() {
        return fieldErrors;
    }

    public boolean isValidationError() {
        return statusCode == 422 && !fieldErrors.isEmpty();
    }

    public boolean isUnauthorized() {
        return statusCode == 401;
    }

    public boolean isSuspended() {
        return statusCode == 403;
    }

    /** First validation message, or the top-level message, whichever exists. */
    public String firstMessage() {
        if (!fieldErrors.isEmpty()) {
            for (java.util.List<String> msgs : fieldErrors.values()) {
                if (!msgs.isEmpty()) return msgs.get(0);
            }
        }
        return getMessage();
    }
}
