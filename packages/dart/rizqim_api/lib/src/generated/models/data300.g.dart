// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data300.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data300 _$Data300FromJson(Map<String, dynamic> json) => Data300(
  order: Order11.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills3.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data300ToJson(Data300 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
