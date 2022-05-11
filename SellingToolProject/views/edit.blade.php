@extends('adminlte::page')

@section('title', 'Поставщики')

@section('content_header')
    <h1>Редактирование поставщика</h1>
@stop

@section('content')
    @include('partials.errors')

    <div class="row">
        <div class="col-md-6">
            {{ Form::model($supplier, ['route' => ['suppliers.update', $supplier->id], 'method' => 'PUT']) }}
            @include('partials.alert')
            <div class="card">
                <div class="card-body">
                    <div class="form-group">
                        {{ Form::label('title', __('admin.title')) }}
                        {{ Form::text('title', $supplier->title, ['class' => 'form-control', 'placeholder' => __('admin.title')]) }}
                    </div>
                    <div class="form-group @if($errors->has('phone')) has-error @endif">
                        {{ Form::label('phone', __('admin.phone')) }}
                        {{ Form::text('phone', $supplier->phone, ['class' => 'form-control', 'placeholder' => __('admin.phone')]) }}
                    </div>
                    <div class="form-group @if($errors->has('email')) has-error @endif">
                        {{ Form::label('email', __('admin.email')) }}
                        {{ Form::text('email', $supplier->email, ['class' => 'form-control', 'placeholder' => __('admin.email')]) }}
                    </div>
                    <div class="form-group @if($errors->has('address')) has-error @endif">
                        {{ Form::label('address1', __('admin.address')) }}
                        {{ Form::text('address1', $supplier->address1, ['class' => 'form-control', 'placeholder' =>  __('admin.address')]) }}
                    </div>
                    <div class="form-group @if($errors->has('password')) has-error @endif">
                        {{ Form::label('description', __('admin.description')) }}
                        {{ Form::textarea('description', $supplier->description, ['class' => 'form-control']) }}
                    </div>
                </div>
                <div class="card-footer">
                    {{ Form::submit('Save', ['class' => 'btn btn-primary']) }}
                </div>
            </div>
            {{ Form::close() }}
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('admin.downloaded_prices') }}</h3>
                </div>
                <div class="card-body">
                    <div id="app">
                        <price-list></price-list>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('js')
    <script src="{{ asset('vendor/vue/vue.js') }}"></script>

    <script type="text/x-template" id="price-list-template">
        <div class="price-list">
            <table class="table table-hover">
                <tr>
                    <th>Имя файла</th>
                    <th>Статус</th>
                    <th>Дата импорта</th>
                    <th></th>
                </tr>
                <tr v-for="price in prices">
                    <td>@{{ price.filename }}</td>
                    <td>@{{ price.status == 'processed' ? 'Обработан' : 'В обработке' }}</td>
                    <td>@{{ price.created_at }}</td>
                    <td class="text-right">
                        <a :href="'/download?path=prices/' + slug + '&file=' + price.filename"
                        >
                            <i @click="downloadPrice(price)" class="far fa-file-excel"></i>
                        </a>
                    </td>
                </tr>
            </table>
            <pagination
                    :pagination="pagination"
                    @pagination-change-page="fetchPrices"
            ></pagination>
        </div>
    </script>
    <script type="text/x-template" id="pagination-template">
        @include('partials.vue.pagination')
    </script>

    <script type="module">
        import Pagination from '../../js/components/pagination.vue.js';

        Vue.component('price-list', {
            template: '#price-list-template',
            data() {
                return {
                    processedStatus: '{{ \App\Price::STATUS_PROCESSED }}',
                    prices: [],
                    pagination: {},
                    slug: '{{ $supplier->slug }}',
                    url: '{{ route('ajax.prices.index', ['id' => $supplier->id]) }}',
                }
            },
            mounted() {
                this.fetchPrices();
            },
            methods: {
                fetchPrices(url = this.url) {
                    fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    })
                        .then(res => res.json())
                        .then(res => {
                            this.prices = res.prices.data;
                            this.pagination = {
                                current_page: res.prices.meta.current_page,
                                last_page: res.prices.meta.last_page,
                                prev_page_url: res.prices.links.prev,
                                next_page_url: res.prices.links.next,
                            };
                        });
                },
            },
        });
        Vue.component('pagination', Pagination)

        let app = new Vue({
            el: '#app'
        })
    </script>
@endpush
