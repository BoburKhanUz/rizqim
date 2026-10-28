// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data99.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data99 _$Data99FromJson(Map<String, dynamic> json) => Data99(
  period: Period3.fromJson(json['period'] as Map<String, dynamic>),
  branchId: (json['branch_id'] as num?)?.toInt(),
  sort: json['sort'] as String,
  products: (json['products'] as List<dynamic>)
      .map((e) => Products.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data99ToJson(Data99 instance) => <String, dynamic>{
  'period': instance.period,
  'branch_id': instance.branchId,
  'sort': instance.sort,
  'products': instance.products,
};
