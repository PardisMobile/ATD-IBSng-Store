# IBSng server UTC offset

Each IBSng server may optionally store its own fixed UTC offset in `ibsng_servers.utc_offset`.
The value is normalized to `+HHMM` or `-HHMM`; an omitted sign means positive.
The accepted range is UTC-14:00 through UTC+14:00, with minutes from 00 to 59.

To read the current offset on the IBSng host, run:

```bash
date '+%z'
```

Paste the result (for example `+0330`) into the server form. The setting is used only when interpreting the IBSng Web Panel's displayed `Nearest Expiration Date`. The HTML extraction, IBSng requests, renewal duration policy, and renewal form submission are unchanged. The same normalized expiry state is consumed by the customer account page and the renewal service.

A NULL value means UTC and preserves the store's previous behavior. Existing servers remain NULL after the additive schema sync until an administrator enters an offset.

After deploying, run the database schema synchronization from the admin upgrade page before opening the IBSng servers page. The sync adds one nullable column and does not rewrite existing rows.
