<?php

/**
 * @file
 * Templates for "simpletest" plugin.
 */

$SIMPLETEST_TEMPLATE['PANEL'] = '
<div class="panel panel-default simpletest-widget-panel">
	<div class="panel-heading">
		<h4 class="panel-title">
			<input type="checkbox"> {PANEL_TITLE}
		</h4>
	</div>
	
	<div id="{PANEL_ID}" class="{PANEL_CLASS}">
		<div class="panel-body form-horizontal">
			{PANEL_HELP}
			{PANEL_BODY}
			{PANEL_FOOTER}
		</div>
	</div>
</div>
';
