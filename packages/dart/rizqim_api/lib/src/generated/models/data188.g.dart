// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data188.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data188 _$Data188FromJson(Map<String, dynamic> json) => Data188(
  period: Period3.fromJson(json['period'] as Map<String, dynamic>),
  branchId: (json['branch_id'] as num?)?.toInt(),
  sort: json['sort'] as String,
  products: (json['products'] as List<dynamic>)
      .map((e) => Products8.fromJson(e as Map<String, dynamic>))
      .toList(),
  comboParts: (json['combo_parts'] as List<dynamic>)
      .map((e) => ComboParts.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data188ToJson(Data188 instance) => <String, dynamic>{
  'period': instance.period,
  'branch_id': instance.branchId,
  'sort': instance.sort,
  'products': instance.products,
  'combo_parts': instance.comboParts,
};
