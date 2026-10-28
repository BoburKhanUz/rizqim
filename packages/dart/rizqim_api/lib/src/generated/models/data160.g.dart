// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data160.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data160 _$Data160FromJson(Map<String, dynamic> json) => Data160(
  order: Order10.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills4.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data160ToJson(Data160 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
