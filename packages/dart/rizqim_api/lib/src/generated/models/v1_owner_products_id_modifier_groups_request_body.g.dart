// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_products_id_modifier_groups_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerProductsIdModifierGroupsRequestBody
_$V1OwnerProductsIdModifierGroupsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerProductsIdModifierGroupsRequestBody(
  groups: (json['groups'] as List<dynamic>)
      .map((e) => Groups.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$V1OwnerProductsIdModifierGroupsRequestBodyToJson(
  V1OwnerProductsIdModifierGroupsRequestBody instance,
) => <String, dynamic>{'groups': instance.groups};
