ALTER TABLE schedules
    ADD COLUMN effective_from DATE NULL AFTER play_at,
    ADD INDEX idx_schedules_effective (effective_from, day_of_week, is_active);