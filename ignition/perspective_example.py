# Perspective: measures.row4.but_ok.click
# Asenda vana if line=='PROM1004' / if row['code']=='VA' puu extra_fault_text kutsega.
# fault_eval.py peab olema Project Library moodul.

from ce_scripts import save_measure
from ce_scripts import errorHandling
from fault_eval import extra_fault_text, make_ui, rules_from_dataset

def doWork():
	line = self.session.custom.line
	root = self.parent.parent
	code_text = root.getChild("row2").getChild("code").props.text.lower()
	root.getChild("row5").getChild("batch").props.text = ''
	coil_no = root.getChild("row5").getChild("coil_no").props.text
	instances = root.getChild("c1").getChild("fr1").props.instances
	c_miss = root.getChild("c_miss")
	c_fault = root.getChild("c_fault")
	c_text = root.getChild("c_text")
	c_miss.getChild('c1').getChild('m_text').props.text = ''
	c_fault.getChild('c1').getChild('m_text').props.text = ''
	c_text.getChild('c1').getChild('m_text').props.text = ''
	c_fault.meta.visible = False
	c_miss.meta.visible = False
	c_text.meta.visible = False
	miss = ''
	fault = ''
	if len(coil_no) == 0:
		miss = system.util.translate('ce_coil no') + '\n'

	logger = system.util.getLogger('fault_eval')

	def on_error(expr, exc):
		logger.warn('fault condition error line=%s expr=%r: %s' % (line, expr, exc))

	rules = rules_from_dataset(
		system.db.runNamedQuery('fault_conditions_for_line', {'line': line})
	)
	ui = make_ui(root)

	for row in instances:
		if row['must']:
			if row['mode'] == 'M':
				if row['allow'] == False:
					if row['number'] == 0:
						miss = miss + row['text'] + '\n '
				value = row['number']
				t_min = value - row['min']
				t_max = value + row['max']
				nominal = row['nominal']
				if value < nominal - row['min'] or value > nominal + row['max']:
					fault = fault + row['text'] + ': ' + str(value) + '\n'
			else:
				if row['radio'] == 'notSelected':
					miss = miss + row['text'] + '\n'
					t_min = row['min']
					t_max = row['max']
				if row['radio'] == 'not ok':
					fault = fault + row['text'] + ': ' + system.util.translate('ce_not ok') + '\n'
				fault = fault + extra_fault_text(
					row, line, rules, code_text, system.util.translate,
					ui=ui, on_error=on_error
				)
	if miss != '':
		c_miss.getChild('c1').getChild('m_text').props.text = miss
		self.parent.getSibling("mask").meta.visible = True
		c_miss.meta.visible = True
		return
	if fault != '':
		c_fault.getChild('c1').getChild('m_text').props.text = fault
		self.parent.getSibling("mask").meta.visible = True
		c_fault.meta.visible = True
		return
	rows = system.db.runPrepQuery('select max(no) from ce_results')
	new_no = rows[0][0] + 1  ## unique counter
	self.session.custom.new_result_no = new_no
	for row in instances:
		value = row['number']
		nominal = row['nominal']
		if row['mode'] == 'M':
			t_min = nominal - row['min']
			t_max = nominal + row['max']
		else:
			t_min = row['min']
			t_max = row['max']
			if row['radio'] == 'not ok':
				value = 0
			elif row['radio'] == 'ok':
				value = 1
			elif row['radio'] == 'na':
				value = 2
		if not (row['mode'] == 'W' and row['radio'] == 'na'):
			user = self.session.custom.name
			order = root.getChild("row1").getChild("order").props.text
			pkg = root.getChild("row1").getChild("pkg").props.text
			mat_code = self.session.custom.mat_id
			mat_name = self.session.custom.mat_name.replace(',', '.')
			product = root.getChild("row6").getChild("product").props.value
			user_no = self.session.props.auth.user.id
			save_measure(new_no, line, user, user_no, order, pkg, row['code'], row['mode'], row['nominal'], value, t_min, t_max, mat_code, mat_name, coil_no, product)
	self.session.custom.last_coil = coil_no
	self.session.custom.last_operator = self.session.custom.name
	self.session.custom.last_order = root.getChild("row1").getChild("order").props.text
	sel_order = self.session.custom.sel_order
	fr1_instances = root.getChild("c1").getChild("fr1").props.instances
	system.perspective.navigate(
		page='/released',
		navType='page'
	)

errorHandling.safeRun(
	session=self.session,
	pageId=self.page.props.pageId,
	component=str(self),
	actionName='measures.row4.but_ok.click',
	func=doWork,
	extraContext={
		"sel_order": self.session.custom.sel_order,
		"fr1_instances": self.parent.parent.getChild("c1").getChild("fr1").props.instances
	}
)
