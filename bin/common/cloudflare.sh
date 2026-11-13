CF_API_TOKEN="${CF_API_TOKEN:-}"
CF_ACCOUNT="${CF_ACCOUNT:-}"
CF_TUNNEL="${CF_TUNNEL:-}"
CF_ZONE="${CF_ZONE:-}"

cf_api() {
    local method="$1" path="$2" body="${3:-}" response
    local args=(-sS -X "$method" "https://api.cloudflare.com/client/v4$path" --config -)
    if [ -n "$body" ]; then
        args+=(-H "Content-Type: application/json" --data "$body")
    fi
    response="$(printf 'header = "Authorization: Bearer %s"\n' "$CF_API_TOKEN" | curl "${args[@]}")" || return 1
    if [ "$(printf '%s' "$response" | jq -r '.success' 2> /dev/null)" != "true" ]; then
        echo "Cloudflare refused $method $path:" >&2
        printf '%s' "$response" | jq -r '.errors[]? | "  \(.code) \(.message)"' >&2 || true
        return 1
    fi
    printf '%s' "$response"
}

cf_require_tools() {
    command -v jq > /dev/null || { echo "jq is needed to manage Cloudflare tunnels." >&2; return 1; }
    command -v curl > /dev/null || { echo "curl is needed to manage Cloudflare tunnels." >&2; return 1; }
}

cf_find_zone() {
    local host="$1" candidate="${1#*.}" response
    while [[ "$candidate" == *.* ]]; do
        response="$(cf_api GET "/zones?name=$candidate")" || return 1
        CF_ZONE="$(printf '%s' "$response" | jq -r '.result[0].id // empty')"
        [ -z "$CF_ZONE" ] || return 0
        candidate="${candidate#*.}"
    done
    echo "No zone the API token can read holds $host." >&2
    return 1
}

cf_tunnel_config() {
    cf_api GET "/accounts/$CF_ACCOUNT/cfd_tunnel/$CF_TUNNEL/configurations"
}

cf_put_tunnel_config() {
    cf_api PUT "/accounts/$CF_ACCOUNT/cfd_tunnel/$CF_TUNNEL/configurations" "$1" > /dev/null
}

cf_route_service() {
    local host="$1" config
    config="$(cf_tunnel_config)" || return 1
    printf '%s' "$config" | jq -r --arg h "$host" '.result.config.ingress[]? | select(.hostname == $h) | .service' | tail -1
}

cf_set_route() {
    local host="$1" service="$2" config updated
    config="$(cf_tunnel_config)" || return 1
    updated="$(printf '%s' "$config" | jq -c --arg h "$host" --arg s "$service" '
        (.result.config // {}) | .ingress = (
            [(.ingress // [])[] | select(.hostname != $h and .hostname != null)]
            + [{hostname: $h, service: $s}]
            + ([(.ingress // [])[] | select(.hostname == null)] | if length == 0 then [{service: "http_status:404"}] else . end)
        ) | {config: .}')" || return 1
    cf_put_tunnel_config "$updated"
}

cf_remove_route() {
    local host="$1" config updated
    config="$(cf_tunnel_config)" || return 1
    if [ "$(printf '%s' "$config" | jq --arg h "$host" '[.result.config.ingress[]? | select(.hostname == $h)] | length')" = "0" ]; then
        return 0
    fi
    updated="$(printf '%s' "$config" | jq -c --arg h "$host" '.result.config | .ingress = [.ingress[] | select(.hostname != $h)] | {config: .}')" || return 1
    cf_put_tunnel_config "$updated"
}

cf_add_dns() {
    local host="$1" comment="$2" target="$CF_TUNNEL.cfargotunnel.com" records content
    records="$(cf_api GET "/zones/$CF_ZONE/dns_records?name=$host")" || return 1
    content="$(printf '%s' "$records" | jq -r '.result[0].content // empty')"
    [ "$content" != "$target" ] || return 0
    if [ -n "$content" ]; then
        echo "$host already points at $content; it is left alone." >&2
        return 1
    fi
    cf_api POST "/zones/$CF_ZONE/dns_records" \
        "$(jq -nc --arg n "$host" --arg c "$target" --arg m "$comment" '{type: "CNAME", name: $n, content: $c, proxied: true, comment: $m}')" > /dev/null
}

cf_remove_dns() {
    local host="$1" records id
    records="$(cf_api GET "/zones/$CF_ZONE/dns_records?name=$host")" || return 1
    id="$(printf '%s' "$records" | jq -r --arg c "$CF_TUNNEL.cfargotunnel.com" '.result[] | select(.content == $c) | .id' | tail -1)"
    [ -z "$id" ] || cf_api DELETE "/zones/$CF_ZONE/dns_records/$id" > /dev/null
}

cf_remove_dns_marked() {
    local host="$1" comment="$2" records id
    records="$(cf_api GET "/zones/$CF_ZONE/dns_records?name=$host")" || return 1
    for id in $(printf '%s' "$records" | jq -r --arg m "$comment" '.result[] | select(.comment == $m and (.content | endswith(".cfargotunnel.com"))) | .id'); do
        cf_api DELETE "/zones/$CF_ZONE/dns_records/$id" > /dev/null || return 1
    done
}

cf_find_tunnel() {
    local name="$1" response
    response="$(cf_api GET "/accounts/$CF_ACCOUNT/cfd_tunnel?name=$name&is_deleted=false")" || return 1
    CF_TUNNEL="$(printf '%s' "$response" | jq -r '.result[0].id // empty')"
}

cf_create_tunnel() {
    local name="$1" response
    response="$(cf_api POST "/accounts/$CF_ACCOUNT/cfd_tunnel" \
        "$(jq -nc --arg n "$name" '{name: $n, config_src: "cloudflare"}')")" || return 1
    CF_TUNNEL="$(printf '%s' "$response" | jq -r '.result.id // empty')"
    [ -n "$CF_TUNNEL" ]
}

cf_tunnel_token() {
    local response
    response="$(cf_api GET "/accounts/$CF_ACCOUNT/cfd_tunnel/$CF_TUNNEL/token")" || return 1
    printf '%s' "$response" | jq -r '.result // empty'
}

cf_delete_tunnel() {
    local attempt
    for attempt in 1 2 3 4 5 6; do
        cf_api DELETE "/accounts/$CF_ACCOUNT/cfd_tunnel/$CF_TUNNEL/connections" > /dev/null 2>&1 || true
        if cf_api DELETE "/accounts/$CF_ACCOUNT/cfd_tunnel/$CF_TUNNEL" > /dev/null; then
            return 0
        fi
        [ "$attempt" -eq 6 ] || sleep 10
    done
    return 1
}
