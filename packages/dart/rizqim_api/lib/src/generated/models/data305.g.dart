// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data305.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data305 _$Data305FromJson(Map<String, dynamic> json) => Data305(
  order: Order12.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills5.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data305ToJson(Data305 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
