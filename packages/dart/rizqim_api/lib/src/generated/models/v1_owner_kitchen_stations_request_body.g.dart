// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_kitchen_stations_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerKitchenStationsRequestBody _$V1OwnerKitchenStationsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerKitchenStationsRequestBody(
  name: json['name'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
  kind: json['kind'] == null ? null : Kind3.fromJson(json['kind'] as String),
  sortOrder: (json['sort_order'] as num?)?.toInt(),
  isActive: json['is_active'] as bool?,
  showOnKds: json['show_on_kds'] as bool?,
);

Map<String, dynamic> _$V1OwnerKitchenStationsRequestBodyToJson(
  V1OwnerKitchenStationsRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'name': instance.name,
  'kind': instance.kind,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'show_on_kds': instance.showOnKds,
};
