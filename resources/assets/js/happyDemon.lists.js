(function ($) {
    var defaults = {
        container: null,
        dataTable: null,
        actions: '',
        complete: null,
        spinner: null,
        showTools: function(actions, container)
        {
            // Places actions above table
            container.prepend('<div class="row row-list-tools"></div>');
            container.find('.row-list-tools').prepend(actions);
        }
    };

    $.dataTableLists = function (options) {
        // If a tag was defined, overwrite container element
        if (typeof options.tag != 'undefined' && typeof options.container == 'undefined') {
            options.container = $('#' + options.tag + '_wrapper');
            options.tag = $('#' + options.tag);
        }

        // If a spinner object was defined, merge it with the local one
        if (typeof options.spinner != 'undefined') {
            options.spinner = $.extend({}, $.dataTableLists.spinner, options.spinner);
        }
        else
        {
            options.spinner = $.dataTableLists.spinner;
        }

        if(typeof options.complete == 'undefined')
        {
            options.complete = $.dataTableLists.complete;
        }

        var settings = $.extend({}, defaults, options);
        var self = this;

        this.init = function () {
            // Register loading indicator
            settings.spinner.init(settings);

            // Register the action completion handler
            settings.tag.on('lists.complete', settings.complete);

            // Register action handler
            settings.container.on('click', '.lists-action-perform', self.performAction);

            // Check/unheck all checkboxes
            settings.tag.on('click', '.list-action-checkbox-select', function (e) {
                settings.dataTable.$('.list-action-checkbox').prop('checked', $(this).prop('checked'));
            });

            // Lastly show the action menu
            settings.showTools(settings.actions, settings.container);

            return this;
        };

        this.performAction = function (e) {
            e.preventDefault();
            var select = settings.container.find('.lists-action-select');
            var action = select.val();

            if (action != '') {
                // Collect all the selected records
                var items = [];

                settings.dataTable.$('input[name="list-keys"]:checked').each(function () {
                    items.push($(this).val());
                });

                if (items.length > 0) {
                    // Get the action element (select option)
                    var option = select.find('option[value="' + action + '"]');

                    console.log(settings.dataTable);
                    settings.tag.trigger('lists.spinner_start', [settings.container, action, option, items]);

                    // Send the action request
                    $.getJSON(option.data('url'), {list_keys: items}, function (data) {
                        settings.tag.trigger('lists.spinner_stop', [settings.container, action, option, data]);
                        settings.tag.trigger('lists.complete', [settings.container, data, action, option, items]);

                        // Redraw the table, but keep the pagination intact
                        settings.dataTable.draw(false);
                        settings.tag.find('.list-action-checkbox-select').prop('checked', false);
                    });
                }
            }
        }

        // run init and return object
        return this.init();
    };
    $.dataTableLists.spinner = {
        indicator: null,
        currentClass: 'text-muted',
        init: function (settings) {
            this.indicator = settings.container.find('.lists-action-indicator');
            settings.container.data('currentClass', 'text-muted');
            settings.dataTable.on('lists.spinner_start', this.start);
            settings.dataTable.on('lists.spinner_stop', this.stop);
        },
        start: function (e, container, action, option, items) {
            container.find('.lists-indicator').removeClass('hide');
            container.find('.lists-action-indicator').addClass('text-muted').text(option.data('status')).slideDown();
            container.find('.lists-indicator').show();
        },
        stop: function (e, container, action, option, data) {
            container.find('.lists-indicator').addClass('hide');
            container.find('.lists-indicator').hide();
            // Hide after 7 seconds
            setTimeout(function () {
                container.find('.lists-action-indicator').slideUp().removeClass(this.currentClass).addClass('text-muted').text('');
                this.currentClass = 'text-muted';
            }, 7000);
        }
    };
    $.dataTableLists.complete = function (e, container, data, action, option,items) {
        var indicator = container.find('.lists-action-indicator');

        var addClass = '';
        switch (data.status) {
            case 'success':
                if (data.type != 'empty')
                    addClass = 'text-success';
                else
                    addClass = 'text-warning';

                indicator.text(data.message);
                break;
            case 'error':
                addClass = 'text-warning';
                indicator.text(data.message);
                break;
            default:
                indicator.text('');
                break;
        }

        indicator.removeClass(container.data('currentClass')).addClass(addClass);
        container.data('currentClass', addClass);
    };
}(jQuery));