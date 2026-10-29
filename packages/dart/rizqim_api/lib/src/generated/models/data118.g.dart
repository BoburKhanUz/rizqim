// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data118.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data118 _$Data118FromJson(Map<String, dynamic> json) => Data118(
  period: Period3.fromJson(json['period'] as Map<String, dynamic>),
  branchId: (json['branch_id'] as num?)?.toInt(),
  sort: json['sort'] as String,
  products: (json['products'] as List<dynamic>)
      .map((e) => Products4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data118ToJson(Data118 instance) => <String, dynamic>{
  'period': instance.period,
  'branch_id': instance.branchId,
  'sort': instance.sort,
  'products': instance.products,
};
