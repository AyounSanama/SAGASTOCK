<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\V1\SupplyOrderController as ApiController;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Site;
use App\Models\SupplyOrder;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplyOrderController extends Controller
{
    public function __construct(private UserScopeService $scopes, private ApiController $api) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('orders.view'),403);
        $organizations=$this->scopes->organizations($request->user())->orderBy('name')->get();
        $organization=$request->filled('organization_id')?$organizations->firstWhere('id',$request->string('organization_id')->toString()):($organizations->firstWhere('id',$request->user()->organization_id)??$organizations->first());
        abort_if(!$organization,$request->filled('organization_id')?404:403);
        $siteIds=$this->scopes->sites($request->user())->where('organization_id',$organization->id)->pluck('id');
        $sites=Site::with('healthFacility:id,name')->whereIn('id',$siteIds)->orderBy('name')->get();
        $products=Product::where('organization_id',$organization->id)->where('is_active',true)->orderBy('name')->get();
        $orders=SupplyOrder::with(['requestingSite.healthFacility','supplyingSite','lines.product','approvals.decisionMaker'])->where('organization_id',$organization->id)->whereIn('requesting_site_id',$siteIds)->latest()->get();
        return view('orders.index',compact('organizations','organization','sites','products','orders'));
    }
    public function store(Request $r,Organization $organization):RedirectResponse{$this->api->store($r,$organization);return back()->with('status','Commande créée avec succès.');}
    public function submit(Request $r,Organization $organization,SupplyOrder $order):RedirectResponse{$this->api->submit($r,$organization,$order);return back()->with('status','Commande transmise au circuit d’approbation.');}
    public function decide(Request $r,Organization $organization,SupplyOrder $order):RedirectResponse{$this->api->decide($r,$organization,$order);return back()->with('status',$r->input('decision')==='approve'?'Décision d’approbation enregistrée.':'Commande rejetée avec justification.');}
    public function prepare(Request $r,Organization $organization,SupplyOrder $order):RedirectResponse{$this->api->prepare($r,$organization,$order);return back()->with('status',$r->boolean('complete')?'Commande préparée et clôturée.':'Préparation enregistrée.');}
}
