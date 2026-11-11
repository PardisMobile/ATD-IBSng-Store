-- Remove the obsolete second (English) introduction field from the
-- shared customer guide settings. The single introduction is language-neutral.
UPDATE app_settings
SET setting_value = JSON_REMOVE(setting_value, '$.intro_en')
WHERE setting_key = 'general_account_guide'
  AND JSON_VALID(setting_value)
  AND JSON_CONTAINS_PATH(setting_value, 'one', '$.intro_en');
