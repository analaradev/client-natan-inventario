#!/usr/bin/env bash
set -euo pipefail

SSH_PORT="65002"
SSH_USER="u842150664"
SSH_HOST="195.179.239.90"
REMOTE_PROJECT="/home/u842150664/domains/sistemasdevida.com/public_html/pan_control_interno"
BRANCH="main"

echo "Actualizando pan_control_interno en Hostinger..."
echo "Servidor: ${SSH_USER}@${SSH_HOST}:${SSH_PORT}"
echo "Proyecto: ${REMOTE_PROJECT}"
echo "Rama: ${BRANCH}"
echo

ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
  "cd '${REMOTE_PROJECT}' && git pull origin '${BRANCH}'"

echo
echo "Listo. Hostinger quedo actualizado con origin/${BRANCH}."
