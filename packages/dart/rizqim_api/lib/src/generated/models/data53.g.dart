// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data53.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data53 _$Data53FromJson(Map<String, dynamic> json) => Data53(
  branchId: (json['branch_id'] as num).toInt(),
  productIds: json['product_ids'] as List<dynamic>,
);

Map<String, dynamic> _$Data53ToJson(Data53 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'product_ids': instance.productIds,
};
