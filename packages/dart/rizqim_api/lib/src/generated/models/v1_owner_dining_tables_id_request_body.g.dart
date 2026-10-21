// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_dining_tables_id_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerDiningTablesIdRequestBody _$V1OwnerDiningTablesIdRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerDiningTablesIdRequestBody(
  hallId: (json['hall_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  kind: json['kind'] == null ? null : Kind2.fromJson(json['kind'] as String),
  seats: (json['seats'] as num?)?.toInt(),
  sortOrder: (json['sort_order'] as num?)?.toInt(),
  isActive: json['is_active'] as bool?,
  minSpend: json['min_spend'] as num?,
  minSpendMode: json['min_spend_mode'] == null
      ? null
      : MinSpendMode.fromJson(json['min_spend_mode'] as String?),
  layout: json['layout'] == null
      ? null
      : Layout7.fromJson(json['layout'] as Map<String, dynamic>),
);

Map<String, dynamic> _$V1OwnerDiningTablesIdRequestBodyToJson(
  V1OwnerDiningTablesIdRequestBody instance,
) => <String, dynamic>{
  'hall_id': instance.hallId,
  'name': instance.name,
  'kind': instance.kind,
  'seats': instance.seats,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'min_spend': instance.minSpend,
  'min_spend_mode': instance.minSpendMode,
  'layout': instance.layout,
};
