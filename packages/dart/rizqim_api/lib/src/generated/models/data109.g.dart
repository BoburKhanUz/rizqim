// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data109.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data109 _$Data109FromJson(Map<String, dynamic> json) => Data109(
  entryId: (json['entry_id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  ingredientId: (json['ingredient_id'] as num).toInt(),
  reason: json['reason'] as String,
  until: json['until'] as String?,
  closedProductIds: Map<String, int>.from(json['closed_product_ids'] as Map),
);

Map<String, dynamic> _$Data109ToJson(Data109 instance) => <String, dynamic>{
  'entry_id': instance.entryId,
  'branch_id': instance.branchId,
  'ingredient_id': instance.ingredientId,
  'reason': instance.reason,
  'until': instance.until,
  'closed_product_ids': instance.closedProductIds,
};
