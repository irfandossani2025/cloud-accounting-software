# PINT OM validation (development only)

Validates generated e-invoices against the **official** OpenPeppol PINT OM Schematron rules. The rules are
XSLT 2.0, which PHP cannot run, so this uses Saxon (via Python). It is only used in development and in the
test suite; nothing here runs on the server.

## Setup

```bash
cd tools/pint-om
curl -sSL -o resources.zip https://docs.peppol.eu/poac/om/pint-om/resources.zip
unzip -o -q resources.zip -d resources
python3 -m venv .venv && .venv/bin/pip install saxonche
```

## Use

```bash
tools/pint-om/.venv/bin/python tools/pint-om/validate.py invoice.xml
```

`php artisan test` runs `EInvoicingTest::test_generated_documents_pass_official_pint_om_rules` automatically when
`tools/pint-om/.venv` and `tools/pint-om/resources` exist (otherwise the test is skipped).

When OpenPeppol publishes a new PINT OM release, download it again and re-run the tests. Update the code lists in
`resources/data/pint-om/` as well (see the README there).
