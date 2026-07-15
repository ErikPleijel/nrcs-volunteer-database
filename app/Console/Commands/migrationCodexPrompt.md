You are working in a Laravel app (redcross_volunteers). There are many Artisan migration/data-import commands currently in app/Console/Commands.

Goal:
Move all “Migrate*” commands (including MigrateOldDatabase and any commands it calls) into a dedicated folder:
app/Console/Commands/Migrations

This is purely an internal code organization refactor; behavior must remain identical.

Scope rules (important):
- Only touch:
    - the affected command classes
    - app/Console/Kernel.php (or wherever commands are registered)
    - any docs file you add under app/Console/Commands/Migrations
- Do NOT change database schema, migrations, business logic, or unrelated commands.
- Do NOT change command names/signatures (the Artisan command name string), so existing scripts still work.

Requirements:

1) Identify all relevant commands:
- Move all command classes whose intent is old DB import / migration / data transfer / “migrate:*” style.
- Specifically: MigrateOldDatabase must be moved, plus every command it calls (directly or indirectly).
- If there are other migration-related commands not called by it but clearly part of the same suite (e.g., migrate:users, migrate:branches, fix:* for old_db cleanup), include them too.

2) Move files and refactor namespaces:
- Move PHP files from app/Console/Commands/* to app/Console/Commands/Migrations/*
- Update namespaces from:
  App\Console\Commands
  to:
  App\Console\Commands\Migrations
- Update any internal references / imports accordingly.

3) Ensure Artisan command registration still works:
- Update app/Console/Kernel.php so all moved commands are discovered/registered.
- If the project uses $commands array, update entries.
- If the project uses $this->load(__DIR__.'/Commands'), ensure it also loads the new subfolder OR adjust to $this->load(__DIR__.'/Commands') in a way that still discovers nested folders (verify). If not, explicitly load Commands/Migrations too.
- Run through the final structure mentally: artisan list should still show all commands.

4) Ensure inter-command calling still works:
- In MigrateOldDatabase (and any other orchestrator command), commands are invoked via Artisan::call('migrate:xyz') or $this->call('migrate:xyz').
- Since signatures must not change, these should continue to work.
- If any orchestrator references classes directly (unlikely), update those references.

5) Add warning documentation in the new folder:
- Create a markdown file:
  app/Console/Commands/Migrations/README.md
- Include a clear warning such as:
  “⚠️ These commands are for one-time data migration and should not be run in production.”
- Include basic usage notes:
    - Where to run them (local/staging)
    - Recommend taking DB backups
    - Mention that MigrateOldDatabase orchestrates the full sequence
    - Mention typical environment safeguards (APP_ENV/local, confirmation prompts)

6) Add a lightweight safety guard (optional but recommended):
- Without changing existing signatures, add a shared guard pattern to ALL moved migration commands:
    - If app()->environment('production') => abort with an error message, unless an explicit --force flag is provided.
- If you add --force, do it consistently across these migration commands.
- If adding --force would be too invasive right now, then ONLY add prominent console warnings and a confirmation prompt in MigrateOldDatabase (e.g., “Type MIGRATE to continue”), but do not break any existing automation unless necessary.
- Prefer minimal risk: do not silently change behavior; if you add a prompt, it must be easy to bypass with --force for scripted runs.

Deliverables:
- Moved command files under app/Console/Commands/Migrations
- Updated namespaces/imports
- Updated Kernel registration so Artisan still finds them
- README.md with warning and usage
- A short summary listing:
    - moved files
    - any added safeguards (and how to bypass them if applicable)

After completing:
- Provide the final file list (old -> new paths) and highlight any commands that were NOT moved and why.
