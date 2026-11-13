// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data254.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data254 _$Data254FromJson(Map<String, dynamic> json) => Data254(
  order: Order12.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills5.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data254ToJson(Data254 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
