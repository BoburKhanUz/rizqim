// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data174.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data174 _$Data174FromJson(Map<String, dynamic> json) => Data174(
  order: Order8.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data174ToJson(Data174 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
