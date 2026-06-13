# [VersoBit] Resource Threads

Synchronizes XenForo Resource Manager resource discussion threads and resource-update discussion posts during approval and deletion.

## Requirements

- XenForo 2.2
- XenForo Resource Manager 2.2

## What it does

- Approves a moderated resource's discussion thread when the resource is approved.
- Soft-deletes a moderated resource discussion thread when the resource is deleted.
- Approves a moderated resource-update discussion post when the update is approved.
- Soft-deletes a moderated resource-update discussion post when the update is deleted.
- Stores a direct `resource_update_id` to `post_id` mapping for reliable update-post synchronization.
- Falls back to route-aware and update-ID-only resolution for legacy discussion posts.
- Avoids fatal errors when a resource has no discussion thread or points to a missing thread.

## Version 1.0.1

This release removes the fragile hard-coded `/resources/.../update/...` discussion-post lookup and replaces it with a persistent update-post mapping plus legacy fallback resolution. It also adds null-safe discussion-thread handling and a CLI reconciliation command for finding and repairing synchronization issues.

## CLI reconciliation

Run a dry-run report:

```bash
php cmd.php vb-resource-threads:reconcile
```

Run safe repairs for missing update-post mappings and visible posts attached to deleted updates:

```bash
php cmd.php vb-resource-threads:reconcile --repair
```

Limit rows inspected by each check:

```bash
php cmd.php vb-resource-threads:reconcile --limit=1000
```

## Installation

Upload the contents of the release zip's `upload/` directory to the XenForo installation root, then install or upgrade `[VersoBit] Resource Threads` from the XenForo Admin control panel.

## Package notes

Release packages should contain only add-on runtime files and XenForo XML export data. IDE metadata, development output, local plans, and old release artifacts are intentionally excluded.
