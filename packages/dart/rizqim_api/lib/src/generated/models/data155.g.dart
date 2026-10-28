// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data155.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data155 _$Data155FromJson(Map<String, dynamic> json) => Data155(
  order: Order8.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data155ToJson(Data155 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
