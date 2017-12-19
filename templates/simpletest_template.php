<?php

/**
 * @file
 * Templates for "simpletest" plugin.
 */

$SIMPLETEST_TEMPLATE['PANEL'] = '
<div class="{PANEL_CLASS}">
	<div class="panel-heading">
		<h4 class="panel-title">
			{PANEL_TITLE}
		</h4>
	</div>
	
	<div id="{PANEL_ID}" class="{PANEL_BODY_CLASS}">
		<div class="panel-body form-horizontal">
			{PANEL_HELP}
			{PANEL_BODY}
			{PANEL_FOOTER}
		</div>
	</div>
</div>
';
