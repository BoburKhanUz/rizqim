// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data232.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data232 _$Data232FromJson(Map<String, dynamic> json) => Data232(
  order: Order9.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills2.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data232ToJson(Data232 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
