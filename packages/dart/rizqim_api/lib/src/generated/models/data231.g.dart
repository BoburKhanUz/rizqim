// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data231.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data231 _$Data231FromJson(Map<String, dynamic> json) => Data231(
  order: Order10.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills4.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data231ToJson(Data231 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
