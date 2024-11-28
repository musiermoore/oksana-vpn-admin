#!/bin/bash

config_file="/etc/wireguard/wg0.conf"
peer_name="$1"

if [[ -z "$peer_name" ]]; then
    echo "Usage: $0 <peer_name>"
    exit 1
fi

# Extract the public key for the given peer
public_key=$(sed -n "/^# BEGIN_PEER $peer_name$/,/^# END_PEER $peer_name$/p" "$config_file" | grep PublicKey | sed -E 's/.*PublicKey = //' | sed -E 's/PresharedKey = .*//')

if [[ -z "$public_key" ]]; then
    echo "Error: Failed to find a valid public key for peer $peer_name in $config_file."
    exit 1
fi

# Remove the peer from the live WireGuard interface
wg set wg0 peer "$public_key" remove
if [[ $? -ne 0 ]]; then
    echo "Error: Failed to disable peer $peer_name."
    exit 1
fi

# Comment out the peer block in the config file
sed -i "/^# BEGIN_PEER $peer_name$/,/^# END_PEER $peer_name$/ s/^/#DISABLED /" "$config_file"

echo "Peer $peer_name has been disabled."
