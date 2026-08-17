# Copy into an Ignition project script library, e.g. project.fault_eval
# then call from the Perspective component that currently builds `fault`.
#
# This file is an example, not imported by tests. The evaluable core lives in
# fault_eval.py (same functions; paste that module into the project library).

# --- inside the Perspective event / custom method that used to have if line=='PROM1004' ---

def build_fault(self, line, rows):
    from fault_eval import collect_faults, make_ui

    logger = system.util.getLogger('fault_eval')

    def on_error(expr, exc):
        logger.warn('fault condition error for line=%s expr=%r: %s' % (line, expr, exc))

    def translate(key):
        return system.util.translate(key)

    rules = system.db.runNamedQuery('fault_conditions_for_line', {'line': line})
    # Named query returns a PyDataSet; convert to list of dicts
    rule_rows = []
    for r in rules:
        rule_rows.append({
            'line': r['line'],
            'code': r['code'],
            'condition': r['condition'],
        })

    root = self.parent.parent
    code_text = root.getChild('row2').getChild('code').props.text

    return collect_faults(
        line,
        rows,
        rule_rows,
        code_text,
        translate,
        ui=make_ui(root),
        on_error=on_error,
    )


# Usage, replacing the old if-tree:
#   fault = build_fault(self, line, rows)
#
# Condition strings in the table may use:
#   radio == 'na' and 'silence' in code_text
#   radio != 'na' and 'silence' not in code_text
#   'xyz' in ui('row5', 'status')
# Leave condition empty to always include that line+code.
