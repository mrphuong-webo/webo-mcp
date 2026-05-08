# WordPress.org review reply draft (2.0.28)

Hello Plugin Review Team,

Thank you for the review and clear guidance. We have addressed both reported issues in version 2.0.28.

## 1) External service disclosure in readme

We added a dedicated External services section in readme.txt to document our third-party call to Google Suggest/Autocomplete.

Included details:
- What the service is and why it is used.
- What data is sent and when it is sent.
- Links to Terms of Service and Privacy Policy.

Readme references:
- External services section: readme.txt ("== External services ==")
- Terms link: https://policies.google.com/terms
- Privacy link: https://policies.google.com/privacy

The external request is only performed when the seo/article-analysis tool is invoked and autocomplete is enabled (no_autocomplete is not true).

## 2) File/directory location handling

We removed use of WPINC-based path construction for nav menu API loading and replaced it with explicit core include paths plus readability checks/fallback logic.

This resolves the reported path-compatibility concern and avoids reliance on internal constants in that code path.

## Release details

- Plugin version: 2.0.28
- Readme stable tag: 2.0.28
- Changelog and upgrade notice updated for 2.0.28

If you want, we can also provide the exact diff snippet for the two fixes in this thread.

Best regards,
WEBO team
