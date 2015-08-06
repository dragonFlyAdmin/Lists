var {{$var}} = $('#{{$tag}}').DataTable({!!$options!!});

@if($checkboxes == true)
    $.dataTableLists({
        tag: '{{$tag}}',
        dataTable: {{$var}},
        actions: '{!!$actions!!}',
    });
@endif