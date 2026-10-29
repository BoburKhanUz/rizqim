// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data49.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data49 _$Data49FromJson(Map<String, dynamic> json) => Data49(
  branchId: (json['branch_id'] as num).toInt(),
  date: json['date'] as String?,
  businessDate: json['business_date'] as String,
  enforced: json['enforced'] as bool,
  setValue: json['set'] as bool,
  productIds: Map<String, int>.from(json['product_ids'] as Map),
  previousDate: json['previous_date'] as String?,
  products: (json['products'] as List<dynamic>)
      .map((e) => Products2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data49ToJson(Data49 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'date': instance.date,
  'business_date': instance.businessDate,
  'enforced': instance.enforced,
  'set': instance.setValue,
  'product_ids': instance.productIds,
  'previous_date': instance.previousDate,
  'products': instance.products,
};
