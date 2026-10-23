// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data157.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data157 _$Data157FromJson(Map<String, dynamic> json) => Data157(
  order: Order10.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills4.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data157ToJson(Data157 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
