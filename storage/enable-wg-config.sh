#!/bin/bash

config_file="/etc/wireguard/wg0.conf"
peer_name="$1"

if [[ -z "$peer_name" ]]; then
    echo "Usage: $0 <peer_name>"
    exit 1
fi

# Extract the public key for the given peer
public_key=$(sed -n "/^# BEGIN_PEER $peer_name$/,/^# END_PEER $peer_name$/p" "$config_file" | grep PublicKey | sed -E 's/^#DISABLED[ ]*//' | sed -E 's/^[ ]*PublicKey = //')

if [[ -z "$public_key" ]]; then
    echo "Error: Failed to find a valid public key for peer $peer_name in $config_file."
    exit 1
fi

# Extract the AllowedIPs for the peer
allowed_ips=$(sed -n "/^# BEGIN_PEER $peer_name$/,/^# END_PEER $peer_name$/p" "$config_file" | grep AllowedIPs | sed -E 's/^#DISABLED[ ]*//' | sed -E 's/^[ ]*AllowedIPs = //')

if [[ -z "$allowed_ips" ]]; then
    echo "Error: Failed to find AllowedIPs for peer $peer_name in $config_file."
    exit 1
fi

# Add the peer back to the live WireGuard interface
wg set wg0 peer "$public_key" allowed-ips "$allowed_ips"
if [[ $? -ne 0 ]]; then
    echo "Error: Failed to enable peer $peer_name."
    exit 1
fi

# Uncomment the peer block in the config file
sed -i "/^# BEGIN_PEER $peer_name$/,/^# END_PEER $peer_name$/ s/^#DISABLED //" "$config_file"

echo "Peer $peer_name has been enabled."
