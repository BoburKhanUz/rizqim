// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data248.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data248 _$Data248FromJson(Map<String, dynamic> json) => Data248(
  order: Order10.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills2.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data248ToJson(Data248 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
