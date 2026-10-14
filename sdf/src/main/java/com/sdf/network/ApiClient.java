package com.sdf.network;

import com.sdf.config.ApiConfig;

import java.io.IOException;
import java.net.ConnectException;
import java.net.SocketException;
import java.net.URI;
import java.net.UnknownHostException;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.net.http.HttpTimeoutException;
import java.time.Duration;

public final class ApiClient {

    private static final int NETWORK_ERROR_STATUS = -1;
    private static final int CLIENT_ERROR_STATUS = -2;

    private static final HttpClient CLIENT =
            HttpClient.newBuilder()
                    .connectTimeout(Duration.ofSeconds(10))
                    .build();

    private ApiClient() {
    }

    public static final class ApiResponse {

        public final int statusCode;
        public final String body;

        public ApiResponse(int statusCode, String body) {
            this.statusCode = statusCode;
            this.body = body;
        }

        public boolean isSuccess() {
            return statusCode >= 200 && statusCode < 300;
        }

        /**
         * A network error means the request could not reach the server.
         *
         * Only these errors should be saved to the offline queue.
         */
        public boolean isNetworkError() {
            return statusCode == NETWORK_ERROR_STATUS;
        }

        /**
         * A client error represents an invalid endpoint, malformed URI,
         * interrupted request, or another local programming/configuration
         * problem.
         *
         * These errors must not be queued for offline retry.
         */
        public boolean isClientError() {
            return statusCode == CLIENT_ERROR_STATUS;
        }
    }

    public static ApiResponse post(
            String endpoint,
            String jsonBody,
            String bearerToken
    ) {
        final String fullUrl;

        try {
            fullUrl = createFullUrl(endpoint);
        } catch (IllegalArgumentException exception) {
            return createClientErrorResponse(
                    "POST",
                    exception
            );
        }

        try {
            HttpRequest.Builder builder =
                    HttpRequest.newBuilder()
                            .uri(URI.create(fullUrl))
                            .timeout(Duration.ofSeconds(20))
                            .header("Content-Type", "application/json")
                            .header("Accept", "application/json")
                            .POST(
                                    HttpRequest.BodyPublishers.ofString(
                                            jsonBody == null
                                                    ? "{}"
                                                    : jsonBody
                                    )
                            );

            addAuthorizationHeader(
                    builder,
                    bearerToken
            );

            HttpResponse<String> response =
                    CLIENT.send(
                            builder.build(),
                            HttpResponse.BodyHandlers.ofString()
                    );

            return new ApiResponse(
                    response.statusCode(),
                    response.body()
            );

        } catch (InterruptedException exception) {
            Thread.currentThread().interrupt();

            System.err.println(
                    "POST request was interrupted."
            );

            return new ApiResponse(
                    CLIENT_ERROR_STATUS,
                    createErrorJson("Request interrupted")
            );

        } catch (IOException exception) {
            return handleIOException(
                    "POST",
                    exception
            );

        } catch (IllegalArgumentException exception) {
            return createClientErrorResponse(
                    "POST",
                    exception
            );

        } catch (RuntimeException exception) {
            return createClientErrorResponse(
                    "POST",
                    exception
            );
        }
    }

    public static ApiResponse get(
            String endpoint,
            String bearerToken
    ) {
        final String fullUrl;

        try {
            fullUrl = createFullUrl(endpoint);
        } catch (IllegalArgumentException exception) {
            return createClientErrorResponse(
                    "GET",
                    exception
            );
        }

        try {
            HttpRequest.Builder builder =
                    HttpRequest.newBuilder()
                            .uri(URI.create(fullUrl))
                            .timeout(Duration.ofSeconds(20))
                            .header("Accept", "application/json")
                            .GET();

            addAuthorizationHeader(
                    builder,
                    bearerToken
            );

            HttpResponse<String> response =
                    CLIENT.send(
                            builder.build(),
                            HttpResponse.BodyHandlers.ofString()
                    );

            return new ApiResponse(
                    response.statusCode(),
                    response.body()
            );

        } catch (InterruptedException exception) {
            Thread.currentThread().interrupt();

            System.err.println(
                    "GET request was interrupted."
            );

            return new ApiResponse(
                    CLIENT_ERROR_STATUS,
                    createErrorJson("Request interrupted")
            );

        } catch (IOException exception) {
            return handleIOException(
                    "GET",
                    exception
            );

        } catch (IllegalArgumentException exception) {
            return createClientErrorResponse(
                    "GET",
                    exception
            );

        } catch (RuntimeException exception) {
            return createClientErrorResponse(
                    "GET",
                    exception
            );
        }
    }

