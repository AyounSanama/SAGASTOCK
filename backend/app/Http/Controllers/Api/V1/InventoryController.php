<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryLine;
use App\Models\Organization;
use App\Models\Site;
use App\Models\StockBalance;
use App\Services\AuditService;
use App\Services\StockLedgerService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(private UserScopeService $scopes, private StockLedgerService $ledger, private AuditService $audit) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'inventories.view');
        return response()->json(Inventory::with(['site.healthFacility', 'lines.product', 'lines.batch'])->where('organization_id', $organization->id)
            ->whereIn('site_id', $this->siteIds($request, $organization))->when($request->input('status'), fn ($q,$v)=>$q->where('status',$v))->latest('period_date')->paginate(30));
    }

    public function options(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'inventories.manage');
        return response()->json(['sites' => Site::with('healthFacility:id,name')->whereIn('id', $this->siteIds($request, $organization))->orderBy('name')->get()]);
    }

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'inventories.manage');
        if ($request->filled('offline_uuid') && ($existing = Inventory::where('offline_uuid', $request->input('offline_uuid'))->first())) {
            abort_unless($existing->organization_id === $organization->id, 404);
            return response()->json(['inventory' => $existing->load(['site', 'lines.product', 'lines.batch'])]);
        }
        $data = $request->validate(['offline_uuid'=>['nullable','uuid','unique:inventories,offline_uuid'],'site_id'=>['required','uuid'],
            'reference'=>['required','max:80',Rule::unique('inventories')->where('organization_id',$organization->id)],
            'inventory_type'=>['required',Rule::in(['monthly','exceptional'])],'period_date'=>['required','date','before_or_equal:today'],'notes'=>['nullable','string','max:2000']]);
        $site = $this->site($request, $organization, $data['site_id']);
        abort_if(Inventory::where('site_id',$site->id)->whereIn('status',['draft','counting','submitted'])->exists(), 422, 'Un inventaire est déjà ouvert sur ce site.');
        $inventory = Inventory::create([...$data,'organization_id'=>$organization->id,'site_id'=>$site->id,'created_by'=>$request->user()->id]);
        $this->audit->record($request,'inventory.created',$inventory);
        return response()->json(['inventory'=>$inventory->load('site')],201);
    }

    public function start(Request $request, Organization $organization, Inventory $inventory): JsonResponse
    {
        $this->manage($request,$organization,$inventory); abort_unless($inventory->status==='draft',422,'Cet inventaire ne peut plus être démarré.');
        DB::transaction(function() use($inventory,$request){
            $balances=StockBalance::with('batch')->where('organization_id',$inventory->organization_id)->where('site_id',$inventory->site_id)->get();
            abort_if($balances->isEmpty(),422,'Aucune ligne de stock à inventorier sur ce site.');
            foreach($balances as $balance) $inventory->lines()->create(['product_id'=>$balance->product_id,'batch_id'=>$balance->batch_id,
                'theoretical_quantity'=>$balance->theoretical_quantity,'unit_cost'=>$balance->batch?->unit_cost]);
            $inventory->update(['status'=>'counting','frozen_at'=>now()]);
        });
        $this->audit->record($request,'inventory.started',$inventory,[],['lines'=>$inventory->lines()->count()]);
        return response()->json(['inventory'=>$inventory->fresh(['site','lines.product','lines.batch'])]);
    }

    public function count(Request $request, Organization $organization, Inventory $inventory): JsonResponse
    {
        $this->manage($request,$organization,$inventory); abort_unless($inventory->status==='counting',422,'Le comptage n’est pas ouvert.');
        $data=$request->validate(['lines'=>['required','array','min:1'],'lines.*.id'=>['required','uuid'],'lines.*.physical_quantity'=>['required','numeric','gte:0'],'lines.*.justification'=>['nullable','string','max:2000']]);
        DB::transaction(function() use($data,$inventory,$request){foreach($data['lines'] as $row){
            $line=$inventory->lines()->findOrFail($row['id']); $physical=(float)$row['physical_quantity']; $variance=$physical-(float)$line->theoretical_quantity;
            if(abs($variance)>.0001 && mb_strlen(trim((string)($row['justification']??'')))<5) throw ValidationException::withMessages(['lines'=>'Une justification précise est obligatoire pour chaque écart.']);
            $line->update(['physical_quantity'=>$physical,'variance_quantity'=>$variance,'variance_value'=>$variance*(float)($line->unit_cost??0),
                'justification'=>$row['justification']??null,'counted_by'=>$request->user()->id,'counted_at'=>now()]);
        }});
        return response()->json(['inventory'=>$inventory->fresh(['lines.product','lines.batch'])]);
    }

    public function submit(Request $request, Organization $organization, Inventory $inventory): JsonResponse
    {
        $this->manage($request,$organization,$inventory); abort_unless($inventory->status==='counting',422);
        abort_if($inventory->lines()->whereNull('physical_quantity')->exists(),422,'Toutes les lignes doivent être comptées.');
        $totals=$inventory->lines()->selectRaw('SUM(theoretical_quantity * COALESCE(unit_cost,0)) theoretical_value, SUM(physical_quantity * COALESCE(unit_cost,0)) physical_value, SUM(variance_quantity * COALESCE(unit_cost,0)) variance_value')->first();
        $inventory->update(['status'=>'submitted','submitted_by'=>$request->user()->id,'submitted_at'=>now(),'theoretical_value'=>$totals->theoretical_value??0,'physical_value'=>$totals->physical_value??0,'variance_value'=>$totals->variance_value??0]);
        $this->audit->record($request,'inventory.submitted',$inventory);
        return response()->json(['inventory'=>$inventory->fresh(['lines.product','lines.batch'])]);
    }

    public function validateInventory(Request $request, Organization $organization, Inventory $inventory): JsonResponse
    {
        $this->access($request,$organization,'inventories.validate'); abort_unless($inventory->organization_id===$organization->id && $this->siteIds($request,$organization)->contains($inventory->site_id),404);
        abort_unless($inventory->status==='submitted',422,'Cet inventaire n’est pas en attente de validation.');
        $data=$request->validate(['decision'=>['required',Rule::in(['approve','reject'])],'rejection_reason'=>[Rule::requiredIf($request->input('decision')==='reject'),'nullable','string','min:5','max:2000']]);
        if($data['decision']==='reject'){$inventory->update(['status'=>'rejected','rejection_reason'=>$data['rejection_reason'],'validated_by'=>$request->user()->id,'validated_at'=>now()]);$this->audit->record($request,'inventory.rejected',$inventory,[],$data);return response()->json(['inventory'=>$inventory]);}
        DB::transaction(function() use($inventory,$request){foreach($inventory->lines()->with('batch')->get() as $line){$variance=(float)$line->variance_quantity;
            if(abs($variance)>.0001)$this->ledger->record($inventory->organization,$inventory->site,$line->batch,$variance>0?'adjustment_in':'adjustment_out',abs($variance),$request->user()->id,
                ['reference_type'=>'inventory','reference_id'=>$inventory->id,'reason'=>$line->justification,'bypass_inventory_freeze'=>true]);
            StockBalance::where('site_id',$inventory->site_id)->where('batch_id',$line->batch_id)->update(['physical_quantity'=>$line->physical_quantity]);
        }$inventory->update(['status'=>'validated','validated_by'=>$request->user()->id,'validated_at'=>now()]);});
        $this->audit->record($request,'inventory.validated',$inventory,[],['variance_value'=>$inventory->variance_value]);
        return response()->json(['inventory'=>$inventory->fresh(['site','lines.product','lines.batch'])]);
    }

    private function access(Request $r,Organization $o,string $p):void{abort_unless($r->user()->hasPermission($p),403);abort_unless($this->scopes->organizations($r->user())->whereKey($o->id)->exists(),404);}
    private function siteIds(Request $r,Organization $o){return $this->scopes->sites($r->user())->where('organization_id',$o->id)->pluck('id');}
    private function site(Request $r,Organization $o,string $id):Site{return $this->scopes->sites($r->user())->where('organization_id',$o->id)->findOrFail($id);}
    private function manage(Request $r,Organization $o,Inventory $i):void{$this->access($r,$o,'inventories.manage');abort_unless($i->organization_id===$o->id&&$this->siteIds($r,$o)->contains($i->site_id),404);}
}
