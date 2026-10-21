// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data148.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data148 _$Data148FromJson(Map<String, dynamic> json) => Data148(
  order: Order8.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data148ToJson(Data148 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
