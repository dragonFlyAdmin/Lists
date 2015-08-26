var {{$var}} = $('#{{$tag}}').DataTable({!!$options!!});

@if($checkboxes == true)
    $(#{{$tag}}).dataTableLists({
        dataTable: {{$var}},
        actions: '{!!$actions!!}',
    });
@endif