(function ($) {
    $.fn.dataTableLists = function (options) {
        var self = $(this);
        // Set the table's container element
        option.container = $(self.attr('id') + '_wrapper');

        var settings = $.extend({}, $.dataTableLists.defaults, options);

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

                    self.trigger('lists.spinner_start', [settings.container, action, option, items]);

                    // Send the action request
                    $.getJSON(option.data('url'), {list_keys: items}, function (data) {
                        self.trigger('lists.spinner_stop', [settings.container, action, option, data]);
                        self.trigger('lists.complete', [settings.container, data, action, option, items]);

                        // Redraw the table, but keep the pagination intact
                        settings.dataTable.draw(false);
                        self.tag.find('.list-action-checkbox-select').prop('checked', false);
                    });
                }
            }
        };

        // Register loading indicator
        settings.spinner.init(settings);

        // Register the action completion handler
        self.on('lists.complete', settings.complete);

        // Register action handler
        settings.container.on('click', '.lists-action-perform', self.performAction);

        // Check/unheck all checkboxes
        self.on('click', '.list-action-checkbox-select', function (e) {
            settings.dataTable.$('.list-action-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Lastly show the action menu
        settings.showTools(settings.actions, settings.container);

        return this;
    };

    $.fn.dataTableLists.defaults = {
        dataTable: null,
        actions: '',
        complete: $.fn.dataTableLists.complete,
        spinner: $.fn.dataTableLists.spinner,
        showTools: $.fn.dataTableLists.initTools
    };

    $.fn.dataTableLists.initTool = function (actions, container) {
        // Places actions above table
        container.prepend('<div class="row-list-tools"></div>');
        container.find('.row-list-tools').prepend(actions);
    };

    $.fn.dataTableLists.spinner = {
        init: function (settings) {
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
            // Hide after 7 seconds
            setTimeout(function () {
                container.find('.lists-action-indicator').slideUp().removeClass(settings.container.data('currentClass')).addClass('text-muted').text('');
                settings.container.data('currentClass', 'text-muted');
            }, 7000);
        }
    };

    $.fn.dataTableLists.complete = function (e, container, data, action, option, items) {
        var indicator = container.find('.lists-action-indicator');

        var addClass = '';
        var text = '';
        switch (data.status) {
            case 'success':
                if (data.type != 'empty')
                    addClass = 'text-success';
                else
                    addClass = 'text-warning';

                text = data.message;
                break;
            case 'error':
                addClass = 'text-warning';
                text = data.message;
                break;
        }

        indicator.text(text);
        indicator.removeClass(container.data('currentClass')).addClass(addClass);
        container.data('currentClass', addClass);
    };
}(jQuery));