    private static ApiResponse handleIOException(
            String requestMethod,
            IOException exception
    ) {
        if (isNetworkException(exception)) {
            System.err.println(
                    requestMethod
                            + " request could not reach the server."
            );

            System.err.println(
                    "Reason: "
                            + getReadableError(exception)
            );

            return new ApiResponse(
                    NETWORK_ERROR_STATUS,
                    createErrorJson("Internet unavailable")
            );
        }

        System.err.println(
                requestMethod
                        + " request failed because of an I/O error."
        );

        System.err.println(
                "Reason: "
                        + getReadableError(exception)
        );

        return new ApiResponse(
                CLIENT_ERROR_STATUS,
                createErrorJson("Request could not be completed")
        );
    }

    private static ApiResponse createClientErrorResponse(
            String requestMethod,
            Exception exception
    ) {
        System.err.println(
                requestMethod
                        + " request could not be created or completed."
        );

        System.err.println(
                "Reason: "
                        + getReadableError(exception)
        );

        return new ApiResponse(
                CLIENT_ERROR_STATUS,
                createErrorJson(
                        getReadableError(exception)
                )
        );
    }

    private static boolean isNetworkException(
            Throwable throwable
    ) {
        Throwable current = throwable;

        while (current != null) {
            if (current instanceof ConnectException
                    || current instanceof UnknownHostException
                    || current instanceof HttpTimeoutException
                    || current instanceof SocketException) {

                return true;
            }

            current = current.getCause();
        }

        return false;
    }

    private static String createFullUrl(String endpoint) {
        if (endpoint == null || endpoint.isBlank()) {
            throw new IllegalArgumentException(
                    "The API endpoint cannot be empty."
            );
        }

        String cleanedEndpoint = endpoint.trim();

        if (cleanedEndpoint.startsWith("http://")
                || cleanedEndpoint.startsWith("https://")) {

            validateUri(cleanedEndpoint);
            return cleanedEndpoint;
        }

        String fullUrl;

        if (cleanedEndpoint.startsWith("/")) {
            fullUrl =
                    ApiConfig.BASE_URL
                            + cleanedEndpoint;
        } else {
            fullUrl =
                    ApiConfig.BASE_URL
                            + "/"
                            + cleanedEndpoint;
        }

        validateUri(fullUrl);

        return fullUrl;
    }

    private static void validateUri(String url) {
        URI uri = URI.create(url);

        if (uri.getScheme() == null
                || uri.getHost() == null) {

            throw new IllegalArgumentException(
                    "The API URL is invalid: " + url
            );
        }
    }

    private static void addAuthorizationHeader(
            HttpRequest.Builder builder,
            String bearerToken
    ) {
        if (bearerToken != null
                && !bearerToken.isBlank()) {

            builder.header(
                    "Authorization",
                    "Bearer " + bearerToken.trim()
            );
        }
    }

    private static String createErrorJson(String message) {
        String safeMessage =
                message == null
                        ? "Unknown error"
                        : message
                        .replace("\\", "\\\\")
                        .replace("\"", "\\\"")
                        .replace("\n", " ")
                        .replace("\r", " ");

        return "{\"message\":\""
                + safeMessage
                + "\"}";
    }

    private static String getReadableError(
            Throwable throwable
    ) {
        if (throwable == null) {
            return "Unknown error";
        }

        String message = throwable.getMessage();

        if (message != null
                && !message.isBlank()) {

            return message;
        }

        Throwable cause = throwable.getCause();

        while (cause != null) {
            String causeMessage = cause.getMessage();

            if (causeMessage != null
                    && !causeMessage.isBlank()) {

                return causeMessage;
            }

            cause = cause.getCause();
        }

        return throwable.getClass().getSimpleName();
    }
}