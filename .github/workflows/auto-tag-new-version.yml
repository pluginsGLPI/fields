name: "Automatically tag new version"

on:
  push:
    branches:
      - "main"
      - "**/bugfixes"
    paths:
      - "setup.php"

jobs:
  auto-tag-new-version:
    name: "Automatically tag new version"
    uses: "glpi-project/plugin-release-workflows/.github/workflows/auto-tag-new-version.yml@v1"
    secrets:
      github-token: "${{ secrets.AUTOTAG_TOKEN }}"
