# CI: Building a Container Image

An example GitHub Actions workflow that renders a preset's `Containerfile` via `podman:generate` and builds/pushes a multi-arch image with `buildah`. Not something this package runs itself — copy it into your own app's `.github/workflows/` and adjust the preset name/paths.

## Prerequisites

- A committed `.env` (or one written in CI, e.g. from an `.env.ci` template) with `APP_KEY` generated before `podman:generate` runs — the preset's `Containerfile`/templates may read app config at render time.
- The preset you're building must ship a `runtimes/Containerfile` (bundled `frankenphp-octane` does; custom presets need their own, see [Customizing](customizing.md)).

## Example: `.github/workflows/build.yml`

```yaml
name: Build

on:
    push:
        branches:
            - main
    pull_request:
    release:
        types: [published]
    workflow_dispatch:

concurrency:
    group: docker-${{ github.workflow }}-${{ github.ref }}
    cancel-in-progress: true

env:
    REGISTRY: ghcr.io
    IMAGE_NAME: ${{ github.event.repository.name }}
    IMAGE: ghcr.io/${{ github.repository }}
    CACHE_IMAGE: ghcr.io/${{ github.repository }}-cache

permissions:
    contents: read

jobs:
    build:
        name: Build (${{ matrix.platform }})
        runs-on: ${{ matrix.runner }}
        timeout-minutes: 30
        permissions:
            contents: read
            packages: write

        strategy:
            fail-fast: false
            matrix:
                include:
                    - platform: linux/amd64
                      runner: ubuntu-latest
                    - platform: linux/arm64
                      runner: ubuntu-24.04-arm

        steps:
            - name: Checkout code
              uses: actions/checkout@v7

            - name: Setup PHP
              uses: shivammathur/setup-php@v2
              with:
                  php-version: 8.5
                  coverage: none

            - name: Install Composer dependencies
              run: composer install --prefer-dist --no-interaction --no-progress

            - name: Render Podman quadlets
              run: |
                  cp .env.ci .env
                  php artisan key:generate
                  php artisan podman:generate frankenphp-octane

            - name: Set platform pair
              id: platform
              env:
                  PLATFORM: ${{ matrix.platform }}
              run: echo "pair=${PLATFORM//\//-}" >> "$GITHUB_OUTPUT"

            - name: Login to GitHub Container Registry
              run: echo "${{ secrets.GITHUB_TOKEN }}" | podman login ghcr.io -u ${{ github.actor }} --password-stdin

            - name: Determine build args
              id: buildargs
              run: |
                  {
                    echo "args<<EOF"
                    echo "--target=production"
                    echo "--cache-from=${CACHE_IMAGE}"
                    if [ "${{ github.event_name }}" != "pull_request" ]; then
                      echo "--cache-to=${CACHE_IMAGE}"
                    fi
                    echo "EOF"
                  } >> "$GITHUB_OUTPUT"

            - name: Build image
              id: build
              uses: redhat-actions/buildah-build@v3
              with:
                  image: ${{ env.IMAGE_NAME }}
                  tags: ci-${{ steps.platform.outputs.pair }}-${{ github.sha }}
                  containerfiles: podman/frankenphp-octane/runtimes/Containerfile
                  context: .
                  platform: ${{ matrix.platform }}
                  layers: true
                  squash: false
                  extra-args: ${{ steps.buildargs.outputs.args }}

            - name: Push image by tag
              if: ${{ github.event_name != 'pull_request' }}
              id: push
              uses: redhat-actions/push-to-registry@v3
              with:
                  image: ${{ steps.build.outputs.image }}
                  tags: ${{ steps.build.outputs.tags }}
                  registry: ${{ env.REGISTRY }}/${{ github.repository_owner }}
                  username: ${{ github.actor }}
                  password: ${{ secrets.GITHUB_TOKEN }}
                  digestfile: /tmp/digest.txt

            - name: Upload digest
              if: ${{ github.event_name != 'pull_request' }}
              uses: actions/upload-artifact@v7
              with:
                  name: digests-${{ steps.platform.outputs.pair }}
                  path: /tmp/digest.txt
                  if-no-files-found: error
                  retention-days: 1

    merge:
        name: Merge & push manifest
        needs: build
        if: ${{ github.event_name != 'pull_request' }}
        runs-on: ubuntu-latest
        permissions:
            contents: read
            packages: write

        steps:
            - name: Download digests
              uses: actions/download-artifact@v8
              with:
                  path: /tmp/digests
                  pattern: digests-*

            - name: Login to GitHub Container Registry
              run: echo "${{ secrets.GITHUB_TOKEN }}" | podman login ghcr.io -u ${{ github.actor }} --password-stdin

            - name: Generate image tags
              id: meta
              uses: docker/metadata-action@v6
              with:
                  images: ${{ env.IMAGE }}
                  flavor: |
                      latest=false
                  tags: |
                      type=ref,event=branch
                      type=sha,format=long,prefix=commit-
                      type=semver,pattern={{version}}
                      type=semver,pattern={{major}}.{{minor}}
                      type=raw,value=latest,enable=${{ github.event_name == 'release' && !github.event.release.prerelease }}

            - name: Create and push manifest list
              run: |
                  podman manifest create manifest-list

                  for digest_file in /tmp/digests/*/digest.txt; do
                    digest=$(tr -d '[:space:]' < "$digest_file")
                    podman manifest add manifest-list "docker://${IMAGE}@${digest}"
                  done

                  while IFS= read -r tag; do
                    [ -z "$tag" ] && continue
                    podman manifest push --all manifest-list "docker://${tag}"
                  done <<< "${{ steps.meta.outputs.tags }}"
```

## Adapting it

- **Different preset** — swap `frankenphp-octane` in both the `podman:generate` call and `containerfiles:` path for your own (see [Customizing](customizing.md#custom-presets)).
- **Single-arch only** — drop the matrix and the `merge` job; push straight from `build` instead of uploading digests.
- **Registry other than GHCR** — swap the login step and `REGISTRY`/`IMAGE` env vars; `buildah`/`podman` work with any OCI registry.

## Links

- [Customizing](customizing.md)
- [Command Reference](commands.md)
- [README](../README.md)
