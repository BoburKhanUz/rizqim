// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_kds_targets_products_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerKdsTargetsProductsRequestBody
_$V1OwnerKdsTargetsProductsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerKdsTargetsProductsRequestBody(
      items: (json['items'] as List<dynamic>)
          .map((e) => Items3.fromJson(e as Map<String, dynamic>))
          .toList(),
      branchId: (json['branch_id'] as num?)?.toInt(),
    );

Map<String, dynamic> _$V1OwnerKdsTargetsProductsRequestBodyToJson(
  V1OwnerKdsTargetsProductsRequestBody instance,
) => <String, dynamic>{'branch_id': instance.branchId, 'items': instance.items};
