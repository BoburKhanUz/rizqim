// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_halls_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerHallsRequestBody _$V1OwnerHallsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerHallsRequestBody(
  branchId: (json['branch_id'] as num).toInt(),
  name: json['name'] as String,
  sortOrder: (json['sort_order'] as num?)?.toInt(),
  isActive: json['is_active'] as bool?,
);

Map<String, dynamic> _$V1OwnerHallsRequestBodyToJson(
  V1OwnerHallsRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'name': instance.name,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
};
