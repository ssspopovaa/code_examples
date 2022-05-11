<?php

namespace App\Http\Controllers;

use App\Helpers\CaseHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\CaseTypeFormRequest;
use App\Models\Appeal;
use App\Models\CaseStatus;
use App\Models\CaseType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CaseTypeController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $caseTypes = CaseType::get();
        return view('case_type.index', [
            'caseTypes' => $caseTypes,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return $this->form('case_type.create');
    }

    /**
     * @param CaseTypeFormRequest $request
     * @return CaseTypeController|\Illuminate\Http\RedirectResponse
     */
    public function store(CaseTypeFormRequest $request)
    {
        return $this->save($request, new CaseType());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\CaseType $caseType
     * @return \Illuminate\Http\Response
     */
    public function edit(CaseType $caseType)
    {
        return $this->form('case_type.edit', $caseType);
    }

    /**
     * @param CaseTypeFormRequest $request
     * @param CaseType $caseType
     * @return CaseTypeController|\Illuminate\Http\RedirectResponse
     */
    public function update(CaseTypeFormRequest $request, CaseType $caseType)
    {
        return $this->save($request, $caseType);
    }

    /**
     * @param CaseType $caseType
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function destroy(CaseType $caseType)
    {
        if ($caseType->userCases->count()) {
            return redirect()->back()->withErrors('This Case has user cases. Deletion canceled.');
        }

        $caseType->delete();

        LogHelper::write(LogHelper::TYPE_CASE_TYPE_DELETED, [
            'Case ID' => $caseType->id,
            'Name' => $caseType->name,
        ], $caseType);

        return redirect()->back()->with('success', 'Case deleted successfully');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statusesList(Request $request)
    {
        $statuses = CaseHelper::statusesListArray(CaseType::where('id', $request->input('case_type_id'))->first());
        $response = [];
        foreach ($statuses as $key => $value) {
            $response[] = ['id' => $key, 'name' => $value];
        }

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function documentsListShow(Request $request)
    {
        $caseType = CaseType::where('id', $request->input('case_type_id'))->first();
        if (!$caseType instanceof CaseType) {
            abort(404);
        }

        return response()->json([
            'html' => view('case_type.documents_list', [
                'caseType' => $caseType,
            ])->render(),
        ]);
    }

    /**
     * @param CaseType $caseType
     * @return \Illuminate\Http\Response
     */
    public function rules(CaseType $caseType)
    {
        $rules = $caseType->rules()->with('status')->get();
        return view('case_type.rules', [
            'caseType' => $caseType,
            'rules' => $rules,
        ]);
    }

    /**
     * @param CaseType $caseType
     * @return \Illuminate\Http\Response
     */
    public function wizardEdit(CaseType $caseType)
    {
        return view('case_type.wizard_edit', [
            'caseType' => $caseType,
        ]);
    }

    /**
     * @param Request $request
     * @param CaseType $caseType
     * @return \Illuminate\Http\RedirectResponse
     */
    public function wizardUpdate(Request $request, CaseType $caseType)
    {
        $caseType->fill($request->all(['wizard_information', 'wizard_end_information']));
        $caseType->save();

        $caseType->wizardDocuments()->detach();

        foreach ($request->input('wizard_items', []) as $id => $priority) {
            $caseType->wizardDocuments()->attach($id, ['priority' => $priority]);
        }

        LogHelper::write(LogHelper::TYPE_CASE_TYPE_WIZARD_UPDATED, [
            'Case ID' => $caseType->id,
            'Name' => $caseType->name,
        ], $caseType);

        return redirect()->route('case-types.index')->with('success', 'Case Wizard saved successfully');
    }

    /**
     * @param $view
     * @param CaseType|null $caseType
     * @return \Illuminate\Http\Response
     */
    protected function form($view, CaseType $caseType = null)
    {
        $modes = CaseHelper::caseTypeModeListArray();
        $caseTypeId = $caseType instanceof CaseType ? $caseType->id : 0;

        $appeals = Appeal::select([
            'appeals.*',
        ])
            ->leftJoin('appeal_case_type', function ($join) use ($caseTypeId) {
                $join->on('appeals.id', '=', 'appeal_case_type.appeal_id')
                    ->where('appeal_case_type.case_type_id', $caseTypeId);
            })
            ->orderBy('appeal_case_type.ordering', 'desc')
            ->get();

        $statuses = CaseStatus::orderBy('priority')->get();

        return view($view, [
            'caseType' => $caseType,
            'appeals' => $appeals,
            'statuses' => $statuses,
            'modes' => $modes,
        ]);
    }

    /**
     * @param Request $request
     * @param CaseType $caseType
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    protected function save(Request $request, CaseType $caseType)
    {
        $caseType->fill($request->all());

        if ($caseType->save()) {

            $caseType->appeals()->detach();
            $order = $request->input('appeals_order', []);
            foreach ($request->input('appeals', []) as $id) {
                $caseType->appeals()->attach($id, ['ordering' => (isset($order[$id]) ? $order[$id] : 0)]);
            }

            $caseType->statuses()->sync($request->input('statuses', []));

            $response = redirect()
                ->route('case-types.index')
                ->with('success', 'Case saved successfully');

            LogHelper::write(LogHelper::TYPE_CASE_TYPE_SAVED, [
                'Case ID' => $caseType->id,
                'Name' => $caseType->name,
            ], $caseType);

        } else {
            $response = redirect()->back()->withErrors('Case not saved');
        }

        return $response;
    }
}
