// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data66.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data66 _$Data66FromJson(Map<String, dynamic> json) => Data66(
  branchId: (json['branch_id'] as num).toInt(),
  productIds: json['product_ids'] as List<dynamic>,
);

Map<String, dynamic> _$Data66ToJson(Data66 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'product_ids': instance.productIds,
};
