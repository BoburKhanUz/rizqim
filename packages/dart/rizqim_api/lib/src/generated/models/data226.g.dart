// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data226.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data226 _$Data226FromJson(Map<String, dynamic> json) => Data226(
  order: Order9.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills2.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data226ToJson(Data226 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
