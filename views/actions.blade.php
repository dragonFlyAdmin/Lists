<div class="col-md-6">
    <form class="form form-inline">
        <div class="form-group">
            <div class="col-md-8">
                <select name="perform_action" class="form-control input-sm lists-action-select">
                    <option value="">Perform an action</option>
                    @foreach($actions as $action)
                        <option value="{{$action['slug']}}" data-url="{{$action['url']}}"
                                data-status="{{$action['status']}}">
                            {{$action['title']}}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-sm btn-primary lists-action-perform">Apply</button>
            </div>
        </div>
    </form>
</div>
<div class="col-md-6">
    <span class="text-right lists-action-indicator hide">

    </span>
</div>