<?php

namespace App\Http\Controllers;

use App\Exports\OrderExport;
use App\Http\Requests\UpdateOrder;
use App\Order;
use App\OrderItem;
use App\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class OrderController extends Controller
{
    const MAX_SEARCH_PRODUCT_COUNT = 1000;
    const ORDER_ITEMS_PAGINATE = 100;

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        return view('orders.index', [
            'orders' => Order::paginate(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('orders.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $order = new Order();
        $input = $request->only($order->getFillable());
        $order->fill($input)->save();

        return redirect()
            ->route('orders.index')
            ->with('alert-message', 'Заявка успешно добавлена');
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $search = urldecode($request->get('filter'));

        if ($search) {
            $keywords = explode(' ', $search);

            $products = \DB::connection('sphinx')->table('gettools')
                ->match(implode(' MAYBE ', array_slice($keywords, 0, 2)))
                ->limit(self::MAX_SEARCH_PRODUCT_COUNT)
                ->get();
        }

        return view('orders.show', [
            'order' => Order::findOrFail($id),
            'ordersItems' => OrderItem::where('order_id', $id)->paginate(self::ORDER_ITEMS_PAGINATE),
            'products' => $products ?? [],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $order = Order::findOrFail($id);

        return view('orders.edit', [
            'order' => $order,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateOrder $request, $id)
    {
        $order = Order::findOrFail($id);

        $input = $request->only($order->getFillable());

        if ($request->hasFile('filename')) {
            $request
                ->file('filename')
                ->storeAs(
                    'orders/' . $order->id,
                    $request->file('filename')->getClientOriginalName()
                );
            $input['filename'] = $request->file('filename')->getClientOriginalName();
        }

        $order->fill($input)->save();

        return redirect()
            ->route('orders.edit', $order->id)
            ->with('alert-message', 'Заявка была успешно сохранена');
    }

    /**
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function process(Request $request, $id)
    {
        try {

            $order = Order::findOrFail($id);
            $path = 'orders/' . $order->id;
            $filePath = $path . '/' . $order->filename;

            if (!Storage::disk('local')->exists($filePath)) {
                throw new \Exception('File not found');
            }

            $price = Storage::disk('local')->path($filePath);
            \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\Order($order->id), $price);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function addItem(Request $request, $id)
    {
        $orderItem = OrderItem::findOrFail($request->get('order_item_id'));

        $input = $request->only($orderItem->getFillable());
        $input['offer_id'] = $id;

        $orderItem->fill($input)->save();

        return redirect()->back();
    }

    /**
     * @param $id
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export($id)
    {
        $order = Order::findOrFail($id);
        $fileName = 'order_export_' . Carbon::now()->format('YmdHis') . '.xls';
        return Excel::download(new OrderExport($order), $fileName);
    }

    /**
     * @param Request $request
     * @param Order $order
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function search(Request $request, Order $order)
    {
        $search = $request->get('search');

        if ($search) {
            $products = Product::where('title', 'like', '%' . $search . '%')
                ->limit(self::MAX_SEARCH_PRODUCT_COUNT)
                ->get();
        }

        return view('orders.show', [
            'order' => Order::findOrFail($order->id),
            'ordersItems' => OrderItem::where('order_id', $order->id)->paginate(self::ORDER_ITEMS_PAGINATE),
            'products' => $products ?? [],
        ]);
    }
}
