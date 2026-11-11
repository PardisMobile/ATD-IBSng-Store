-- Store an encrypted copy of each newly generated OpenVPN token so admins can copy
-- the same personalized URL again. The hash remains the token lookup/validation key.
ALTER TABLE openvpn_links
    ADD COLUMN token_ciphertext TEXT NULL AFTER token_hash;
