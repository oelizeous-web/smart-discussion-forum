package com.sdf.api;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.sdf.config.ApiConfig;
import com.sdf.session.Session;

import java.io.IOException;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.Duration;
import java.util.ArrayList;
import java.util.Iterator;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

/**
 * Thin wrapper around java.net.http.HttpClient that talks to the Smart
 * Discussion Forum Laravel API.
 *
 * Every method here is BLOCKING (plain synchronous HTTP call).
 * Callers on the JavaFX Application Thread should execute these
 * through ApiTask so the UI remains responsive.
 */
public final class ApiClient {

    private static final ApiClient INSTANCE = new ApiClient();

    private final HttpClient http = HttpClient.newBuilder()
            .connectTimeout(Duration.ofSeconds(15))
            .build();

    private final ObjectMapper mapper = new ObjectMapper();

    private ApiClient() {
    }

    public static ApiClient get() {
        return INSTANCE;
    }

    public JsonNode get(String path)
            throws ApiException, IOException, InterruptedException {

        return send(request(path, "GET", null));
    }

    public JsonNode post(
            String path,
            Map<String, Object> body
    ) throws ApiException, IOException, InterruptedException {

        return send(request(path, "POST", body));
    }

    public JsonNode put(
            String path,
            Map<String, Object> body
    ) throws ApiException, IOException, InterruptedException {

        return send(request(path, "PUT", body));
    }

    public JsonNode delete(String path)
            throws ApiException, IOException, InterruptedException {

        return send(request(path, "DELETE", null));
    }

    // ------------------------------------------------------------------

    private HttpRequest request(
            String path,
            String method,
            Map<String, Object> body
    ) throws IOException {

        HttpRequest.Builder builder = HttpRequest.newBuilder()
                .uri(URI.create(ApiConfig.BASE_URL + path))
                .timeout(Duration.ofSeconds(20))
                .header("Accept", "application/json");

        String token = Session.get().getToken();

        if (token != null && !token.isBlank()) {
            builder.header(
                    "Authorization",
                    "Bearer " + token
            );
        }

        HttpRequest.BodyPublisher publisher =
                HttpRequest.BodyPublishers.noBody();

        if (body != null) {
            builder.header(
                    "Content-Type",
                    "application/json"
            );

            publisher = HttpRequest.BodyPublishers.ofString(
                    mapper.writeValueAsString(body)
            );
        }

        return builder.method(method, publisher).build();
    }

    private JsonNode send(HttpRequest request)
            throws ApiException, IOException, InterruptedException {

        HttpResponse<String> response =
                http.send(
                        request,
                        HttpResponse.BodyHandlers.ofString()
                );

        int status = response.statusCode();
        String raw = response.body();

        JsonNode json = null;

        if (raw != null && !raw.isBlank()) {
            try {
                json = mapper.readTree(raw);
            } catch (Exception ignored) {
                // Ignore non-JSON responses
            }
        }

        if (status >= 200 && status < 300) {
            return json;
        }

        String message =
                json != null && json.has("status")
                        ? json.get("status").asText()
                        : "Request failed with status " + status;

        Map<String, List<String>> fieldErrors =
                new LinkedHashMap<>();

        if (json != null
                && json.has("errors")
                && json.get("errors").isObject()) {

            Iterator<Map.Entry<String, JsonNode>> fields =
                    json.get("errors").fields();

            while (fields.hasNext()) {

                Map.Entry<String, JsonNode> entry =
                        fields.next();

                List<String> messages =
                        new ArrayList<>();

                entry.getValue().forEach(
                        node -> messages.add(node.asText())
                );

                fieldErrors.put(
                        entry.getKey(),
                        messages
                );
            }
        }

        throw new ApiException(
                status,
                message,
                fieldErrors
        );
    }
}