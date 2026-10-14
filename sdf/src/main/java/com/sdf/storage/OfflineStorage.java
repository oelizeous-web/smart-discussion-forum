package com.sdf.storage;

import com.sdf.database.DatabaseConnection;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;

public class OfflineStorage {

    private static final int MAX_RETRY_COUNT = 5;

    private OfflineStorage() {
    }

    public static void savePendingRequest(
            String userEmail,
            String endpoint,
            String requestMethod,
            String payload
    ) {
        String sql = """
                INSERT INTO offline_queue (
                    user_email,
                    endpoint,
                    request_method,
                    payload,
                    status
                )
                VALUES (?, ?, ?, ?, 'PENDING')
                """;

        try (Connection connection = DatabaseConnection.getConnection();
             PreparedStatement statement = connection.prepareStatement(sql)) {

            statement.setString(1, userEmail);
            statement.setString(2, endpoint);
            statement.setString(3, requestMethod);
            statement.setString(4, payload);

            statement.executeUpdate();

            System.out.println("Request saved to offline queue.");

        } catch (SQLException exception) {
            System.err.println(
                    "Could not save pending request: "
                            + exception.getMessage()
            );
        }
    }

    public static ResultSet getPendingRequests(
            Connection connection,
            String userEmail
    )
            throws SQLException {

        String sql = """
                SELECT *
                FROM offline_queue
                WHERE status = 'PENDING'
                  AND user_email = ?
                  AND retry_count < ?
                ORDER BY id ASC
                """;

        PreparedStatement statement = connection.prepareStatement(sql);
        statement.setString(1, userEmail);
        statement.setInt(2, MAX_RETRY_COUNT);
        ResultSet resultSet = statement.executeQuery();
        statement.closeOnCompletion();
        return resultSet;
    }

    public static void markAsSent(Connection connection, int id)
            throws SQLException {

        String sql = """
                UPDATE offline_queue
                SET status = 'SENT'
                WHERE id = ?
                """;

        try (PreparedStatement statement =
                     connection.prepareStatement(sql)) {

            statement.setInt(1, id);
            statement.executeUpdate();
        }
    }

    public static void increaseRetryCount(Connection connection, int id)
            throws SQLException {

        String sql = """
                UPDATE offline_queue
                SET retry_count = retry_count + 1,
                    status = CASE
                        WHEN retry_count + 1 >= ? THEN 'FAILED'
                        ELSE 'PENDING'
                    END
                WHERE id = ?
                """;

        try (PreparedStatement statement =
                     connection.prepareStatement(sql)) {

            statement.setInt(1, MAX_RETRY_COUNT);
            statement.setInt(2, id);

            statement.executeUpdate();
        }
    }
}