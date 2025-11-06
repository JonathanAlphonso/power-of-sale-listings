**Request Flow**

- `request.php` builds the OData URL by assembling the `$top` and `$filter` parameters, then issues a cURL request with the required headers (`Accept`, `OData-Version`, bearer token).
- The HTTP status, response body, and any cURL error are captured; the script echoes the outcome and appends the full entry to `request.log`, giving a trace of each run.
- `count_results.php` reuses the same query, decodes the JSON, and counts the items in the `value` array, confirming the endpoint returns all 30 records when called with those parameters.

**Laravel Integration**

- **Service class**: Port the request logic into a reusable service such as `app/Services/PropertyFeed.php` with a `fetch(int $limit = 30): array` method that mirrors the standalone script.
- **HTTP client**: Use Laravel’s HTTP client (`Http::withHeaders([...])->get($url, $query)`) or Guzzle. Pass the query array exactly as in the working script so Laravel handles RFC3986 encoding of `$filter`.
- **Configuration**: Store the base URL, bearer token, and query fragments in `config/services.php`, backed by `.env` keys, so ENV changes don’t require code edits.
- **Logging**: Replace file appends with `Log::info(...)` entries (optionally dumping payloads to `storage/app/logs` if needed) to stay consistent with Laravel’s logging pipeline.
- **Usage**: Inject the service wherever the feed is needed (controllers, jobs, scheduled commands) and return or persist the fetched collection. Resources/collections can shape the response for views or APIs.
- **Testing**: Add a feature test that fakes the HTTP client (`Http::fake`) and verifies the service returns 30 items with the expected filter, protecting against regressions.
