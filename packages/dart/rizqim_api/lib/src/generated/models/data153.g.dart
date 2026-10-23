// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data153.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data153 _$Data153FromJson(Map<String, dynamic> json) => Data153(
  order: Order9.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills2.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data153ToJson(Data153 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
