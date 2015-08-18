<div class="row" style="margin: 0; margin-bottom: 15px;">
    <div class="col-md-6">
        <form class="form form-inline">
                    <select name="perform_action" class="form-control input-sm block lists-action-select">
                        <option value="">{!!trans('lists::actions.menu_initial')!!}</option>
                        @foreach($actions as $action)
                            <option value="{{$action['slug']}}" data-url="{{$action['url']}}"
                                    data-status="{!!trans($action['status'], ['slug' => $action['slug']])!!}">
                                {{$action['title']}}
                            </option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-primary lists-action-perform">{!!trans('lists::actions.apply')!!}</button>
        </form>
    </div>
    <div class="col-md-6 text-right">
        <div class="pull-left lists-indicator hide"><i class="fa fa-circle-o-notch fa-spin"></i></div>
        <span class="lists-action-indicator">

        </span>
    </div>
</div>