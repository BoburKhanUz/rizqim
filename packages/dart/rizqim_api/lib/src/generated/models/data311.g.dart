// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data311.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data311 _$Data311FromJson(Map<String, dynamic> json) => Data311(
  order: Order12.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills5.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data311ToJson(Data311 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
