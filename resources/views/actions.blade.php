<div class="col-md-6">
    <form class="form form-inline">
                <select name="perform_action" class="form-control input-sm block lists-action-select">
                    <option value="">Perform an action</option>
                    @foreach($actions as $action)
                        <option value="{{$action['slug']}}" data-url="{{$action['url']}}"
                                data-status="{{$action['status']}}">
                            {{$action['title']}}
                        </option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-primary lists-action-perform">Apply</button>
    </form>
</div>
<div class="col-md-6 text-right">
    <span class="lists-action-indicator">

    </span>
</div>