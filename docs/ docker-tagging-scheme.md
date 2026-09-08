# Docker Tagging Scheme — Bookhive

## Rule
Never use `latest` for anything that gets deployed or referenced by another system (Compose, ECS task definitions, CI). `latest` is mutable and ambiguous — it can silently point to a different image than the one you tested.

## Scheme

Tag format: `<git-short-sha>` for every build, plus a floating `dev` tag for local iteration only.

Examples:
```
bookhive:a3f9c1e      # immutable, tied to exact commit
bookhive:dev          # floating, local development only — never deployed
```

## How to tag on build

```bash
GIT_SHA=$(git rev-parse --short HEAD)
docker build -t bookhive:$GIT_SHA -t bookhive:dev .
```

## Rules going forward

- CI pushes only the git-SHA tag to ECR — never `latest`.
- `dev` tag is rebuilt locally as often as needed; it is never pushed to a shared registry.
- Any tag referenced in `docker-compose.yml`, ECS task definitions, or documentation must be a git-SHA tag, so it's always traceable back to an exact commit.
