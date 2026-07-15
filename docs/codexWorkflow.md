# Codex Workflow

## PREPARE
- Commit and push to GitHub:
- git checkout main
- git status
- git add -A
- git commit -m "Baseline before Codex"
- git push

# Codex
- Run Codex. Check if ok.
- Create PR, check page on GitHub
- git fetch origin
- git checkout -b codex/add-records-filter-to-trainings-and-payments-index origin/codex/add-records-filter-to-trainings-and-payments-index

# Test Locally
- Test Locally
- [ ] HAPPY:
- If made changes. Commit and Push
- Squash and merge (on GitHub)
- git checkout main
- git pull
- (Optional clean up:
  git branch -D codex/implement-archive/reactivate-for-users
  git fetch --prune)

- [ ] NOT HAPPY:
- GitHub: Close PR, Delete branch
- git checkout main
- git pull
- git branch -D codex/implement-archive/reactivate-for-users
- git fetch --prune
