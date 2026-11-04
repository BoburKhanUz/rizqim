// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data237.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data237 _$Data237FromJson(Map<String, dynamic> json) => Data237(
  order: Order10.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills4.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data237ToJson(Data237 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
