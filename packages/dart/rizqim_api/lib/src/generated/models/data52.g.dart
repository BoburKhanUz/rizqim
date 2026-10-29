// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data52.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data52 _$Data52FromJson(Map<String, dynamic> json) => Data52(
  branchId: (json['branch_id'] as num).toInt(),
  date: json['date'] as String?,
  businessDate: json['business_date'] as String,
  enforced: json['enforced'] as bool,
  setValue: json['set'] as bool,
  productIds: Map<String, int>.from(json['product_ids'] as Map),
  previousDate: json['previous_date'] as String?,
  products: (json['products'] as List<dynamic>)
      .map((e) => Products3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data52ToJson(Data52 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'date': instance.date,
  'business_date': instance.businessDate,
  'enforced': instance.enforced,
  'set': instance.setValue,
  'product_ids': instance.productIds,
  'previous_date': instance.previousDate,
  'products': instance.products,
};
