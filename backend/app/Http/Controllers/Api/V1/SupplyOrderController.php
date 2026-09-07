<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Site;
use App\Models\SupplyOrder;
use App\Notifications\OperationalNotification;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplyOrderController extends Controller
{
    public function __construct(private UserScopeService $scopes, private AuditService $audit) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'orders.view');
        $orders = SupplyOrder::with(['requestingSite.healthFacility','supplyingSite','lines.product','approvals.decisionMaker'])
            ->where('organization_id', $organization->id)
            ->whereIn('requesting_site_id', $this->siteIds($request, $organization))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate(30);
        return response()->json($orders);
    }

    public function options(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'orders.manage');
        return response()->json([
            'sites' => Site::with('healthFacility:id,name')->whereIn('id', $this->siteIds($request, $organization))->orderBy('name')->get()->map(function (Site $site) {
                $inventory = Inventory::where('site_id', $site->id)->where('status', 'validated')->latest('validated_at')->first();
                $site->setAttribute('last_validated_inventory_at', $inventory?->validated_at?->toISOString());
                $site->setAttribute('order_proposal_ready', $inventory !== null);
                return $site;
            }),
            'products' => Product::where('organization_id', $organization->id)->where('is_active', true)->orderBy('name')->get(['id','code','name','strength']),
        ]);
    }

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'orders.manage');
        if ($request->filled('offline_uuid') && ($existing = SupplyOrder::where('offline_uuid', $request->input('offline_uuid'))->first())) {
            abort_unless(
                $existing->organization_id === $organization->id
                    && $this->siteIds($request, $organization)->contains($existing->requesting_site_id),
                404,
            );
            return response()->json(['order' => $existing->load('lines.product')]);
        }
        $data = $request->validate([
            'offline_uuid'=>['nullable','uuid','unique:supply_orders,offline_uuid'],
            'requesting_site_id'=>['required','uuid'],'supplying_site_id'=>['nullable','uuid','different:requesting_site_id'],
            'reference'=>['required','max:80',Rule::unique('supply_orders')->where('organization_id',$organization->id)],
            'requested_delivery_date'=>['nullable','date','after_or_equal:today'],'priority'=>['required',Rule::in(['normal','urgent','emergency'])],
            'required_approval_levels'=>['required','integer','between:1,3'],'notes'=>['nullable','string','max:2000'],
            'lines'=>['required','array','min:1'],'lines.*.product_id'=>['required','uuid','distinct'],
            'lines.*.requested_quantity'=>['required','numeric','gt:0'],'lines.*.justification'=>['nullable','string','max:1000'],
        ]);
        $site = $this->site($request,$organization,$data['requesting_site_id']);
        abort_unless(
            Inventory::where('site_id', $site->id)->where('status', 'validated')->exists(),
            422,
            'Un inventaire clôturé et validé est obligatoire avant de créer une proposition de commande.'
        );
        if ($data['supplying_site_id'] ?? null) $this->site($request,$organization,$data['supplying_site_id']);
        $productIds = collect($data['lines'])->pluck('product_id');
        abort_unless(Product::where('organization_id',$organization->id)->whereIn('id',$productIds)->count()===$productIds->unique()->count(),422,'Un produit est invalide pour cette organisation.');
        $order = DB::transaction(function () use ($data,$organization,$request) {
            $lines=$data['lines']; unset($data['lines']);
            $order=SupplyOrder::create([...$data,'organization_id'=>$organization->id,'created_by'=>$request->user()->id]);
            $order->lines()->createMany($lines); return $order;
        });
        $this->audit->record($request,'order.created',$order);
        return response()->json(['order'=>$order->fresh(['requestingSite','lines.product'])],201);
    }

    public function submit(Request $request, Organization $organization, SupplyOrder $order): JsonResponse
    {
        $this->manage($request,$organization,$order); abort_unless(in_array($order->status,['draft','rejected'],true),422,'Cette commande ne peut pas être soumise.');
        abort_if($order->lines()->doesntExist(),422,'La commande doit contenir au moins un produit.');
        $order->approvals()->delete();
        $order->update(['status'=>'submitted','current_approval_level'=>0,'rejection_reason'=>null,'submitted_by'=>$request->user()->id,'submitted_at'=>now(),'approved_at'=>null]);
        $this->audit->record($request,'order.submitted',$order);
        $request->user()->notify(new OperationalNotification([
            'title' => 'Proposition de commande soumise',
            'message' => "La proposition {$order->reference} attend une décision.",
            'category' => 'order',
            'action_path' => '/orders',
        ]));
        return response()->json(['order'=>$order->fresh(['lines.product','approvals'])]);
    }

    public function decide(Request $request, Organization $organization, SupplyOrder $order): JsonResponse
    {
        $this->access($request,$organization,'orders.approve'); $this->withinScope($request,$organization,$order);
        abort_unless($order->status==='submitted',422,'Cette commande n’attend pas de décision.');
        abort_if($order->approvals()->where('decided_by',$request->user()->id)->exists(),422,'Un même utilisateur ne peut pas valider plusieurs niveaux de la même commande.');
        $data=$request->validate(['decision'=>['required',Rule::in(['approve','reject'])],'comment'=>[Rule::requiredIf($request->input('decision')==='reject'),'nullable','string','min:5','max:2000'],'lines'=>['nullable','array'],'lines.*.id'=>['required_with:lines','uuid'],'lines.*.approved_quantity'=>['required_with:lines','numeric','gte:0']]);
        DB::transaction(function() use($data,$order,$request){
            if(isset($data['lines'])) foreach($data['lines'] as $row){$line=$order->lines()->findOrFail($row['id']);abort_if((float)$row['approved_quantity']>(float)$line->requested_quantity,422,'La quantité approuvée ne peut pas dépasser la quantité demandée.');$line->update(['approved_quantity'=>$row['approved_quantity']]);}
            $level=$order->current_approval_level+1;
            $order->approvals()->create(['level'=>$level,'decision'=>$data['decision'],'comment'=>$data['comment']??null,'decided_by'=>$request->user()->id,'decided_at'=>now()]);
            if($data['decision']==='reject'){$order->update(['status'=>'rejected','rejection_reason'=>$data['comment'],'current_approval_level'=>$level]);return;}
            $approved=$level >= $order->required_approval_levels;
            $order->update(['current_approval_level'=>$level,'status'=>$approved?'approved':'submitted','approved_at'=>$approved?now():null]);
        });
        $this->audit->record($request,$data['decision']==='approve'?'order.approved':'order.rejected',$order,[],$data);
        return response()->json(['order'=>$order->fresh(['lines.product','approvals.decisionMaker'])]);
    }

    public function prepare(Request $request, Organization $organization, SupplyOrder $order): JsonResponse
    {
        $this->access($request,$organization,'orders.prepare'); $this->withinScope($request,$organization,$order); abort_unless(in_array($order->status,['approved','preparing'],true),422);
        $data=$request->validate(['complete'=>['nullable','boolean'],'lines'=>['required','array','min:1'],'lines.*.id'=>['required','uuid'],'lines.*.prepared_quantity'=>['required','numeric','gte:0']]);
        DB::transaction(function() use($data,$order,$request){foreach($data['lines'] as $row){$line=$order->lines()->findOrFail($row['id']);$maximum=(float)($line->approved_quantity??$line->requested_quantity);abort_if((float)$row['prepared_quantity']>$maximum,422,'La quantité préparée dépasse la quantité approuvée.');$line->update(['prepared_quantity'=>$row['prepared_quantity']]);}$complete=(bool)($data['complete']??false);$order->update(['status'=>$complete?'completed':'preparing','prepared_by'=>$request->user()->id,'prepared_at'=>now(),'completed_at'=>$complete?now():null]);});
        $this->audit->record($request,($data['complete']??false)?'order.completed':'order.prepared',$order);
        return response()->json(['order'=>$order->fresh(['lines.product','approvals'])]);
    }

    private function access(Request $request,Organization $organization,string $permission):void{abort_unless($request->user()->hasPermission($permission),403);abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(),404);}
    private function siteIds(Request $request,Organization $organization){return $this->scopes->sites($request->user())->where('organization_id',$organization->id)->pluck('id');}
    private function site(Request $request,Organization $organization,string $id):Site{return $this->scopes->sites($request->user())->where('organization_id',$organization->id)->findOrFail($id);}
    private function withinScope(Request $request,Organization $organization,SupplyOrder $order):void{abort_unless($order->organization_id===$organization->id&&$this->siteIds($request,$organization)->contains($order->requesting_site_id),404);}
    private function manage(Request $request,Organization $organization,SupplyOrder $order):void{$this->access($request,$organization,'orders.manage');$this->withinScope($request,$organization,$order);}
}
