package com.sdf.service;

import com.sdf.database.DatabaseConnection;
import com.sdf.network.ApiClient;
import com.sdf.storage.OfflineStorage;
import com.sdf.storage.TokenStorage;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicBoolean;

public final class SyncService {

    private static final int INITIAL_SYNC_DELAY_SECONDS = 5;
    private static final int SYNC_INTERVAL_SECONDS = 5;

    private static ScheduledExecutorService scheduler;

    private static final AtomicBoolean syncRunning =
            new AtomicBoolean(false);

    private SyncService() {
    }

    /**
     * Sends a POST request immediately.
     *
     * The request is saved to the offline queue only when a genuine
     * network problem prevents it from reaching the server.
     */
    public static ApiClient.ApiResponse sendPost(
            String endpoint,
            String payload,
            String bearerToken
    ) {
        ApiClient.ApiResponse response =
                ApiClient.post(
                        endpoint,
                        payload,
                        bearerToken
                );

        if (response.isNetworkError()) {
            String loggedInEmail =
                    TokenStorage.getLoggedInEmail();

            OfflineStorage.savePendingRequest(
                    loggedInEmail,
                    endpoint,
                    "POST",
                    payload
            );

            System.out.println(
                    "Request queued because the server could not be reached."
            );

        } else if (response.isSuccess()) {
            System.out.println(
                    "Post sent successfully."
            );

        } else if (response.isClientError()) {
            System.err.println(
                    "Post was not queued because the request contains "
                            + "a local configuration or programming error. "
                            + "Response: "
                            + response.body
            );

        } else {
            System.err.println(
                    "Server rejected the post. Status: "
                            + response.statusCode
                            + ", response: "
                            + response.body
            );
        }

        return response;
    }

    public static ApiClient.ApiResponse sendGet(
            String endpoint,
            String bearerToken
    ) {
        return ApiClient.get(
                endpoint,
                bearerToken
        );
    }

    /**
     * Starts the background task that retries pending offline requests.
     */
    public static synchronized void startAutomaticSync() {
        if (scheduler != null && !scheduler.isShutdown()) {
            return;
        }

        scheduler =
                Executors.newSingleThreadScheduledExecutor(
                        runnable -> {
                            Thread thread =
                                    new Thread(
                                            runnable,
                                            "offline-sync-thread"
                                    );

                            thread.setDaemon(true);
                            return thread;
                        }
                );

        scheduler.scheduleWithFixedDelay(
                SyncService::retryPendingRequests,
                INITIAL_SYNC_DELAY_SECONDS,
                SYNC_INTERVAL_SECONDS,
                TimeUnit.SECONDS
        );

        System.out.println(
                "Automatic offline synchronization started."
        );
    }

    /**
     * Stops the automatic background synchronization task.
     */
    public static synchronized void stopAutomaticSync() {
        if (scheduler == null) {
            return;
        }

        scheduler.shutdownNow();
        scheduler = null;

        System.out.println(
                "Automatic offline synchronization stopped."
        );
    }

    /**
     * Attempts to resend all pending requests.
     *
     * Requests rejected by the server or caused by invalid local
     * configuration have their retry count increased. OfflineStorage
     * marks them as FAILED after the maximum retry count is reached.
     *
     * Genuine connection failures are left pending because they may
     * succeed when internet access returns.
     */
    public static void retryPendingRequests() {
        if (!syncRunning.compareAndSet(false, true)) {
            return;
        }

        try {
            String token = TokenStorage.getToken();
            String loggedInEmail =
                    TokenStorage.getLoggedInEmail();

            if (token == null || token.isBlank()) {
                return;
            }

            if (loggedInEmail == null || loggedInEmail.isBlank()) {
                return;
            }

            try (
                    Connection connection =
                            DatabaseConnection.getConnection();

                    ResultSet resultSet =
                            OfflineStorage.getPendingRequests(
                                    connection,
                                    loggedInEmail
                            )
            ) {
                while (resultSet.next()) {
                    processQueuedRequest(
                            connection,
                            resultSet,
                            token
                    );
                }
            }

        } catch (SQLException exception) {
            System.err.println(
                    "Error synchronizing offline requests: "
                            + getReadableError(exception)
            );

        } catch (RuntimeException exception) {
            System.err.println(
                    "Unexpected synchronization error: "
                            + getReadableError(exception)
            );

        } finally {
            syncRunning.set(false);
        }
    }

    private static void processQueuedRequest(
            Connection connection,
            ResultSet resultSet,
            String token
    ) throws SQLException {

        int id =
                resultSet.getInt("id");

        String endpoint =
                resultSet.getString("endpoint");

        String requestMethod =
                resultSet.getString("request_method");

        String payload =
                resultSet.getString("payload");

        if (requestMethod == null || requestMethod.isBlank()) {
            recordFailedAttempt(
                    connection,
                    id,
                    "Queued request has no HTTP method."
            );

            return;
        }

        ApiClient.ApiResponse response;

        if ("POST".equalsIgnoreCase(requestMethod)) {
            response =
                    ApiClient.post(
                            endpoint,
                            payload,
                            token
                    );

        } else if ("GET".equalsIgnoreCase(requestMethod)) {
            response =
                    ApiClient.get(
                            endpoint,
                            token
                    );

        } else {
            recordFailedAttempt(
                    connection,
                    id,
                    "Unsupported queued HTTP method: "
                            + requestMethod
            );

            return;
        }

        if (response.isSuccess()) {
            OfflineStorage.markAsSent(
                    connection,
                    id
            );

            System.out.println(
                    "Queued request synced successfully. ID: "
                            + id
            );

            return;
        }

        if (response.isNetworkError()) {
            System.out.println(
                    "The server is still unavailable. "
                            + "Queued requests will be retried later."
            );

            return;
        }

        if (response.isClientError()) {
            recordFailedAttempt(
                    connection,
                    id,
                    "Queued request has a local configuration "
                            + "or programming error. Response: "
                            + response.body
            );

            return;
        }

        recordFailedAttempt(
                connection,
                id,
                "Queued request was rejected by the server. Status: "
                        + response.statusCode
                        + ", response: "
                        + response.body
        );
    }

    private static void recordFailedAttempt(
            Connection connection,
            int requestId,
            String message
    ) throws SQLException {

        OfflineStorage.increaseRetryCount(
                connection,
                requestId
        );

        System.err.println(
                message
                        + " Request ID: "
                        + requestId
        );
    }

    private static String getReadableError(
            Throwable throwable
    ) {
        if (throwable == null) {
            return "Unknown error";
        }

        String message = throwable.getMessage();

        if (message != null && !message.isBlank()) {
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