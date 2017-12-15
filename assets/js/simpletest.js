var e107 = e107 || {'settings': {}, 'behaviors': {}};

(function ($)
{
	'use strict';

	e107.callbacks = e107.callbacks || {};
	e107.simpletest = e107.simpletest || {'groupToggle': true};

	/**
	 * @type {{attach: e107.behaviors.simpletestToggleAll.attach}}
	 */
	e107.behaviors.simpletestToggleAll = {
		attach: function (context, settings)
		{
			// #simpletest-toggle-all
			// .panel-heading input[type="checkbox"]
			// .panel-body input[type="checkbox"]

			$(context).find('#simpletest-toggle-all').once('simpletest-toggle-all').each(function ()
			{
				$(this).change(function ()
				{
					var checked = $(this).is(':checked');

					if(e107.simpletest.groupToggle)
					{
						e107.simpletest.groupToggle = false;
						$('.simpletest-widget-panel input[type="checkbox"]').each(function ()
						{
							$(this).prop('checked', checked);
						});
						e107.simpletest.groupToggle = true;
					}
				});
			});

			$(context).find('.panel-heading input[type="checkbox"]').once('simpletest-toggle-all').each(function ()
			{
				$(this).change(function ()
				{
					var checked = $(this).is(':checked');

					if(e107.simpletest.groupToggle)
					{
						$(this).closest('.panel').find('.panel-body').find('input[type="checkbox"]').each(function ()
						{
							$(this).prop('checked', checked);
						});

						if(!checked)
						{
							e107.simpletest.groupToggle = false;
							$('#simpletest-toggle-all').prop('checked', false);
							e107.simpletest.groupToggle = true;
						}
					}
				});
			});

			$(context).find('.panel-body input[type="checkbox"]').once('simpletest-toggle-all').each(function ()
			{
				$(this).change(function ()
				{
					var checked = $(this).is(':checked');

					if(e107.simpletest.groupToggle)
					{
						if(!checked)
						{
							e107.simpletest.groupToggle = false;
							$('#simpletest-toggle-all').prop('checked', false);
							$(this).closest('.panel').find('.panel-heading').find('input[type="checkbox"]').prop('checked', false);
							e107.simpletest.groupToggle = true;
						}
						else
						{
							var checkedAll = true;

							$(this).closest('.panel').find('.panel-body').find('input[type="checkbox"]').each(function ()
							{
								var checked = $(this).is(':checked');

								if(!checked)
								{
									checkedAll = false;
								}
							});

							if(checkedAll)
							{
								e107.simpletest.groupToggle = false;
								$(this).closest('.panel').find('.panel-heading').find('input[type="checkbox"]').prop('checked', true);
								e107.simpletest.groupToggle = true;
							}
						}
					}
				});
			});

			$(context).find('.simpletest-widget-panel input[type="checkbox"]').once('simpletest-toggle-all-single').each(function ()
			{
				$(this).change(function ()
				{
					if(e107.simpletest.groupToggle)
					{
						var checkedAll = true;

						$('.simpletest-widget-panel input[type="checkbox"]').each(function ()
						{
							var checked = $(this).is(':checked');

							if(!checked)
							{
								checkedAll = false;
							}
						});

						if(checkedAll)
						{
							e107.simpletest.groupToggle = false;
							$('#simpletest-toggle-all').prop('checked', true);
							e107.simpletest.groupToggle = true;
						}
					}
				});
			});
		}
	};

})(jQuery);
