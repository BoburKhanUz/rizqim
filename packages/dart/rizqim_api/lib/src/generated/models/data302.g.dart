// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data302.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data302 _$Data302FromJson(Map<String, dynamic> json) => Data302(
  order: Order10.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills2.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data302ToJson(Data302 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
