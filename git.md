## Commit Message

A "commit" is a change in the codebase. Every commit has a message that explains what and why the changes were made.

### Glossary

- `type`: specifies the category of the change, indicating its purpose
    - `amend`: an additional (forgotten) change to the previous (pushed) commit
    - `build`: changes affecting the build system (e.g., Webpack, Docker, CI/CD scripts)
    - `chore`: routine tasks like config updates, dependency bumps, etc.
    - `ci`: changes to CI/CD configuration and pipeline (e.g., GitHub Actions, Travis)
    - `deps`: dependency updates or removals
    - `docs`: documentation changes (README, inline docs, etc.)
    - `env`: environment configuration changes
    - `feat`: introduces new behavior or expands existing capabilities
    - `fix`: corrects unintended or incorrect behavior
    - `hotfix`: emergency fix deployed quickly (usually a subtype of fix)
    - `init`: initial commit or project setup
    - `perf`: changes that improve performance
    - `refactor`: changes structure without altering behavior or contracts
    - `revert`: reverts a previous commit
    - `security`: patches or upgrades related to security
    - `style`: code style changes (formatting, linting, no logic change)
    - `test`: adding or updating tests
    - `ui`: user interface changes (visual updates, styling)
    - `ux`: user experience improvements (non-functional but noticeable to users)
- `scope`: provides additional context or the location of the change (e.g., file, folder, resource, module, api, ui, etc.)
- `description`: brief summary of the change

### Format

```
(type) scope: description
```

```
(type) description
```

and maybe

```
scope: description
```

The `type` and `scope` are used to categorize the change; the `description` explains it.

### Examples

- `(chore) env: remove unused environment variables`

- `(docs) readme: add setup instructions for Docker`

- `(feat) auth: add two-factor authentication`

- `(fix) api: return correct status code on failure`

- `(perf) db: cache user queries for faster lookup`

- `(refactor) router: remove unused route middleware`

- `(style) button: fix indentation and spacing`

- `(test) auth: add tests for token expiration`

### See

- [Conventional Commits](https://www.conventionalcommits.org/)
- [How to Write a Git Commit Message](https://cbea.ms/git-commit/)
