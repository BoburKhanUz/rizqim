// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_halls_id_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerHallsIdRequestBody _$V1OwnerHallsIdRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerHallsIdRequestBody(
  name: json['name'] as String?,
  sortOrder: (json['sort_order'] as num?)?.toInt(),
  isActive: json['is_active'] as bool?,
);

Map<String, dynamic> _$V1OwnerHallsIdRequestBodyToJson(
  V1OwnerHallsIdRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
};
