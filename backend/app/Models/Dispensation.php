<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Dispensation extends Model { use HasUuids; public $incrementing=false; protected $keyType='string'; protected $fillable=['organization_id','patient_id','prescription_id','prescription_attachment_path','prescription_attachment_original_name','prescription_attachment_mime_type','prescription_attachment_size','prescription_attachment_captured_at','site_id','destination_type','destination_name','offline_uuid','reference','dispensed_at','status','notes','dispensed_by','origin_type','origin_project_id']; protected $hidden=['prescription_attachment_path','prescription_attachment_original_name']; protected function casts(): array{return ['dispensed_at'=>'datetime','prescription_attachment_captured_at'=>'datetime','prescription_attachment_size'=>'integer'];} public function patient(){return $this->belongsTo(Patient::class);} public function prescription(){return $this->belongsTo(Prescription::class);} public function site(){return $this->belongsTo(Site::class);} public function items(){return $this->hasMany(DispensationItem::class);}

    /** Niveau 7 — Destinations de sortie (cahier des charges). */
    public const DESTINATION_PATIENT = 'patient';
    public const DESTINATION_SERVICE = 'hospital_service';
    public const DESTINATION_EXPIRED = 'expired_damaged';
    public const DESTINATION_NGO_RETURN = 'ngo_return';
    /** Sorties limitées aux produits de la Liste Standard. */
    public const STANDARD_LIST_DESTINATIONS = [self::DESTINATION_PATIENT, self::DESTINATION_SERVICE, 'community'];

    /** Destinations proposées en V1 ; « Communauté » reste masquée (C-08). @return array<string,string> */
    public static function destinations(): array
    {
        return [
            self::DESTINATION_PATIENT => 'Patient',
            self::DESTINATION_SERVICE => 'Service hospitalier',
            self::DESTINATION_EXPIRED => 'Périmés / détériorés',
            self::DESTINATION_NGO_RETURN => 'Retour pharmacie ONG',
        ] + (config('pharmacare_v1.features.community_dispensation') ? ['community' => 'Communauté'] : []);
    }

    /** Type de mouvement de stock selon la destination. */
    public static function movementType(string $destination): string
    {
        return match ($destination) {
            self::DESTINATION_EXPIRED => 'expiry',
            self::DESTINATION_NGO_RETURN => 'return_out',
            default => 'issue',
        };
    }

    /** Couple ONG/Bailleur d’origine du stock délivré. */
    public function originProject(){return $this->belongsTo(Project::class,'origin_project_id');}
}
