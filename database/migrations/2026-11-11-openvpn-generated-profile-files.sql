-- Persist the filename of each pre-generated personalized OpenVPN profile.
ALTER TABLE openvpn_links
    ADD COLUMN generated_filename VARCHAR(100) NULL AFTER token_ciphertext;

CREATE INDEX idx_openvpn_generated_filename
    ON openvpn_links (generated_filename);
