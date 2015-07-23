var {{$var}} = $('#{{$tag}}').DataTable({!!$options!!});

@if($checkboxes == true)
    var container = $('#{{$tag}}_wrapper');


    {{$var}}.on('init', function(){
        console.log('hey');
        container.prepend('<div class="row row-list-tools"></div>');
        container.find('.row-list-tools').prepend('{!!$actions!!}');
    });

    // Check/unheck all checkboxes
    {{$var}}.on('click', '.list-action-checkbox-select', function(e){
        {{$var}}.$('.list-action-checkbox').prop('checked', $(this).prop('checked'));
    });

    container.on('click', '.lists-action-perform', function(e){
        e.preventDefault();
        var select = container.find('.lists-action-select');
        var action = select.val();

        if(action != '')
        {
            var items = [];

            {{$var}}.$('input[name="list-keys"]:checked').each(function(){
                items.push($(this).val());
            });

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
                    setTimeout(function(){
                        indicator.slideUp().removeClass(addClass).text('');
                    }, 7000);
                });
            }
        }
    });
@endif