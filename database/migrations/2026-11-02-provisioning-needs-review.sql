-- Preserve ambiguous remote provisioning outcomes for operator reconciliation.
-- Do not automatically retry a create request when IBSng may have accepted it.
ALTER TABLE provisioning_attempts
    MODIFY COLUMN status ENUM('queued','running','succeeded','failed','skipped','needs_review')
    NOT NULL DEFAULT 'queued';
