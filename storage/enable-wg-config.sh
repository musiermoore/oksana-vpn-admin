#!/bin/bash

config_file="/etc/wireguard/wg0.conf"

if [[ -z "$1" ]]; then
    echo "Usage: $0 <peer_name>"
    exit 1
fi

peer_name="$1"

# Check if the disabled peer exists
if ! grep -q "^#DISABLED # BEGIN_PEER $peer_name$" "$config_file"; then
    echo "Peer $peer_name not found or is not disabled."
    exit 1
fi

echo "Enabling peer: $peer_name"

# Uncomment the peer block in the configuration file
sed -i "/^#DISABLED # BEGIN_PEER $peer_name$/,/^#DISABLED # END_PEER $peer_name$/ s/^#DISABLED //" "$config_file"

# Add the peer back to the live WireGuard interface
wg set wg0 peer "$(grep -A 1 "^# BEGIN_PEER $peer_name$" "$config_file" | grep PublicKey | cut -d ' ' -f 3)" allowed-ips "$(grep AllowedIPs "$config_file" | cut -d ' ' -f 3)"

echo "Peer $peer_name has been enabled."
