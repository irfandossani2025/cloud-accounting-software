"""Validate a UBL file against the official PINT OM Schematron (XSLT 2) rules. Prints failed asserts."""
import sys, re, os
from saxonche import PySaxonProcessor

base = os.environ.get('PINT_OM_RESOURCES') or os.path.join(os.path.dirname(os.path.abspath(__file__)), 'resources')
NS = 'http://purl.oclc.org/dsdl/svrl'

def run(xml_path):
    kind = 'trn-creditnote' if '<CreditNote' in open(xml_path, encoding='utf-8').read(2000) else 'trn-invoice'
    results = []
    with PySaxonProcessor(license=False) as proc:
        xslt = proc.new_xslt30_processor()
        doc = proc.parse_xml(xml_file_name=os.path.abspath(xml_path))
        for name in ['PINT-UBL-validation-preprocessed.xslt', 'PINT-jurisdiction-aligned-rules.xslt']:
            exe = xslt.compile_stylesheet(stylesheet_file=os.path.join(base, kind, 'schematron', name))
            svrl = exe.transform_to_string(xdm_node=doc)
            for m in re.finditer(r'<svrl:failed-assert[^>]*?(?:id="([^"]*)")?[^>]*?flag="([^"]*)"[^>]*>(.*?)</svrl:failed-assert>', svrl, re.S):
                text = re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', m.group(3))).strip()
                results.append((m.group(2), text[:260]))
    return results

if __name__ == '__main__':
    failed = False
    for path in sys.argv[1:]:
        res = run(path)
        fatal = [r for r in res if r[0] == 'fatal']
        print(f'{os.path.basename(path)}: {len(fatal)} fatal, {len(res) - len(fatal)} warning(s)')
        for flag, text in res:
            print(f'   [{flag}] {text}')
        failed |= bool(fatal)
    sys.exit(1 if failed else 0)
