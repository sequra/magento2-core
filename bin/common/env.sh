env_value() {
    local file="$1" key="$2"
    [ -f "$file" ] || return 0
    sed -n "s/^${key}=//p" "$file" | tail -1 | sed 's/[[:space:]]*#.*$//' | tr -d '\r'
}

set_env() {
    local file="$1" key="$2" value="$3"
    if grep -q "^${key}=" "$file"; then
        sed -i.bak "s|^${key}=.*|${key}=${value}|" "$file"
        rm -f "$file.bak"
    else
        [ -z "$(tail -c1 "$file")" ] || printf '\n' >> "$file"
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
}
