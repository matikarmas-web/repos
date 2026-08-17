"""Evaluate boolean fault conditions stored as strings (Ignition / Jython 2.7 + CPython).

Expressions are eval'd with a restricted builtin set. Empty condition => True.

Intended context names (available inside a condition string):

    row         current item dict
    line        current production line, e.g. 'PROM1004'
    radio       row['radio']
    code        row['code']
    code_text   lowercased text from the UI 'code' field (row2/code)
    ui(*names)  lowercased props.text of a child path from the view root
                example: ui('row2', 'code') == code_text
"""

from __future__ import print_function

try:
    _STRING_TYPES = basestring  # Jython 2.7 / Python 2
except NameError:
    _STRING_TYPES = str

_DUNDER_MARKER = '__'

# Keep this small: enough for real conditions, not enough for file/OS access.
_SAFE_BUILTINS = {
    'True': True,
    'False': False,
    'None': None,
    'abs': abs,
    'all': all,
    'any': any,
    'bool': bool,
    'dict': dict,
    'float': float,
    'int': int,
    'len': len,
    'list': list,
    'max': max,
    'min': min,
    'set': set,
    'sorted': sorted,
    'str': str,
    'tuple': tuple,
}


class ConditionError(Exception):
    """Raised when a stored expression is rejected or fails to evaluate."""


def _as_text(value):
    if value is None:
        return ''
    if isinstance(value, _STRING_TYPES):
        return value
    return str(value)


def row_get(row, key, default=None):
    if hasattr(row, 'get'):
        try:
            return row.get(key, default)
        except TypeError:
            pass
    try:
        return row[key]
    except (KeyError, IndexError, TypeError):
        return default


def _lower(value):
    return _as_text(value).lower()


def make_ui(root):
    """Return ui(*child_names) that reads lowercased props.text from an Ignition container.

    root is typically self.parent.parent (the container that holds row2, ...).
    """

    def ui(*names):
        node = root
        for name in names:
            if node is None:
                return ''
            node = node.getChild(name)
        if node is None:
            return ''
        try:
            text = node.props.text
        except AttributeError:
            return ''
        return _lower(text)

    return ui


def build_context(row, line, code_text, ui=None, extra=None):
    """Namespace used as locals for eval. Keep this dict the public 'language' of conditions."""
    ctx = {
        'row': row,
        'line': line,
        'radio': row_get(row, 'radio'),
        'code': row_get(row, 'code'),
        'code_text': _lower(code_text),
        'ui': ui,
    }
    if extra:
        ctx.update(extra)
    return ctx


def evaluate_condition(expr, context):
    """Return bool for a stored expression. Blank/None is True.

    Unsafe patterns (dunder names) are rejected. Eval errors raise ConditionError.
    """
    if expr is None:
        return True
    text = _as_text(expr).strip()
    if not text:
        return True
    if _DUNDER_MARKER in text:
        raise ConditionError("condition must not contain '__': %r" % (text,))

    globals_ns = {'__builtins__': _SAFE_BUILTINS}
    try:
        result = eval(text, globals_ns, context)
    except Exception as exc:
        raise ConditionError("failed to evaluate %r: %s" % (text, exc))
    return bool(result)


def evaluate_condition_safe(expr, context, on_error=None):
    """Like evaluate_condition, but returns False on error and optionally reports it."""
    try:
        return evaluate_condition(expr, context)
    except ConditionError as exc:
        if on_error is not None:
            on_error(expr, exc)
        return False


def _rule_key_matches(rule_value, actual):
    if rule_value is None:
        return True
    text = _as_text(rule_value).strip()
    if text == '' or text == '*':
        return True
    return text == _as_text(actual)


def matching_rules(rules, line, code):
    matched = []
    for rule in rules:
        if _rule_key_matches(rule.get('line'), line) and _rule_key_matches(rule.get('code'), code):
            matched.append(rule)
    return matched


def row_applies(row, line, rules, context, on_error=None):
    """Whether a dataset row should be appended to the fault text.

    If no rule matches this line+code, the row is included (default path).
    If one or more rules match, the row is included when ANY condition is true.
    """
    code = row_get(row, 'code')
    found = matching_rules(rules, line, code)
    if not found:
        return True
    for rule in found:
        expr = rule.get('condition')
        if evaluate_condition_safe(expr, context, on_error=on_error):
            return True
    return False


def format_fault_line(row, translate):
    radio = row_get(row, 'radio')
    text = row_get(row, 'text')
    return _as_text(text) + ': ' + _as_text(translate('ce_' + _as_text(radio))) + '\n'


def collect_faults(line, rows, rules, code_text, translate, ui=None, extra=None, on_error=None):
    """Build the concatenated fault string for one production line."""
    fault = ''
    use_rules = bool(rules)
    for row in rows:
        ctx = build_context(row, line, code_text, ui=ui, extra=extra)
        if use_rules:
            applies = row_applies(row, line, rules, ctx, on_error=on_error)
        else:
            applies = evaluate_condition_safe(row_get(row, 'condition'), ctx, on_error=on_error)
        if applies:
            fault += format_fault_line(row, translate)
    return fault
