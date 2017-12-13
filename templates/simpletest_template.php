<?php

/**
 * @file
 * Templates for "simpletest" plugin.
 */

$SIMPLETEST_TEMPLATE['PANEL']['OPEN'] = '
<div class="panel panel-default simpletest-widget-panel">
';

$SIMPLETEST_TEMPLATE['PANEL']['HEADER'] = '
	<div class="panel-heading">
		<h4 class="panel-title">
			{PANEL_HEADING}
		</h4>
	</div>
';

$SIMPLETEST_TEMPLATE['PANEL']['BODY'] = '
	<div id="{PANEL_ID}" class="{PANEL_CLASS}">
		<div class="panel-body form-horizontal">
			{PANEL_BODY}
		</div>
	</div>
';

$SIMPLETEST_TEMPLATE['PANEL']['FOOTER'] = '
	{PANEL_FOOTER}
';

$SIMPLETEST_TEMPLATE['PANEL']['CLOSE'] = '
</div>
';

$SIMPLETEST_TEMPLATE['PANEL']['HELP'] = '
<div class="col-sm-12">
	<div class="form-group">
		{PANEL_HELP}
	</div>
</div>
';

$SIMPLETEST_TEMPLATE['PANEL']['FIELD'] = '
<div class="form-group form-group-{PANEL_FIELD_ID}">
	<label for="{PANEL_FIELD_ID}" class="control-label col-sm-3">
		{PANEL_FIELD_LABEL}
	</label>
	<div class="col-sm-9">
		{PANEL_FIELD}
		{PANEL_FIELD_HELP}
	</div>
</div>
';
