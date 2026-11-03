// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_kitchen_stations_id_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerKitchenStationsIdRequestBody
_$V1OwnerKitchenStationsIdRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerKitchenStationsIdRequestBody(
      name: json['name'] as String?,
      kind: json['kind'] == null
          ? null
          : KitchenStationKind.fromJson(json['kind'] as String),
      targetSeconds: (json['target_seconds'] as num?)?.toInt(),
      sortOrder: (json['sort_order'] as num?)?.toInt(),
      isActive: json['is_active'] as bool?,
      showOnKds: json['show_on_kds'] as bool?,
    );

Map<String, dynamic> _$V1OwnerKitchenStationsIdRequestBodyToJson(
  V1OwnerKitchenStationsIdRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'kind': instance.kind,
  'target_seconds': instance.targetSeconds,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'show_on_kds': instance.showOnKds,
};
