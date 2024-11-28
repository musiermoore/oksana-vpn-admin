#!/bin/bash

config_file="/etc/wireguard/wg0.conf"

if [[ -z "$1" ]]; then
    echo "Usage: $0 <peer_name>"
    exit 1
fi

peer_name="$1"

# Check if the peer exists
if ! grep -q "^# BEGIN_PEER $peer_name$" "$config_file"; then
    echo "Peer $peer_name not found."
    exit 1
fi

echo "Disabling peer: $peer_name"

# Comment out the peer block in the configuration file
sed -i "/^# BEGIN_PEER $peer_name$/,/^# END_PEER $peer_name$/ s/^/#DISABLED /" "$config_file"

# Remove the peer from the live WireGuard interface
wg set wg0 peer "$(grep -A 1 "^#DISABLED # BEGIN_PEER $peer_name$" "$config_file" | grep PublicKey | sed 's/^#DISABLED //' | cut -d ' ' -f 3)" remove

echo "Peer $peer_name has been disabled."
