# ADR: Export without a lock when the filesystem cannot lock

**Status:** accepted 2026-10-27.

## Context

Each run takes an exclusive `flock()` on `storage/framework/lang-export.lock`, so a full export and the watcher's `--source` runs queue instead of interleaving their reads and writes of the same locale file. Some network and container mounts refuse `flock()` outright. Before [GH-2](https://github.com/abetwothree/laravel-lang/issues/2), that refusal failed every export with exit 1, so the package could not run on those mounts at all.

## Considered options

- **Fail the run.** This was the old behaviour. It is safe, but the package is unusable on such a mount.
- **A config switch to skip locking.** Nobody knows to set it until an export fails, and the failure message would have to point at it.
- **Export unlocked and warn.** Chosen.

## Decision

[`ExportLock::acquire()`](../../src/Support/ExportLock.php) uses `flock()`'s `$wouldBlock` to tell contention from a filesystem that cannot lock:

- **Contention** waits up to 30 seconds, then fails.
- **A filesystem that cannot lock** gets an unheld lock at once. [`LangExporter::locked()`](../../src/LangExporter.php) then exports anyway and warns `Exported without a lock: …`.

## Consequences

- Without a lock, two overlapping `--source` runs can each patch the same locale file. One change is lost until the next full export. Callers already have to serialize `--source` calls; see the [output contract](../output-contract.md).
- An unlocked run skips the sweep of orphaned temp files, because another run may still be writing one.
- A lock file that cannot be created still fails the run: an unwritable storage directory is a misconfiguration, not a missing capability.
