name: "Publish release"

on:
  push:
    tags:
       - '*'

jobs:
  publish-release:
    permissions:
      contents: "write"
    name: "Publish release"
    uses: "glpi-project/plugin-release-workflows/.github/workflows/publish-release.yml@v1"
