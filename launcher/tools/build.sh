#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
go test -race ./internal/...
go run github.com/akavel/rsrc -arch amd64 -manifest assets/application.manifest -ico assets/launcher.ico -o cmd/launcher/resource_windows_amd64.syso
GOOS=windows GOARCH=amd64 CGO_ENABLED=0 go build -buildvcs=false -trimpath -ldflags='-H windowsgui -s -w' -o dist/LA2PLUS-Launcher.exe ./cmd/launcher
go run ./cmd/notices dist/ThirdPartyNotices.txt
makensis -DAPP_EXE="$(pwd)/dist/LA2PLUS-Launcher.exe" -DBUILD_DIR="$(pwd)/dist" installer/setup.nsi
sha256sum dist/LA2PLUS-Setup-1.0.0.exe > dist/LA2PLUS-Setup-1.0.0.exe.sha256
