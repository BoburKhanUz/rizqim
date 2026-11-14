// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'branches.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Branches _$BranchesFromJson(Map<String, dynamic> json) => Branches(
  branchId: json['branch_id'] as String,
  stopped: json['stopped'] as List<dynamic>,
  soldOut: SoldOut.fromJson(json['sold_out'] as Map<String, dynamic>),
  stopList: json['stop_list'] as List<dynamic>,
  stoppedIngredients: json['stopped_ingredients'] as String,
  limitedProducts: json['limited_products'] as List<dynamic>,
  nextBatches: json['next_batches'],
  batches: (json['batches'] as List<dynamic>)
      .map((e) => Batches.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$BranchesToJson(Branches instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'stopped': instance.stopped,
  'sold_out': instance.soldOut,
  'stop_list': instance.stopList,
  'stopped_ingredients': instance.stoppedIngredients,
  'limited_products': instance.limitedProducts,
  'next_batches': instance.nextBatches,
  'batches': instance.batches,
};
