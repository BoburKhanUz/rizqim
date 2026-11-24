// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data70.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data70 _$Data70FromJson(Map<String, dynamic> json) => Data70(
  branchId: (json['branch_id'] as num).toInt(),
  productIds: json['product_ids'] as List<dynamic>,
);

Map<String, dynamic> _$Data70ToJson(Data70 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'product_ids': instance.productIds,
};
