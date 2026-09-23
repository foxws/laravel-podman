---
section: Advanced
order: 2
---

# CI: Building a Container Image

An example GitHub Actions workflow. It renders a preset's `Containerfile` with `podman:generate`, then builds and pushes a multi-arch image with `buildah`. Copy it to your app's `.github/workflows/` and change the preset name and paths to match.

The `devcontainer` preset doesn't need this. It has no placeholders and no app code, so it builds straight from this repo. [`build-devcontainer.yml`](../.github/workflows/build-devcontainer.yml) publishes it to `ghcr.io/foxws/laravel-podman-devcontainer` (see [Devcontainer](devcontainer.md)):

- Each build is tagged by PHP version and variant, e.g. `php-8.5` and `php-8.5-ai`. The `variant` matrix maps to the Containerfile's `base`/`ai` stages.
- The PHP version marked `default: true` also gets the `main`, commit-sha and `latest` tags.
- To build another PHP version, add it to the `php:` matrix.

## Prerequisites

| Requirement | Why |
| --- | --- |
| An `.env` in CI (committed, or copied from e.g. `.env.ci`), with `APP_KEY` generated before `podman:generate` | Templates can read app config while rendering |
| A `runtimes/Containerfile` in the preset | `frankenphp-octane` has one. Custom presets need their own, see [Customizing](customizing.md) |

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

| Change | What to do |
| --- | --- |
| Different preset | Replace `frankenphp-octane` in the `podman:generate` step and the `containerfiles:` path (see [Customizing](customizing.md#custom-presets)) |
| One architecture only | Remove the matrix and the `merge` job, and push directly from `build` |
| Other registry than GHCR | Change the login step and the `REGISTRY`/`IMAGE` env vars. `buildah` and `podman` work with any OCI registry |

## Links

- [Customizing](customizing.md)
- [Command Reference](commands.md)
- [Introduction](index.md)
