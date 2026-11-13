// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data249.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data249 _$Data249FromJson(Map<String, dynamic> json) => Data249(
  order: Order11.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills3.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data249ToJson(Data249 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
