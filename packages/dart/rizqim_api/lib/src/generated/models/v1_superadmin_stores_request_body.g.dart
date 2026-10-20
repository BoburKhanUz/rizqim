// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_superadmin_stores_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1SuperadminStoresRequestBody _$V1SuperadminStoresRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1SuperadminStoresRequestBody(
  name: json['name'] as String,
  commissionRate: json['commission_rate'] as num,
  ownerName: json['owner_name'] as String,
  branchRegionId: (json['branch_region_id'] as num).toInt(),
  branchDistrictId: (json['branch_district_id'] as num).toInt(),
  branchAddress: json['branch_address'] as String,
  branchLat: json['branch_lat'] as num,
  branchLng: json['branch_lng'] as num,
  branchRadius: json['branch_radius'] as num,
  phone: json['phone'] as String?,
  ownerPhone: json['owner_phone'] as String?,
  branchPhone: json['branch_phone'] as String?,
  branchOpenTime: json['branch_open_time'] as String?,
  branchCloseTime: json['branch_close_time'] as String?,
);

Map<String, dynamic> _$V1SuperadminStoresRequestBodyToJson(
  V1SuperadminStoresRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'phone': instance.phone,
  'commission_rate': instance.commissionRate,
  'owner_name': instance.ownerName,
  'owner_phone': instance.ownerPhone,
  'branch_region_id': instance.branchRegionId,
  'branch_district_id': instance.branchDistrictId,
  'branch_address': instance.branchAddress,
  'branch_phone': instance.branchPhone,
  'branch_lat': instance.branchLat,
  'branch_lng': instance.branchLng,
  'branch_radius': instance.branchRadius,
  'branch_open_time': instance.branchOpenTime,
  'branch_close_time': instance.branchCloseTime,
};
