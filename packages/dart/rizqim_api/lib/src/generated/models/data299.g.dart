// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data299.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data299 _$Data299FromJson(Map<String, dynamic> json) => Data299(
  order: Order10.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills2.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data299ToJson(Data299 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
