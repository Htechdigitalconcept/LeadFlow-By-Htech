# Development log

| Phase | Work and verification status |
|---|---|
| Architecture | Documented table schema, trust boundaries, dependency limits and capability scopes. Initialized Git locally; remote creation requires an authenticated owner. |
| Foundation | Implemented the bootstrap, site-level activation, versioned schema, options, menu and permissions; checked schema/index installation and deactivation. |
| Capture | Implemented form definitions, shortcodes, validation, attribution and spam controls; exercised rendering, validation and persistence in WordPress. |
| CRM | Implemented searchable lists, records, stages, notes, history and assignment; exercised persistence and authorization. |
| Communication and follow-ups | Implemented click-to-chat, WordPress mail configuration and follow-up screens; verified URL and mail arguments, not real delivery. |
| Advanced features | Implemented analytics, scoring, CSV tools, privacy callbacks, retention and printing. Exercised key data paths. |
| User experience | Implemented onboarding and responsive admin/public layouts. Inspected browser screenshots at desktop and mobile sizes. |
| Testing and security | Ran PHPUnit, browser journeys, PHP compatibility and manual code review. Strict WPCS, independent security review, and compatibility coverage remain release gates. |
| Production preparation | Built an installable release candidate with documentation and SHA-256 checksum. No stable tag, GitHub push or production sign-off. |
