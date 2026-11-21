// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data304.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data304 _$Data304FromJson(Map<String, dynamic> json) => Data304(
  order: Order11.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills3.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data304ToJson(Data304 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
