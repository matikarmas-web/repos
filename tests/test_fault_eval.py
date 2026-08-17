import unittest

from fault_eval import (
    ConditionError,
    collect_faults,
    evaluate_condition,
    evaluate_condition_safe,
    make_ui,
    matching_rules,
    row_applies,
    build_context,
)


PROM_RULES = [
    {
        'line': 'PROM1004',
        'code': 'VA',
        'condition': "radio == 'na' and 'silence' in code_text",
    },
    {
        'line': 'PROM1004',
        'code': 'VA',
        'condition': "radio != 'na' and 'silence' not in code_text",
    },
]


def _translate(key):
    return '[' + key + ']'


class FakeNode(object):
    def __init__(self, text='', children=None):
        self.props = type('P', (), {'text': text})()
        self._children = children or {}

    def getChild(self, name):
        return self._children.get(name)


class FaultEvalTests(unittest.TestCase):
    def test_empty_condition_is_true(self):
        ctx = {'radio': 'na'}
        self.assertTrue(evaluate_condition(None, ctx))
        self.assertTrue(evaluate_condition('', ctx))
        self.assertTrue(evaluate_condition('   ', ctx))

    def test_prom1004_va_na_requires_silence(self):
        row = {'code': 'VA', 'radio': 'na', 'text': 'Valve'}
        ctx_yes = build_context(row, 'PROM1004', 'Silence requested')
        ctx_no = build_context(row, 'PROM1004', 'normal')
        self.assertTrue(row_applies(row, 'PROM1004', PROM_RULES, ctx_yes))
        self.assertFalse(row_applies(row, 'PROM1004', PROM_RULES, ctx_no))

    def test_prom1004_va_other_radio_rejects_silence(self):
        row = {'code': 'VA', 'radio': 'xx', 'text': 'Valve'}
        ctx_yes = build_context(row, 'PROM1004', 'normal')
        ctx_no = build_context(row, 'PROM1004', 'silence')
        self.assertTrue(row_applies(row, 'PROM1004', PROM_RULES, ctx_yes))
        self.assertFalse(row_applies(row, 'PROM1004', PROM_RULES, ctx_no))

    def test_unlisted_code_is_included_by_default(self):
        row = {'code': 'VB', 'radio': 'na', 'text': 'Other'}
        ctx = build_context(row, 'PROM1004', 'silence')
        self.assertTrue(row_applies(row, 'PROM1004', PROM_RULES, ctx))
        self.assertEqual(matching_rules(PROM_RULES, 'PROM1004', 'VB'), [])

    def test_collect_faults_matches_original_if_tree(self):
        rows = [
            {'code': 'VA', 'radio': 'na', 'text': 'Valve A'},
            {'code': 'VB', 'radio': 'na', 'text': 'Other'},
        ]
        with_silence = collect_faults(
            'PROM1004', rows, PROM_RULES, 'has silence', _translate
        )
        without_silence = collect_faults(
            'PROM1004', rows, PROM_RULES, 'normal', _translate
        )
        self.assertEqual(with_silence, 'Valve A: [ce_na]\nOther: [ce_na]\n')
        self.assertEqual(without_silence, 'Other: [ce_na]\n')

    def test_row_condition_when_no_rules_table(self):
        rows = [
            {
                'code': 'VA',
                'radio': 'na',
                'text': 'Valve A',
                'condition': "radio == 'na' and 'silence' in code_text",
            }
        ]
        included = collect_faults('PROM1004', rows, [], 'silence', _translate)
        skipped = collect_faults('PROM1004', rows, [], 'normal', _translate)
        self.assertEqual(included, 'Valve A: [ce_na]\n')
        self.assertEqual(skipped, '')

    def test_ui_helper_reads_nested_text(self):
        root = FakeNode(children={
            'row2': FakeNode(children={'code': FakeNode(text='SILENCE alarm')}),
        })
        ui = make_ui(root)
        self.assertEqual(ui('row2', 'code'), 'silence alarm')
        ctx = {'ui': ui, 'radio': 'na'}
        self.assertTrue(evaluate_condition("radio == 'na' and 'silence' in ui('row2', 'code')", ctx))

    def test_rejects_dunder_and_missing_names(self):
        self.assertRaises(ConditionError, evaluate_condition, '__import__("os")', {})
        self.assertRaises(ConditionError, evaluate_condition, 'unknown_name == 1', {})
        errors = []
        self.assertFalse(
            evaluate_condition_safe(
                'radio ==',
                {'radio': 'na'},
                on_error=lambda expr, exc: errors.append(exc),
            )
        )
        self.assertEqual(len(errors), 1)

    def test_open_is_not_available(self):
        self.assertRaises(ConditionError, evaluate_condition, 'open("/etc/passwd")', {})


if __name__ == '__main__':
    unittest.main()
