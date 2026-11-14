// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data69.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data69 _$Data69FromJson(Map<String, dynamic> json) => Data69(
  branchId: (json['branch_id'] as num).toInt(),
  productIds: json['product_ids'] as List<dynamic>,
);

Map<String, dynamic> _$Data69ToJson(Data69 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'product_ids': instance.productIds,
};
