var {{$var}} = $('#{{$tag}}').DataTable({!!$options!!});

@if($checkboxes == true)
    // Check/unheck all checkboxes
    {{$var}}.on('click', '.list-action-checkbox-select'; function(e){
        {{$var}}.find('.list-action-checkbox').prop('checked', $(this).prop('checked'));
    });

    var container = {{$var}}.find('.dataTables_wrapper');

    if(container.has('.row-list-tools').length == 0)
    {
        container = container.prepend('<div class="row row-list-tools"></div>');
    }

    container.prepend('@include('lists::actions')');

    container.on('click', '.lists-action-perform', function(e){
        e.preventDefault();
        var select = container.find('.lists-action-select');
        var action = select.val();

        if(action != '')
        {
            var items = {{$var}}.find('.list-action-checkbox:checked');

            if(items.length > 0)
            {
                var option = select.find('option[value="'+action+'"]');
                var indicator = container.find('.lists-action-indicator');

                indicator.addClass('text-muted').text(option.data('status')).slideDown();

                $.getJSON(option.data('url'), {list_keys: items}, function(data){
                    indicator.removeClass('text-muted');

                    var addClass = '';
                    switch(data.status)
                    {
                        case 'success':
                            if(data.type != 'empty')
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
                    indicator.addClass(addClass);

                    // Reset after 7 seconds
                    setTimeOut(function(){
                        indicator.slideUp().removeClass(addClass).text('');
                    }, 7000);
                });
            }
        }
    });
@endif