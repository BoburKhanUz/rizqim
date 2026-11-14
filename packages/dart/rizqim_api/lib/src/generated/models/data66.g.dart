// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data66.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data66 _$Data66FromJson(Map<String, dynamic> json) => Data66(
  branchId: (json['branch_id'] as num).toInt(),
  date: json['date'] as String,
  businessDate: json['business_date'] as String,
  enforced: json['enforced'] as bool,
  setValue: json['set'] as bool,
  productIds: Map<String, int>.from(json['product_ids'] as Map),
  previousDate: json['previous_date'] as String?,
  products: (json['products'] as List<dynamic>)
      .map((e) => Products.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data66ToJson(Data66 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'date': instance.date,
  'business_date': instance.businessDate,
  'enforced': instance.enforced,
  'set': instance.setValue,
  'product_ids': instance.productIds,
  'previous_date': instance.previousDate,
  'products': instance.products,
};
